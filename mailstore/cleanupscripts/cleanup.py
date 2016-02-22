# !/usr/bin/env python
# -*- coding: utf-8 -*-
# vim: tabstop=8 expandtab shiftwidth=4 softtabstop=4

import datetime
from datetime import timedelta
import sys
import zarafa
from MAPI.Util import *


def opt_args():
    parser = zarafa.parser('skpcfm')
    parser.add_option("--user", dest="user", action="store", help="Run script for user")
    parser.add_option("--wastebasket", dest="wastebasket", action="store_true",
                      help="Run cleanup script for the wastebasket folder")
    parser.add_option("--junk", dest="junk", action="store_true", help="Run cleanup script for the junk folder")
    parser.add_option("--force", dest="force", action="store_true", help="Force items without date to be removed")
    parser.add_option("--days", dest="days", action="store", help="Delete older then x days")
    parser.add_option("--verbose", dest="verbose", action="store_true", help="Verbose mode")
    parser.add_option("--dry-run", dest="dryrun", action="store_true", help="Run script in dry mode")
    parser.add_option("--progressbar", dest="progressbar", action="store_true", help="Show progressbar ")
    return parser.parse_args()


def progressbar(folder, daysbeforedeleted):
    try:
        from progressbar import Bar, AdaptiveETA, Percentage, ProgressBar
    except ImportError:
        print '''Please download the progressbar library from https://github.com/niltonvolpato/python-progressbar or
        run without the --progressbar parameter'''
        sys.exit(1)
    widgets = [Percentage(),
               ' ', Bar(),
               ' ', AdaptiveETA()]
    progressmax = 0
    for item in folder.items():
        if item.received <= daysbeforedeleted:
            progressmax += 1
    pbar = ProgressBar(widgets=widgets, maxval=progressmax)
    pbar.start()

    return pbar


def deleteitems(options, folder, daysbeforedeleted, pbar):
    itemcount = 0
    for item in folder.items():
        date = None
        if not item.prop(PR_LAST_MODIFICATION_TIME).value and options.force:
            date = daysbeforedeleted
        elif item.prop(PR_LAST_MODIFICATION_TIME).value:
            date = item.prop(PR_LAST_MODIFICATION_TIME).value
        if date:
            if date <= daysbeforedeleted:
                if options.verbose:
                    print 'Deleting \'%s\'' % item.subject
                if not options.dryrun:
                    folder.delete(item)
                if pbar:
                    pbar.update(itemcount + 1)
                itemcount += 1
    return itemcount


def main():
    options, args = opt_args()
    if not options.user or not options.days:
        print 'Please use:\n %s --user <username> --days <days> ' % (sys.argv[0])
        sys.exit(1)

    daysbeforedeleted = datetime.datetime.now() - timedelta(days=int(options.days))

    user = zarafa.Server(options).user(options.user)
    print 'Running script for \'%s\'' % user.name
    pbar = None
    if options.wastebasket:
        folder = user.store.wastebasket
        if options.progressbar:
            pbar = progressbar(folder, daysbeforedeleted)
        count = deleteitems(options, folder, daysbeforedeleted, pbar)
        if options.progressbar:
            pbar.finish()
        print 'Deleted %s item(s) for user \'%s\' in folder \'%s\'' % (count, user.name, folder.name)

    if options.junk:
        folder = user.store.junk
        if options.progressbar:
            pbar = progressbar(folder, daysbeforedeleted)
        count = deleteitems(options, folder, daysbeforedeleted, pbar)
        if options.progressbar:
            pbar.finish()
        print 'Deleted %s item(s) for user \'%s\' in folder \'%s\'' % (count, user.name, folder.name)


if __name__ == "__main__":
    main()

