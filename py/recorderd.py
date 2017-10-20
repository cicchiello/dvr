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
    print "Usage:",sys.argv[0]," -ini <ini-file> [-mode {prod|dev}]"
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

dvr_fs = os.path.dirname(sys.argv[0])
iniFilename = sys.argv[2]
mode = "prod" if (len(sys.argv) < 4) else sys.argv[4]
config = configparser.ConfigParser()

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

activeCaptures=[]

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
        return None;
    
    earliest = 9999999999
    mini = -1
    for i in range(0, len(resultSet['rows'])):
        #print "row[",i,"]:",json.dumps(v['rows'][i]['value'],indent=3)
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


capturingSetRefreshCnt = 0
def getCapturingSet(prev):
    global capturingSetRefreshCnt
    global activeCaptures
    if (len(activeCaptures) > 0):
        resetIt = len(prev) == 0
        if (not resetIt):
            capturingSetRefreshCnt += 1
            resetIt = capturingSetRefreshCnt > 5
        if (resetIt):
            capturingSetRefreshCnt = 0
            return fetchCapturingSet()
        else:
            return prev
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
    cmdArr = ['/usr/bin/curl','-X','GET',url,'-i','-o',fs+'/raw/'+id+'.mp4'];
    print nowstr(),"INFO: cmd: ",cmdArr
    return subprocess.Popen(cmdArr, shell=False)

def startCapture(n, now, fs):
    global activeCaptures
    proc = invoke(n, fs)
    #print nowstr(),"INFO: returned from invoke"
    id = n['_id']
    n['type'] = 'capturing'
    n['capture-start-timestamp'] = now
    n['capture-heartbeat'] = now
    n['pid'] = proc.pid
    n['file'] = 'raw/'+id+'.mp4'
    del n['_id']
    print nowstr(),"Here's the update I'm going to make:", json.dumps(n,indent=3)
    url = POST_URL+'/'+id
    r = requests.put(url, auth=DbWriteAuth, json=n)
    #print nowstr(),("Success" if 'ok' in r.json() else "Failed: "+r.json())
    n['_id'] = id
    n['_rev'] = r.json()['rev']
    activeCaptures.append({'pid':proc.pid,'record':deepcopy(n),'heartbeat':now})
    #print nowstr(),"DEBUB: activeCaptures:", json.dumps(activeCaptures,indent=3)


def stopCapture(s, now):
    global activeCaptures

    print nowstr(),"DEBUG: completed capture of:", json.dumps(s,indent=3)
    print nowstr(),"DEBUG: activeCaptures before removing completed capture:", \
        json.dumps(activeCaptures, indent=3)

    newActiveCaptures = []
    for x in activeCaptures:
        if x['pid'] == s['pid']:
            # x might have a more recent _rev depending on timing of heartbeats and refreshes
            s = x['record']
            process = psutil.Process(s['pid'])
            for proc in process.children(recursive=True):
                proc.kill()
            process.kill()
        else:
            newActiveCaptures.append(x)
    activeCaptures = newActiveCaptures
    print nowstr(),"DEBUG: activeCaptures after removing completed capture:", \
        json.dumps(activeCaptures, indent=3)
    print nowstr(),"DEBUG: s after shutting down process:", json.dumps(s, indent=3)
            
    id = s['_id']
    print nowstr(), "TRACE: 1"
    hasHeartbeat = 'capture-heartbeat' in s
    print nowstr(), "TRACE: 2"
    s['capture-stop-timestamp'] = s['capture-heartbeat'] if hasHeartbeat else now
    print nowstr(), "TRACE: 3"
    del s['pid']
    print nowstr(), "TRACE: 4"
    del s['_id']
    print nowstr(), "TRACE: 5"
    s.pop('capture-heartbeat', None)
    print nowstr(), "TRACE: 6"
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
    s = selectNextStop(crs, now)
    while (s != None):
        print nowstr(),"INFO: Found an active capture that should be stopped:", json.dumps(s,indent=3)
        stopCapture(s, now)
        crs = fetchCapturingSet()
        s = selectNextStop(crs, now)
    return crs



