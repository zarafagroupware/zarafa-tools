read\_webapp-recipienthistory.py
=====
Reads recipient history for the given user.  
Usage: ./read\_webapp-recipienthistory.py username

read\_webapp-settings.py
=====
Reads settings for the given user.  
Usage: ./read\_webapp-settings.py username

reset\_webapp-recipienthistory.py
=====
Resets recipient history for the given user.  
Usage: ./reset\_webapp-recipienthistory.py username

reset\_webapp-settings.py
=====
Resets settings for the given user.  
Usage: ./reset\_webapp-settings.py username

write\_webapp-settings.py
=====
Writes custom settings for the given user.  
Usage: ./write\_webapp-settings.py username

jabberauth.php
=====
Script to authenticate a user to ejabberd after logging in to the Zarafa WebApp
https://community.zarafa.com/pg/plugins/project/6450/developer/milo/zarafa-webapp-ejabberd-authentication-script

backup\_webapp\_settings.py
=====
Back up user's webapp settings, keep in mind these do not include the recipient auto completion list!  
Usage: ./backup\_webapp\_settings.py username

restore\_webapp\_settings.py
=====
Restore a user's webapp settings.
Usage: ./backup\_webapp\_settings.py username

enable\_webapp\_shortcuts.py
=====
Enables the keyboard shortcuts for the specified user.  
Usage: ./enable\_webapp\_shortcuts.py username

disable\_webapp\_shortcuts.py
=====
Disables the keyboard shortcuts for the specified user.  
Usage: ./disable\_webapp\_shortcuts.py username

restore\_webapp-settings.py
=====
Restores a users webapp settings.
Usage: ./restore\_webapp-settings.py username

smime\-fix\-case.py
=====
The WebApp smime plugin could save some of the certificate attributes with an incorrect capitalization (Webapp.Security.Public whereas WebApp.Security.Public should have been used).
This causes no issues for the smime plugin itself but it could make things difficult when using scripts to import or export certificates.
This script will fix the attributes for all local users
Requires python-zarafa which is included from Zarafa 7.2