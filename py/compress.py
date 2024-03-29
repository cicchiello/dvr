#!/usr/bin/python

import json
import socket
import calendar
import time
import requests
import os
import os.path
import psutil
import subprocess
import time
import datetime
import sys
import configparser

MAX_COMPRESSIONS=1

HEARTBEAT_RATE_MIN=2
# ZOMBIE_HUNT_RATE_MIN needs to be at least 2x HEARTBEAT_RATE_MIN
ZOMBIE_HUNT_RATE_MIN=2*HEARTBEAT_RATE_MIN+1

activeCompressions=[]

def usage():
    print "Usage:",sys.argv[0]," -ini <ini-file> [-mode {prod|dev}] [-v]"
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

if ((len(sys.argv) > 3) and (sys.argv[3] != "-mode") and (sys.argv[3] != "-v")):
    print "ERROR: expected '-mode' or '-v' for 4th argument"
    usage()

verbose = False
mode = "prod"
if ((len(sys.argv) > 3) and (sys.argv[3] == "-mode")):
    mode = sys.argv[4]
    if ((len(sys.argv) > 5) and (sys.argv[5] != "-v")):
        print "ERROR: expected '-v' for last argument"
    else:
        verbose = (len(sys.argv) > 5) and (sys.argv[5] == "-v")
else:
    verbose = (len(sys.argv) > 3) and (sys.argv[3] == "-v")
    
dvr_fs = os.path.dirname(sys.argv[0])
iniFilename = sys.argv[2]
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

if (verbose):
    print nowstr(), "ECHO: Host:", socket.gethostname()
    print nowstr(), "ECHO: Launched:", sys.argv[0]
    print nowstr(), "ECHO: Mode:", mode
    print nowstr(), "ECHO: Using dvr-filesystem root:", dvr_fs
    print nowstr(), "ECHO: DbBase:", DbBase
    print nowstr(), "ECHO: DbKey:", DbKey
    print nowstr(), "ECHO: DbPswd:", DbPswd
    print nowstr(), "ECHO: Db:", Db
    print nowstr(), "ECHO: DbWriteAuth:", DbWriteAuth

ALL_OBJS_URL = DbBase+'/'+Db+'/_all_docs'
BULK_DOCS_URL = DbBase+'/'+Db+'/_bulk_docs'
POST_URL = DbBase+'/'+Db
VIEW_BASE = DbBase+'/'+Db+'/_design/dvr/_view/'

CAPTURED_URL = VIEW_BASE+'uncompressed-recordings'
COMPRESSING_URL = VIEW_BASE+'compressing-recordings'


def selectEarliest(resultSet,now):
    if (resultSet == None):
        return None
    
    earliest = 9999999999
    mini = -1
    for i in range(0, len(resultSet)):
        #print "row[",i,"]:",json.dumps(resultSet[i]['value'],indent=3)
        r = resultSet[i]['value']
        stime = r['record-end']
        #print "stime: ",stime
        if (stime < earliest):
            mini = i
            earliest = stime
            #print "found an earlier: ",earliest
    return None if mini == -1 else resultSet[mini]['value']


def fetchActiveCompressionsResultSet():
    #print nowstr(),"INFO: Fetching active compressions with GET to: "+COMPRESSING_URL
    responseStr = requests.get(COMPRESSING_URL).text
    #print nowstr(),"response: "+responseStr
    rset = json.loads(responseStr)['rows']
    actives = []
    for i in range(0, len(rset)):
        n = rset[i]['value']
        actives.append({'proc':n['proc'],'record':n,'heartbeat':n['compression-heartbeat']})
    return actives

    
def fetchUncompressedRecordingSet():
    #print nowstr(),"fetching uncompressed recording set with GET to: "+CAPTURED_URL
    responseStr = requests.get(CAPTURED_URL).text
    #print nowstr(),"response: "+responseStr
    return json.loads(responseStr)['rows']

    
def cleanDescription(d):
    cleanedDescription = ''
    legalchars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-., '
    for c in d:
        if (c not in legalchars):
            c = 'X'
        cleanedDescription += c
    return cleanedDescription
    

