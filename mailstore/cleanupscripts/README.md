Cleanup a users Junk and Deleted items folder
=====

The following scripts give you the possibility to automatically delete all items older than x days in your Junk E-mail folder and Deleted Items folder.

To use these scripts the php-cli and python-mapi package need to be installed. To install and use the script follow these steps:
Extract the tar to /usr/local/bin
Set in both the spam.php & deleted.php the option $daysBeforeDeleted to the correct value
Set in rssfeeds.py the 'offset' variable to the correct value.
To run the script use the command /usr/local/bin/cleanup

You can put this script in a daily or weekly cronjob to schedule a cleanup action.

Clean up the indexer
=====

The indexer directory can take up a large amount of space, as an automatic cleanup of old files is currently not implemented.
Therefore, you have to stop the indexing service, remove the index directory and restart the indexing service again. It will then recreate the index without the orphaned data.
A cleanup of the indexer is something that should not be done automatically, but only if you run into issues. As a full reindex is nothing you want to do on a weekly basis on large setups.

Source: http://www.zarafa.com/wiki/index.php?title=Cleanup_scripts
