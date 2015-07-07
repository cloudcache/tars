#!/bin/bash
# +++++++++++++++++++++++++++++++++++++++++++++++++++++++
# Copyright @ Tencent
# public.sh
# 
# 
#
# ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
# configurations
cur_path=$( dirname $(which $0) )

debug_log_dir="$cur_path/../log"
if [ ! -d $debug_log_dir ];then mkdir -p $debug_log_dir;fi

user_log_dir="$cur_path/../log"
if [ ! -d $user_log_dir ];then mkdir -p $user_log_dir; fi

time_debug_dir="$cur_path/../log"
vidc_time_file=$time_debug_dir/vidc_time.log
gz_time_file=$time_debug_dir/install_sf_time.log
st_time_file=$time_debug_dir/update_sf_time.log
debug_log_file=$debug_log_dir/debug_log.log.$(date +%F)
user_log_file=$debug_log_dir/user_log.log.$(date +%F)

export_pkg_lock="/tmp/svn_export_package_to_cache.lock"
if [ ! -f $export_pkg_lock ];then touch $export_pkg_lock; fi

export_upd_pkg_lock="/tmp/svn_export_update_package_to_cache.lock"
if [ ! -f $export_upd_pkg_lock ];then touch $export_upd_pkg_lock; fi

usleep=`which usleep`
if [ -z $usleep ];then usleep="$cur_path/usleep"; fi

# bebug_log
function debug_log()
{  
    local this_file=$(basename $0) 
    local dt=`date +"%F %T"`
    echo "DEBUG [$dt]" "$*" | tee -a "${debug_log_file}"
}

# user_log
function user_log()
{
    local dt=`date +"%F %T"`
    echo "[$dt]" "$*" >> "${user_log_file}"
    debug_log $*
    return 0
}

# mutex_lock
function mutex_lock()
{
    local lock_file="/tmp/mutex_lock.$1"
    local lock_pid=''
    local sleep_time=$$
    let "sleep_time=$sleep_time+100000"
    while :
    do
        $usleep $sleep_time
        if [ ! -f $lock_file ];then break; fi
        lock_pid=`cat $lock_file`
        if [[ -z $lock_pid || ! -d /proc/$lock_pid ]];then break; fi
        $usleep $sleep_time 
    done
    echo $$ > $lock_file
    return 0
}

# mutex_unlock
function mutex_unlock()
{
    local lock_file="/tmp/mutex_lock.$1"
    if [ ! -f $lock_file ]; then return 0; fi
    local lock_pid=`cat $lock_file`
    if [[ -z $lock_pid || $lock_pid != "$$" ]];then return 0; fi
    rm -f $lock_file > /dev/null 2>&1
    return 0
}

# check_pkg
function check_pkg()
{
    local pkg_path=$1
    if [[ ! -d $pkg_path || ! -f $pkg_path/init.xml || ! -f $pkg_path/install.sh || ! -d $pkg_path/admin || ! -f $pkg_path/admin/data/version.ini || ! -f $pkg_path/admin/data/source.ini ]];then return 1; fi
    cat $pkg_path/init.xml | grep -q '^name='
    return $?
}

function check_upd_pkg()
{
    local upd_pkg=$1
    if [[ ! -d $upd_pkg || ! -f $upd_pkg/md5.conf || ! -f $upd_pkg/update.conf || ! -f $upd_pkg/update.sh ]];then
        return 1
    fi
    while read action file
    do
        if [[ "$atction" != "M" && "$atction" != "A" ]];then continue; fi
        if [[ ! -f $upd_pkg/$file ]];then return 1; fi
    done < $upd_pkg/update.conf
    return 0
}

function check_ip()
{
    echo $1 | grep -q "^[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}$"
    return $?
}

# exit_proc
function exit_proc()
{
    debug_log "$(basename $0) errno:$1 errmsg:\"$2\""
    if [[ $1 != '0' ]];then
        echo "%%result%%failed%%$2%%"
        echo "result_start_tag=0&result=failed&msg=$2&result_end_tag=0"
    else
        echo "%%result%%success%%$2%%"
        echo "result_start_tag=0&result=success&msg=$2&result_end_tag=0"
    fi
    exit $1
}

function get_config()
{
    if [ "$1" = "" ];then return 1;fi
    local dir_pre=$2
    local conf_file=$dir_pre/init.xml
    if [ ! -f $conf_file ];then return 1;fi

    local conf_value=$dir_pre/`date +%s%N`${RANDOM}.tmp

    ## Todo: load config
    local row1=`grep -n "^<$1>\$" $conf_file | awk -F: '{print $1}' | tail -n 1`
    local row2=`grep -n "^</$1>\$" $conf_file | awk -F: '{print $1}' | tail -n 1`

    if [ "$row1" = "" -o "$row2" = "" ];then
        return 1
    fi

    if [ $row1 -gt $row2 ];then
        return 1
    fi

    head -n `expr $row2 - 1` $conf_file | tail -n `expr $row2 - $row1 - 1 ` > $conf_value
    ret=$conf_value
    return 0
}

function run_config()
{
    get_config $1 $2
    if [ ! -f $ret ];then return ;fi
    if [[ $? -eq 0 && $ret ]];then
        . $ret
        rm -f $ret > /dev/null 2>&1
    fi
}

# test_connect
# 机器user password连接测试
function test_connect()
{
    local ip=$1
    local passuser=$2
    local password="$3"
    $cur_path/test_connect.sh "$ip" "$passuser" "$password" > /dev/null 2>&1
    local func_result=$?
    if [ $func_result -ne 0 ];then
        errno=3
        errmsg="ip:$ip user:$passuser password:$password"
        debug_log "$(basename $0):$FUNCNAME $errmsg"
        user_log "ERROR:机器不可达或用户名密码错误"
    fi
    return $func_result
}

function get_rsyncd_svr()
{
    rsyncd_conf="./rsyncd.conf"
    if [ -f "${rsyncd_conf}" ];then

       ip_num=`cat ${rsyncd_conf}|wc -l`
       line=$((RANDOM%${ip_num}+1))
       rsyncd_svr_ip=`sed -n ${line}p ${rsyncd_conf}`
    else
        errno=1
        errmsg="config file not exists"
        debug_log "$(basename $0):$FUNCNAME $errmsg"
        user_log "rsync配置文件不存在"
        return $errno
    fi
}
