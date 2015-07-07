#! /bin/sh

###file_ver=2.0.5

PATH=$PATH:.

#stop the application
#create by leonlaili,2006-12-6

####### Custom variables begin  ########
##todo: add custom variables here
#get script path
dir_pre=$(dirname $(which $0))
pkg_base_path="${dir_pre}/../"

#sleep when application stop
sleep_count=2
####### Custom variables end    ########

get_dir()
{
    if [ "$1" = "" ];then
        return
    fi

    tmp=`pwd`
    cd $( dirname $1 )
    dir_pre=`pwd`
    #install_path=`dirname $dir_pre`
    cd $tmp
}

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
        echo "Usage: stop.sh <all|app_name>"
    else
        echo "Usage: stop.sh"
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

#kill proccess by name
kill_app()
{
    if [ $# -lt 1 ];then
        return 1
    fi
    app=$1
    signal=$2
    if [[ "${signal}" = "" ]];then
        killall -9 "${app}" >>/dev/null 2>&1
        if [[ $? -ne 0 ]];then
            pkill -9 -xf '^((\S*/)?(perl|python|php|sh|bash)\s+)?(\S+/)?'${app}'($|\s+.+$)'>>/dev/null 2>&1
        fi
    else
        killall -${signal} "${app}" >>/dev/null 2>&1
        if [[ $? -ne 0 ]];then
            pkill -$signal -xf '^((\S*/)?(perl|python|php|sh|bash)\s+)?(\S+/)?'${app}'($|\s+.+$)'>>/dev/null 2>&1
        fi
    fi
}

#clear runing app
clear_runing()
{
    if [ "$1" = "all" -o "$1" = "" ];then
        rm -f $runing_file
        return
    fi
    tmp_file=`create_tmp_file $install_path/admin/data/tmp`
    cat $runing_file | grep -v "^${1}$" > ${tmp_file}
    if [ "`cat ${tmp_file}`" = "" ];then
        rm -f $runing_file $tmp_file
        return
    fi
       
    cat ${tmp_file} > $runing_file
    rm $tmp_file
}

#stop the application
stop_app()
{
    ##todo: add stop statement here

    #app to stop
    app_to_stop="$1"
    if [[ -d $install_path/bin ]];then
        cd $install_path/bin
    fi
    run_config "stop"
}

#on stoped
on_stoped()
{
    ##todo: add any statment you want after application stop

    run_config "stop_on_stoped"
    
#    log $default_log "stop successful"
}
check_stop_result()
{
    result="true"
    if [ "$1" = "all" -o "$1" = "" ];then
        stop_list=''
        for app_info in `echo $app_name`
        do
            app=`echo $app_info | awk -F: '{print $1}'`
            stop_list="${stop_list} ${app}"
        done
    else
        stop_list=$1
    fi
    failed_list=''
    for app_to_stop in ${stop_list}
    do
        err_app='';
        check_process $app_to_stop
        err_app=`echo $err_app`
        if [[ "${err_app}" = "${app_to_stop}" ]];then
            #msg="stop ${app_to_stop} success"
            #echo $msg
            log $default_log "${msg}"
        else
            #msg="stop ${app_to_stop} failed"
            #echo $msg
            #log $msg
            failed_list="${failed_list} ${app_to_stop}"
            result="false"
        fi
    done
    if [[ "${result}" != "true" ]];then
        msg="stop ${failed_list} failed"
        exit_proc 1 "stop" "${msg}"
        log $default_log $msg
    else
        msg="stop ${stop_list} success"
        exit_proc 0 "stop" "${msg}"
        log $default_log $msg
    fi
}


###### Main Begin ########
if [ "$1" = "--help" ];then
    print_help
    exit
fi
get_dir $0

load_lib
log $default_log "The pkg begin to stop the process. $*"
check_user
check_params $*
if [[ $2 != "restart" ]];then
    clear_runing $*
fi
#if [ "$2" != "restart" -a ! -f $runing_file ];then
if [ ! -f $runing_file ];then
    $install_path/admin/clear.sh configure
fi
stop_app $*
on_stoped
check_stop_result
log $default_log "The pkg have stoped the process. $*"
###### Main End   ########
