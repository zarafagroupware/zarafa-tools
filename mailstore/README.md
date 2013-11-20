fix-ipm-subtree.py
=====
Corrects the entryid of the PR\_IPM\_SUBTREE\_ENTRYID property inside of a users store.  
This property is required for a users store to properly display all folders.

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

