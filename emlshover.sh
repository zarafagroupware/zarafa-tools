#!/usr/bin/env bash

############################
# Script by Phantium       #
# 30-01-2012               #
# Return raw EML to sender #
# Made for use with Exim   #
############################

# Full from including name
USER=$1
# Normal from address
FROM=`echo $USER | sed -e 's/.*<//' -e 's/>.*//'`
# @ to _at_ for filename
FMAIL=`echo $FROM | sed 's/@/_at_/'`
# Original subject
SUBJECT=$2

UNIXTIME=`date +%s`
FILENAME="/tmp/email-$UNIXTIME-$RANDOM-$FMAIL.eml"

# Shove it into the file and gzip the file!
gzip -c >$FILENAME > $FILENAME.gz

# Send the e-mail
echo -e "\nHi $FROM, here is your raw EML!\n\nRegards,\nEML Shover" | mutt "$USER" -b bcc@yourdomain.com -s "RE: $SUBJECT" -a $FILENAME.gz

# Remove temp files :-)
rm -rf "$FILENAME" "$FILENAME.gz"
