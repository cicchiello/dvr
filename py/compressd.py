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

HEARTBEAT_RATE_MIN=10
# ZOMBIE_HUNT_RATE_MIN needs to be at least 2x HEARTBEAT_RATE_MIN
ZOMBIE_HUNT_RATE_MIN=2*HEARTBEAT_RATE_MIN+1

activeCompressions=[]

def usage():
    print("Usage: %s -ini <ini-file> [-mode {prod|dev}]" % (sys.argv[0]))
    print("")
    print("Note, each instance of this will, at most, process one compression at a time.  ")
    print("You can safely start multiple instaces if the platform can handle multiple ")
    print("compressions at once")
    exit()

          
def nowstr():
    return datetime.datetime.today().strftime('%Y-%b-%d %H:%M:%S')

          
if ((len(sys.argv) < 3) or \
    ((len(sys.argv) > 3) and (len(sys.argv) < 5))):
    print("ERROR(%s): wrong number of arguments; expected 2 or 4" % (nowstr()))
    print("")
    usage()

if (sys.argv[1] != "-ini"):
    print("ERROR(%s): expected '-ini' for 2nd argument" % (nowstr()))
    print("")
    usage()

if ((len(sys.argv) > 3) and (sys.argv[3] != "-mode")):
    print("ERROR(%s): expected '-mode' for 4th argument" % (nowstr()))
    print("")
    usage()

          
dvr_fs = os.path.dirname(sys.argv[0])
iniFilename = sys.argv[2]
mode = "prod" if (len(sys.argv) < 4) else sys.argv[4]
config = configparser.ConfigParser()

if (not os.path.isfile(iniFilename)):
    print("ERROR(%s): %s file doesn't exist" % (nowstr(), iniFilename))
    print("")
    usage()

          
config.read(iniFilename)

if (not os.path.isdir(dvr_fs+"/raw")):
    print("ERROR(%s): recording location doesn't exists: %s/raw" % (nowstr(), dvr_fs))
    print("")
    usage()

if (not (mode in config)):
    print("ERROR(%s): invalid config file; expected %s section" % (nowstr(), mode))
    print("")
    usage()

          
DbBase = config[mode]['DbBase']
DbKey = config[mode]['DbKey']
DbPswd = config[mode]['DbPswd']
Db = config[mode]['Db']
DbWriteAuth = None if (not (DbKey and DbPswd)) else (DbKey,DbPswd)

print("INFO(%s): Launched: %s" % (nowstr(), sys.argv[0]))
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

CAPTURED_URL = VIEW_BASE+'uncompressed-recordings'
COMPRESSING_URL = VIEW_BASE+'compressing-recordings'


def selectEarliest(resultSet,now):
    if (resultSet == None):
        return None
    
    earliest = 9999999999
    mini = -1
    for i in range(0, len(resultSet)):
        r = resultSet[i]['value']
        stime = r['record-end']
        if (stime < earliest):
            mini = i
            earliest = stime
    return None if mini == -1 else resultSet[mini]['value']


uncompressedSetRefreshCnt = 0
def fetchUncompressedRecordingSet():
    global uncompressedSetRefreshCnt
    uncompressedSetRefreshCnt = 0;
    print("INFO(%s): Checking for uncompressed recordings..." % (nowstr()))
    return json.loads(requests.get(CAPTURED_URL).text)['rows']

    
def getUncompressedRecordingSet(prev):
    global uncompressedSetRefreshCnt
    resetIt = prev == None
    if (not resetIt):
        uncompressedSetRefreshCnt += 1
        resetIt = (uncompressedSetRefreshCnt > 5)
    if (resetIt):
        uncompressedSetRefreshCnt = 0
        return fetchUncompressedRecordingSet()
    else:
        return prev


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
    tmpfile = fs+'/compressed/'+id+'.mkv';
    outfile = fs+'/compressed/'+id+'.mp4';
    cmdArr = ['/bin/mv',tmpfile,outfile]
    print("INFO(%s): mv result file cmd: %s" % (nowstr(),cmdArr))

    try:
        r = subprocess.check_call(cmdArr)
        print("INFO(%s): return code from mv call: %s" % (nowstr(),r))
    except subprocess.CalledProcessError as e:
        print("ERROR(%s): subprocess.CalledProcessError: %s" % (nowstr(), e.output))
        exit()

    cleanDesc = cleanDescription(n['description'])
    dstfile = fs+'/library/'+cleanDesc+'.mp4'
    print("DEBUG(%s): Here's the symlink to establish: %s->%s" % (nowstr(),dstfile,outfile))
    if os.path.isfile(dstfile):
        os.remove(dstfile)
    os.symlink(outfile, dstfile)
    
    #mv raw file to ./trashcan
    infile = fs+'/'+n['file']
    trashdir = fs+'/trashcan';
    cmdArr = ['/bin/mv',infile,trashdir]
    print("INFO(%s): mv raw file to trashcan cmd: %s" % (nowstr(), cmdArr))

    try:
        r = subprocess.check_call(cmdArr)
        print("DEBUG(%s): return code from mv call: %d" % (nowstr(), r))
    except subprocess.CalledProcessError as e:
        print("ERROR(%s): subprocess.CalledProcessError: %s" % (nowstr(), e.output))
        exit()

    id = n['_id']
    url = POST_URL+'/'+id
    del n['_id']
    n.pop('compressing', None)
    n.pop('compression-heartbeat', None)
    n['compression-end-timestamp'] = now
    n['file'] = 'library/'+cleanDesc+'.mp4'
    n['is-compressed'] = True;
    print("INFO(%s): Here's the update I'm going to make: %s" % (nowstr(),json.dumps(n,indent=3)))
    r = requests.put(url, auth=DbWriteAuth, json=n)
    if 'ok' in r.json():
       print("INFO(%s): Success" % (nowstr()))
    else:
       print("ERROR(%s): Failed: %s" % (nowstr(), r.json()))


