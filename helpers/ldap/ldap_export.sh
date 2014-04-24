#!/bin/bash

trim () {
  R=`echo $(sed -e 's/^[[:space:]]*//' <<<"$1")`
  echo "$R"
}

get_config_value () {
  CFG_FILE=$1
  CFG_OPTION=$2
  RETVAL=`grep ^${CFG_OPTION} ${CFG_FILE} | sed -e 's/=/#/' | awk -F"#" {'print $2'} | sed -e 's/\"//g'`
  echo `trim "${RETVAL}"`
}

print_help () {
  echo "Start the script with no options to create a full ldap export from the"
  echo "configured zarafa search base."
  echo
  echo "Options with extra parameters:"
  echo
  echo -e "  -m [email address]\tQueries ldap for ([\$ldap_emailaddress_attribute]=[email address])"
  echo -e "  -u [user]\t\tQueries ldap for ([\$ldap_loginname_attribute]=[user])"
  echo -e "  -q [custom query]\tQueries ldap with [custom query]"
  echo -e "  -o [value]\t\tOverride ldap_page_size with [value]"
  echo
  echo "Options without parameters:"
  echo -e "  -a\tDo an anonymous bind"
  echo -e "  -i\tIgnore ldap_page_size config option in Zarafa ldap config"
  echo -e "  -p\tPrompt on every ldap page when needed"
  echo -e "  -s\tShows used ldapsearch command at the end of the execution"
  echo -e "  -w\tDisables ldapsearch default behavior of wrapping lines after 76 characters"
  echo -e "  -d\tFind duplicate unique attributes  -  (not tested very well)"
  echo -e "  -h\tShows this help text"
  echo
}

LDAP_CFG=`get_config_value /etc/zarafa/server.cfg user_plugin_config`

LDAP_HOST=`get_config_value ${LDAP_CFG} ldap_host`
LDAP_PORT=`get_config_value ${LDAP_CFG} ldap_port`
LDAP_USER=`get_config_value ${LDAP_CFG} ldap_bind_user`
LDAP_PASS=`get_config_value ${LDAP_CFG} ldap_bind_passwd`
LDAP_BASE=`get_config_value ${LDAP_CFG} ldap_search_base`
if [ "$LDAP_BASE" == "" ]; then
  LDAP_BASE=`get_config_value ${LDAP_CFG} ldap_user_search_base`
fi
LDAP_PAGESIZE=`get_config_value ${LDAP_CFG} ldap_page_size`
LDAP_UID=`get_config_value ${LDAP_CFG} ldap_loginname_attribute`
LDAP_MAIL=`get_config_value ${LDAP_CFG} ldap_emailaddress_attribute`

SHOW_QUERY=0
IGNORE_PAGESIZE=0
OVERRIDE_PAGESIZE=0
PAGE_PROMPT="noprompt"
WRAP=1
ANON=0

function gen_ldap_cmd () {

	if [ "$ANON" -eq 1 ]; then
		echo 'ldapsearch '${EXT}' -h '"${LDAP_HOST}"' -p '${LDAP_PORT}' -x -b '"${LDAP_BASE}"
	else
		echo 'ldapsearch '${EXT}' -h '"${LDAP_HOST}"' -p '${LDAP_PORT}' -D '"\"${LDAP_USER}\""' -x -w '"${LDAP_PASS}"' -b '"${LDAP_BASE}"
	fi
}


