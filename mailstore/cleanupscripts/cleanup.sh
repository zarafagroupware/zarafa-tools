#!/bin/bash

echo 'Cleanup spam & deleted items folders'

for name in $(zarafa-admin -l | awk '{print $1}')
do	
  php /usr/local/bin/spam.php $name 1> /dev/null
  php /usr/local/bin/delete.php $name 1> /dev/null
  python /usr/local/bin/rssfeeds.py $name 1> /ndev/null
done

exit 0