def revertCompression(n, now, fs):
    id = n['_id']
    url = POST_URL+'/'+id
    del n['_id']
    n.pop('compressing', None)
    n.pop('compression-start-timestamp', None)
    n.pop('compression-heartbeat', None)
    print("DEBUG(%s): Here's the update I'm going to make: %s" % (nowstr(),json.dumps(n,indent=3)))
    r = requests.put(url, auth=DbWriteAuth, json=n)
    if 'ok' in r.json():
        print("INFO(%s): Success" % (nowstr()))
    else:
        print("ERROR(%s): Failed: %s" % (nowstr(), r.json()))

    
def heartbeat(n, now):
    id = n['_id']
    url = POST_URL+'/'+id
    del n['_id']
    prevHeartbeat = n['compression-heartbeat'];
    n['compression-heartbeat'] = now
    print("INFO(%s): Here's the heartbeat update I'm making: %s" % (nowstr(),json.dumps(n,indent=3)))
    r = requests.put(url, auth=DbWriteAuth, json=n)
    if 'ok' in r.json():
        #print("DEBUG(%s): Success" % (nowstr()))
        n['_rev'] = r.json()['rev']
    else:
        n['compression-heartbeat'] = prevHeartbeat
        print("ERROR(%s): Failed: %s" % (nowstr(), json.dumps(r.json(),indent=3)))
    n['_id'] = id
    return n


def preexec_fn():
    pid = os.getpid()
    ps = psutil.Process(pid)
    ps.nice(15)

    
def compress(n, now, fs):
    global activeCompressions
    
    id = n['_id']
    url = POST_URL+'/'+id
    del n['_id']
    print('DEBUG(%s): checking for file: %s' % (nowstr(),fs+'/'+n['file']))
    if (os.path.isfile(fs+'/'+n['file'])):
        n['compression-start-timestamp'] = now
        n['compressing'] = True;
        n['compression-heartbeat'] = now

        #spawn the compression job
        infile = fs+'/'+n['file']
        tmpfile = fs+'/compressed/'+id+'.mkv';
        cmdArr = ['/usr/local/bin/ffmpeg','-loglevel','quiet','-i',infile, \
                  '-vf', 'scale=-1:720', '-c:v','libx264','-crf','24','-y',tmpfile];
        print('INFO(%s): compression cmd to issue: %s' % (nowstr(),cmdArr))
        proc = subprocess.Popen(cmdArr, stdout=subprocess.PIPE, stderr=subprocess.PIPE, shell=False, preexec_fn=preexec_fn)
        
        print("INFO(%s): Here's the db update I'm going to make for id: %s %s" % (nowstr(),id,json.dumps(n,indent=3)))
        r = requests.put(url, auth=DbWriteAuth, json=n)
        #print("DEBUG(%s): Here's the reply: %s" % (nowstr(), json.dumps(r.json(),indent=3)))
        if ('ok' in r.json()):
            print("INFO(%s): Success" % (nowstr()))
            n['_rev'] = r.json()['rev']
            n['_id'] = id
            activeCompressions.append({'proc':proc,'record':n,'heartbeat':now})
            return proc
        else:
            print("ERROR(%s): Failed: %s" % (nowstr(), json.dumps(r.json(),ndent=3)))
          
    else:
        print("WARNING(%s): File not found for compression; marking as compressed and skipping" % nowstr())
        n.pop('compression-start-timestamp', None)
        n.pop('compressing', None)
        n.pop('compression-heartbeat', None)
        n['is-compressed'] = True
        print("INFO(%s): Here's the update I'm going to make: %s %s" % (nowstr(), url, json.dumps(n,indent=3)))
        r = requests.put(url, auth=DbWriteAuth, json=n)
        if 'ok' in r.json():
            print("INFO(%s): Success" % (nowstr()))
        else:
            print("ERROR(%s): Failed: %s" % (nowstr(), json.dumps(r.json(),indent=3)))
          
    return None


