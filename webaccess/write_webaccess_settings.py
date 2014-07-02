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

        PR_EC_WEBACCESS_SETTINGS = PROP_TAG(PT_STRING8, PR_EC_BASE+0x70)

        settings = st.OpenProperty(PR_EC_WEBACCESS_SETTINGS, IID_IStream, 0, MAPI_MODIFY|MAPI_CREATE)
        settings.SetSize(0)
        settings.Seek(0, STREAM_SEEK_END)

        # EXAMPLE: Default settings with automatic logout after 15 minutes.
        writesettings = settings.Write('a:1:{s:8:"settings";a:7:{s:6:"global";a:13:{s:18:"hierarchylistwidth";s:4:"0.15";s:13:"maillistwidth";s:3:"375";s:13:"sizeinpercent";s:4:"true";s:7:"startup";a:1:{s:6:"folder";s:5:"inbox";}s:8:"rowcount";s:2:"50";s:8:"language";s:5:"en_EN";s:11:"theme_color";s:5:"white";s:17:"mail_readflagtime";s:1:"0";s:11:"auto_logout";s:6:"900000";s:11:"previewpane";s:5:"right";s:20:"readreceipt_handling";s:3:"ask";s:9:"shortcuts";a:1:{s:7:"enabled";s:0:"";}s:17:"last_settings_tab";s:11:"preferences";}s:12:"advancedfind";a:1:{s:12:"refresh_time";s:1:"0";}s:10:"createmail";a:12:{s:10:"mailformat";s:4:"html";s:15:"maildefaultfont";s:0:"";s:8:"reply_to";s:0:"";s:14:"close_on_reply";s:2:"no";s:18:"always_readreceipt";s:5:"false";s:8:"autosave";s:5:"false";s:17:"autosave_interval";s:1:"3";s:18:"on_message_replies";s:10:"add_prefix";s:19:"on_message_forwards";s:10:"add_prefix";s:15:"cursor_position";s:5:"start";s:15:"toccbcc_maxrows";s:1:"3";s:4:"from";s:0:"";}s:21:"outofoffice_change_id";s:26:"pmlhld5gn2qfo1541hogov0o91";s:8:"calendar";a:8:{s:12:"workdaystart";s:3:"540";s:10:"workdayend";s:4:"1020";s:5:"vsize";s:1:"2";s:21:"appointment_time_size";s:1:"2";s:10:"mucalendar";a:2:{s:9:"zoomlevel";s:1:"3";s:15:"numofdaysloaded";s:1:"5";}s:8:"reminder";s:4:"true";s:16:"reminder_minutes";s:2:"15";s:23:"calendar_refresh_button";s:4:"true";}s:11:"addressbook";a:1:{s:7:"default";a:2:{s:10:"foldertype";s:3:"gab";s:7:"entryid";s:72:"00000000ac21a95040d3ee48b319fba75330442500000000040000000100000000000000";}}s:5:"tasks";a:1:{s:14:"show_completed";s:4:"true";}}}')

        if writesettings:
                print "Settings for user '%s' were applied." % sys.argv[1]
        else:
                print "Settings for user '%s' failed to be applied." % sys.argv[1]
        settings.Commit(0)

if __name__ == '__main__':
        check_input()
        write_settings()
