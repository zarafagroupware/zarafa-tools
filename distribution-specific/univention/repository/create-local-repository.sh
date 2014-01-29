#!/bin/sh
## based on http://docs.univention.de/developer-reference-3.1.html#pkt:repository
#
## create/update (and populate) local apt repository with Zarafa components
## update ZARAFADL with links to the version you want to provide
#
## further info on how to add the repository can be found in add-local-repository.sh

WWW_BASE="/var/www/repository/3.2/maintained/component"
COMP="zarafa/all"
KEYID=$(sed -ne 's/.* KEY_CREATED P \([0-9A-F]\+\) apt$/\1/p' /root/apt.fpr)
USER=username-in-portal
PASSWORD=password-of-user
ZARAFADL="https://download.zarafa.com/supported/beta/7.1/7.1.8rc1-43691/zarafa-archiver-7.1.8rc1-43691-ucs-3.0-x86_64.tar.gz https://download.zarafa.com/supported/beta/7.1/7.1.8rc1-43691/zarafa-archiver-7.1.8rc1-43691-ucs-3.0-i386.tar.gz https://download.zarafa.com/supported/beta/7.1/7.1.8rc1-43691/zcp-7.1.8rc1-43691-ucs-3.0-i386-supported.tar.gz https://download.zarafa.com/supported/beta/7.1/7.1.8rc1-43691/zcp-7.1.8rc1-43691-ucs-3.0-x86_64-supported.tar.gz https://download.zarafa.com/supported/beta/7.1/7.1.8rc1-43691/zarafa-ws-7.1.8rc1-43691-ucs-3.0-i386.tar.gz https://download.zarafa.com/supported/beta/7.1/7.1.8rc1-43691/zarafa-ws-7.1.8rc1-43691-ucs-3.0-x86_64.tar.gz"

if [ ! -e /root/apt.fpr ]; then
	echo "please generate key first"
	exit 1
fi

mkdir -p $WWW_BASE
install -m755 -d "$WWW_BASE/$COMP"
cd "$WWW_BASE/$COMP"
for URL in $ZARAFADL; do
	wget --user=$USER --password=$PASSWORD --no-check-certificate $URL -O- | tar xz --strip-components=1
done
install -m644 -t "$WWW_BASE/$COMP" *.deb

( cd "$WWW_BASE" &&
  rm -f "$COMP/Packages"* &&
  apt-ftparchive packages "$COMP" > "Packages" &&
  gzip -9 < "Packages" > "$COMP/Packages.gz" &&
  mv "Packages" "$COMP/Packages" )

( cd "$WWW_BASE/$COMP" &&
  apt-ftparchive \
    -o "APT::FTPArchive::Release::Origin=Univention" \
    -o "APT::FTPArchive::Release::Label=Univention" \
    -o "APT::FTPArchive::Release::Version=3.2" \
    -o "APT::FTPArchive::Release::Codename=3.2/zarafa" \
    release . >Release.tmp &&
  mv Release.tmp Release &&
  rm Release.gpg &&
  gpg --no-default-keyring --no-use-agent \
    --secret-keyring /root/apt.sec --keyring /root/apt.pub \
    --local-user "$KEYID" --passphrase-file /root/apt.pwd \
    --detach-sign --armor \
    --output Release.gpg Release )