def handleUncompressedRecordingSet(rs, now, fs):
    #print json.dumps(rs,indent=3)
    n = selectEarliest(rs, now)
    if (n != None):
        if (len(activeCompressions) < MAX_COMPRESSIONS):
            print('INFO(%s): Found the oldest uncompressed recording; starting compression now' % (nowstr()))
            proc = compress(n, now, fs)
            rs = fetchUncompressedRecordingSet()
        else:
            print("INFO(%s): skipping compression opportunity since it's already running" % (nowstr()))
    return rs



def zombieHunt(now):
    print("INFO(%s): Performing Zombie Hunt" % nowstr())
    #print("DEBUG(%s): Making GET request to: %s" % (nowstr(), COMPRESSING_URL))
    rset = json.loads(requests.get(COMPRESSING_URL).text)['rows']
    #print("DEBUG(%s): there are %d compressing jobs found" % (nowstr(), len(rset)))
    #print("DEBUG(%s): here's rset: %s" % (nowstr(),json.dumps(rset,indent=3)))
    for i in range(0, len(rset)):
        #print("DEBUG(%s): here's rset[%d]: %s" % (nowstr(), i, json.dumps(rset[i],indent=3)))
        n = rset[i]['value']
        if (now > n['compression-heartbeat']+60*2*ZOMBIE_HUNT_RATE_MIN):
            print('INFO(%s): Found a zombie!  Reverting it to uncompressed state.' % (nowstr()))
            #print('DEBUG(%s): last heartbeat: %s' % (nowstr(),n['compression-heartbeat']))
            revertCompression(n, now, dvr_fs)
    print("INFO(%s): Done Zombie Hunt" % nowstr())



def sysexception(t,e,tb):
    progname = "compressd"
    
    print('INFO(%s): sysexception called; preparing an email...' % nowstr())
    filename = "/tmp/"+progname+"-email-msg.txt"
    f = open(filename, "w", 0)
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
    f.write("trace back: ")
    print("INFO(%s): trace back %s" % (nowstr(), str(tb)))
    f.write(str(tb))
    f.write("\n")
    print("INFO(%s): " % (nowstr()))
    f.write("\n")
    print("INFO(%s): " % (nowstr()))
    f.close()
    with open(filename, 'r') as infile:
        subprocess.Popen(['/usr/sbin/ssmtp', 'j.cicchiello@gmail.com'],
                         stdin=infile, stdout=sys.stdout, stderr=sys.stderr)
    traceback.print_tb(tb)
    exit()


sys.excepthook = sysexception


# Let's wait a bit before starting anything that might need the db, in case it's not available yet
print("INFO(%s): Waiting a bit in case anything's not available yet" % (nowstr()))
time.sleep(50)

urs = getUncompressedRecordingSet(None)
now = calendar.timegm(time.gmtime())
zombieTimestamp = now
zombieHunt(zombieTimestamp)
print("INFO(%s): Entering main loop" % (nowstr()))
while (True):
    now = calendar.timegm(time.gmtime())

    if (len(activeCompressions) < MAX_COMPRESSIONS):
        #print("DEBUG(%s): looking for newly captured recording to compress..." % (nowstr()))
        urs = handleUncompressedRecordingSet(urs, now, dvr_fs)

    time.sleep(50)

    newCompressions = []
    for compression in activeCompressions:
        proc = compression['proc']
        n = compression['record']
        id = n['_id']
        now = calendar.timegm(time.gmtime())
        if (proc.poll() != None):
            print('INFO(%s): detected subprocess %s completion...' % (nowstr(), proc.pid))
            if (proc.returncode == 0):
                print('INFO(%s): The compression job for %s completed successfully' % (nowstr(), id))
                closeCompression(n, now, dvr_fs);
            else:
                print('INFO(%s): The compression job for %s failed' % (nowstr(), id))
                print('INFO(%s): the subprocess returncode is %s' % (nowstr(), proc.returncode))
                print('INFO(%s): Reverting the compression record' % (nowstr()))
                revertCompression(n, now, dvr_fs);
        else:
            if (now > compression['heartbeat']+60*HEARTBEAT_RATE_MIN):
                # annotate the db record with a heartbeat so that future runs can
                # identify zombie jobs
                n = heartbeat(n, now)
                compression['heartbeat'] = n['compression-heartbeat']
                compression['record'] = n
            newCompressions.append(compression)
    activeCompressions = newCompressions
    
    urs = getUncompressedRecordingSet(urs)
    #print("DEBUG(%s)Uncompressed recording set length: %d" % (nowstr(), len(urs)))

    if (now > zombieTimestamp+60*ZOMBIE_HUNT_RATE_MIN):
        zombieTimestamp = now
        zombieHunt(zombieTimestamp)
