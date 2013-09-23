#!/usr/bin/env python

from MAPI import *
from MAPI.Util import *
import sys

def check_input():
        if len(sys.argv) < 2:
            sys.exit('Usage: %s username' % sys.argv[0])

def read_settings():
        settings = None
        data = None

        PR_EC_WEBACCESS_SETTINGS = PROP_TAG(PT_STRING8, PR_EC_BASE+0x70)

        s = OpenECSession(sys.argv[1], '', 'file:///var/run/zarafa')
        st = GetDefaultStore(s)

        try:
                settings = st.OpenProperty(PR_EC_WEBACCESS_SETTINGS, IID_IStream, 0, 0)
                data = settings.Read(4096)
        except:
                print 'User has not used WebAccess yet, no settings property exists.'

        if not data:
                data = 'No settings present.'
        elif data == 'a:1:{s:8:"settings";a:1:{s:6:"global";a:3:{s:18:"hierarchylistwidth";s:19:"0";s:13:"maillistwidth";s:3:"375";s:13:"sizeinpercent";s:4:"true";}}}':
                print 'Default settings present, user has not yet logged in.'
        else:
                print data

if __name__ == '__main__':
        check_input()
        read_settings()
