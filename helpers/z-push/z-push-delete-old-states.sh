#!/bin/bash
IFS=$'\n'
ZPA=/usr/share/z-push/z-push-admin.php

# set terminal in case the script is called from cron
export TERM="xterm"

TWOMONTHSAGO=$(date +"%s" --date='2 months ago')

for i in $($ZPA -a lastsync | tail -n +6); do
	DEVICE=$(echo $i | awk '{print $1}')
	USER=$(echo $i | awk '{print $2}')
	LASTDATE=$(echo $i | awk '{print $3}')

	if [[ -z $LASTDATE ]]; then
		echo "something must have gone wrong"
		exit 1
	fi

	if [[ $LASTDATE == *"never"* ]]; then
		echo "$DEVICE never synced"
		$ZPA -a remove -u $USER -d $DEVICE
		continue
	else
		LASTDATE2=$(date -d $LASTDATE +%s)
	fi

	if [[ $TWOMONTHSAGO -ge $LASTDATE2 ]]; then
		echo "$DEVICE is older than 2 months"
		$ZPA -a remove -u $USER -d $DEVICE
	fi
done
