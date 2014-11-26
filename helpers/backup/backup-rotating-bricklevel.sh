#!/bin/sh

BACKUPPATH=/backup/bricklevel
TARGET=/media/ext-backup
dayofweek=$(date +%0u);
today=$(date +%Y-%m-%d-%s)

# delete content of $BACKUPPATH every sunday, to force a fresh backup
if [ $dayofweek = "0" ]; then
	rm -r $BACKUPPATH/*
fi

# run zarafa-backup
zarafa-backup -a -J -o $BACKUPPATH

# copy bricklevel backup to external location and create hardlinked copies
rsync -avP --delete "${BACKUPPATH}/" "${TARGET}/${today}/" \
--link-dest="${TARGET}/last/"
ln -nsf "${TARGET}/${today}" "${TARGET}/last"
exit 0
