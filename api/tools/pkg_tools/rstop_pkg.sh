#!/bin/bash
ip_inner=`/sbin/ifconfig eth1 2>/dev/null |grep "inet addr:"|awk -F ":" '{ print $2 }'|awk '{ print $1 }'`

cur_path=$(dirname $(which $0))

function check_result()
{
    cat $install_path/init.xml | grep '^is_shell' | grep -q "true"
    if [ $? -eq 0 ];then
        return 0
    fi
	
    local app_name=`cat $install_path/init.xml | grep '^app_name=' | sed -e 's/^app_name=//' -e 's/"//g'`
    # 检查进程
    for app in `echo "$app_name" | sed -e "s/:[^ ]*//g"`
    do
        ps -f -C $app | fgrep -w $app >/dev/null
        if [ $? -ne 0 ];then
			echo "${app} stop succ"
            continue
        fi
        
        findApp='true'
        #第一次等待
        for ((i=0;i<4;i++))
        do
			sleep 1 

			ps -f -C $app | fgrep -w $app >/dev/null
			if [ $? -ne 0 ];then
				findApp='false'
				break 
			fi
        done

        if [ "$findApp" = "false" ];then
			echo "${app} stop succ"
            continue; 
        fi
        
		echo "${app} stop fail"
        err_count=$(( $err_count + 1 ))
        if [ "x${error_app_list}" = "x" ];then
            error_app_list="${app}"
        else
            error_app_list="${error_app_list},${app}"
        fi
    done
}

install_path=$1
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

#操作对象：operSelf：自己,operParent：框架
oper_target=$2
if [ "$oper_target" = "operParent" ];then
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
if [ `whoami` = $user_name ];then
    $install_path/admin/stop.sh all
else
    su $user_name -c "$install_path/admin/stop.sh all"
fi

check_result
if [ "x${error_app_list}" = "x" ];then
    result="success"
	echo "result%%${result}%%${ip_inner}%%"
else
    result="${error_app_list} stop fail"
	echo "result%%failed%%${ip_inner}%%${result}%%"
fi




