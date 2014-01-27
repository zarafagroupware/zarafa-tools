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

        PR_EC_WEBACCESS_SETTINGS_JSON = PROP_TAG(PT_STRING8, PR_EC_BASE+0x72)

        s = OpenECSession(sys.argv[1], '', 'file:///var/run/zarafa')
        st = GetDefaultStore(s)

        try:
                settings = st.OpenProperty(PR_EC_WEBACCESS_SETTINGS_JSON, IID_IStream, 0, 0)
                data = settings.Read(40960)
        except:
                print 'User has not used WebApp yet, no settings property exists.'

        if not data:
                data = 'No settings present.'
        else:
                print data

if __name__ == '__main__':
        check_input()
        read_settings()
