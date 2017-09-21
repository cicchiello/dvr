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
import configparser


def usage():
    print "Usage:",sys.argv[0]," -ini <ini-file> [-mode {prod|dev}]"
    print ""
    print "Note, each instance of this will, at most, process one compression at a time.  You can safely "
    print "start multiple instaces if the platform can handle multiple compressions at once"
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

CAPTURED_URL = VIEW_BASE+'uncompressed-recordings'

def fetchUncompressedRecordingSet():
    return json.loads(requests.get(CAPTURED_URL).text)

    
def selectEarliest(resultSet,now):
    if (resultSet == None):
        return None;
    
    earliest = 9999999999
    mini = -1
    for i in range(0, len(resultSet['rows'])):
        #print "row[",i,"]:",json.dumps(resultSet['rows'][i]['value'],indent=3)
        r = resultSet['rows'][i]['value']
        stime = r['record-end']
        #print "stime: ",stime
        if (stime < earliest):
            mini = i
            earliest = stime
            #print "found an earlier: ",earliest
    return None if mini == -1 else resultSet['rows'][mini]['value']


uncompressedSetRefreshCnt = 0
def getUncompressedRecordingSet(prev):
    global uncompressedSetRefreshCnt
    resetIt = prev == None
    if (not resetIt):
        uncompressedSetRefreshCnt += 1
        resetIt = (uncompressedSetRefreshCnt > 15)
    if (resetIt):
        uncompressedSetRefreshCnt = 0
        return fetchUncompressedRecordingSet()
    else:
        return prev


def invoke(n, id):
    # three things to do, all while blocking (i.e. wait for background processes)
    #   1: ffmpeg to convert (and effectively compress) from raw to h264 (use file ext ".mpg")
    #   2: mv resultant file to ./library; change file ext to ".mp4"
    #   3: mv raw file to ./trashcan

    # ffmpeg step
    url = n['url']
    infile = dvr_fs+'/'+n['file']
    tmpfile = dvr_fs+'/compressing/'+id+'.mpg';
    outfile = dvr_fs+'/library/'+id+'.mp4';
    trashdir = dvr_fs+'/trashcan';
    cmdArr = ['/usr/local/bin/ffmpeg','-loglevel','quiet','-i',infile,'-vf','yadif=1','-vf','scale=-1:720','-c:v','libx264',tmpfile];
    print nowstr(),"INFO: compression cmd: ",cmdArr

    try: 
        r = subprocess.check_call(cmdArr)
        print nowstr(),"return code from ffmpeg call:",r
    except subprocess.CalledProcessError as e:
        print "subprocess.CalledProcessError:",e.output
        exit()

    #mv resulting h264 file to ./library
    cmdArr = ['/bin/mv',tmpfile,outfile]
    print nowstr(),"INFO: mv result file cmd: ",cmdArr

    try:
        r = subprocess.check_call(cmdArr)
        print nowstr(),"return code from mv call:",r
    except subprocess.CalledProcessError as e:
        print "subprocess.CalledProcessError:",e.output
        exit()

    #mv raw file to ./trashcan
    cmdArr = ['/bin/mv',infile,trashdir]
    print nowstr(),"INFO: mv raw file to trashcan cmd: ",cmdArr

    try:
        r = subprocess.check_call(cmdArr)
        print nowstr(),"return code from mv call:",r
    except subprocess.CalledProcessError as e:
        print "subprocess.CalledProcessError:",e.output
        exit()


    
def compress(n, now, fs):
    id = n['_id']
    url = POST_URL+'/'+id
    del n['_id']
    if (os.path.isfile(dvr_fs+'/'+n['file'])):
        n['compression-start-timestamp'] = now
        n['compressing'] = True;
        print nowstr(),"Here's the update I'm going to make:", json.dumps(n,indent=3)
        r = requests.put(url, auth=DbWriteAuth, json=n)
        print json.dumps(r.json(),indent=3)
        if ('ok' in r.json()):
            print nowstr(),"Success"
            n['_rev'] = r.json()['rev']
            invoke(n, id)
            print nowstr(),"INFO: returned from invoke; updating db record..."
            del n['compressing']
            n['compression-end-timestamp'] = calendar.timegm(time.gmtime())
            n['file'] = 'library/'+id+'.mp4'
            n['is-compressed'] = True;
            print nowstr(),"Here's the update I'm going to make:", json.dumps(n,indent=3)
            r = requests.put(url, auth=DbWriteAuth, json=n)
            if ('ok' in r.json()):
                print nowstr(),"Success"
            else:
                print nowstr(),"Failed: ",r.json()
        else:
            print nowstr(),"Failed: ",r.json()
    else:
        print nowstr(),"File not found for compression; marking as compressed and skipping"
        if ('compression-start-timestamp' in n): del n['compression-start-timestamp']
        if ('compressing' in n): del n['compressing']
        n['is-compressed'] = True
        print nowstr(),"Here's the update I'm going to make:", json.dumps(n,indent=3)
        r = requests.put(url, auth=DbWriteAuth, json=n)
        if ('ok' in r.json()):
            print nowstr(),"Success"
        else:
            print nowstr(),"Failed: ",r.json()


def handleUncompressedRecordingSet(rs, now, fs):
    #print json.dumps(rs,indent=3)
    n = selectEarliest(rs, now)
    if (n != None):
        print nowstr(),"INFO: Found the oldest uncompressed recording; starting compression now"
        compress(n, now, fs)
        rs = fetchUncompressedRecordingSet()
    return rs


urs = getUncompressedRecordingSet(None)
while (True):
    now = calendar.timegm(time.gmtime())
    print nowstr(), "now:", now
    print ""

    print nowstr(),"looking for newly captured recording to compress..."
    srs = handleUncompressedRecordingSet(urs, now, dvr_fs)
    print ""

    print nowstr(),"Sleeping..."
    time.sleep(50)
    urs = getUncompressedRecordingSet(None)
