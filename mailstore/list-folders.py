#!/usr/bin/env python

from MAPI import *
from MAPI.Struct import *
from MAPI.Util import *
from MAPI.Time import *

import locale, sys, string, time

level = 0

if len(sys.argv) < 2:
    sys.exit('Usage: %s username' % sys.argv[0])

def do_folder(folderid):
        folder = session.OpenEntry(folderid, None, 0)
        global level
        level += 1

        table = folder.GetHierarchyTable(0)
        table.SetColumns([PR_DISPLAY_NAME, PR_ENTRYID], 0)

        rows = table.QueryRows(-1, 0)

        for row in rows:
                prefix = ""
                for i in range(1, level):
                        prefix = prefix + "-"
                print prefix + row[0].Value

                do_folder(row[1].Value)
        level -= 1

session = OpenECSession(sys.argv[1], '', 'file:///var/run/zarafa')
store = GetDefaultStore(session)

props = store.GetProps([PR_IPM_SUBTREE_ENTRYID], 0)
do_folder(props[0].Value)
