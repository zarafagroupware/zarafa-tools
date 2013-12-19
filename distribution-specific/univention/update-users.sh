#!/bin/sh
# Update existing (single-server) users inside of the Univention LDAP with their new home server.
# Replace master in the set line with the name of the desired Zarafa host

eval "$(ucr shell)"

for i in $( zarafa-admin -l | tail -n +4 | grep -v SYSTEM | awk {'print $1'} ); do
	univention-directory-manager users/user modify --dn uid=$i,cn=users,$ldap_base \
	--set z4uUserServer="master"
done
