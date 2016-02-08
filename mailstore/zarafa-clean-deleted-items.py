#!/usr/bin/env python
# -*- coding: utf-8 -*-
# vim: tabstop=8 expandtab shiftwidth=4 softtabstop=4
# Running from CRON ?, do not forget to "export PYTHONIOENCODING=UTF-8" before execution.
import datetime
import zarafa


def opt_args():
    parser = zarafa.parser('msvkpcuC')
    parser.add_option("--all", dest="all", action="store_true",
                      default=False, help="run program for all users")
    parser.add_option("--after", dest="after", type=int, default=None
                      , action="store", help="delete from \'Deleted Items\' after x amount of days")
    return parser.parse_args()


def b2m(sizeinbytes):
    return (sizeinbytes / 1024) / 1024


def main():
    options, args = opt_args()

    if not (len(options.users) or options.all) or not options.after:
        print 'Run `zarafa-clean-deleted-items.py --help` for parameters'
        return
    else:
        if options.modify:
            action = ['Delete', 'Deleted']
        else:
            action = ['Found', 'Detected']

        count, size = 0, 0
        conn = zarafa.Server(options=options)

        for user in conn.users():
            if options.verbose:
                print 'Scanning user [%s] [%s and Subfolders]' % (user.name, user.store.wastebasket.name)

            wastefolders = [user.store.wastebasket.entryid]
            for wastefolder in user.store.wastebasket.folders():
                wastefolders.append(wastefolder.entryid)

            for folder in wastefolders:

                for item in user.store.folder(folder).items():

                    if item.received:

                        if item.received.date() < (datetime.date.today() - datetime.timedelta(days=options.after)):

                            if options.verbose:
                                print '\t %s in Folder: [%s] Date: [%s] Subject: [%s]' % (
                                    action[0], getattr(user.folder(folder), 'path', 'name'), item.received.date(),
                                    item.subject)

                            if options.modify:
                                try:
                                    user.store.wastebasket.delete(item)

                                except Exception as e:
                                    if options.verbose:
                                        print '\tDeleting failed error : [%s]' % e

                            count += 1
                            size += item.size

        print '%s : [%s]' % (action[1], count), 'Approximately size freed: [%s Mb]' % b2m(size)


if __name__ == '__main__':
    main()
