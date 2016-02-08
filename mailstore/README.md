fix-ipm-subtree.py
=====
Corrects the entryid of the PR\_IPM\_SUBTREE\_ENTRYID property inside of a users store.  
This property is required for a users store to properly display all folders.

foldernames.php
=====
Change language of default mapi folders, rename folders to a new locale.

foldersize.php
=====
Displays the size per folder for a users mailstore.

list-folder-size.py
=====
Displays the size per folder for a users mailstore.

list-folders.py
=====
Displays all of the folders for a users mailstore.

list-hierarchy.py
=====
Displays all of the folder for a users mailstore including how many messages are in the folder.

list-public-folders.py
=====
Lists public folders including the person who created it and the creation date if available.

outbox.py
=====
Displays any messages in the Outbox folder if there are any pending to be sent.

remove-duplicatemessages.php
=====
Removes duplicate messages from a users mailstore.

resetfolders.py
=====
Resets a users mailstore folders to the default folders as you would otherwise do with OUTLOOK.EXE /resetfolders

deletemessages.py
=====
Deletes messages in a given folder.  
E.g. python deletemessages.py -u john -f Inbox -n 10

full-restore-public.sh
=====
This script is for licensed installations for use with zarafa-restore.  
It allows you to restore a public stores bricklevel backup.  
Usage: ./full-restore-public.sh <bricklevel name> e.g. Public.

list-userguids.py
=====
Lists user guids for all or given user.

mailstore-permissions.php
=====
Provides the option to set permissions on mailstores.

list-folders-folderid.py
=====
List folders with their respective folderid.

migrate.py
=====
Migrate the users mail store to the Inbox -> Archives folder of another account.

zarafa-clean-deleted-items.py
====
Clean up items from 'Deleted Items' older than x days.  
Requires Zarafa 7.2 or higher or 7.1 with python-zarafa from https://github.com/zarafagroupware/python-zarafa

