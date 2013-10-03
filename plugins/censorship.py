import re
from MAPI.Util import *
from plugintemplates import *

CENSOR = '*censored*'

class Censorship(IMapiDAgentPlugin):
    def PostConverting(self, session, addrbook, store, folder, message):
        body = message.GetProps([PR_BODY],0)[0].Value
        badwords = [line.strip() for line in file('censorship.txt')]
        body = re.compile('|'.join(map(re.escape, badwords)), re.I).sub(CENSOR, body)
        self.logger.logDebug('%d badword(s) in body' % len(badwords))
        message.SetProps([SPropValue(PR_BODY, body)])
        return MP_CONTINUE,
