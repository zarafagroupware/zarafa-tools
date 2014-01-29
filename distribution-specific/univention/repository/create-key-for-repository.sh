(umask 0077 ; makepasswd --chars 16 >/root/apt.pwd)
gpg --gen-key --status-fd 3 --batch 3>/root/apt.fpr <<__EOF__
%echo Generating key for APT
Key-Type: RSA
Key-Length: 1024
Key-Usage: sign
Passphrase: $(</root/apt.pwd)
Name-Real: Local APT Mirror
Name-Email: apt-mirror@univention.de
Expire-Date: 365d
Handle: apt
%pubring /root/apt.pub
%secring /root/apt.sec
%commit
%echo done
__EOF__


mkdir -p /var/www/repository
cp /root/apt.pub /var/www/repository
