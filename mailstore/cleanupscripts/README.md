Cleanup a users Junk and Deleted items folder
=====

The following scripts allow you to automatically delete all items older than x days in a users **Junk E-mail** and **Deleted Items** folder.

To use these scripts the **php-cli** and **python-mapi** packages need to be installed.

To install and use the script follow these steps:

1. Extract the tar to **/usr/local/bin**
2. In both **spam.php** and **deleted.php** set the option **$daysBeforeDeleted** to the desired value.
3. In **rssfeeds.py** set the **offset** variable to the desired value.
4. To run the script use the command **/usr/local/bin/cleanup**.

You can put this script in a daily or weekly cronjob to schedule a cleanup action.

Clean up the indexer
=====

**Applies only to 7.0 as 7.1 has the new and improved zarafa-search!**

The indexer directory can take up a large amount of space, as an automatic cleanup of old files is currently not implemented.
Therefore, you have to stop the indexing service, remove the index directory and restart the indexing service again. It will then recreate the index without the orphaned data.
A cleanup of the indexer is something that should not be done automatically, but only if you run into issues. As a full reindex is nothing you want to do on a weekly basis on large setups.

Source: http://www.zarafa.com/wiki/index.php?title=Cleanup_scripts
