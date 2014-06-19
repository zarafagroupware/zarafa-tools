#!/usr/bin/env bash
# Script to initiate purge softdelete
# Copyright Zarafa B.V. 2014

# Requirements:
# - Red Hat Cluster
# - xmlstarlet installed

# Configuration
SOFTDELETE_DAYS=30
SERVICE_NAME="zarafa-app"


# Do not edit below this line !
###############################

CLUSTAT=`which clustat`
XMLSTARLET=`which xmlstarlet`
ZARAFA_ADMIN=`which zarafa-admin`

# Check if SOFTDELETE_DAYS is defined.
if [ -z $SOFTDELETE_DAYS ]; then
        echo "SOFTDELETE_DAYS is undefined!";
        exit 1;
fi

# Check if SOFTDELETE_DAYS matches a number
re='^[0-9]+$'
if ! [[ $SOFTDELETE_DAYS =~ $re ]]; then
        echo "Error: SOFTDELETE_DAYS: $SOFTDELETE_DAYS is not a number!" >&2; exit 1;
fi

# Get status from clustat and parse with xmlstarlet
STATUS=`$CLUSTAT -x -s $SERVICE_NAME|$XMLSTARLET sel -t -m //clustat/groups/group -v @owner -o "," -v @state_str`

if [ $? -ne 0 ]; then
        echo "Unable to get cluster status from clustat!";
        exit 1;
fi

SERVER=`echo $STATUS | cut -d"," -f 1`
STATE=`echo $STATUS | cut -d"," -f 2`

if [ `hostname -a` == $SERVER ]; then
        if [ $STATE == "started" ]; then
                $ZARAFA_ADMIN --purge-softdelete $SOFTDELETE_DAYS
        fi
fi

exit 0;
