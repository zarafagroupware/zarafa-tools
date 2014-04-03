#!/usr/bin/env bash

ZCPVERSION=$(zarafa-admin -V|grep "Product"|awk '{print $NF}'|tr ',' '.'|sed 's/\(.*\)\./\1-/')
STATS=$(zarafa-stats --session | awk '{print $6, $11, $3, $12}'| egrep -v "0x6746000B|SYSTEM"|grep "\,"|grep -i "OUTLOOK"|tr ' ' ';'|sort|uniq|grep -v $ZCPVERSION)

echo -e "Running ZCP version: $ZCPVERSION\n---"
echo -e "Clients using an old client version:\n"
for user in $STATS
do
        username=$(echo $user|cut -d';' -f1)
        version=$(echo $user|cut -d';' -f2|tr ',' '.'|sed 's/\(.*\)\./\1-/')
        address=$(echo $user|cut -d';' -f3)
        echo "User: $username is using version: $version from IP: $address"
done
