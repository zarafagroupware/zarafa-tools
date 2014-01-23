#!/usr/bin/python -u
# http://www.isartor.org/wiki/Check_Out_of_Office_for_all_users

from MAPI.Util.Generators import *

session = OpenECSession("SYSTEM", "", "file:///var/run/zarafa", flags = 0)

for store in GetStores(session):
  props = store.GetProps([PR_MAILBOX_OWNER_NAME, 0x6760000b], 0)
  owner = props[0].Value.encode('utf-8')
  ooo = str(props[1].Value)
  if ooo == "2147746063": ooo = "False"
  print owner +": "+ str(ooo)
