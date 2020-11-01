#!/usr/bin/python

import json
import calendar
import time
import requests
import os
import subprocess
import psutil
import time
import datetime
import sys
import configparser
from copy import deepcopy


ZOMBIE_HUNT_RATE_MIN=60


def usage():
    print "Usage:",sys.argv[0]," -ini <ini-file> [-mode {prod|dev} [-v]]"
    print ""
    exit()

def nowstr():
    fmt = 'INFO: %Y-%b-%d %H:%M:%S:'
    return datetime.datetime.today().strftime('INFO: %Y-%b-%d %H:%M:%S :')

if ((len(sys.argv) < 3) or \
    ((len(sys.argv) > 3) and (len(sys.argv) < 5))):
    print "ERROR: wrong number of arguments; expected 2 or 4"
    usage()

if (sys.argv[1] != "-ini"):
    print "ERROR: expected '-ini' for 2nd argument"
    usage()

if ((len(sys.argv) > 3) and (sys.argv[3] != "-mode")):
    print "ERROR: expected '-mode' for 4th argument"
    usage()

if ((len(sys.argv) > 5) and (sys.argv[5] != "-v")):
    print "ERROR: expected '-v' for 6th argument"
    usage()

dvr_fs = os.path.dirname(sys.argv[0])
iniFilename = sys.argv[2]
mode = "prod" if (len(sys.argv) < 4) else sys.argv[4]
config = configparser.ConfigParser()
verbose = len(sys.argv) > 5

if (not os.path.isfile(iniFilename)):
    print "ERROR:",iniFilename,"file doesn't exist"
    usage()
    
config.read(iniFilename)

if (not os.path.isdir(dvr_fs+"/raw")):
    print "ERROR: recording location doesn't exists: ",(dvr_fs+"/raw")
    usage()

if (not (mode in config)):
    print "ERROR: invalid config file; expected",mode,"section"
    usage()
    
DbBase = config[mode]['DbBase']
DbKey = config[mode]['DbKey']
DbPswd = config[mode]['DbPswd']
Db = config[mode]['Db']
DbWriteAuth = None if (not (DbKey and DbPswd)) else (DbKey,DbPswd)
    
if (verbose):
    print nowstr(), "Mode:", mode
    print nowstr(), "Using dvr-filesystem root:", dvr_fs
    print nowstr(), "DbBase:", DbBase
    print nowstr(), "DbKey:", DbKey
    print nowstr(), "DbPswd:", DbPswd
    print nowstr(), "Db:", Db
    print nowstr(), "DbWriteAuth:", DbWriteAuth

ALL_OBJS_URL = DbBase+'/'+Db+'/_all_docs'
BULK_DOCS_URL = DbBase+'/'+Db+'/_bulk_docs'
POST_URL = DbBase+'/'+Db
VIEW_BASE = DbBase+'/'+Db+'/_design/dvr/_view/'

SCHEDULE_URL = VIEW_BASE+'scheduled'
CAPTURING_URL = VIEW_BASE+'capturing'

def cleanDescription(d):
    cleanedDescription = ''
    legalchars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-., '
    for c in d:
        if (c not in legalchars):
            c = 'X'
        cleanedDescription += c
    return cleanedDescription
    
def fetchScheduledSet():
    return json.loads(requests.get(SCHEDULE_URL).text)

    
def fetchCapturingSet():
    return json.loads(requests.get(CAPTURING_URL).text)

    
def selectNextMissedEnd(resultSet,now):
    if (resultSet == None):
        return None;
    
    earliest = 9999999999
    mini = -1
    for i in range(0, len(resultSet['rows'])):
        #print "row[",i,"]:",json.dumps(v['rows'][i]['value'],indent=3)
        r = resultSet['rows'][i]['value']
        stime = int(r['record-start'])
        etime = int(r['record-end'])
        if (etime < now and stime < earliest):
            mini = i
            earliest = stime
    return None if mini == -1 else resultSet['rows'][mini]['value']

    
def selectNextMissedStart(resultSet,now):
    if (resultSet == None):
        return None;
    
    earliest = 9999999999
    mini = -1
    #print nowstr(),"DEBUG: resultSet['rows']:",json.dumps(resultSet['rows'],indent=3)
    for i in range(0, len(resultSet['rows'])):
        r = resultSet['rows'][i]['value']
        stime = int(r['record-start'])
        etime = int(r['record-end'])
        if (stime < now and now < etime and stime < earliest):
            #print nowstr(),"DEBUG: tentatively choosing",i
            mini = i
            earliest = stime
    return None if mini == -1 else resultSet['rows'][mini]['value']

    
