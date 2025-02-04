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
import traceback
from copy import deepcopy

PROGNAME = "recorderd"

ZOMBIE_HUNT_RATE_MIN=60

def usage():
    print("Usage: %s -ini <ini-file> [-mode {prod|dev}]" % (sys.argv[0]))
    print("")
    exit()

def nowstr():
    return datetime.datetime.today().strftime('%Y-%b-%d %H:%M:%S')

if ((len(sys.argv) < 3) or \
    ((len(sys.argv) > 3) and (len(sys.argv) < 5))):
    print("ERROR: wrong number of arguments; expected 2 or 4")
    usage()

if (sys.argv[1] != "-ini"):
    print("ERROR: expected '-ini' for 2nd argument")
    usage()

if ((len(sys.argv) > 3) and (sys.argv[3] != "-mode")):
    print("ERROR: expected '-mode' for 4th argument")
    usage()

dvr_fs = os.path.dirname(sys.argv[0])
iniFilename = sys.argv[2]
mode = "prod" if (len(sys.argv) < 4) else sys.argv[4]
config = configparser.ConfigParser()

if (not os.path.isfile(iniFilename)):
    print("ERROR:",iniFilename,"file doesn't exist")
    usage()
    
config.read(iniFilename)

if (not os.path.isdir(dvr_fs+"/raw")):
    print("ERROR: recording location doesn't exists: ",(dvr_fs+"/raw"))
    usage()

if (not (mode in config)):
    print("ERROR: invalid config file; expected",mode,"section")
    usage()
    
DbBase = config[mode]['DbBase']
DbKey = config[mode]['DbKey']
DbPswd = config[mode]['DbPswd']
Db = config[mode]['Db']
DbWriteAuth = None if (not (DbKey and DbPswd)) else (DbKey,DbPswd)
    
print("INFO(%s): Mode: %s" % (nowstr(), mode))
print("INFO(%s): Using dvr-filesystem root: %s" % (nowstr(), dvr_fs))
print("INFO(%s): DbBase: %s" % (nowstr(), DbBase))
print("INFO(%s): DbKey: %s" % (nowstr(), DbKey))
print("INFO(%s): DbPswd: %s" % (nowstr(), DbPswd))
print("INFO(%s): Db: %s" % (nowstr(), Db))
print("INFO(%s): DbWriteAuth: %s" % (nowstr(), DbWriteAuth))

ALL_OBJS_URL = DbBase+'/'+Db+'/_all_docs'
BULK_DOCS_URL = DbBase+'/'+Db+'/_bulk_docs'
POST_URL = DbBase+'/'+Db
VIEW_BASE = DbBase+'/'+Db+'/_design/dvr/_view/'

SCHEDULE_URL = VIEW_BASE+'scheduled'
CAPTURING_URL = VIEW_BASE+'capturing'

retryCnt = 0
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
    _haveResponse = False
    retryCnt = 0
    while (not _haveResponse and (retryCnt < 5)):
        _text = requests.get(SCHEDULE_URL).text
        _resultSet = json.loads(_text)
        if ('error' in _resultSet):
            retryCnt += 1
            if retryCnt < 5:
                _alertmsg = "WARNING(%s): ScheduleSet query failed; retrying\n" % nowstr()
                _alertmsg += _text
                print(_alertmsg)
                alertEmail(_alertmsg)
            time.sleep(10)
        else:
            _haveResponse = True
            retryCnt = 0

    if retryCnt == 5:
        _alertmsg = "WARNING(%s): Couldn't retrieve ScheduledSet after 5 tries" % nowstr()
        print(_alertmsg)
        alertEmail(_alertmsg)
        return None
    else:
        return _resultSet

    
def fetchCapturingSet():
    return json.loads(requests.get(CAPTURING_URL).text)

    
def selectNextMissedEnd(resultSet,now):
    if (resultSet == None):
        return None;
    
    earliest = 9999999999
    mini = -1
    for i in range(0, len(resultSet['rows'])):
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
    for i in range(0, len(resultSet['rows'])):
        r = resultSet['rows'][i]['value']
        stime = int(r['record-start'])
        etime = int(r['record-end'])
        if (stime < now and now < etime and stime < earliest):
            mini = i
            earliest = stime
    return None if mini == -1 else resultSet['rows'][mini]['value']

    
def selectNextStart(resultSet,now):
    if (resultSet == None):
        return None;
    
    earliest = 9999999999
    mini = -1
    for i in range(0, len(resultSet['rows'])):
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
        _alertmsg = "WARNING(%s): Found a schedule item that I missed completely! %s" % \
            (nowstr(), json.dumps(missedCompletely))
        print(_alertmsg)
        alertEmail(_alertmsg)
        missedCompletely['type'] = 'error'
        missedCompletely['errmsg'] = 'schedule missed completely'
        missedCompletely['missed-timestamp'] = str(now)
        id = missedCompletely['_id']
        del missedCompletely['_id']
        url = POST_URL+'/'+id
        r = requests.put(url, auth=DbWriteAuth, json=missedCompletely)
        rs = fetchScheduledSet()
        missedCompletely = selectNextMissedEnd(rs,now)
    return rs


