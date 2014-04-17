#!/bin/bash
# http://www.zarafa.com/wiki/index.php/Full_database_backup

# Modify the variables below to your need

# Mysql Credentials
MyUSER="mysqluser"
MyPASS="password"
MyHOST="mysqlhost"
MYSQL="$(which mysql)"

# Owner of mysql backup dir
OWNER="root"
# Group of mysql backup dir
GROUP="root"

# Backup all existing databases
DBS="$($MYSQL -u $MyUSER -h $MyHOST -p$MyPASS -Bse 'show databases')"
# Or specify which databases to backup
#DBS="mysql zarafa"

# DO NOT BACKUP these databases
IGGY="test"

# Backup Dest directory, change this if you have someother location
DEST="/path/to/backup/dir"

# mysqldump parameters
DUMP_OPTS="-Q --skip-lock-tables --single-transaction"

# Send Result EMail
SEND_EMAIL=1
NOTIFY_EMAIL="user@domain.com"
NOTIFY_SUBJECT="MySQL Backup Notification"

# Delete old backups
DELETE_OLD_BACKUPS=1
DELETE_BACKUPS_OLDER_THAN_DAYS=10

# Usually there is no need to modify the variables below

# Linux bin paths, change this if it can't be autodetected via which command
MYSQLDUMP="$(which mysqldump)"
GREP="$(which grep)"
CHOWN="$(which chown)"
CHMOD="$(which chmod)"
GZIP="$(which gzip)"
MAIL="$(which mail)"
FIND="$(which find)"
DF="$(which df)"

# Get hostname
HOST="$(hostname)"

# Get data in yyyy-mm-dd format
NOW="$(date +"%Y%m%d")"

# Function for generating Email
function gen_email {
  DO_SEND=$1
  TMP_FILE=$2
  NEW_LINE=$3
  LINE=$4
  if [ $DO_SEND -eq 1 ]; then
    if [ $NEW_LINE -eq 1 ]; then
      echo "$LINE" >> $TMP_FILE
    else
      echo -n "$LINE" >> $TMP_FILE
    fi
  fi
}

# Main directory where backup will be stored
if [ ! -d $DEST ]; then 
  mkdir -p $DEST
  # Only $OWNER.$GROUP can access it!
  $CHOWN $OWNER:$GROUP -R $DEST
  $CHMOD 0750 $DEST
fi

# Create backup directory
MBD="$DEST/$NOW"
if [ ! -d "$MBD" ]; then
  mkdir "$MBD"
  # Only $OWNER.$GROUP can access it!
  $CHOWN $OWNER:$GROUP -R $MBD
  $CHMOD 0750 $MBD
fi

# Temp Message file
TMP_MSG_FILE="/tmp/$RANDOM.msg"
if [ $SEND_EMAIL -eq 1 -a -f "$TMP_MSG_FILE" ]; then
  rm -f "$TMP_MSG_FILE"
fi

set -o pipefail

# Start backing up databases
STARTTIME=$(date +%s)
for db in $DBS
do
    skipdb=-1
    if [ "$IGGY" != "" ];
    then
	for i in $IGGY
	do
	    [ "$db" == "$i" ] && skipdb=1 || :
	done
    fi

    if [ "$skipdb" == "-1" ] ; then
	FILE="$MBD/$db.$HOST.$NOW"
	# do all inone job in pipe,
	# connect to mysql using mysqldump for select mysql database
	# and pipe it out to gz file in backup dir :)
        $MYSQLDUMP $DUMP_OPTS -u $MyUSER -h $MyHOST -p$MyPASS $db | $GZIP -9 > "$FILE.gz"
        ERR=$?
        if [ $ERR != 0 ]; then
	  NOTIFY_MESSAGE="Error: $ERR, while backing up database: $db"	
	else
	  NOTIFY_MESSAGE="Successfully backed up database: $db"
	fi
        gen_email $SEND_EMAIL $TMP_MSG_FILE 1 "$NOTIFY_MESSAGE"
        echo $NOTIFY_MESSAGE
    fi
done
ENDTIME=$(date +%s)
DIFFTIME=$(( $ENDTIME - $STARTTIME ))
DUMPTIME="$(($DIFFTIME / 60)) minutes and $(($DIFFTIME % 60)) seconds."

# Empty line in email and stdout
gen_email $SEND_EMAIL $TMP_MSG_FILE 1 ""
echo ""

# Log Time
gen_email $SEND_EMAIL $TMP_MSG_FILE 1 "mysqldump took: ${DUMPTIME}"
echo "mysqldump took: ${DUMPTIME}"

# Empty line in email and stdout
gen_email $SEND_EMAIL $TMP_MSG_FILE 1 ""
echo ""

# Delete old backups
if [ $DELETE_OLD_BACKUPS -eq 1 ]; then
  find "$DEST" -maxdepth 1 -mtime +$DELETE_BACKUPS_OLDER_THAN_DAYS -type d | $GREP -v "^$DEST$" | while read DIR; do
    gen_email $SEND_EMAIL $TMP_MSG_FILE 0 "Deleting: $DIR: "
    echo -n "Deleting: $DIR: "
    rm -rf "$DIR"
    ERR=$?
    if [ $ERR != 0 ]; then
      NOTIFY_MESSAGE="ERROR"
    else
      NOTIFY_MESSAGE="OK"
    fi
    gen_email $SEND_EMAIL $TMP_MSG_FILE 1 "$NOTIFY_MESSAGE"
    echo "$NOTIFY_MESSAGE"
  done
fi

# Empty line in email and stdout
gen_email $SEND_EMAIL $TMP_MSG_FILE 1 ""
echo ""

# Add disk space stats of backup filesystem
if [ $SEND_EMAIL -eq 1 ]; then
  $DF -h "$DEST" >> "$TMP_MSG_FILE"
fi
$DF -h "$DEST"

# Sending notification email
if [ $SEND_EMAIL -eq 1 ]; then
  $MAIL -s "$NOTIFY_SUBJECT" "$NOTIFY_EMAIL" < "$TMP_MSG_FILE"
  rm -f "$TMP_MSG_FILE"
fi
