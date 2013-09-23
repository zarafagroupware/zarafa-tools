#!/usr/bin/env python

from MAPI import *
from MAPI.Util import *
import sys

def check_input():
        if len(sys.argv) < 2:
            sys.exit('Usage: %s username' % sys.argv[0])

def reset_settings():
        s = OpenECSession(sys.argv[1], '', 'file:///var/run/zarafa')
        st = GetDefaultStore(s)

        PR_EC_WEBACCESS_SETTINGS_JSON = PROP_TAG(PT_STRING8, PR_EC_BASE+0x72)

        settings = st.OpenProperty(PR_EC_WEBACCESS_SETTINGS_JSON, IID_IStream, 0, MAPI_MODIFY|MAPI_CREATE)
        settings.SetSize(0)
        settings.Seek(0, STREAM_SEEK_END)
        writesettings = settings.Write('{"settings":{"zarafa":{"v1":{"contexts":{"mail":[]}}}}}')

        if writesettings:
                print "Settings for user '%s' were reset." % sys.argv[1]
        else:
                print "Settings for user '%s' failed to be reset." % sys.argv[1]
        settings.Commit(0)

if __name__ == '__main__':
        check_input()
        reset_settings()
