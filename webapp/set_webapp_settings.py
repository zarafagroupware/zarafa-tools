#!/usr/bin/env python

from MAPI import *
from MAPI.Util import *
import sys

try:
    import json
except ImportError:
    import simplejson

def check_input():
        if len(sys.argv) < 2:
            sys.exit('Usage: %s username' % sys.argv[0])

def set_settings():
        settings = None
        data = None

        PR_EC_WEBACCESS_SETTINGS_JSON = PROP_TAG(PT_STRING8, PR_EC_BASE+0x72)

        s = OpenECSession(sys.argv[1], '', 'file:///var/run/zarafa')
        st = GetDefaultStore(s)

        try:
                settings = st.OpenProperty(PR_EC_WEBACCESS_SETTINGS_JSON, IID_IStream, 0, MAPI_MODIFY)
                data = settings.Read(33554432)
        except:
                print 'User has not used WebApp yet, no settings property exists.'

        if not data:
                data = 'No settings present.'
        else:
                j = json.loads(data)
                try:
                    j['settings']['zarafa']['v1']['main']['keycontrols_enabled'] = True
                except KeyError:
                    print "User has not logged into webapp, unable to enable keyboard shortcuts"
                    return

                new_settings = json.dumps(j)

                settings.SetSize(0)
                settings.Seek(0, STREAM_SEEK_END)
                write_settings = settings.Write(new_settings)

                if write_settings:
                    print "Enabling keyboard shortcuts for user '%s'" % sys.argv[1]
                else:
                    print "Unable to set keyboard shortcuts for user '%s'" % sys.argv[1]

if __name__ == '__main__':
        check_input()
        set_settings()
