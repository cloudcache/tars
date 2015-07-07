#!/bin/bash

##########################################################
#
#
#
#
#
###########################################################
# PATH="/usr/bin:.:$PATH"
# export $PATH
export PATH=".:"$PATH

cur_path=$(dirname $(which $0))
cd $cur_path


ip=$1
src=$2
dst=$3
passuser=$4
password=$5
function get_root_passwd()
{
    local ip=$1
    server=`awk -F '=' '/\[ApiServer\]/{a=1}a==1&&$1~/server/{print $2;exit}' ../../conf/Conf.ini`
    port=`awk -F '=' '/\[ApiServer\]/{a=1}a==1&&$1~/port/{print $2;exit}' ../../conf/Conf.ini`
    host=`awk -F '=' '/\[ApiServer\]/{a=1}a==1&&$1~/hostname/{print $2;exit}' ../../conf/Conf.ini`
    ret=`curl -s -H "Host:$host" "http://$server:$port/query/device_password?deviceId=$ip"`
    echo "$ret"|awk -F'"' '{print $4}'
    return 0

}

function exit_proc()
{
    if [[ $1 != '0' ]];then
        echo "result%%failed%%$2"
    else
        echo "result%%success%%$2"
    fi
    exit $1
}

if [[ -z $passuser || -z $password ]];then
    passuser=root
    password=`get_root_passwd $ip`
fi
echo $password;
if [ -z $password ];then
    errno=1
    errmsg='无root密码'
    exit_proc "$errno" "$errmsg"
fi

if [ ! -d $dst ];then
    errno=3
    errmsg="目标目录$dst不存在"
    exit_proc "$errno" "$errmsg"
fi

if [ -z "$src" ];then
    errno=4
    errmsg="未配置源文件"
    exit_proc "$errno" "$errmsg"
fi

$cur_path/download.exp "$passuser" "$ip" "$password" "$src" "$dst"
exit_code=$?
if [ $exit_code -ne 0 ];then
    errno=5
    errmsg="拷贝过程错误,请重试"
    exit_proc "$errno" "$errmsg"
fi

errno=0
errmsg="$dst"
exit_proc "$errno" "$errmsg"
