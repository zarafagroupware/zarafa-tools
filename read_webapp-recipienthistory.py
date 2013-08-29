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

        PR_EC_RECIPIENT_HISTORY_JSON = PROP_TAG(PT_STRING8, PR_EC_BASE+0x73)

        s = OpenECSession(sys.argv[1], '', 'file:///var/run/zarafa')
        st = GetDefaultStore(s)

        try:
                settings = st.OpenProperty(PR_EC_RECIPIENT_HISTORY_JSON, IID_IStream, 0, 0)
                data = settings.Read(4096)
        except:
                print 'User has no saved recipients in WebApp yet, recipients array is empty.'

        if not data:
                data = 'No recipients present.'
        elif data == '{"recipients":[]}':
                print 'Default recipients container present, user has no saved recipients.'
        else:
                print data

if __name__ == '__main__':
        check_input()
        read_settings()
