#!/usr/bin/env bash
 
FROM=$1
TO=$2
SUBJECT=$3
USER=$4
MSG=$5
 
# Log to autoresponse.log in /tmp
exec >> /tmp/autoresponse.log 2>&1
 
# defaults
AUTORESPOND_CC=0
AUTORESPOND_NORECIP=0
TIMELIMIT=$[24*60*60]
SENDDB=${TMP:-/tmp}/zarafa-vacation-$USER.db
SENDDBTMP=${TMP:-/tmp}/zarafa-vacation-$USER-$$.tmp
SENDMAILCMD=/usr/sbin/sendmail
SENDMAILPARAMS="-t -f"
 
if [ -r /etc/zarafa/autorespond ]; then
        . /etc/zarafa/autorespond
fi
 
# Check whether we want to respond to the message
RESPOND=0
if [ "$AUTORESPOND_NORECIP" = "1" ]; then
        RESPOND=1
        echo "NORECIP: Incoming autoresponse for email: '$2' from email: '$1' with subject: '$3' and username: '$4'"
elif [ "$AUTORESPOND_CC" = "1" -a "$MESSAGE_CC_ME" = "1" ]; then
        RESPOND=1
        echo "RESPOND: Incoming autoresponse for email: '$2' from email: '$1' with subject: '$3' and username: '$4'"
elif [ "$MESSAGE_TO_ME" = "1" ]; then
        RESPOND=1
        echo "MSG_TO_ME: Incoming autoresponse for email: '$2' from email: '$1' with subject: '$3' and username: '$4'"
fi
 
if [ $RESPOND -ne 1 ]; then
        echo "ALREADY_SENT: User '$4' (TO: $2) already received an autoresponse from email: '$1'"
        exit 0;
fi
 
# Subject is required
if [ -z "$SUBJECT" ]; then
    SUBJECT="Autoreply";
fi
# not enough parameters
if [ -z "$FROM" -o -z "$TO" -o -z "$USER" -o -z "$MSG" ]; then
    exit 0;
fi
if [ ! -f "$MSG" ]; then
    exit 0;
fi
 
# Loop prevention tests
if [ "$FROM" = "$TO" ]; then
    exit 0;
fi
shortto=`echo "$TO" | sed -e 's/\(.*\)@.*/\1/' | tr '[A-Z]' '[a-z]'`
if [ "$shortto" = "mailer-daemon" -o "$shortto" = "postmaster" -o "$shortto" = "root" ]; then
    exit 0;
fi
shortfrom=`echo "$FROM" | sed -e 's/\(.*\)@.*/\1/' | tr '[A-Z]' '[a-z]'`
if [ "$shortfrom" = "mailer-daemon" -o "$shortfrom" = "postmaster" -o "$shortfrom" = "root" ]; then
    exit 0;
fi
 
# Check if mail was send in last $TIMELIMIT timeframe
TIMESTAMP=`date +%s`
if [ -f "$SENDDB" ]; then
    while read last to; do
        if [ "$TO" != "$to" ]; then
            continue
        fi
        if [ $[$last+$TIMELIMIT] -ge $TIMESTAMP ]; then
            echo "TIMELIMIT: User '$4' (TO: $2) already received an autoresponse within timelimit. Details: TIMELIMIT: '$(($last + $TIMELIMIT ))' TIME REMAINING: '$(($last + $TIMELIMIT - $TIMESTAMP))' TIMESTAMP: $TIMESTAMP"
            exit 0;
        fi
    done < "$SENDDB"
fi
 
umask 066
grep -v "$TO" "$SENDDB" > "$SENDDBTMP" 2>/dev/null
mv "$SENDDBTMP" "$SENDDB" 2>/dev/null
echo $TIMESTAMP "$TO" >> "$SENDDB" 2>/dev/null
 
$SENDMAILCMD $SENDMAILPARAMS "$FROM" < "$MSG"
