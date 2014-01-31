reset-freebusy.php
=====
Resets freebusy data for the whole company.  
This script is not multi-tenant capable, it only works on a single-tenant server.

reset-publicstore-permissions.py
=====
Resets publicstore folder permissions, works on both single and multi-tenant servers.  
Usage: run without parameters for single-tenant, specify tenant name for multi-tenant.


zarafa-cachestat.py
=====
Print the cache usage of zarafa-server with usage and hit ratios.

ldap\_export.sh
====
Searches the ldap tree configured in server.cfg and ldap.cfg.

Usage:
- ldap_export.sh - Creates ldif of complete search_base
- ldap_export.sh -m [email address] - Queries ldap for ([$ldap_emailaddress_attribute]=[email address])
- ldap_export.sh -u [user] - Queries ldap for ([$ldap_loginname_attribute]=[user])
- ldap_export.sh -q "[custom query]" - Queries ldap with [custom query]

check-out-of-office.py
=====
Creates list of all users with a simple false/true message concerning the out of office message state.

process_meetingsrequests.php
=====
Enters meeting requests from the root of the inbox as tentative in the users default calendar.
