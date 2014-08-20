#!/usr/bin/env bash
 
FROM=$1
TO=$2
SUBJECT=$3
USER=$4
MSG=$5
 
# Optional: Log to autoresponse.log in /tmp
# exec >> /tmp/autoresponse.log 2>&1
 
# Configuration of domains not to autorespond to
DOMAINS=(mydomain.com myotherdomain.com)
 
# Strip user from and keep only the domain
RECRESULT=$(echo $TO | sed -n 's/.*@//p')
 
# Loop through the array of domains
for i in "${DOMAINS[@]}"
do
        # If matching with received e-mail domain, do not autorespond
        if [ $RECRESULT == $i ]; then
                echo "Not sending autoresponse to e-mail $TO with subject $SUBJECT."
                exit 0;
        # If not matching, continue
        else
                echo "Sending autoresponse for domain $i which is not part of filter."
        fi
done
 
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
elif [ "$AUTORESPOND_CC" = "1" -a "$MESSAGE_CC_ME" = "1" ]; then
        RESPOND=1
elif [ "$MESSAGE_TO_ME" = "1" ]; then
        RESPOND=1
fi
 
if [ $RESPOND -ne 1 ]; then
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
            exit 0;
        fi
    done < "$SENDDB"
fi
 
umask 066
grep -v "$TO" "$SENDDB" > "$SENDDBTMP" 2>/dev/null
mv "$SENDDBTMP" "$SENDDB" 2>/dev/null
echo $TIMESTAMP "$TO" >> "$SENDDB" 2>/dev/null
 
$SENDMAILCMD $SENDMAILPARAMS "$FROM" < "$MSG"
