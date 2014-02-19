#!/usr/bin/env python

from MAPI import *
from MAPI.Util import *
import sys

try:
        import json
except ImportError:
        import simplejson as json

def check_input():
        if len(sys.argv) < 2:
            sys.exit('Usage: %s username' % sys.argv[0])

def backup_settings():
        settings = None
        data = None

        username=sys.argv[1]
        filename=username+'.json'

        PR_EC_WEBACCESS_SETTINGS_JSON = PROP_TAG(PT_STRING8, PR_EC_BASE+0x72)

        try:
                s = OpenECSession(sys.argv[1], '', 'file:///var/run/zarafa')
                st = GetDefaultStore(s)

        except MAPIErrorNotFound:
                print 'User '+username+' has no user store'
                return

        except MAPIErrorLogonFailed:
                print 'User '+username+' not found'
                return

        try:
                settings = st.OpenProperty(PR_EC_WEBACCESS_SETTINGS_JSON, IID_IStream, 0, 0)
                data = settings.Read(33554432)
        except:
                print 'User has not used WebApp yet, no settings property exists.'

        if not data:
                data = 'No settings present.'
        else:
                jsondata=json.loads(data)

                print 'Backing up settings for '+username+' to '+filename
                try:
                        with open(filename,'w') as outfile:
                                json.dump(jsondata, outfile , indent=4)
                except:
                        print 'Could not backup for '+username


if __name__ == '__main__':
        check_input()
        backup_settings()
