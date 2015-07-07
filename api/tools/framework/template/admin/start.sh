#! /bin/sh
#####
#start the application
#file_ver=2.0.2
#author: leonlaili,2006-12-6
#	linuschen update 2015-01-15
#
# arguments:
#   $app_name|all
#
#########


PATH=$PATH:.
####### Custom variables begin  ########
##todo: add custom variables here
#get script path

dir_pre=$(dirname $(which $0))
pkg_base_path="${dir_pre}/../"

####### Custom variables end    ########

#######
#load common functions
# arguments: NULL
# return: 0
# globals: $dir_pre
######
load_lib()
{
    common_file=$dir_pre/common.sh
    if [ -f $common_file ];then
        . $common_file
    fi
}

#######
# check current user
# arguments: NULL
# return: NULL
# globals: $user
######
check_user()
{
    if [ "$user" != "`whoami`" ];then
        echo "Only $user can execute this script"
        exit 1
    fi
}

#######
# print help information
# arguments: NULL
# return: NULL
# globals: $app_count
######
print_help()
{
    echo "Usage: start.sh <all|app_name>"
    return
}

#######
# check script parameters
# arguments: NULL
# return: NULL
# globals: $app_count,$#
######
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

#init environment before start application
init()
{
    ##todo: add init statement here
    run_config "start_init"
    return
}

#start application
start_app()
{
    ##todo: add start statement here
    #get curent path
    tmp="`pwd`"
    if [[ -d $install_path/bin/ ]];then
        cd $install_path/bin/
    fi
    export LD_LIBRARY_PATH="$install_path/lib:$LD_LIBRARY_PATH"

    #app to start
    app_to_start="$1"

    run_config "start"
    
    cd $tmp
}

configure()
{
    export PATH="$install_path/admin:$PATH"
    export VISUAL="crontab.sh add"    
    crontab -e

    if [ $? -ne 0 ];then
        log $default_log "Set crontab failed"
    fi
}

on_started()
{
    ##todo: add any statment you want after application start
    run_config "start_on_started"
    
    touch $runing_file

    # add app_name to runing_file
    if [ $app_count -gt 1 -a "$1" != "all" ];then
        echo "$app_name" | grep -wq "$1"
        if [ $? -eq 0 ];then
            grep "^${1}$" $runing_file >/dev/null
            if [ $? -ne 0 ];then
                echo "$1" >> $runing_file
            fi
        fi
        return
    fi

    rm -f $runing_file

    for app_info in `echo $app_name`                              
    do
        app=`echo $app_info | awk -F: '{print $1}'`
        echo "$app" >> $runing_file 
    done 

}
check_start_result()
{
    check_app
    if [[ ${err_app} = "" ]];then
        exit_proc 0 "start $* successfull"
    else
        exit_proc 1 "start $err_app failed"
    fi 
}

###### Main Begin ########
app_name=$1
if [ "${app_name}" = "--help" ];then
    print_help
    exit
fi

load_lib
check_user
#record the log
log $default_log "The pkg begin to start the process. $*"
check_params $*
init $*

#if [ "$2" != "restart" -a ! -f $runing_file ];then
if [ ! -f $runing_file ];then
    need_conf="true"
fi

start_app ${app_name}

if [ "${need_conf}" = "true" ];then
    configure
fi

on_started $app_name
log $default_log "The pkg have started the process. $*"
check_start_result $*
###### Main End   ########