def heartbeat(n, now):
    id = n['_id']
    url = POST_URL+'/'+id
    del n['_id']
    n['capture-heartbeat'] = now
    print nowstr(),"DEBUG: Updating the db heartbeat"
    #print nowstr(),"DEBUG: Here's the update I'm going to make:", json.dumps(n,indent=3)
    r = requests.put(url, auth=DbWriteAuth, json=n)
    #print nowstr(),("Success" if 'ok' in r.json() else "Failed: "+r.json())
    n['_id'] = id
    n['_rev'] = r.json()['rev']
    return n



def zombieHunt(now):
    #print nowstr(),"On a zombie hunt!"
    rset = json.loads(requests.get(CAPTURING_URL).text)['rows']
    #print nowstr(),"DEBUG: there are",len(rset),"capture jobs found"
    #print nowstr(),"DEBUG: here's rset:",json.dumps(rset,indent=3)
    for i in range(0, len(rset)):
        n = rset[i]['value']
        hasHeartbeat = 'capture-heartbeat' in n
        isZombie = not hasHeartbeat and (now > (int(n['record-end'])+60*ZOMBIE_HUNT_RATE_MIN))
        isZombie = isZombie or (now > int(n['capture-heartbeat'])+60*ZOMBIE_HUNT_RATE_MIN)
        if (isZombie):
            id = n['_id']
            url = POST_URL+'/'+id
            del n['_id']
            n.pop('pid', None)
            n['capture-stop-timestamp'] = n['capture-heartbeat'] if hasHeartbeat else now
            n.pop('capture-heartbeat', None)
            n['type'] = 'error';
            n['errmsg']  = 'identified as capture-zombie';
            print nowstr(),"Found a zombie!  Here's the update I'm going to make:", \
                json.dumps(n,indent=3)
            r = requests.put(url, auth=DbWriteAuth, json=n)
            #print nowstr(),("Success" if 'ok' in r.json() else "Failed: "+r.json())


            
def sysexception(t,e,tb):
    f = open("/tmp/trap.txt", "w", 0)
    f.write("To: j.cicchiello@ieee.org\n")
    f.write("From: jcicchiello@ptd.net\n")
    f.write("Subject: compressd.py has crashed!?!?\n")
    f.write("\n")
    f.write("compressd.py has shutdown unexpectedly!\n")
    f.write("\n")
    f.write("type: ")
    f.write(t)
    f.write("\n")
    f.write("exception: ")
    f.write(e)
    f.write("\n")
    f.write("trace back: ")
    f.write(tb)
    f.write("\n")
    f.write("\n")
    f.close()
    with open('/tmp/msg.txt', 'r') as infile:
        subprocess.Popen(['/usr/sbin/ssmtp', 'j.cicchiello@gmail.com'],
                         stdin=infile, stdout=sys.stdout, stderr=sys.stderr)
    exit()


sys.excepthook = sysexception


now = calendar.timegm(time.gmtime())
zombieTimestamp = now            
print nowstr(), "Performing Zombie Hunt"
zombieHunt(now)

srs = getScheduledSet(None)
crs = getCapturingSet([])
print nowstr(), "Entering main loop"
while (True):
    now = calendar.timegm(time.gmtime())
    #print nowstr(), "now:", now
    #print ""

    #print nowstr(),"looking for completely missed..."
    srs = handleMissedSchedules(srs, now)
    #print ""

    #print nowstr(),"looking for late..."
    srs = handleLateStarts(srs, now, dvr_fs)
    #print ""
    
    #print nowstr(),"looking for ones that should start now..."
    srs = handleNextStart(srs, now, dvr_fs)
    #print ""

    #print nowstr(),"looking for ones that should stop now..."
    crs = handleNextStop(crs, now)
    #print ""

    #print nowstr(),"considering heartbeats..."
    now = calendar.timegm(time.gmtime())
    newCaptures = []
    for capture in activeCaptures:
        #print nowstr(),"DEBUG: capture:",json.dumps(capture,indent=3)
        n = capture['record'];
        hb = capture['heartbeat'];
        if (now > hb+60):
            n = heartbeat(n, now)
            capture['heartbeat'] = now
            capture['record'] = n
        newCaptures.append(capture)
    activeCaptures = newCaptures
        
    time.sleep(50)
    srs = getScheduledSet(srs)
    crs = getCapturingSet(crs)
    #print "srs: ", srs
    #print "crs: ", crs

    if (now > zombieTimestamp+60*ZOMBIE_HUNT_RATE_MIN):
        zombieTimestamp = now
        zombieHunt(now)
