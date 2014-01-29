#!/bin/sh
# Original Source: http://forum.univention.de/viewtopic.php?f=70&t=2442#p8381
# Create needed fields in the Univention GUI to manage a Zarafa Multi-Server setup incl. Zarafa Archiver

eval "$(ucr shell)"

# AddEmptyValue allows to leave the "Zarafa Home/Archiver Server" field empty.
univention-directory-manager settings/syntax create --ignore_exists \
        --position "cn=custom attributes,cn=univention,$ldap_base" \
        --set name=z4uUserServerSyntax \
        --set description="Search syntax for Zarafa Server" \
        --set filter="(&(objectClass=zarafa-server)(zarafaHttpPort=*)(zarafaSslPort=*)(zarafaFilePath=*))" \
        --set ldapattribute="cn" \
        --set ldapvalue="cn" \
        --set viewonly=FALSE \
	--set addEmptyValue=1

univention-directory-manager settings/extended_attribute create --ignore_exists \
        --position "cn=custom attributes,cn=univention,$ldap_base" \
        --set name="z4uUserServer" \
        --set module=users/user \
        --set module=settings/usertemplate \
        --set tabName="Zarafa" \
        --set tabPosition=21 \
        --set shortDescription="Zarafa Home Server" \
        --set longDescription="Zarafa server the user store will be hosted on" \
        --set translationShortDescription='"de_DE" "Zarafa Home Server"' \
        --set translationLongDescription='"de_DE" "Zarafa-Server, auf dem der Benutzer-Store gespeichert wird"' \
        --set objectClass=zarafa-user \
        --set syntax=z4uUserServerSyntax \
        --set mayChange=1 \
        --set ldapMapping=zarafaUserServer \
        --set multivalue=0


univention-directory-manager settings/extended_attribute create "$@" --ignore_exists \
        --position "cn=custom attributes,cn=univention,$ldap_base" \
        --set name="z4uContainsPublic" \
        --set module=computers/domaincontroller_master \
        --set module=computers/domaincontroller_backup \
        --set module=computers/domaincontroller_slave \
        --set module=computers/memberserver \
        --set tabName="Zarafa" \
        --set shortDescription="Contains Public" \
        --set longDescription="This server contains the public store" \
        --set translationShortDescription='"de_DE" "Contains Public"' \
        --set translationLongDescription='"de_DE" "Dieser Server enthält einen öffentlichen Zarafa-Store"' \
        --set objectClass=zarafa-server \
        --set syntax=string \
        --set mayChange=1 \
        --set ldapMapping=zarafaContainsPublic \
        --set tabPosition=1 \
        --set multivalue=0

univention-directory-manager settings/extended_attribute create "$@" --ignore_exists \
        --position "cn=custom attributes,cn=univention,$ldap_base" \
        --set name="z4uFilePath" \
        --set module=computers/domaincontroller_master \
        --set module=computers/domaincontroller_backup \
        --set module=computers/domaincontroller_slave \
        --set module=computers/memberserver \
        --set tabName="Zarafa" \
        --set shortDescription="Server File Path" \
        --set longDescription="The unix socket or named pipe to the server" \
        --set translationShortDescription='"de_DE" "Server File Path"' \
        --set translationLongDescription='"de_DE" "Pfad zum Unix-Socket or der Named-Pipe des Zarafa-Servers"' \
        --set objectClass=zarafa-server \
        --set syntax=string \
        --set mayChange=1 \
        --set ldapMapping=zarafaFilePath \
        --set tabPosition=3 \
        --set multivalue=0

univention-directory-manager settings/extended_attribute create "$@" --ignore_exists \
        --position "cn=custom attributes,cn=univention,$ldap_base" \
        --set name="z4uHttpPort" \
        --set module=computers/domaincontroller_master \
        --set module=computers/domaincontroller_backup \
        --set module=computers/domaincontroller_slave \
        --set module=computers/memberserver \
        --set tabName="Zarafa" \
        --set shortDescription="HTTP Port" \
        --set longDescription="Port for HTTP connections" \
        --set translationShortDescription='"de_DE" "HTTP Port"' \
        --set translationLongDescription='"de_DE" "Port für HTTP-Verbindungen"' \
        --set objectClass=zarafa-server \
        --set syntax=string \
        --set mayChange=1 \
        --set ldapMapping=zarafaHttpPort \
        --set tabPosition=5 \
        --set multivalue=0

univention-directory-manager settings/extended_attribute create "$@" --ignore_exists \
        --position "cn=custom attributes,cn=univention,$ldap_base" \
        --set name="z4uSslPort" \
        --set module=computers/domaincontroller_master \
        --set module=computers/domaincontroller_backup \
        --set module=computers/domaincontroller_slave \
        --set module=computers/memberserver \
        --set tabName="Zarafa" \
        --set shortDescription="SSL Port" \
        --set longDescription="Port for SSL connections" \
        --set translationShortDescription='"de_DE" "SSL Port"' \
        --set translationLongDescription='"de_DE" "Port für SSL-Verbindungen"' \
        --set objectClass=zarafa-server \
        --set syntax=string \
        --set mayChange=1 \
        --set ldapMapping=zarafaSslPort \
        --set tabPosition=7 \
        --set multivalue=0

#archiver related
univention-directory-manager settings/extended_attribute create --ignore_exists \
        --position "cn=custom attributes,cn=univention,$ldap_base" \
        --set name="z4uArchiveServer" \
        --set module=users/user \
        --set module=settings/usertemplate \
        --set tabName="Zarafa" \
        --set tabPosition=22 \
        --set shortDescription="Zarafa Archive Server" \
        --set longDescription="Zarafa server the user archive will be hosted on" \
        --set translationShortDescription='"de_DE" "Zarafa Archive Server"' \
        --set translationLongDescription='"de_DE" "Zarafa-Server, auf dem das Benutzer-Archiv gespeichert wird"' \
        --set objectClass=zarafa-user \
        --set syntax=z4uUserServerSyntax \
        --set mayChange=1 \
        --set ldapMapping=zarafaUserArchiveServers \
        --set multivalue=0
