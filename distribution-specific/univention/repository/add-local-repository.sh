#!/bin/sh

## replace repository.server with the hostname where your local repository is stored
REPO=repository.server

ucr set \
       repository/online/component/zarafa=yes \
       repository/online/component/zarafa/server=$REPO \
       repository/online/component/zarafa/prefix=repository
echo "Adding key to apt"
wget -q http://$REPO/repository/apt.pub -O- | apt-key add -
