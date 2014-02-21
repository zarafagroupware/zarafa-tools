#!/usr/bin/env bash

if [ -e /usr/share/zarafa-backup/full-restore.sh ];
then
   RESTORE=/usr/share/zarafa-backup/full-restore.sh
elif [ -e /usr/share/zarafa/full-restore.sh ];
then
   RESTORE=/usr/share/zarafa/full-restore.sh
else
   echo Error: Cannot find full-restore.sh.
   exit 1
fi

for user in `zarafa-admin -l | egrep -v "\-|SYSTEM|User list|Username" | awk '{print $1}'`;
do
   echo ------
   echo Starting bricklevel import for user: $user
   if [ -e $user.index.zbk ];
   then
       echo Importing bricklevel for user: $user
       $RESTORE $user
   else
       echo Cannot find bricklevel for user: $user, skipping.
   fi
done
