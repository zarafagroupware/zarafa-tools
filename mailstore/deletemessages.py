#!/usr/bin/env python

import sys, getopt, locale

import MAPI
from MAPI.Util import *


def findFolder(f, path):
    table = f.GetHierarchyTable(0)
    table.SetColumns([PR_ENTRYID], TBL_BATCH)
    table.FindRow(SPropertyRestriction(RELOP_EQ, PR_DISPLAY_NAME, SPropValue(PR_DISPLAY_NAME, path[0])), 0, 0)
    rows = table.QueryRows(1, 0)
    s = f.OpenEntry(rows[0][0].Value, None, MAPI_MODIFY)
    if len(path) > 1:
        return findFolder(s, path[1:])
    return s

def findPath(session, path):
    store = GetDefaultStore(session)
    ipm = store.OpenEntry(HrGetOneProp(store, PR_IPM_SUBTREE_ENTRYID).Value, None, MAPI_MODIFY)
    p = path.split('/')
    if len(p) == 0:
        return ipm
    return findFolder(ipm, p)

def deleteMessages(folder, limit):
    table = folder.GetContentsTable(0)
    table.SetColumns([PR_ENTRYID], TBL_BATCH)
    rows = table.QueryRows(limit, 0)
    folder.DeleteMessages([row[0].Value for row in rows], 0, None, DELETE_HARD_DELETE)
    print "Deleted %u messages" % len(rows)

def main(argv = None):
    if argv is None:
        argv = sys.argv

    try:
        opts, args = getopt.gnu_getopt(argv[1:], 'h:s:p:u:f:n:', ['help', 'host=', 'sslkey-file=', 'sslkey-pass='])
    except getopt.GetoptError, err:
        # print help information and exit:
        print str(err)
        usage()
        return 1

    #defaults
    host = 'file:///var/run/zarafa'
    sslkey_file = None
    sslkey_pass = None

    user = None
    folder = None
    limit = None

    for o, a in opts:
        if o == '--help':
            usage()
            return 0
        elif o in ('-h', '--host'):
            host = a
        elif o in ('-s', '--sslkey-file'):
            sslkey_file = a
        elif o in ('-p', '--sslkey-pass'):
            sslkey_pass = a
        elif o == '-u':
            user = a
        elif o == '-f':
            folder = a
        elif o == '-n':
            limit = int(a)  # This will fail if crap is entered
        else:
            assert False, "unhandled option"

    if user is None:
        print "No user specified."
        return 1
    if folder is None:
        print "No folder specified."
        return 1
    if limit is None:
        print "No limit specified."
        return 1

    try:
        session = OpenECSession(user, '', host, sslkey_file = sslkey_file, sslkey_pass = sslkey_pass)
    except MAPIError, err:
        if err.hr == MAPI_E_LOGON_FAILED:
            print "Failed to logon. Make sure your SSL certificate is correct."
        elif err.hr == MAPI_E_NETWORK_ERROR:
            print "Unable to connect to server. Make sure you specified the correct server."
        else:
            print "Unexpected error occurred. hr=%08x" % err.hr
            print "Traceback:"
            exc_info = sys.exc_info()
            traceback.print_tb(exc_info[2], None, sys.stdout)
        return 1

    try:
        f = findPath(session, folder)
    except MAPIError, err:
        if err.hr == MAPI_E_NOT_FOUND:
            print "Folder '%s' not found" % folder
        else:
            print "Unexpected error occurred. hr=%08x" % err.hr
            print "Traceback:"
            exc_info = sys.exc_info()
            traceback.print_tb(exc_info[2], None, sys.stdout)
        return 1

    deleteMessages(f, limit)

if __name__ == '__main__':
    locale.setlocale(locale.LC_CTYPE, '')
    sys.exit(main())
