#!/usr/bin/env python

import zarafa
import sys

if len(sys.argv) < 3:
    sys.exit("Usage: %s source-user destination-user" % sys.argv[0])

server = zarafa.Server()

sourceuser = server.user(sys.argv[1])
targetuser = server.user(sys.argv[2])

left = sourceuser.store.subtree
try:
    right = targetuser.store.inbox.folder("Archives")
except zarafa.ZarafaNotFoundException:
    sys.exit("Archives folder not created under Inbox of user '%s', please create it." % targetuser.name)

if len(sourceuser.fullname) > 5:
    sourcename = sourceuser.fullname
else:
    sourcename = sourceuser.name

try:
    right.folder(sourcename)
except zarafa.ZarafaNotFoundException:
    right.create_folder(sourcename)

for folder in left.folders(recurse=False):
    if folder.name == "RSS Feeds": continue
    if folder.name == "Sync Issues": continue
    print "Copying folder '%s/IPM_SUBTREE/%s' to '%s/Inbox/%s'" % (sourceuser.name, folder.name, targetuser.name, right.name)
    left.copy(folder, right.folder(sourcename))
