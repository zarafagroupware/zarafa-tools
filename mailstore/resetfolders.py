#!/usr/bin/env python

from MAPI.Util import *
import sys
import locale
locale.setlocale(locale.LC_CTYPE, '')

class FolderNotFoundError(RuntimeError):
    def __init__(self, name):
        RuntimeError.__init__(self, 'Folder %s not found' % name)

class AmbiguousFolderError(RuntimeError):
    def __init__(self, name):
        RuntimeError.__init__(self, 'Folder %s duplicate' % name)

def FindFolder(folder, name):
    t = folder.GetHierarchyTable(0)
    t.SetColumns([PR_ENTRYID], 0)
    t.Restrict(SPropertyRestriction(RELOP_EQ, PR_DISPLAY_NAME, SPropValue(PR_DISPLAY_NAME, name)), 0)
    rows = t.QueryRows(-1, 0)
    if len(rows) == 0:
        raise FolderNotFoundError(name)
    if len(rows) > 1:
        raise AmbiguousFolderError(name)
    return rows[0][0].Value


s = OpenECSession(sys.argv[1], '', 'file:///var/run/zarafa')
st = GetDefaultStore(s)

root = st.OpenEntry(None, None, MAPI_MODIFY)
rootid = root.GetProps([PR_ENTRYID], 0)[0].Value

subid = FindFolder(root, 'IPM_SUBTREE')


sub = root.OpenEntry(subid, None, MAPI_MODIFY)

# MODIFY THESE TO MATCH YOUR LANGUAGE
outid = FindFolder(sub,         'Outbox')
wasteid = FindFolder(sub,       'Deleted Items')
sentid = FindFolder(sub,        'Sent Items')
inid = FindFolder(sub,          'Inbox')
apptid = FindFolder(sub,        'Calendar')
contactid = FindFolder(sub, 'Contacts')
draftsid = FindFolder(sub,      'Drafts')
journalid = FindFolder(sub, 'Journal')
noteid = FindFolder(sub,        'Notes')
taskid = FindFolder(sub,        'Tasks')

storeprops = [  SPropValue(PR_IPM_SUBTREE_ENTRYID, subid),
                SPropValue(PR_IPM_OUTBOX_ENTRYID, outid),
                SPropValue(PR_IPM_WASTEBASKET_ENTRYID, wasteid),
                SPropValue(PR_IPM_SENTMAIL_ENTRYID, sentid)
                ]

rootprops = [ SPropValue(PR_IPM_APPOINTMENT_ENTRYID, apptid),
              SPropValue(PR_IPM_CONTACT_ENTRYID, contactid),
              SPropValue(PR_IPM_DRAFTS_ENTRYID, draftsid),
              SPropValue(PR_IPM_JOURNAL_ENTRYID, journalid),
              SPropValue(PR_IPM_NOTE_ENTRYID, noteid),
              SPropValue(PR_IPM_TASK_ENTRYID, taskid)
              ]

st.SetProps(storeprops)
root.SetProps(rootprops)

inbox = st.OpenEntry(inid, None, MAPI_MODIFY)
inbox.SetProps(rootprops)

st.SetReceiveFolder('', 0, inid)
st.SetReceiveFolder('IPM', 0, inid)
st.SetReceiveFolder('REPORT.IPM', 0, inid)
st.SetReceiveFolder('IPC', 0, rootid)
