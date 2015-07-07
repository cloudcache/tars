#!/bin/bash
ip_inner=`/sbin/ifconfig eth1 2>/dev/null |grep "inet addr:"|awk -F ":" '{ print $2 }'|awk '{ print $1 }'`

cur_path=$(dirname $(which $0))

install_path=$1
#操作对象：operSelf：自己,operParent：框架, operParentGraceful: restart framework graceful
oper_target=$2
if [ -z $install_path ];then
    echo "result%%failed%%${ip_inner}%%missing install_path"
    exit 1
fi

if [ ! -d $install_path ];then
    echo "result%%failed%%${ip_inner}%%install_path:$install_path not existes"
    exit 1
fi

if [ ! -f $install_path/init.xml ];then
    echo "result%%failed%%${ip_inner}%%init.xml not existes"
    exit 1
fi

user_name=$(cat $install_path/init.xml | grep '^user=\"*\"' | head -n 1 | sed -e 's/user=//g' -e 's/^\"//g' -e 's/\"$//g')
log_file=/tmp/rrestart_pkg.pkg.$$
#su $user_name -c "$install_path/admin/restart.sh all > ${log_file}"

if [ "$oper_target" = "operParent" -o "$oper_target" = "operParentGraceful" ];then
    install_base=$(cat $install_path/init.xml | grep '^install_base=\"*\"' | head -n 1 | sed -e 's/install_base=//g' -e 's/^\"//g' -e 's/\"$//g')
    if [ "$install_base" != "" ];then
        if [ ! -d $install_base ];then
            install_path="$install_path/../../"
        else
            install_path="$install_base"
        fi
    else
        install_path="$install_path/../../"
    fi
fi

if [ "$oper_target" = "operParentGraceful" ];then
    $cur_path/rrestart_graceful.sh "${install_path}" > ${log_file}
    stop_result=`cat  ${log_file} | grep "^result%%" | tail -n 1 | awk -F"%%" '{print $2}'`
    stop_result="stop:${stop_result}"
    start_result="start:${stop_result}"
else
    $cur_path/rstop_pkg.sh "${install_path}" > ${log_file}
    stop_result=`cat  ${log_file} | grep "^result%%" | tail -n 1 | awk -F"%%" '{print $2}'`
    stop_result="stop:${stop_result}"

    sleep 1
    $cur_path/rstart_pkg.sh "${install_path}" >> ${log_file}
    start_result=`cat  ${log_file} | grep "result%%" | tail -n 1 | awk -F"%%" '{print $2}'`
    start_result="start:${start_result}"
fi

result="success"
if [ "$start_result" != "start:success" -o "$stop_result" != "stop:success" ];then
    result="${stop_result};${start_result}"
    if [ -f ${log_file} ];then
        cat ${log_file}
        rm ${log_file}
    fi
else
    if [ -f ${log_file} ];then
        rm ${log_file}
    fi
fi

echo "result%%${result}%%${ip_inner}%%${result}%%"


