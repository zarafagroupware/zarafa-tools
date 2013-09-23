#!/usr/bin/python -u
# m.verwijs@zarafa.com 

# This deletes rss feeds in the main folder. It does not see subfolders (yet). 
# Requires zarafa >= 6.40.11, or 7.x


# Help? : http://msdn.microsoft.com/en-us/library/cc839597.aspx

from MAPI import *
from MAPI.Struct import *
from MAPI.Util import *
from MAPI.Time import *
from time import *
from datetime import *
import time
import sys

# Age, in seconds, that rss feed entries are allowed to be.
# Change this before taking this into production! 
# 864000 = 10 days
offset = 864000


if len(sys.argv) != 2:
    print >> sys.stderr, "Usage: %s username" % sys.argv[0]
    sys.exit(1)
else:
    u=sys.argv[1]

# Opening a session to Zarafa with user / password:
session = OpenECSession(u, '', 'file:///var/run/zarafa')

# Opening the default store of the user
#  (Note: this is NOT the mail-inbox!)
store = GetDefaultStore(session)

# Getting the ID from the properties ...
props = store.GetProps([PR_IPM_SUBTREE_ENTRYID], 0)

# ... assign its value:
entryid = props[0].Value

# Using that ID to open the ipm_subtree
ipmsubtree = store.OpenEntry(entryid, None, MAPI_MODIFY)

# Assign the folders underneath the ipmsubtree to a table we can query:
table = ipmsubtree.GetHierarchyTable(0)

# Limit the columns to display when we query that table:
table.SetColumns([PR_DISPLAY_NAME, PR_ENTRYID], 0)

# Prepare the query:
table.FindRow(SPropertyRestriction(RELOP_EQ, PR_DISPLAY_NAME, SPropValue(PR_DISPLAY_NAME, 'RSS Feeds')), BOOKMARK_BEGINNING, 0)

# Execute the query and assign it the resulting id (as it should return only 1
# item we can immediatly assign it):
id = table.QueryRows(1,0)[0][1].Value

# In our session, open the entry with the id we got returned from our table
# query: 
rss = session.OpenEntry(id, None, MAPI_MODIFY)
table = rss.GetHierarchyTable(0)
table.SetColumns([PR_DISPLAY_NAME, PR_ENTRYID], TBL_BATCH)
rows = table.QueryRows(50, 0)

# For every feed in the Rss Feeds, 
for rss_feed in rows:
    rss_feed_id = rss_feed[1].Value
    rss_feed_entry = session.OpenEntry(rss_feed_id, None, MAPI_MODIFY)
    table = rss_feed_entry.GetContentsTable(0)
    table.SetColumns([PR_CREATION_TIME, PR_ENTRYID, PR_SUBJECT], TBL_BATCH)

    today = date.today()
    t = datetime.today()
    t = t.strftime("%Y/%m/%d %H:%M:%S GMT")
    u = unixtime(time.time() - offset)

    table.Restrict(SPropertyRestriction(RELOP_LT, PR_CREATION_TIME, SPropValue(PR_CREATION_TIME, unixtime(time.time() - offset))), TBL_BATCH)

    feed_entries = []
    while True:
        rows = table.QueryRows(50,0)
        if len(rows) == 0: 
            break
        for r in rows:
            feed_entries.append(r[1].Value)
    rss_feed_entry.DeleteMessages(feed_entries, 0, None, 0)
            
sys.exit(0)