def selectNextStart(resultSet,now):
    if (resultSet == None):
        return None;
    
    earliest = 9999999999
    mini = -1
    for i in range(0, len(resultSet['rows'])):
        #print "row[",i,"]:",json.dumps(v['rows'][i]['value'],indent=3)
        r = resultSet['rows'][i]['value']
        stime = int(r['record-start'])
        if ((stime > now) and (stime < now + 60) and (stime < earliest)):
            mini = i
            earliest = stime
    return None if mini == -1 else resultSet['rows'][mini]['value']


def selectNextStop(resultSet,now):
    if (len(resultSet) == 0):
        #print nowstr(),"DEBUG: resultSet is empty"
        return None;
    
    #print nowstr(),"DEBUG: resultSet is not empty"
    earliest = 9999999999
    mini = -1
    for i in range(0, len(resultSet['rows'])):
        #print nowstr(),"DEBUG: row[",i,"]:",json.dumps(resultSet['rows'][i]['value'],indent=3)
        r = resultSet['rows'][i]['value']
        stime = int(r['record-end'])
        if ((stime > now) and (stime < now + 60) and (stime < earliest)):
            mini = i
            earliest = stime
    return None if mini == -1 else resultSet['rows'][mini]['value']


scheduledSetRefreshCnt = 0
def getScheduledSet(prev):
    global scheduledSetRefreshCnt
    resetIt = prev == None
    if (not resetIt):
        scheduledSetRefreshCnt += 1
        resetIt = (scheduledSetRefreshCnt > 3)
    if (resetIt):
        scheduledSetRefreshCnt = 0
        return fetchScheduledSet()
    else:
        return prev


def handleMissedSchedules(rs, now):
    missedCompletely = selectNextMissedEnd(rs, now)
    while (missedCompletely != None):
        print nowstr(),"INFO: Found one that I missed completely!"
        missedCompletely['type'] = 'error'
        missedCompletely['errmsg'] = 'schedule missed completely'
        missedCompletely['missed-timestamp'] = str(now)
        id = missedCompletely['_id']
        del missedCompletely['_id']
        #print "Here's the update I'm going to make:", json.dumps(missedCompletely,indent=3)
        url = POST_URL+'/'+id
        r = requests.put(url, auth=DbWriteAuth, json=missedCompletely)
        #print r.json()
        rs = fetchScheduledSet()
        missedCompletely = selectNextMissedEnd(rs,now)
    return rs


def invoke(n, fs):
    id = n['_id']
    url = n['url']
    cmdArr = ['/usr/bin/curl','-s','-X','GET',url,'-i','-o',fs+'/raw/'+id+'.mp4'];
    print nowstr(),"INFO: cmd: ",cmdArr
    return subprocess.Popen(cmdArr, shell=False)


def startCapture(n, now, fs):
    proc = invoke(n, fs)
    #print nowstr(),"INFO: returned from invoke"
    id = n['_id']
    n['type'] = 'capturing'
    n['capture-start-timestamp'] = now
    n['pid'] = proc.pid
    n['file'] = 'raw/'+id+'.mp4'
    del n['_id']
    print nowstr(),"Here's the update I'm going to make:", json.dumps(n,indent=3)
    url = POST_URL+'/'+id
    r = requests.put(url, auth=DbWriteAuth, json=n)
    #print nowstr(),("Success" if 'ok' in r.json() else "Failed: "+r.json())
    n['_id'] = id
    n['_rev'] = r.json()['rev']


def stopCapture(s, now):
    print nowstr(),"DEBUG: completed capture of:", json.dumps(s,indent=3)

    process = psutil.Process(s['pid'])
    for proc in process.children(recursive=True):
        proc.kill()
    process.kill()
            
    print nowstr(),"DEBUG: s after shutting down process:", json.dumps(s, indent=3)
            
    id = s['_id']
    s['capture-stop-timestamp'] = now
    del s['pid']
    del s['_id']
    s['type'] = 'recording'
    
    print nowstr(),"Here's the update I'm going to make:", json.dumps(s,indent=3)
    url = POST_URL+'/'+id
    r = requests.put(url, auth=DbWriteAuth, json=s)
    #print r.json()
    
    dst = dvr_fs+'/library/'+cleanDescription(s['description'])+'.mp4'
    src = dvr_fs+'/'+s['file']
    print nowstr(),"DEBUG: Here's the symlink to establish:",src," ",dst
    os.symlink(src, dst)

    
def handleLateStarts(rs, now, fs):
    #print json.dumps(rs,indent=3)
    late = selectNextMissedStart(rs, now)
    while (late != None):
        print nowstr(),"INFO: Found a schedule that should have started already!  Starting now."
        startCapture(late, now, fs)
        rs = fetchScheduledSet()
        late = selectNextMissedStart(rs, now)
    return rs


