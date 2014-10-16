#!/usr/bin/env python
import os
import zarafa

z = zarafa.Server()

def backup_archives():

	for user in z.users():
		if user.archive_store:
			if not os.path.isdir(user.name):
				os.mkdir(user.name)
			command = 'zarafa-backup -i %s -o %s' % (user.archive_store.guid, user.name)
			os.system(command)

if __name__ == '__main__':
        backup_archives()
