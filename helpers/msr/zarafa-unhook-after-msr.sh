#!/bin/bash

# Create local only admin.cfg for zarafa-admin
cat > /tmp/admin-only-local.cfg << EOF
server_socket = file:///var/run/zarafa
sslkey_file =
EOF

zarafaadmin=$(which zarafa-admin)

LOCALSERVER=$(grep server_name /etc/zarafa/server.cfg | awk '{print $3}')

# Refresh user list (just in case)
$zarafaadmin --sync

# get a list of users that are not on this server
for moved in $($zarafaadmin -l | tail -n +4 | head -n -1| grep -v $LOCALSERVER | awk '{print $1}'); do
        # unhooking user $moved, will "fail" if the user has no store on local server
        $zarafaadmin --config /tmp/admin-only-local.cfg --unhook-store $moved
done