function find_dups () {
  function run_cmd () {
    SEARCH_QUERY=$1
    RETURN_ATTR=$2
    CMD=`gen_ldap_cmd`" ${SEARCH_QUERY} $RETURN_ATTR"
    OUTPUT=`${CMD} | perl -p00e 's/\r?\n //g' | grep ^${RETURN_ATTR} | sort | uniq -c | grep -v "1 ${RETURN_ATTR}"`
    if [ "${OUTPUT}" == "" ]; then
      echo "  None"
    else
      echo "${OUTPUT}"
    fi
  }


  LDAP_OBJECT_TYPE=`get_config_value ${LDAP_CFG} ldap_object_type_attribute`

  # Find duplicate unique user id's
  LDAP_USER_TYPE=`get_config_value ${LDAP_CFG} ldap_user_type_attribute_value`
  LDAP_USER_FILTER=`get_config_value ${LDAP_CFG} ldap_user_search_filter`
  LDAP_UNIQUE_USER_ATTR=`get_config_value ${LDAP_CFG} ldap_user_unique_attribute`
  CMD=`gen_ldap_cmd`' (&('${LDAP_OBJECT_TYPE}'='${LDAP_USER_TYPE}')'${LDAP_USER_FILTER}') '${LDAP_UNIQUE_USER_ATTR}
  echo "Searching for duplicate unique user attributes --> ${LDAP_UNIQUE_USER_ATTR}:"
  run_cmd '(&('${LDAP_OBJECT_TYPE}'='${LDAP_USER_TYPE}')'${LDAP_USER_FILTER}')' ${LDAP_UNIQUE_USER_ATTR}
  
  echo

  # Find duplicate unique group id's
  LDAP_GROUP_TYPE=`get_config_value ${LDAP_CFG} ldap_group_type_attribute_value`
  LDAP_GROUP_FILTER=`get_config_value ${LDAP_CFG} ldap_group_search_filter`
  LDAP_UNIQUE_GROUP_ATTR=`get_config_value ${LDAP_CFG} ldap_group_unique_attribute`
  echo "Searching for duplicate unqiue group attributes --> ${LDAP_UNIQUE_GROUP_ATTR}:"
  run_cmd '(&('${LDAP_OBJECT_TYPE}'='${LDAP_GROUP_TYPE}')'${LDAP_GROUP_FILTER}')' ${LDAP_UNIQUE_GROUP_ATTR}

  echo

  # Find duplicate mail addresses
  LDAP_EMAIL_ATTR=`get_config_value ${LDAP_CFG} ldap_emailaddress_attribute`
  CMD=`gen_ldap_cmd`' ('${LDAP_EMAIL_ATTR}'=*) '${LDAP_MAIL_ATTR}
  echo "Searching for duplicate email address attributes --> ${LDAP_EMAIL_ATTR}:"
  run_cmd "" ${LDAP_EMAIL_ATTR}

  echo

  # Find duplicate aliases
  LDAP_ALIAS_ATTR=`get_config_value ${LDAP_CFG} ldap_emailaliases_attribute`
  CMD=`gen_ldap_cmd`' ('${LDAP_ALIAS_ATTR}'=*) '${LDAP_ALIAS_ATTR}
  echo "Searching for duplicate alias address attributes --> ${LDAP_ALIAS_ATTR}:"
  run_cmd "" ${LDAP_ALIAS_ATTR}

  echo

}

while (( "$#" )); do
  case $1 in
    "-u")
	QUERY="($LDAP_UID=$2)"
	shift 
	;;
    "-m")
	QUERY="($LDAP_MAIL=$2)"
	shift
	;;
    "-q")
	QUERY="$2"
        shift
	;;
    "-s")
	SHOW_QUERY=1
	;;
    "-i")
	IGNORE_PAGESIZE=1
	;;
    "-p")
	PAGE_PROMPT="prompt"
	;;
    "-o")
	OVERRIDE_PAGESIZE=$2
	shift
	;;
    "-w")
	WRAP=0
	shift
	;;
    "-a")
	ANON=1
	;;
    "-h")
	print_help
	exit
	;;
    "-d")
	find_dups
	exit
	;;
    *)
	QUERY=""
	;;
  esac
  shift
done

if [ "$LDAP_PAGESIZE" != "" -a $IGNORE_PAGESIZE -eq 0 ]; then
  EXT="-E pr=${LDAP_PAGESIZE}/${PAGE_PROMPT}"
fi
if [ $OVERRIDE_PAGESIZE -gt 0 ]; then
  EXT="-E pr=${OVERRIDE_PAGESIZE}/${PAGE_PROMPT}"
fi


if [ "$QUERY" == "" ]; then
  CMD=`gen_ldap_cmd`
else
  CMD="`gen_ldap_cmd` \"${QUERY}\""
fi

if [ $WRAP -eq 1 ]; then
  eval ${CMD}
else
  eval ${CMD} | perl -p00e 's/\r?\n //g'
fi 


if [ $SHOW_QUERY -eq 1 ]; then
  echo
  if [ "$QUERY" == "" ]; then
    echo Command used: ${CMD}
  else
    echo Command used: ${CMD} | sed "s/${QUERY}$/\"${QUERY}\"/"
  fi
fi