def handleNextStart(rs, now, fs):
    #print json.dumps(rs,indent=3)
    n = selectNextStart(rs, now)
    while (n != None):
        print nowstr(),"INFO: Found a schedule due to start within a minute; starting it now"
        startCapture(n, now, fs)
        rs = fetchScheduledSet()
        n = selectNextStart(rs, now)
    return rs


def handleNextStop(crs, now):
    #print nowstr(),"DEBUG: in handleNextStop"
    s = selectNextStop(crs, now)
    while (s != None):
        print nowstr(),"INFO: Found an active capture that should be stopped:", json.dumps(s,indent=3)
        stopCapture(s, now)
        crs = fetchCapturingSet()
        s = selectNextStop(crs, now)
    return crs



def zombieHunt(now):
    #print nowstr(),"On a zombie hunt!"
    #print nowstr(),"DEBUG: Here's the url: "+CAPTURING_URL
    responseStr = requests.get(CAPTURING_URL).text
    #print nowstr(),"DEBUG: response: "+responseStr
    rset = json.loads(responseStr)['rows']
    #print nowstr(),"DEBUG: there are",len(rset),"capture jobs found"
    #print nowstr(),"DEBUG: here's rset:",json.dumps(rset,indent=3)
    for i in range(0, len(rset)):
        n = rset[i]['value']
        #print nowstr(),"DEBUG: now: ",now
        #print nowstr(),"DEBUG: n['record-end']: ",n['record-end']
        isZombie = (now > (int(n['record-end'])+60*ZOMBIE_HUNT_RATE_MIN))
        if (isZombie):
            #print nowstr(),"DEBUG: determined it to be a zombie!"
            id = n['_id']
            url = POST_URL+'/'+id
            del n['_id']
            n.pop('pid', None)
            n['capture-stop-timestamp'] = now
            n['type'] = 'error';
            n['errmsg']  = 'identified as capture-zombie';
            print nowstr(),"Found a zombie!  Here's the update I'm going to make:", \
                json.dumps(n,indent=3)
            r = requests.put(url, auth=DbWriteAuth, json=n)
            print nowstr(),("Success" if 'ok' in r.json() else "Failed: "+r.json())


            
def sysexception(t,e,tb):
    progname = "recorder"
    
    print nowstr(),'sysexception called; preparing an email...'
    filename = "/tmp/"+progname+"-msg.txt"
    f = open(filename, "w", 0)
    f.write("To: j.cicchiello@ieee.org\n")
    print nowstr(), "To: j.cicchiello@ieee.org"
    f.write("From: jcicchiello@ptd.net\n")
    print nowstr(), "From: jcicchiello@ptd.net"
    f.write("Subject: "+progname+".py has crashed!?!?\n")
    print nowstr(), "Subject: "+progname+".py has crashed!?!?"
    f.write("\n")
    print nowstr(), ""
    f.write(progname+".py has shutdown unexpectedly!\n")
    print nowstr(), progname+".py has shutdown unexpectedly!"
    f.write("\n")
    print nowstr(), ""
    f.write("type 1: ")
    print nowstr(), "type 1: "+str(t)
    f.write(str(t))
    f.write("\n")
    f.write("type 2: ")
    print nowstr(), "type 2: "+t.__name__
    f.write(t.__name__)
    f.write("\n")
    f.write("exception: ")
    print nowstr(), "exception: "+str(e)
    f.write(str(e))
    f.write("\n")
    print nowstr(), ""
    f.write("traceback: ")
    print nowstr(), "traceback: "+str(tb)
    f.write(str(tb))
    f.write("\n")
    f.write("traceback.print_exception(etype,value,tb): ")
    tb.print_exception(t,e,tb,f);
    f.write("\n")
    print nowstr(), ""
    f.write("\n")
    print nowstr(), ""
    f.close()
    with open(filename, 'r') as infile:
        subprocess.Popen(['/usr/sbin/ssmtp', 'j.cicchiello@gmail.com'],
                         stdin=infile, stdout=sys.stdout, stderr=sys.stderr)
    exit()


sys.excepthook = sysexception


now = calendar.timegm(time.gmtime())
zombieTimestamp = now            
if (verbose):
    print nowstr(), "INFO: Performing Zombie Hunt"
zombieHunt(zombieTimestamp)

srs = getScheduledSet(None)

now = calendar.timegm(time.gmtime())
#print nowstr(), "DEBUG: now:", now

if (verbose):
    print nowstr(),"INFO: looking for completely missed..."
srs = handleMissedSchedules(srs, now)

if (verbose):
    print nowstr(),"INFO: looking for late..."
srs = handleLateStarts(srs, now, dvr_fs)
    
if (verbose):
    print nowstr(),"INFO: considering scheduled sessions..."
srs = handleNextStart(srs, now, dvr_fs)

print nowstr(),"INFO: considering capture sessions..."
crs = handleNextStop(fetchCapturingSet(), now)

