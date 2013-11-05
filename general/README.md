reset-freebusy.php
=====
Resets freebusy data for the whole company.  
This script is not multi-tenant capable, it only works on a single-tenant server.

reset-publicstore-permissions.py
=====
Resets publicstore folder permissions, works on both single and multi-tenant servers.  
Usage: run without parameters for single-tenant, specify tenant name for multi-tenant.

ldap-export.sh
====
Searches the ldap tree configured in server.cfg and ldap.cfg.

Usage:
- ldap_export.sh - Creates ldif of complete search_base
- ldap_export.sh -m [email address] - Queries ldap for ([$ldap_emailaddress_attribute]=[email address])
- ldap_export.sh -u [user] - Queries ldap for ([$ldap_loginname_attribute]=[user])
- ldap_export.sh -q "[custom query]" - Queries ldap with [custom query] 
