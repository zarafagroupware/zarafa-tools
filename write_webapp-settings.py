#!/usr/bin/env python

from MAPI import *
from MAPI.Util import *
import sys

def check_input():
        if len(sys.argv) < 2:
            sys.exit('Usage: %s username' % sys.argv[0])

def write_settings():
        s = OpenECSession(sys.argv[1], '', 'file:///var/run/zarafa')
        st = GetDefaultStore(s)

        PR_EC_WEBACCESS_SETTINGS_JSON = PROP_TAG(PT_STRING8, PR_EC_BASE+0x72)

        settings = st.OpenProperty(PR_EC_WEBACCESS_SETTINGS_JSON, IID_IStream, 0, MAPI_MODIFY|MAPI_CREATE)
        settings.SetSize(0)
        settings.Seek(0, STREAM_SEEK_END)
        ## Basic set of, leaves welcome screen:
        # writesettings = settings.Write('{"settings":{"zarafa":{"v1":{"main":{"language":"nl_NL.UTF-8"},"contexts":{"mail":[]}}}}}')

        ## Without the welcome screen, extended:
        writesettings = settings.Write('{"settings":{"zarafa":{"v1":{"main":{"language":"nl_NL.UTF-8","default_context":"mail","start_working_hour":540,"end_working_hour":1020,"week_start":1,"show_welcome":false},"contexts":{"mail":[],"calendar":{"default_zoom_level":30,"datepicker_show_busy":true}},"state":{"models":{"note":{"current_data_mode":0}},"contexts":{"mail":{"current_view":0,"current_view_mode":1}}}}}}}')

        # Use read_webapp-settings.py to get more options you can set, these are shown after the first logon.
        # Keep in mind you should not copy the settings from a user who has already actively been using WebApp.
        # This is because he will have certain mailboxes open, they would probably get an error that he has no permissions.

        if writesettings:
                print "Settings for user '%s' were applied." % sys.argv[1]
        else:
                print "Settings for user '%s' failed to be applied." % sys.argv[1]
        settings.Commit(0)

if __name__ == '__main__':
        check_input()
        write_settings()
