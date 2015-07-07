#! /bin/sh

###file_ver=2.0.2

PATH=$PATH:.

#addcrontab
#create by leonlaili,2006-12-6

####### Custom variables begin  ########
##todo: add custom variables here
#get script path
dir_pre=$(dirname $(which $0))
pkg_base_path="${dir_pre}/../"
####### Custom variables end    ########

#load common functions
load_lib()
{
    common_file=$pkg_base_path/admin/common.sh
    if [ -f $common_file ];then
        . $common_file
    fi
}

#check current user
check_user()
{
    if [ "$user" != "`whoami`" ];then
        echo "Only $user can execute this script"
        exit 1
    fi
}


configure()
{
    export PATH="$install_path/admin:$PATH"
    export VISUAL="crontab.sh add"    
    crontab -e

    if [ $? -ne 0 ];then
        log "Set crontab failed"
    fi
}


###### Main Begin ########
load_lib
#record the log
check_user
log "The pkg begin to addcrontab. $*"

configure

log "The pkg have addcrontabed. $*"
###### Main End   ########