def invoke(n, fs):
    id = n['_id']
    url = n['url']
    cmdArr = ['/usr/bin/curl','-X','GET',url,'-s','-i','-o',fs+'/raw/'+id+'.mp4'];
    _alertmsg = "INFO(%s): creating subporcess; cmd: %s" % (nowstr(), cmdArr)
    print(_alertmsg)
    alertEmail(_alertmsg)
    return subprocess.Popen(cmdArr, shell=False)


def startCapture(n, now, fs):
    global activeCaptures
    proc = invoke(n, fs)
    id = n['_id']
    n['type'] = 'capturing'
    n['capture-start-timestamp'] = now
    n['capture-heartbeat'] = now
    n['pid'] = proc.pid
    n['file'] = 'raw/'+id+'.mp4'
    del n['_id']
    print("INFO(%s): Here's the update I'm going to make: %s" % \
          (nowstr(), json.dumps(n,indent=3)))
    url = POST_URL+'/'+id
    r = requests.put(url, auth=DbWriteAuth, json=n)
    n['_id'] = id
    n['_rev'] = r.json()['rev']
    activeCaptures.append({'pid':proc.pid,'record':deepcopy(n),'heartbeat':now})


def stopCapture(s, now):
    global activeCaptures

    print("DEBUG(%s): completed capture of: %s" % (nowstr(), json.dumps(s,indent=3)))
    print("DEBUG(%s): activeCaptures before removing completed capture: %s" % (nowstr(), json.dumps(activeCaptures,indent=3)))

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
    print("DEBUG(%s): activeCaptures after removing completed capture: %s" % \
          (nowstr(), json.dumps(activeCaptures, indent=3)))
    print("DEBUG(%s): s after shutting down process: %s" % \
          (nowstr(), json.dumps(s, indent=3)))
            
    id = s['_id']
    hasHeartbeat = 'capture-heartbeat' in s
    s['capture-stop-timestamp'] = s['capture-heartbeat'] if hasHeartbeat else now
    del s['pid']
    del s['_id']
    s.pop('capture-heartbeat', None)
    s['type'] = 'recording'

    print("INFO(%s): Here's the update I'm going to make: %s" % \
          (nowstr(), json.dumps(s,indent=3)))
    url = POST_URL+'/'+id
    r = requests.put(url, auth=DbWriteAuth, json=s)
    
    dst = dvr_fs+'/library/'+cleanDescription(s['description'])+'.mp4'
    src = dvr_fs+'/'+s['file']
    print("DEBUG(%s): Here's the symlink to establish: %s->%s" % (nowstr(), dst, src))
    os.symlink(src, dst)

    
def handleLateStarts(rs, now, fs):
    late = selectNextMissedStart(rs, now)
    while (late != None):
        print("INFO(%s): Found a schedule that should have started already!  Starting now." %\
              (nowstr()))
        startCapture(late, now, fs)
        rs = fetchScheduledSet()
        late = selectNextMissedStart(rs, now)
    return rs


def handleNextStart(rs, now, fs):
    n = selectNextStart(rs, now)
    while (n != None):
        print("INFO(%s): Found a schedule due to start within a minute; starting it now" % \
              (nowstr()))
        startCapture(n, now, fs)
        rs = fetchScheduledSet()
        n = selectNextStart(rs, now)
    return rs


def handleNextStop(crs, now):
    s = selectNextStop(crs, now)
    while (s != None):
        print("INFO(%s): Found an active capture that should be stopped: %s" % \
              (nowstr(), json.dumps(s,indent=3)))
        stopCapture(s, now)
        crs = fetchCapturingSet()
        s = selectNextStop(crs, now)
    return crs



def heartbeat(n, now):
    id = n['_id']
    url = POST_URL+'/'+id
    del n['_id']
    n['capture-heartbeat'] = now
    print("DEBUG(%s): Updating the db heartbeat" % (nowstr()))
    r = requests.put(url, auth=DbWriteAuth, json=n)
    n['_id'] = id
    n['_rev'] = r.json()['rev']
    return n



def zombieHunt(now):
    #print("INFO(%s): On a zombie hunt!" % (nowstr()))
    rset = json.loads(requests.get(CAPTURING_URL).text)['rows']
    #print("DEBUG(%s): there are %d capture jobs found" % (nowstr(), len(rset)))
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

            _alertmsg = "WARNING(%s): Found a zombie!  Here's the db update to make: %s" % \
                (nowstr(), json.dumps(n,indent=3))
            print(_alertmsg)
            alertEmail(_alertmsg)
            r = requests.put(url, auth=DbWriteAuth, json=n)