def closeCompression(n, now, fs):
    #setup symlink to resulting h264 file in ./library
    
    #  (but first due to quirk in ffmpeg libs, have to correct the file extension)
    id = n['_id']
    tmpfile = fs+'/compressed/'+id+'.mpg';
    outfile = fs+'/compressed/'+id+'.mp4';
    cmdArr = ['/bin/mv',tmpfile,outfile]
    print nowstr(),"INFO: mv result file cmd: ",cmdArr

    try:
        r = subprocess.check_call(cmdArr)
        print nowstr(),"return code from mv call:",r
    except subprocess.CalledProcessError as e:
        print "subprocess.CalledProcessError:",e.output
        exit()

    cleanDesc = cleanDescription(n['description'])
    dstfile = fs+'/library/'+cleanDesc+'.mp4'

    print nowstr(),"DEBUG: if present, first remove:",dstfile
    try:
        os.remove(dstfile)
    except:
        print nowstr(),"DEBUG: exception caught during remove attempt; assuming the file doesn't exist"
        
    print nowstr(),"DEBUG: Here's the symlink to establish:",outfile," ",dstfile
    os.symlink(outfile, dstfile)
    
    #mv raw file to ./trashcan
    infile = fs+'/'+n['file']
    trashdir = fs+'/trashcan';
    cmdArr = ['/bin/mv',infile,trashdir]
    print nowstr(),"INFO: mv raw file to trashcan cmd: ",cmdArr

    try:
        r = subprocess.check_call(cmdArr)
        print nowstr(),"return code from mv call:",r
    except subprocess.CalledProcessError as e:
        print "subprocess.CalledProcessError:",e.output
        exit()

    id = n['_id']
    url = POST_URL+'/'+id
    del n['_id']
    n.pop('compressing', None)
    n.pop('compression-heartbeat', None)
    n.pop('proc', None)
    n['compression-end-timestamp'] = now
    n['file'] = 'library/'+cleanDesc+'.mp4'
    n['is-compressed'] = True;
    print nowstr(),"Here's the update I'm going to make: \n", url, json.dumps(n,indent=3)
    r = requests.put(url, auth=DbWriteAuth, json=n)
    print nowstr(),("Success" if 'ok' in r.json() else "Failed: ",json.dumps(r.json(),indent=3))


def revertCompression(n, now, fs):
    id = n['_id']
    url = POST_URL+'/'+id
    del n['_id']
    n.pop('compressing', None)
    n.pop('compression-start-timestamp', None)
    n.pop('compression-heartbeat', None)
    print nowstr(),"Here's the update I'm going to make:\n", url, json.dumps(n,indent=3)
    r = requests.put(url, auth=DbWriteAuth, json=n)
    print nowstr(),("Success" if 'ok' in r.json() else "Failed: ",json.dumps(r.json(),indent=3))


    
def heartbeat(n, now):
    id = n['_id']
    url = POST_URL+'/'+id
    del n['_id']
    prevHeartbeat = n['compression-heartbeat'];
    n['compression-heartbeat'] = now
    #print nowstr(),"Updating the db heartbeat"
    print nowstr(),"Here's the heartbeat update I'm going to make:", url, json.dumps(n,indent=3)
    r = requests.put(url, auth=DbWriteAuth, json=n)
    #print nowstr(),"Here's the reply: ", json.dumps(r.json(),indent=3)
    n['_id'] = id
    if ('ok' in r.json()):
        print nowstr(),"Success"
        n['_rev'] = r.json()['rev']
    else:
        print nowstr(),"Failed:",json.dumps(r.json(),indent=3)
        n['compression-heartbeat'] = prevHeartbeat
    return n


def preexec_fn():
    pid = os.getpid()
    ps = psutil.Process(pid)
    ps.nice(15)

    
def compress(n, now, fs):
    id = n['_id']
    url = POST_URL+'/'+id
    del n['_id']
    if (os.path.isfile(fs+'/'+n['file'])):
        n['compression-start-timestamp'] = now
        n['compressing'] = True;
        n['compression-heartbeat'] = now

        #spawn the compression job
        infile = fs+'/'+n['file']
        tmpfile = fs+'/compressed/'+id+'.mpg';
        cmdArr = ['/usr/local/bin/ffmpeg','-loglevel','quiet','-i',infile, \
                  '-vf','yadif=1','-vf','scale=-1:720','-c:v','libx264','-y',tmpfile];
        print nowstr(),'INFO: compression cmd to issue: ',cmdArr
        proc = subprocess.Popen(cmdArr, stdout=subprocess.PIPE, stderr=subprocess.PIPE, shell=False, preexec_fn=preexec_fn)
        n['proc'] = proc.pid
        print nowstr(),"Here's the db update I'm going to make for id:", id, json.dumps(n,indent=3)
        r = requests.put(url, auth=DbWriteAuth, json=n)
        print nowstr(),("Success" if 'ok' in r.json() else "Failed: ",json.dumps(r.json(),indent=3))
    else:
        print nowstr(),"File not found for compression; marking as compressed and skipping"
        n.pop('compression-start-timestamp', None)
        n.pop('compressing', None)
        n.pop('compression-heartbeat', None)
        n['is-compressed'] = True
        print nowstr(),"Here's the update I'm going to make:\n", url, json.dumps(n,indent=3)
        r = requests.put(url, auth=DbWriteAuth, json=n)
        print nowstr(),("Success" if 'ok' in r.json() else "Failed: ",json.dumps(r.json(),indent=3))
    return None


