#!/usr/bin/env python

import os
import sys

from MAPI.Util import *


ROLE_OWNER = 0x5fb
ROLE_PUBLIC_AUTHOR = 0x49b
ROLE_REVIEWER = 0x401

MUIDECSAB = DEFINE_GUID(0x50a921ac, 0xd340, 0x48ee, 0xb3, 0x19, 0xfb, 0xa7, 0x53, 0x30, 0x44, 0x25)
def DEFINE_ABEID(type, id):
    return struct.pack("4B16s3I4B", 0, 0, 0, 0, MUIDECSAB, 0, type, id, 0, 0, 0, 0)
EID_EVERYONE = DEFINE_ABEID(MAPI_DISTLIST, 1)

FOLDER_PERMS = ((PR_EC_PUBLIC_IPM_SUBTREE_ENTRYID, ROLE_PUBLIC_AUTHOR),
                (PR_SPLUS_FREE_BUSY_ENTRYID, ROLE_REVIEWER),
                (PR_FREE_BUSY_FOR_LOCAL_SITE_ENTRYID, ROLE_OWNER))

def  set_rights(obj, rights, memberid):
    acls = obj.OpenProperty(PR_ACL_TABLE, IID_IExchangeModifyTable, 0, MAPI_MODIFY)
    acls.ModifyTable(ROWLIST_REPLACE, [ROWENTRY(ROW_ADD, [SPropValue(PR_MEMBER_RIGHTS, rights), SPropValue(PR_MEMBER_ENTRYID, memberid)])])

def main():
    if len(sys.argv) > 2:
        print "Usage: %s [company name]" % os.path.basename(sys.argv[0])
        return 1
    try:
        session = OpenECSession('SYSTEM','',os.getenv('ZARAFA_SOCKET','file:///var/run/zarafa'))
    except MAPIError, e:
        print "Unable to logon using SYSTEM user, %08X" % e.hr
        return 1
    if len(sys.argv) == 2:
        sa = GetDefaultStore(session).QueryInterface(IID_IECServiceAdmin)
        ems = sa.QueryInterface(IID_IExchangeManageStore)
        try:
            memberid = sa.ResolveCompanyName(sys.argv[1], 0)
            member = session.OpenEntry(memberid, None, 0)
        except MAPIError, er:
            print "Unable to find company '%s'. hr=0x%08x" % (sys.argv[1], er.hr)
            return 1
        try:
            pubstoreid = ems.CreateStoreEntryID(None, sys.argv[1], 0)
            pubstore = session.OpenMsgStore(0, pubstoreid, None, MDB_WRITE)
        except MAPIError, er:
            print "Unable to get public store for company '%s'. hr=0x%08x" % (sys.argv[1], er.hr)
            return 1
    else:
        pubstore = GetPublicStore(session)
        if not pubstore:
            print "Unable to open public store in single tenant mode"
            return 1
        memberid = EID_EVERYONE
    try:
        print "Resetting permissions on public store"
        set_rights(pubstore, ROLE_REVIEWER, memberid)
    except MAPIError, er:
        print "That didn't work. hr=0x%08x" % er.hr
    folderprops = pubstore.GetProps([proptag for proptag, _rights in FOLDER_PERMS], 0)
    for folderprop, (_proptag, rights) in zip(folderprops, FOLDER_PERMS):
        try:
            folder = pubstore.OpenEntry(folderprop.Value, IID_IMAPIFolder, MAPI_MODIFY)
            print "Resetting permissions on folder '%s'" % HrGetOneProp(folder, PR_DISPLAY_NAME).Value
            set_rights(folder, rights, memberid)
        except MAPIError, er:
            print "That didn't work. hr=0x%08x" % er.hr

if __name__ == '__main__':
    sys.exit(main())
