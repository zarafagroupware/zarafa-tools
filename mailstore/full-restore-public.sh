#!/bin/sh

#
# This script performs a full restore of a backup to an existing public store
#

if [ -z "$1" ]; then
    echo "You must specify the backup set name to perform the restore with.\nFor example: Public"
    exit 1
fi

FROM_NAME=$1
INDEX=${FROM_NAME}.index.zbk

root=`head -2 "${INDEX}" | grep ^R | cut -d\: -f3`
if [ -z "${root}" ]; then
    echo 'Root entry not found in index!'
    exit 1
fi
# The options '-i -' makes the zarafa-restore tool read the restore keys from stdin
grep ^C "${INDEX}" | grep ${root} | cut -d\: -f7 | zarafa-restore -p -f "${FROM_NAME}" -r -v -i -
