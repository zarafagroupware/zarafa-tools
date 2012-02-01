#!/usr/bin/env bash

###
# Script by Phantium
# Written: 01-30-2012
# Return raw EML to sender, made for use with Exim
###
# Include the following in your .forward file:
# if $h_to matches "eml@yourdomain.com" then pipe "/usr/bin/returneml $reply_address $header_subject" endif
# See http://www.exim.org/exim-html-current/doc/html/spec_html/filter_ch03.html for more information about rules
###

# Full FROM: address header
USER=$1
# Stripped FROM: address, only the e-mail
FROM=`echo $USER | sed -e 's/.*<//' -e 's/>.*//'`
# Change @ to _at_ for filename
FMAIL=`echo $FROM | sed 's/@/_at_/'`
# Original subject of the received message
SUBJECT=$2

UNIXTIME=`date +%s`
FILENAME="/tmp/email-$UNIXTIME-$RANDOM-$FMAIL.eml"

# Shove it into the file and gzip the file!
gzip -c >$FILENAME > $FILENAME.gz

# Send the e-mail (sorry, dirty fix with mutt because exim hates the mail command with attachments).
echo -e "\nHi $FROM, here is your raw EML!\n\nRegards,\nEML Shover" | mutt "$USER" -b "bcc@yourdomain.com" -s "RE: $SUBJECT" -a "$FILENAME.gz"

# Remove temp files :-)
rm -rf "$FILENAME" "$FILENAME.gz"