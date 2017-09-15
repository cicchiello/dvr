#!/usr/bin/python

import json
import calendar
import time
import requests
import os.path
import subprocess
import psutil
import time
import datetime
import sys

SemaphoreDirtyFile = '/tmp/dbtouched'

ProdDbKey = 'socksookeesayedwerameate'
ProdDbPswd = 'c2d5c73bc067e9f73fd568c3ef783232fb2d0498'

ProdDbBase = 'https://jfcenterprises.cloudant.com'
DevDbBase = 'http://joes-mac-mini:5984'

ProdDb = 'dvr'
DevDb = 'dvr'

ProdDbWriteAuth = (ProdDbKey,ProdDbPswd)
DevDbWriteAuth = None

mode = "dev"

DbBase = ProdDbBase if (mode == "prod") else DevDbBase
Db = ProdDb if (mode == "prod") else DevDb
DbWriteAuth = ProdDbWriteAuth if (mode == "prod") else DevDbWriteAuth

ALL_OBJS_URL = DbBase+'/'+Db+'/_all_docs'
BULK_DOCS_URL = DbBase+'/'+Db+'/_bulk_docs'
POST_URL = DbBase+'/'+Db
VIEW_BASE = DbBase+'/'+Db+'/_design/dvr/_view/'

SCHEDULE_URL = VIEW_BASE+'scheduled'
CAPTURING_URL = VIEW_BASE+'capturing'

activeCaptureCnt = 0

def nowstr():
    fmt = 'INFO: %Y-%b-%d %H:%M:%S :'
    return datetime.datetime.today().strftime('INFO: %Y-%b-%d %H:%M:%S :')

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
    if (len(resultSet) == 0):
        return None;
    
    earliest = 9999999999
    mini = -1
    for i in range(0, len(resultSet['rows'])):
        #print "row[",i,"]:",json.dumps(v['rows'][i]['value'],indent=3)
        r = resultSet['rows'][i]['value']
        stime = r['record-end']
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
        resetIt = len(prev) == 0
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
        rs = fetchScheduledResultSet()
        missedCompletely = selectNextMissedEnd(rs,now)
    return rs


def testInvoke(n):
    return subprocess.Popen(["/usr/bin/python", "recording-proxy.py"], shell=True)
    
def devInvoke(n, fs):
    id = n['_id']
    url = n['url']
    cmdArr = ['/usr/bin/curl','-X','GET',url,'-i','-o',fs+'/'+id+'.mp4'];
    print nowstr(),"INFO: cmd: ",cmdArr
    return subprocess.Popen(cmdArr, shell=False)

def prodInvoke(n):
    print "Hi"
    
def startCapture(n, now, fs):
    global activeCaptureCnt
    proc = devInvoke(n, fs) if (mode == "dev") else prodInvoke(n)
    print nowstr(),"INFO: returned from invoke"
    activeCaptureCnt += 1
    n['type'] = 'capturing'
    n['capture-start-timestamp'] = str(now)
    n['pid'] = str(proc.pid)
    id = n['_id']
    del n['_id']
    print nowstr(),"Here's the update I'm going to make:", json.dumps(n,indent=3)
    url = POST_URL+'/'+id
    r = requests.put(url, auth=DbWriteAuth, json=n)
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
    print nowstr(),"Here's the update I'm going to make:", json.dumps(s,indent=3)
    url = POST_URL+'/'+id
    r = requests.put(url, auth=DbWriteAuth, json=s)
    #print r.json()

    
def handleLateStarts(rs, now, fs):
    #print json.dumps(rs,indent=3)
    late = selectNextMissedStart(rs, now)
    while (late != None):
        print nowstr(),"INFO: Found at least one schedule that should have started already!  Starting now." #, json.dumps(late,indent=3)
        startCapture(late, now, fs)
        rs = fetchScheduledResultSet()
        late = selectNextMissedStart(rs, now)
    return rs


def handleNextStart(rs, now, fs):
    #print json.dumps(rs,indent=3)
    n = selectNextStart(rs, now)
    while (n != None):
        print nowstr(),"INFO: Found a schedule that starts within a minute; starting it now"
        startCapture(n, now, fs)
        rs = fetchScheduledResultSet()
        n = selectNextStart(rs, now)
    return rs


def handleNextStop(crs, now):
    #print "trace 1; crs: ",crs
    s = selectNextStop(crs, now)
    #print "trace 2"
    while (s != None):
        print nowstr(),"INFO: Found an active capture that should be stopped:", json.dumps(s,indent=3)
        stopCapture(s, now)
        crs = fetchCapturingResultSet()
        s = selectNextStop(crs, now)
    #print "trace 3"
    return crs


def usage():
    print "ERROR: missing argument <dvr-filesystem>"
    print ""
    print "Usage:",sys.argv[0]," <dvr-filesystem>"
    print ""
    exit()


if (len(sys.argv) < 2):
    usage()

dvr_fs = sys.argv[1]
print nowstr(), "Using dvr-filesystem set to:", dvr_fs
            
srs = getScheduleResultSet(None)
crs = getCapturingResultSet([])
while (True):
    now = calendar.timegm(time.gmtime())
    print nowstr(), "now:", now
    print ""

    print nowstr(),"looking for completely missed..."
    srs = handleMissedSchedules(srs, now)
    print ""

    print nowstr(),"looking for late..."
    srs = handleLateStarts(srs, now, dvr_fs)
    print ""
    
    print nowstr(),"looking for ones that should start now..."
    srs = handleNextStart(srs, now, dvr_fs)
    print ""

    print nowstr(),"looking for ones that should stop now..."
    crs = handleNextStop(crs, now)
    print ""

    time.sleep(50)
    srs = getScheduleResultSet(srs)
    crs = getCapturingResultSet(crs)
    #print "srs: ", srs
    #print "crs: ", crs
