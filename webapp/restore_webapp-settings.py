#!/usr/bin/env python

from MAPI import *
from MAPI.Util import *

try:
    import json
except ImportError:
    import simplejson as json

def check_input():

    global username,filename,json_data
    
    if len(sys.argv) < 2:
        sys.exit('Usage: %s username' % sys.argv[0])

    username=sys.argv[1]
    filename=username+'.json'

    try:
        json_file=open(filename)
    except:
        sys.exit("'%s' could not be opened" % filename)

    try:    
        json_data = json.load(json_file)
    except:
        sys.exit("'%s' is not a valid json file" % filename)

    try:
        if json_data['settings'].has_key('zarafa'):
            print "'%s' looks like WebApp backup file" % filename
    except:
        sys.exit("'%s' does not look like a WebApp backup file" % filename)

def restore_settings():

    settings = None
    data = None
    
    print "Restoring settings from '%s' to '%s'" % (filename, username)

    PR_EC_WEBACCESS_SETTINGS_JSON = PROP_TAG(PT_STRING8, PR_EC_BASE+0x72)

    s = OpenECSession(username, '', 'file:///var/run/zarafa')
    st = GetDefaultStore(s)

    settings = st.OpenProperty(PR_EC_WEBACCESS_SETTINGS_JSON, IID_IStream, 0, MAPI_MODIFY)

    new_settings = json.dumps(json_data)

    settings.SetSize(0)
    settings.Seek(0, STREAM_SEEK_END)
    write_settings = settings.Write(new_settings)

    if write_settings:
        print "Settings have been restored for user '%s'" % username
    else:
        print "Unable to restore settings for user '%s'" % username

if __name__ == '__main__':
        check_input()
        restore_settings()
