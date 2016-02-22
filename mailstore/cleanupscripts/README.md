Cleanup a users Junk and Deleted items folder
=====

The following scripts allow you to automatically delete all items older than x days in a users **Junk E-mail** and **Deleted Items** folder.

Dependencies
============

cleanup depends on a few Python libraries:

* [python-zarafa](https://github.com/zarafagroupware/python-zarafa.git)
* [python-mapi](https://download.zarafa.com/community/final/)
 

Usage
=====

  python cleanup.py --user <user> --junk --wastebasker --days <days>
