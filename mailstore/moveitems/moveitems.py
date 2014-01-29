#!/usr/bin/env python

from MAPI.Util import *
import sys
import locale
import getopt
locale.setlocale(locale.LC_CTYPE, '')

def print_help():
    print "Usage: %s -u <username>" % sys.argv[0]
    print ""
    print "Migrate messages from one folder to another."
    print ""
    print "Required arguments:"
    print " -u, --user             Username"
    print ""
    print "Optional arguments:"
    print ""
    print " --help                      Displays this information"
    print " -d <1|0>, --delete <1|0>    Delete the source folder after moving the data to the destination folder"
    print " -h, --host                  Hostname to connect to, e.g. https://localhost:237/zarafa"
    print " -s, --sslkey-file           SSL certificate"
    print " -p, --sslkey-pass           SSL password"
    print ""

def FindFolder(folder, name):
    table = folder.GetHierarchyTable(0)
    table.SetColumns([PR_ENTRYID], 0)
    table.Restrict(SPropertyRestriction(RELOP_EQ, PR_DISPLAY_NAME, SPropValue(PR_DISPLAY_NAME, name)), 0)
    rows = table.QueryRows(-1, 0)
    if len(rows) == 0:
        table = folder.GetHierarchyTable(0)
        table.SetColumns([PR_ENTRYID, PR_SUBFOLDERS], 0)
        rows = table.QueryRows(-1, 0)
        for row in rows:
            if row[1].Value:
                subfolder = folder.OpenEntry(row[0].Value, None, MAPI_MODIFY)
                f = FindFolder(subfolder, name)
                if f:
                    return f
        return
    if len(rows) > 1:
        print "Folder '%s' duplicate." % name
    folderentryid = rows[0][0].Value

    return folderentryid

def DataMover(ipmsub, source, destination, delete = 0):
    srcfld = FindFolder(ipmsub, source)
    dstfld = FindFolder(ipmsub, destination)

    if not ipmsub:
        print "The users IPM_SUBTREE does not exist! This usually means the mailstore is corrupt/broken."
        return
    if not srcfld:
        print "Source folder '%s' does not exist!" % source
        return
    if not dstfld:
        print "Destination folder '%s' does not exist!" % destination
        return

    # Messages
    top = ipmsub.OpenEntry(srcfld, None, MAPI_MODIFY)
    table = top.GetContentsTable(0)
    table.SetColumns([PR_ENTRYID], 0)
    entryids = [src[0].Value for src in table.QueryRows(-1, 0)]
    destfolder = ipmsub.OpenEntry(dstfld, None, MAPI_MODIFY)
    if not destfolder:
        print "Destination folder '%s' does not exist (no messages moved)!" % destination
        return
    if entryids:
        print "Moving %d messages from folder '%s' to folder '%s'" % (len(entryids), source, destination)
        top.CopyMessages(entryids, IID_IMAPIFolder, destfolder, 0, None, MESSAGE_MOVE)
    else:
        print "No messages to copy for folder '%s'" % source
    # Folders
    ftable = top.GetHierarchyTable(0)
    ftable.SetColumns([PR_ENTRYID], 0)
    fentryids = [src[0].Value for src in ftable.QueryRows(-1, 0)]
    if fentryids:
        print "Moving %d folders for source folder '%s' to destination '%s'" % (len(fentryids), source, destination)
    for folder in fentryids:
        try:
            if folder and destfolder:
                top.CopyFolder(folder, IID_IMAPIFolder, destfolder, None, 0, None, FOLDER_MOVE)
        except:
            return
    if int(delete):
        try:
            print "Deleting source folder '%s'" % source
            top.DeleteFolder(srcfld, 0, None, DEL_FOLDERS)
        except:
            print "Cannot delete source folder '%s'" % source
            return
    return len(entryids)

def main(argv = None):
    if argv is None:
        argv = sys.argv

    try:
        opts, args = getopt.gnu_getopt(argv, "h:s:p:u:d:he", ["host=", "sslkey-file=", "sslkey-pass=", "user=", "delete=", "help"])
    except getopt.GetoptError, err:
        print str(err)
        print ""
        print_help()
        return 1

    # defaults
    host = os.getenv("ZARAFA_SOCKET", "file:///var/run/zarafa")
    sslkey_file = None
    sslkey_pass = None
    username = None
    delete = 0 

    for o, a in opts:
        if o in ("-h", "--host"):
            host = a
        elif o in ("-s", "--sslkey-file"):
            sslkey_file = a
        elif o in ("-p", "--sslkey-pass"):
            sslkey_pass = a
        elif o in ("-u", "--user"):
            username = a
        elif o in ("-d", "--delete"):
            delete = a
        elif o in ("--help"):
            print_help()
            return 0
        else:
            assert False, ("unhandled option '%s'" % o)

    if not username:
        print "No username specified."
        print ""
        print_help()
        sys.exit(1)

    try:
        session = OpenECSession(username, "", host, sslkey_file = sslkey_file, sslkey_pass = sslkey_pass)
    except MAPIError, err:
        if err.hr == MAPI_E_LOGON_FAILED:
            print "Failed to logon. Make sure your SSL certificate is correct."
        elif err.hr == MAPI_E_NETWORK_ERROR:
            print "Unable to connect to server. Make sure you specified the correct server."
        else:
            print "Unexpected error occurred. hr=0x%08x" % err.hr
        sys.exit(1)

    try:
        store = GetDefaultStore(session)
        subtree = store.GetProps([PR_IPM_SUBTREE_ENTRYID], 0)[0].Value
        if not subtree:
            print "Unable to open the users IPM_SUBTREE!"
            sys.exit(1)
        sub = store.OpenEntry(subtree, IID_IMAPIFolder, MAPI_MODIFY)
    except:
        print "Unable to open store for user '%s'" % username

    try:
        folders = open("moveitems.cfg")
    except IOError:
        print "Unable to open moveitems.cfg, make sure the file exists."

    print "Executing move items for user '%s':" % username

    for linenum, line in enumerate(folders):
        if linenum == 0: continue
        try:
            source, dest = line.strip().split(",")
        except ValueError, e:
            print "Error on line %d, %s" % (linenum, e)
        if not source or not dest:
            print "Error on line %d" % linenum
            continue

        DataMover(sub, source, dest, delete)

if __name__ == "__main__":
    locale.setlocale(locale.LC_ALL, "")
    sys.exit(main())