def handleUncompressedRecordingSet(rs, now, fs):
    #print json.dumps(rs,indent=3)
    n = selectEarliest(rs, now)
    if (n != None):
        print nowstr(),'INFO: Found the oldest uncompressed recording; starting compression now'
        proc = compress(n, now, fs)
        rs = fetchUncompressedRecordingSet()
    return rs



def zombieHunt(now):
    print nowstr(), "INFO: Performing Zombie Hunt"
    print nowstr(),"Making GET request to: "+COMPRESSING_URL
    rset = json.loads(requests.get(COMPRESSING_URL).text)['rows']
    #print nowstr(),"DEBUG: there are",len(rset),"compressing jobs found"
    #print nowstr(),"DEBUG: here's rset:",json.dumps(rset,indent=3)
    for i in range(0, len(rset)):
        #print nowstr(),"DEBUG: here's rset[i]:",json.dumps(rset[i],indent=3)
        n = rset[i]['value']
        if ((not psutil.pid_exists(n['proc'])) and (now > n['compression-heartbeat']+60*2*ZOMBIE_HUNT_RATE_MIN)):
            print nowstr(),'Found a zombie!  Reverting it to uncompressed state.'
            print nowstr(),'now: ',now
            print nowstr(),'last heartbeat: ',n['compression-heartbeat']
            revertCompression(n, now, dvr_fs)
    #print nowstr(), "DEBUG: Done Zombie Hunt"



def sysexception(t,e,tb):
    progname = "compress"
    
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
    f.write(progname+".py, on "+socket.gethostname()+", has shutdown unexpectedly!\n")
    print nowstr(), progname+".py, on "+socket.gethostname()+", has shutdown unexpectedly!"
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
    f.write("trace back: ")
    print nowstr(), "trace back"+str(tb)
    f.write(str(tb))
    f.write("\n")
    print nowstr(), ""
    f.write("\n")
    print nowstr(), ""
    f.close()
    with open(filename, 'r') as infile:
        subprocess.Popen(['/usr/sbin/ssmtp', 'j.cicchiello@gmail.com'],
                         stdin=infile, stdout=sys.stdout, stderr=sys.stderr)
    traceback.print_tb(tb)
    exit()


sys.excepthook = sysexception


# Let's wait a bit before starting anything that might need the db, in case it's not available yet
time.sleep(5)

urs = fetchUncompressedRecordingSet()
now = calendar.timegm(time.gmtime())
zombieTimestamp = now
zombieHunt(zombieTimestamp)

now = calendar.timegm(time.gmtime())
#print nowstr(), "now:", now

#print nowstr(),"INFO: Fetching the list of active compression jobs..."
activeCompressions = fetchActiveCompressionsResultSet()
#print nowstr(),"INFO: ...found",len(activeCompressions)
if (len(activeCompressions) < MAX_COMPRESSIONS):
    print nowstr(),"INFO: Looking for a captured recording to compress..."
    urs = handleUncompressedRecordingSet(urs, now, dvr_fs)
    #print nowstr(),"DEBUG: Refetching the list of active compression jobs..."
    activeCompressions = fetchActiveCompressionsResultSet()
else:
    print nowstr(),"INFO: Not considering new compressions while MAX_COMPRESSIONS =",MAX_COMPRESSIONS,"already running"

print nowstr(),"INFO: Looking for active compression jobs"
for compression in activeCompressions:
    #print "considering: ",compression
    n = compression['record']
    id = n['_id']
    now = calendar.timegm(time.gmtime())
    pid = compression['proc']
    if (psutil.pid_exists(pid)):
        if (now > compression['heartbeat']+60*HEARTBEAT_RATE_MIN):
            # annotate the db record with a heartbeat so that future runs can
            # identify zombie jobs
            n = heartbeat(n, now)
            compression['heartbeat'] = n['compression-heartbeat']
            compression['record'] = n
    else:
        print nowstr(),'INFO: detected subprocess',pid,'completion...'
        print "NEED A WAY TO DETECT FAILURE!"
        success=True
        if (success):
            closeCompression(n, now, dvr_fs);
        else:
            print nowstr(),'The compression job for',pid,'failed'
            print nowstr(),'the subprocess returncode is',proc.returncode
            revertCompression(n, now, dvr_fs);
    
#print nowstr(),"INFO: Done for now"
