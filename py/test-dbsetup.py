#!/usr/bin/python

import requests
import json
import re
import time
import calendar

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

# first, let's delete any records from previous runs of this script
# find them by description containing '.*test-dbsetup.*"
r = requests.get(ALL_OBJS_URL)
if (r.status_code != 200):
    print "GetAllObjs failed: ", r.status_code, r.reason

allIds = []
for row in json.loads(r.text)['rows']:
    allIds.append(row['id'])

#print "allIds: ",allIds
r = requests.get(ALL_OBJS_URL+'?include_docs=true', json={'keys':allIds})
#print json.dumps(json.loads(r.text)['rows'])

exp = re.compile('.*test-dbsetup.*')
deletionDoc = {"docs":[]}
for row in json.loads(r.text)['rows']:
    if (('description' in row['doc']) and (exp.match(row['doc']['description']))):
        deletionDoc['docs'].append({'_id':row['doc']['_id'],'_rev':row['doc']['_rev'],'_deleted':True})

#print "deletionDoc: ", deletionDoc
if (len(deletionDoc['docs'])):
    print "INFO: Deleting",len(deletionDoc['docs']),"doc(s)"
    r = requests.post(BULK_DOCS_URL, auth=DbWriteAuth, json=deletionDoc)
    if (r.status_code != 201):
        print "Deletion status: ",r.status_code, r.reason
        exit(-1);
    

# Missed test: provide an entry to test handling of the case where the event was totally missed
#   record-start set to 1 hr ago
#   record-end set to 30 minutes ago
missedStart = calendar.timegm(time.gmtime()) - 60*60
missedEnd = missedStart + 30*60
d={
    "type": "scheduled",
    "device": "1323B1E8",
    "channel": "14",
    "description": "test-dbsetup missed",
    "record-start": missedStart,
    "record-end": missedEnd,
    "url": "http://192.168.1.130:5004/auto/v16"
}
r = requests.post(POST_URL, auth=DbWriteAuth, json=d)
if (r.status_code != 201):
    print "Error: ", r.status_code, r.reason
    exit(-1);
print "INFO: Added a record for a completely missed event"

# late starting test: provide an entry to test handling of an event that should have started but
# not finished yet
#
#    record-start set to 30 min ago
#    record-end set to 30 minutes in the future
lateStart = calendar.timegm(time.gmtime()) - 30*60
lateEnd = lateStart + 33*60
d={
    "type": "scheduled",
    "device": "1323B1E8",
    "channel": "16",
    "description": "test-dbsetup late",
    "record-start": lateStart,
    "record-end": lateEnd,
    "url": "http://192.168.1.130:5004/auto/v18"
}
r = requests.post(POST_URL, auth=DbWriteAuth, json=d)
if (r.status_code != 201):
    print "Error: ", r.status_code, r.reason
    exit(-1);
print "INFO: Added a record for a late-starting event, that's due to stop in 3 minutes"

# about to start test: provide an entry to test handling of an event that is about to start
#    record-start set to 2 min in the future
#    record-end set to 5 minutes in the future
soonStart = calendar.timegm(time.gmtime()) + 2*60
soonEnd = soonStart + 3*60
d={
    "type": "scheduled",
    "device": "1323B1E8",
    "channel": "14",
    "description": "test-dbsetup near future",
    "record-start": soonStart,
    "record-end": soonEnd,
    "url": "http://192.168.1.130:5004/auto/v16"
}
r = requests.post(POST_URL, auth=DbWriteAuth, json=d)
if (r.status_code != 201):
    print "Error: ", r.status_code, r.reason
    exit(-1);
print "INFO: Added a record for an event that should start in 2 minutes and end in 5"
