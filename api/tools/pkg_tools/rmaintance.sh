#!/bin/bash
cur_path=$(dirname $(which $0))
cd $cur_path
install_path=$1
operation=$2
param=$3
#start, stop, restart中参数为：是否是启动框架. add by tomxuan
case $operation in
start)
    $cur_path/rstart_pkg.sh $install_path $param
    ;;
stop)
    $cur_path/rstop_pkg.sh $install_path $param
    ;;
restart)
    $cur_path/rrestart_pkg.sh $install_path $param
    ;;
uninstall)
    $cur_path/runinstall_pkg.sh $install_path $param
    ;;
rollback)
    $cur_path/rrollback_pkg.sh $install_path $param
esac
