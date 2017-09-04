import json
import calendar
import time
import requests
import os.path
import subprocess
import psutil

SemaphoreDirtyFile = '/tmp/dbtouched'

DbKey = 'socksookeesayedwerameate'
DbPswd = 'c2d5c73bc067e9f73fd568c3ef783232fb2d0498'

SCHEDULE_URL = 'https://jfcenterprises.cloudant.com/dvr/_design/dvr/_view/scheduled'
CAPTURING_URL = 'https://jfcenterprises.cloudant.com/dvr/_design/dvr/_view/capturing'
UPDATE_URL = 'https://jfcenterprises.cloudant.com/dvr'

activeCaptureCnt = 0

def fetchScheduledResultSet():
    return json.loads(requests.get(SCHEDULE_URL).text)

    
def fetchCapturingResultSet():
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
            ealiest = stime
    return None if mini == -1 else resultSet['rows'][mini]['value']

    
def selectNextMissedStart(resultSet,now):
    if (resultSet == None):
        return None;
    
    earliest = 9999999999
    mini = -1
    for i in range(0, len(resultSet['rows'])):
        #print "row[",i,"]:",json.dumps(v['rows'][i]['value'],indent=3)
        r = resultSet['rows'][i]['value']
        stime = int(r['record-start'])
        etime = int(r['record-end'])
        if (stime < now and now < etime and stime < earliest):
            mini = i
            ealiest = stime
    return None if mini == -1 else resultSet['rows'][mini]['value']

    
def selectNextStart(resultSet,now):
    if (resultSet == None):
        return None;
    
    earliest = 9999999999
    mini = -1
    for i in range(0, len(resultSet['rows'])):
        #print "row[",i,"]:",json.dumps(v['rows'][i]['value'],indent=3)
        r = resultSet['rows'][i]['value']
        stime = r['record-start']
        if ((stime > now) and (stime < now + 60) and (stime < earliest)):
            mini = i
            ealiest = stime
    return None if mini == -1 else resultSet['rows'][mini]['value']


def selectNextStop(resultSet,now):
    if (resultSet == None):
        return None;
    
    earliest = 9999999999
    mini = -1
    for i in range(0, len(resultSet['rows'])):
        #print "row[",i,"]:",json.dumps(v['rows'][i]['value'],indent=3)
        r = resultSet['rows'][i]['value']
        stime = r['record-stop']
        if ((stime > now) and (stime < now + 60) and (stime < earliest)):
            mini = i
            ealiest = stime
    return None if mini == -1 else resultSet['rows'][mini]['value']


scheduleSemaphoreCnt = 0
def getScheduleResultSet(prev):
    global scheduleSemaphoreCnt
    resetIt = prev == None
    if (not resetIt):
        scheduleSemaphoreCnt += 1
        resetIt = (scheduleSemaphoreCnt > 15)
        if (not resetIt):
            resetIt = os.path.isfile(SemaphoreDirtyFile)
    if (resetIt):
        scheduleSemaphoreCnt = 0
        if (os.path.isfile(SemaphoreDirtyFile)):
            os.remove(SemaphoreDirtyFile)
        return fetchScheduledResultSet()
    else:
        return prev


captureSemaphoreCnt = 0
def getCapturingResultSet(prev):
    global captureSemaphoreCnt
    global activeCaptureCnt
    if (activeCaptureCnt):
        resetIt = prev == None
        if (not resetIt):
            captureSemaphoreCnt += 1
            resetIt = captureSemaphoreCnt > 5
        if (resetIt):
            captureSemaphoreCnt = 0
            return fetchCapturingResultSet()
        else:
            return prev
    else:
        return prev

    
def handleMissedSchedules(rs, now):
    missedCompletely = selectNextMissedEnd(rs, now)
    while (missedCompletely != None):
        print "Found at least one that I missed completely!", json.dumps(missedCompletely,indent=3)
        missedCompletely['type'] = 'error'
        missedCompletely['errmsg'] = 'schedule missed completely'
        missedCompletely['missed-timestamp'] = str(now)
        id = missedCompletely['_id']
        del missedCompletely['_id']
        #print "Here's the update I'm going to make:", json.dumps(missedCompletely,indent=3)
        url = UPDATE_URL+'/'+id
        r = requests.put(url, auth=(DbKey, DbPswd), json=missedCompletely)
        #print r.json()
        rs = fetchScheduledResultSet(None)
        missedCompletely = selectNextMissedEnd(rs,now)
    return rs


def startCapture(n, now):
    global activeCaptureCnt
    proc = subprocess.Popen(["/usr/bin/python", "test.py"], shell=True)
    activeCaptureCnt += 1
    n['type'] = 'capturing'
    n['capture-start-timestamp'] = str(now)
    n['pid'] = str(proc.pid)
    id = n['_id']
    del n['_id']
    print "Here's the update I'm going to make:", json.dumps(n,indent=3)
    url = UPDATE_URL+'/'+id
    r = requests.put(url, auth=(DbKey, DbPswd), json=n)
    #print r.json()


def stopCapture(s, now):
    global activeCaptureCnt
    process = psutil.Process(int(s['pid']))
    for proc in process.children(recursive=True):
        proc.kill()
    process.kill()
    activeCaptureCnt -= 1
    s['type'] = 'recording'
    s['capture-stop-timestamp'] = str(now)
    id = s['_id']
    del s['_id']
    print "Here's the update I'm going to make:", json.dumps(s,indent=3)
    url = UPDATE_URL+'/'+id
    r = requests.put(url, auth=(DbKey, DbPswd), json=s)
    #print r.json()

    
def handleLateStarts(rs, now):
    #print json.dumps(rs,indent=3)
    late = selectNextMissedStart(rs, now)
    while (late != None):
        print "Found at least schedule that should have started already!", json.dumps(late,indent=3)
        startCapture(late, now)
        rs = fetchScheduledResultSet()
        late = selectNextMissedStart(rs, now)
    return rs


def handleNextStart(rs, now):
    print json.dumps(rs,indent=3)
    n = selectNextStart(rs, now)
    while (n != None):
        print "Found a schedule that starts within a minute:", json.dumps(n,indent=3)
        startCapture(n, now)
        rs = fetchScheduledResultSet(None)
        n = selectNextStart(rs, now)
    return rs


def handleNextStop(crs, now):
    s = selectNextStop(crs, now)
    while (s != None):
        print "Found an active capture that should be stopped:", json.dumps(s,indent=3)
        stopCapture(s, now)
        crs = fetchCapturingResultSet()
        s = selectNextStop(crs, now)
    return crs


            
srs = getScheduleResultSet(None)
crs = getCapturingResultSet(None)
while (True):
    now = calendar.timegm(time.gmtime())
    print "now:", now
    print ""

    print "looking for completely missed..."
    srs = handleMissedSchedules(srs, now)
    print ""

    print "looking for late..."
    srs = handleLateStarts(srs, now)
    print ""
    
    print "looking for ones that should start now..."
    srs = handleNextStart(srs, now)
    print ""

    print "looking for ones that should stop now..."
    crs = handleNextStop(crs, now)
    print ""

    time.sleep(50)
    srs = getScheduleResultSet(srs)
    crs = getCapturingResultSet(crs)