def alertEmail(msg):
    print("INFO(%s): preparing an alert email..." % (nowstr()))
    filename = "/tmp/%s-alert-email-%d-msg.txt" % (PROGNAME, os.getpid())
    f = open(filename, "w")
    f.write("To: j.cicchiello@ieee.org\n")
    print("INFO(%s): To: j.cicchiello@ieee.org" % (nowstr()))
    f.write("From: jcicchiello@ptd.net\n")
    print("INFO(%s): From: jcicchiello@ptd.net" % (nowstr()))
    f.write("Subject: "+PROGNAME+".py has hit an alert condition!\n")
    print("INFO(%s): Subject: %s has hit an alert condition!" % (nowstr(), PROGNAME))
    f.write("INFO(%s): \n" % (nowstr()))
    print("INFO(%s): " % (nowstr()))
    f.write("INFO(%s): alert msg: %s\n" % (nowstr(), msg))
    print("INFO(%s): alert msg: %s" % (nowstr(), msg))
    f.write("INFO(%s): \n" % (nowstr()))
    print("INFO(%s): " % (nowstr()))
    f.close()
    time.sleep(5)
    with open(filename, 'r') as infile:
        subprocess.Popen(['/usr/bin/msmtp', 'j.cicchiello@gmail.com'],
                         stdin=infile, stdout=sys.stdout, stderr=sys.stderr)


def sysexception(t,e,tb):
    progname = "recorderd"

    print("ERROR(%s): sysexception called; preparing an email..." % (nowstr()))
    filename = "/tmp/%s-email-%d-msg.txt" % (progname, os.getpid())
    f = open(filename, "w")
    f.write("To: j.cicchiello@ieee.org\n")
    print("INFO(%s): To: j.cicchiello@ieee.org" % (nowstr()))
    f.write("From: jcicchiello@ptd.net\n")
    print("INFO(%s): From: jcicchiello@ptd.net" % (nowstr()))
    f.write("Subject: "+progname+".py has crashed!?!?\n")
    print("INFO(%s): Subject: %s has crashed!?!?" % (nowstr(), progname))
    f.write("\n")
    print("INFO(%s): " % (nowstr()))
    f.write(progname+".py has shutdown unexpectedly!\n")
    print("INFO(%s): %s has shutdown unexpectedly!" % (nowstr(), progname))
    f.write("\n")
    print("INFO(%s): " % (nowstr()))
    f.write("type 1: ")
    print("INFO(%s): type 1: %s" % (nowstr(), str(t)))
    f.write(str(t))
    f.write("\n")
    f.write("type 2: ")
    print("INFO(%s): type 2: %s" % (nowstr(), t.__name__))
    f.write(t.__name__)
    f.write("\n")
    f.write("exception: ")
    print("INFO(%s): exception: %s" % (nowstr(), str(e)))
    f.write(str(e))
    f.write("\n")
    print("INFO(%s): " % (nowstr()))
    f.write("traceback: ")
    print("INFO(%s): traceback: %s" % (nowstr(), str(tb)))
    f.write(str(tb))
    f.write("\n")
    f.write("traceback.print_exception(etype,value,tb): ")
    f.write(str(traceback.format_exception(t, e, tb)))
    f.write("\n")
    print("INFO(%s): " % (nowstr()))
    f.write("\n")
    print("INFO(%s): " % (nowstr()))
    f.close()
    with open(filename, 'r') as infile:
        subprocess.Popen(['/usr/bin/msmtp', 'j.cicchiello@gmail.com'],
                         stdin=infile, stdout=sys.stdout, stderr=sys.stderr)
    traceback.print_tb(tb)
    exit()


sys.excepthook = sysexception


# Let's wait a bit before starting anything that might need the db, in case it's not available yet
time.sleep(50)

now = calendar.timegm(time.gmtime())
zombieTimestamp = now            
print("INFO(%s): Performing Zombie Hunt" % (nowstr()))
zombieHunt(zombieTimestamp)

srs = getScheduledSet(None)
crs = getCapturingSet([])
print("INFO(%s): Entering main loop" % (nowstr()))
while (True):
    now = calendar.timegm(time.gmtime())
    srs = handleMissedSchedules(srs, now)
    srs = handleLateStarts(srs, now, dvr_fs)
    srs = handleNextStart(srs, now, dvr_fs)
    crs = handleNextStop(crs, now)

    now = calendar.timegm(time.gmtime())
    newCaptures = []
    for capture in activeCaptures:
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

    if (now > zombieTimestamp+60*ZOMBIE_HUNT_RATE_MIN):
        zombieTimestamp = now
        zombieHunt(zombieTimestamp)
