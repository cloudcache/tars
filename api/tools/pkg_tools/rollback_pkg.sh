#!/bin/bash
ip_inner=`/sbin/ifconfig eth1 2>/dev/null |grep "inet addr:"|awk -F ":" '{ print $2 }'|awk '{ print $1 }'`

cur_path=$(dirname $(which $0))

install_path=$1
update_pkg=$2
cur_version=$3

function compVersion() 
{ 
    v1=$1 
    v2=$2
    old_IFS=$IFS
    IFS='.'
    v1_arr=($v1)
    v2_arr=($v2)
    IFS=$old_IFS
    i=0
    for sub_version in ${v1_arr[*]}
    do
        if [ $sub_version -gt ${v2_arr[$i]} ];then
            return 1
        elif [ $sub_version -lt ${v2_arr[$i]} ];then
            return 2 
        fi 
        i=$(($i+1))
    done
    return 0
}
function getUpdatePkgPath()
{
    install_path=$1
    req_version=$2
    version_list=`cat $install_path/admin/data/version.ini |awk '{print $3}'|sed -e "s:[^\.0-9]::g"|uniq`
    last_version=`echo "$version_list"|tail -n 1`
    reverse_list=`echo "$version_list"|tac`
    if [ "${req_version}x" != "${last_version}x" ];then
        echo "cur version is ${last_version} ,not ${req_version}"
        return 2
    fi

    for sub_ver in $reverse_list
    do
        compVersion $last_version $sub_ver
        ret=$?
        if [ $ret -eq 1 ];then
            pre_ver=$sub_ver
            break
        fi
    done

    pkg_name=`cat $install_path/admin/data/source.ini |awk -F '/' '{print $3}'|head -n 1`
    user_t=`egrep "^user=" $install_path/init.xml`
    eval $user_t
    if [ $user = "root" ];then
        run_path="/root/install/"
    else
        run_path="/home/${user}/install/"
    fi
    upd_pkg_name_new="${pkg_name}_update-${pre_ver}-${last_version}-${pkg_name}"
    upd_pkg_name_old="${pkg_name}_update-${pre_ver}_${last_version}-${pkg_name}"
    upd_pkg_name_v3="${pkg_name}-update-${pre_ver}-${last_version}"
    if [ -d "${run_path}${upd_pkg_name_new}" ];then
        upd_pkg_name=${upd_pkg_name_new}
    elif [ -d "${run_path}${upd_pkg_name_new}" ];then
        upd_pkg_name=${upd_pkg_name_old}
    else
        upd_pkg_name=${upd_pkg_name_v3}
    fi    
    echo $upd_pkg_name
}


if [ -z $update_pkg ];then
    echo "result%%failed%%${ip_inner}%%missing update_pkg_path"
    exit 1
fi

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

update_pkg=`getUpdatePkgPath "${install_path}" "${cur_version}"`
if [ $? -ne 0 ];then
    echo $?
    echo "update package not exists";
fi


user_name=$(cat $install_path/init.xml | grep '^user=\"*\"' | head -n 1 | sed -e 's/user=//g' -e 's/^\"//g' -e 's/\"$//g')
log_file=/tmp/rrollback_pkg.pkg.$$
if [ `whoami` = $user_name ];then
    ~/install/$update_pkg/update.sh rollback install_path=$install_path > ${log_file}
else
    su $user_name -c "~/install/$update_pkg/update.sh rollback install_path=$install_path > ${log_file}"
fi

result="success"
if [ -f ${log_file} ];then
    cat ${log_file} 
    cat ${log_file} | grep  "rollback" |grep -q "success"
    if [ $? -ne 0 ];then result="failed"; fi
    #rm ${log_file}
fi
#扫描包日志
#./pkgScript/scanLog.sh

echo "result%%${result}%%${ip_inner}%%${result}"


