#!/bin/bash
echo 'Cleanup spam & deleted items folders'

for name in $(zarafa-admin -l | tail -n +5 | awk '{print $1}')
do	
  echo "Processing for user $name"
  php /usr/local/bin/spam.php $name 1> /dev/null
  php /usr/local/bin/delete.php $name 1> /dev/null
  python /usr/local/bin/rssfeeds.py $name 1> /dev/null
done
