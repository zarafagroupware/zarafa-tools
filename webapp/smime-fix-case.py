#!/usr/bin/env python
import zarafa
from MAPI.Tags import *
from MAPI.Util import *

z = zarafa.Server()


def main():
    for user in z.users():
        for item in user.store.root.associated.items():
            if item.prop(PR_MESSAGE_CLASS):
                if item.prop(PR_MESSAGE_CLASS).value == 'Webapp.Security.Public':
                    print "Correcting item"
                    item.mapiobj.SetProps([SPropValue(PR_MESSAGE_CLASS, 'WebApp.Security.Public')])
                    item.mapiobj.SaveChanges(0)

if __name__ == '__main__':
    main()
