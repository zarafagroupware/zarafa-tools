#!/usr/bin/python -u
###
# Author: Phantium
# List stuck Outbox messages and their hierarchy ID, allowing you to delete
# them from the database. While this is a VERY rare occurrence, this script assists
# with this.
###
# TO DO:
# - Add recipient table opening, to show on view
# - Add support to show Outbox for all users
# - Add -u to specify user and -d to generate a SQL query to pipe to MySQL. ./outbox.py -ublaat | mysql
###

from MAPI import *
from MAPI.Struct import *
from MAPI.Util import *
from MAPI.Time import *
from optparse import OptionParser

import locale
import sys
import string
import time

if len(sys.argv) < 2:
  sys.exit('Usage: %s username' % sys.argv[0])

s = OpenECSession(sys.argv[1], '', 'file:///var/run/zarafa')
st = GetDefaultStore(s)

outbox = st.GetProps([PR_IPM_OUTBOX_ENTRYID], 0)[0].Value

folder = st.OpenEntry(outbox, None, 0)
table = folder.GetContentsTable(0)
table.SetColumns([PR_SUBJECT, 0x67110003, PR_CREATION_TIME, PR_ENTRYID], 0)

rows = table.QueryRows(20, 0)

for row in rows:
  print "[" + sys.argv[1] + \
  "] [" + str(row[2].Value) + "] Subject: [" + row[0].Value + "] HierarchyID: ["  + str(row[1].Value) + "]"