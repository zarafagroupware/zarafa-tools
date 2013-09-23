#!/usr/bin/env python

from MAPI import *
from MAPI.Struct import *
from MAPI.Util import *
from MAPI.Time import *

import locale, sys
import MySQLdb, re

locale.setlocale(locale.LC_CTYPE, '')
level = 0

def ReadConfig(filename):
    r = re.compile(r'^\s*(\S+)\s*=\s*([^\n\r]*?)[\s\n\r]?$')
    l = []
    for line in open(filename, 'r'):
        if line.startswith('#') or line.startswith('!'):    # comment or special directive
            continue
        m = r.match(line)
        if m:
            l.append(m.groups())
    return dict(l)

def ConnectDB(config):
    host = 'localhost'
    port = 3306
    user = 'root'
    password = ''
    database = 'zarafa'

    if 'mysql_host' in config: host = config['mysql_host']
    if 'mysql_port' in config: port = int(config['mysql_port'])
    if 'mysql_user' in config: user = config['mysql_user']
    if 'mysql_password' in config: password = config['mysql_password']
    if 'mysql_database' in config: database = config['mysql_database']

    return MySQLdb.connect(host=host, port=port, user=user, passwd=password, db=database)

MUIDECSAB = DEFINE_GUID(0x50a921ac, 0xd340, 0x48ee, 0xb3, 0x19, 0xfb, 0xa7, 0x53, 0x30, 0x44, 0x25)
def DEFINE_ABEID0(type, id):
    return struct.pack("4B16s3I4B", 0, 0, 0, 0, MUIDECSAB, 0, type, id, 0, 0, 0, 0)
def DEFINE_ABEID1(type, id):
    return struct.pack("4B16s3I"+str(len(id))+"s4B", 0, 0, 0, 0, MUIDECSAB, 1, type, 0, id, 0, 0, 0, 0)

try:
    config = ReadConfig('/etc/zarafa/server.cfg')
except Exception, e:
    print "Unable to find config: " + str(e)
    sys.exit(1)

try:
    db = ConnectDB(config)
    cursor = db.cursor()
except:
    print "Unable to get MySQL connection"
    sys.exit(1)

try:
    session = OpenECSession('SYSTEM', '', 'file:///var/run/zarafa')
except MAPIError, e:
    print "Unable to open SYSTEM session, MAPI error: 0x%x" % e.hr
    sys.exit(1)

store = GetPublicStore(session)
if not store:
    print "This script is not compatible with hosted setups."
    sys.exit(1)

def get_username(hierarchyid):
    global session
    abook = session.OpenAddressBook(0, None, 0)

    query = "SELECT users.externid FROM hierarchy JOIN users ON owner=users.id WHERE hierarchy.id = "
    cursor.execute(query + str(hierarchyid))
    result = cursor.fetchone()
    if not result or not result[0]:
        # not found or user without externid: SYSTEM.
        userid = DEFINE_ABEID0(MAPI_MAILUSER, 2)
    else:
        userid = DEFINE_ABEID1(MAPI_MAILUSER, result[0].encode('base64'))
    try:
        user = abook.OpenEntry(userid, None, 0)
        name = user.GetProps([PR_DISPLAY_NAME], 0)[0].Value
    except:
        name = "MAPI Unknown"

    return name


def do_folder(folderid):
    folder = session.OpenEntry(folderid, None, 0)
    global level
    level += 1

    table = folder.GetHierarchyTable(0)
    table.SetColumns([PR_DISPLAY_NAME, PR_ENTRYID, PR_EC_HIERARCHYID, PR_CREATION_TIME], 0)

    rows = table.QueryRows(-1, 0)

    for row in rows:
            prefix = ""
            for i in range(1, level):
                    prefix = prefix + "-"
            folder_name = row[0].Value
            created_by = get_username(row[2].Value)
            folder_date = row[3].Value if created_by != "SYSTEM" else "Not available"

            print prefix + folder_name, "- Created by:", created_by, "- On date:", folder_date

            do_folder(row[1].Value)
    level -= 1

props = store.GetProps([PR_IPM_SUBTREE_ENTRYID], 0)
do_folder(props[0].Value)
