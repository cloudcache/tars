#! /bin/sh

###file_ver=2.0.1

PATH=$PATH:.

#restart the application
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
    common_file=$dir_pre/common.sh
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

#print help information
print_help()
{
    ##todo: output help information here
    if [ $app_count -gt 1 ];then
        echo "Usage: restart.sh <all|app_name>"
    fi
    return
}

#check script parameters
check_params()
{
    ok="true"
    ##todo: add addition parameters checking statement here...
    if [ $app_count -gt 1 -a $# -lt 1 ];then
        ok="false"
    fi
    
    if [ "$ok" != "true" ];then
        echo "Some of the parameters are invalid. "
        print_help
        exit 1
    fi    
}

###### Main Begin ########
if [ "$1" = "--help" ];then
    print_help
    exit 0
fi

load_lib
check_user
check_params $*

cd $install_path/admin

if [ "$1" != "" ];then
   app=$1
else
   app="all"
fi

if [ -f $runing_file ];then
    ./stop.sh $app restart
    sleep 2
    ./start.sh $app restart force
else
    ./start.sh $app force
fi
###### Main End   ########
