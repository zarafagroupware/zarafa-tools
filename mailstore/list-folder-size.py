#!/usr/bin/env python

import sys
import os
import getopt
import traceback
import locale
import string
import MAPI
from MAPI.Util import *

def usage():
    print "Usage: %s [OPTIONS] [users...]" % sys.argv[0]
    print ""
    print "Display hierarchy with message count in each folder."
    print ""
    print "OPTIONS:"
    print "  -h | --host    \tHost to connect with. Default: file:///var/run/zarafa"
    print "  --help         \tShow this help message."
    print ""


class printer:
    def __init__(self, session, ema, user):
        self.session = session
        self.ema = ema
        self.user = user
        self.abook = self.session.OpenAddressBook(0, None, MAPI_UNICODE)


    def processTable(self, table):
        count = 0;
        size = 0;

        table.SetColumns([PR_MESSAGE_SIZE], TBL_BATCH)
        while True:
            rows = table.QueryRows(128, 0)
            if len(rows) == 0:
                break
            for row in rows:
                count += 1
                size += row[0].Value

        return (count, size)


    def processFolder(self, folder, name, indent = '  '):
        table = folder.GetContentsTable(0)
        count, size = self.processTable(table)
        
        table = folder.GetContentsTable(MAPI_ASSOCIATED)
        a_count, a_size = self.processTable(table)
        
        print 'regular: count=%-5u size=%-10u  associated: count=%-5u size=%-10u %s%s: ' % (count, size, a_count, a_size, indent, name)
        
        table = folder.GetHierarchyTable(0)
        table.SetColumns([PR_ENTRYID, PR_DISPLAY_NAME], TBL_BATCH)
        table.Restrict(SPropertyRestriction(RELOP_EQ, PR_FOLDER_TYPE, SPropValue(PR_FOLDER_TYPE, FOLDER_GENERIC)), TBL_BATCH)
        while True:
            rows = table.QueryRows(50, 0)
            if len(rows) == 0:
                break
            for row in rows:
                self.processFolder(self.store.OpenEntry(row[0].Value, IID_IMAPIFolder, MAPI_DEFERRED_ERRORS), row[1].Value, indent + '  ')


    def processStore(self):
        print "Processing store for user %s" % self.user
        try:
            storeid = self.ema.CreateStoreEntryID(None, self.user, 0)
        except MAPIError, err:
            if err.hr == MAPI_E_NOT_FOUND:
                print "Unable to find store for user %s" % self.user
                return
            raise
            
        self.store = self.session.OpenMsgStore(0, storeid, IID_IMsgStore, MAPI_DEFERRED_ERRORS)
        root = self.store.OpenEntry(None, IID_IMAPIFolder, MAPI_DEFERRED_ERRORS)

        table = root.GetHierarchyTable(0)
        table.SetColumns([PR_ENTRYID, PR_DISPLAY_NAME], TBL_BATCH)
        table.Restrict(SPropertyRestriction(RELOP_EQ, PR_FOLDER_TYPE, SPropValue(PR_FOLDER_TYPE, FOLDER_GENERIC)), TBL_BATCH)
        while True:
            rows = table.QueryRows(50, 0)
            if len(rows) == 0:
                break
            for row in rows:
                self.processFolder(self.store.OpenEntry(row[0].Value, IID_IMAPIFolder, MAPI_DEFERRED_ERRORS), row[1].Value)
        print ''
    

def getUserList(session):
    print "Obtaining user list"
    users = []
    ab = session.OpenAddressBook(0, None, 0)
    gab = ab.OpenEntry(ab.GetDefaultDir(), None, 0)
    table = gab.GetContentsTable(0)
    table.SetColumns([PR_EMAIL_ADDRESS], TBL_BATCH)
    while True:
        rows = table.QueryRows(50, 0)
        if len(rows) == 0:
            break
        [users.append(row[0].Value) for row in rows]
    return users


def processStores(session, users):
    if users is None or len(users) == 0:
        users = getUserList(session)

    ema = GetDefaultStore(session).QueryInterface(IID_IExchangeManageStore)
    with_errors = False
    for user in users:
        try:
            printer(session, ema, user).processStore()
        except MAPIError, err:
            print "Unexpected error while processing store for user %s. hr=%08x" % (user, err.hr)
            print "Traceback:"
            exc_info = sys.exc_info()
            traceback.print_tb(exc_info[2], None, sys.stdout)
            with_errors = True

    if with_errors:
        return 2
    return 0
    

def main(argv = None):
    if argv is None:
        argv = sys.argv

    try:
        opts, args = getopt.gnu_getopt(argv[1:], 'h:', ['help', 'host='])
    except getopt.GetoptError, err:
        # print help information and exit:
        print str(err)
        usage()
        return 1

    #defaults
    host = os.getenv('ZARAFA_SOCKET', 'file:///var/run/zarafa')
    
    for o, a in opts:
        if o == '--help':
            usage()
            return 0
        elif o in ('-h', '--host'):
            host = a
        else:
            assert False, "unhandled option"

    try:
        session = OpenECSession('SYSTEM', '', host)
    except MAPIError, err:
        if err.hr == MAPI_E_LOGON_FAILED:
            print "Failed to logon."
        elif err.hr == MAPI_E_NETWORK_ERROR:
            print "Unable to connect to server. Make sure you specified the correct server."
        else:
            print "Unexpected error occurred. hr=%08x" % err.hr
            print "Traceback:"
            exc_info = sys.exc_info()
            traceback.print_tb(exc_info[2], None, sys.stdout)
        return 1

    return processStores(session, args)


if __name__ == '__main__':
    locale.setlocale(locale.LC_CTYPE, '')
    sys.exit(main())
