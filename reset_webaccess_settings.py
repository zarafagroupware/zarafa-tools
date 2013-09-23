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

        PR_EC_WEBACCESS_SETTINGS = PROP_TAG(PT_STRING8, PR_EC_BASE+0x70)

        settings = st.OpenProperty(PR_EC_WEBACCESS_SETTINGS, IID_IStream, 0, MAPI_MODIFY|MAPI_CREATE)
        settings.SetSize(0)
        settings.Seek(0, STREAM_SEEK_END)
        writesettings = settings.Write('a:1:{s:8:"settings";a:1:{s:6:"global";a:3:{s:18:"hierarchylistwidth";s:19:"0";s:13:"maillistwidth";s:3:"375";s:13:"sizeinpercent";s:4:"true";}}}')

        if writesettings:
                print "Settings for user '%s' were reset." % sys.argv[1]
        else:
                print "Settings for user '%s' failed to be reset." % sys.argv[1]
        settings.Commit(0)

if __name__ == '__main__':
        check_input()
        reset_settings()
