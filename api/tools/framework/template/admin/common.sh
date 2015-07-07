#! /bin/sh

###file_ver=2.0.8

export PATH=$PATH:/usr/bin:/usr/sbin:/sbin:/bin:.

#需要两个参数1.更新哪个变量 2.其值变为什么
update_config()
{
    conf_file=${pkg_base_path}/init.xml
    if [ ! -f $conf_file ];then return;fi

    item=$1
    value=$2

    max_row=`grep -n "^</base_info>\$" $conf_file | awk -F: '{print $1}' | tail -n 1`
    if [ "$max_row" = "" ];then
        echo "Error:can not find item: base_info"
        return 1
    fi

    item_row=`grep -n "^${item}=" $conf_file | awk -F: '{print $1}' | head -n 1`
    if [ "$item_row" = "" ];then
        echo "Error:can not find item: $item"
        return 1
    fi

    rows=`wc -l $conf_file | awk '{print $1}'`
    if [ $item_row -ge $max_row ];then
        echo "Error:can not find item: $item"
        return 1
    fi

    tmp_file=${pkg_base_path}/`date +%s%N`${RANDOM}.tmp

    head -n $(( $item_row -1 )) $conf_file > $tmp_file
    echo "${item}=\"${value}\"" >> $tmp_file
    #tail -n $(( $rows - $item_row )) $conf_file >> $tmp_file
    sed "1,${item_row}d" $conf_file >> $tmp_file
    cp $tmp_file $conf_file
    rm $tmp_file
}

get_config()
{
    if [ "$1" = "" ];then return 1;fi

    conf_file=${pkg_base_path}/init.xml
    if [ ! -f $conf_file ];then return 1;fi

    conf_value="${pkg_base_path}/admin/data/tmp/${1}_`date +%s`_$RANDOM"
    
    ## Todo: load config
    row1=`grep -n "^<$1>\$" $conf_file | awk -F: '{print $1}' | tail -n 1`
    row2=`grep -n "^</$1>\$" $conf_file | awk -F: '{print $1}' | tail -n 1`
        
    if [ "$row1" = "" -o "$row2" = "" ];then
        return 1
    fi  
    
    if [ $row1 -gt $row2 ];then
        return 1
    fi  
        
    mkdir -p ${pkg_base_path}/admin/data/tmp/
    head -n `expr $row2 - 1` $conf_file | tail -n `expr $row2 - $row1 - 1` > $conf_value
    ret=$conf_value
    return 0
}

run_config()
{
    get_config $1
    resultcode=$?
    tmp_file=$ret
    if [ $resultcode -eq 0 ];then
        . $tmp_file
        rm $tmp_file > /dev/null 2>&1
    fi
}

#append log to default log device
#log()
#{
#    if [ "$1" = "" ];then
#        return
#    fi
#    
#    echo "[`date '+%Y%m%d %H:%M:%S'`] $*" >> $default_log 2>/dev/null
#}

#append log to appointed log device
#parameters:    $1 log device
#               $2 log message
log()
{
    if [ "$1" = "" ] || [ "$2" = "" ];then
        return
    fi
    log_file=$1
    shift
    msg=$*
    echo "[`date '+%Y%m%d %H:%M:%S'`] $*" 2>&1 |tee -a $log_file
}

report()
{
    if [ "$1" = "" ];then
        return
    fi

    expr "$2" : "[0-9]\{1,\}" >/dev/null
    if [ $? -eq 0 ];then
        tmp_port=$2
    else
        tmp_port=$rpt_port
    fi

    log $default_log "$1"
    /usr/local/agenttools/agent/agentRepStr $tmp_port "$1"
}
exit_proc()
{
    code=$1
    shift
    op=$1
    shift
    msg=$*
    echo "tag=start&operation=${op}&code=${code}&msg=${msg}&tag=end"
    echo $msg
    log $default_log "${msg}"
    exit $code

}

#return boot file path
get_boot_path()
{
    ret="#BOOT_PATH"
}

#get a setting from user
#parameters:    $1 setting name, required
#               $2 default value, optional
#return:            user input, get the value by variable "ret"
get_setting()
{
    default_value=$2
    if [ "$default_value" != "" ];then
        echo -n "Please input $1 [$2] : "
        read ret
        if [ -z $tmp ];then
            ret=$default_value
        fi
        return
    fi
    until [ "$ret" != "" ]
    do
        echo -n "Please input $1 : "
        read ret
    done
}

#create temp file
#parameters:  $1 directory path
#             $2 file prefix
create_tmp_file()
{
    if [ ! -d "${1}" ];then
        mkdir ${1}
        chgrp users ${1}
        chmod 775 ${1}
    fi
    ret=${1}/${2}`date +%Y%m%d%H%M%S%N`.$RANDOM
    while [ -f $ret ]
    do
       sleep 1
       ret=${1}/${2}`date +%Y%m%d%H%M%S%N`.$RANDOM
    done
    touch $ret
    echo $ret
}

#backup file
#parameters:  $1 backup directory path
#             $2 file path
backup_file()
{
    if [ ! -f "$2" ];then return 1;fi

    create_tmp_file $1 `basename $2`.bak > /dev/null
    cp $2 $ret 
}

#convert size to bytes
to_bytes()
{
    size_tmp=`echo "$1" | sed -e "s:[^0-9kKmMgG]::g"`
    if [ "$size_tmp" == "" ];then
        ret=0
        echo $ret
        return
    fi

    expr "$size_tmp" : "[0-9]\{1,\}[kKmMgG]\{0,1\}$" > /dev/null
    if [ $? -ne 0 ];then
        ret=0
        echo $ret
        return
    fi

    size_unit=`echo $size_tmp | sed -e "s:[0-9]::g"`
    size_value=`echo $size_tmp | sed -e "s:[^0-9]::g"`
    if [ "$size_unit" = "k" -o "$size_unit" = "K" ];then
        ret=`expr $size_value '*' 1024`
    elif [ "$size_unit" = "m" -o "$size_unit" = "M" ];then
        ret=`expr $size_value '*' 1024 '*' 1024`
    elif [ "$size_unit" = "g" -o "$size_unit" = "G" ];then
        ret=`expr $size_value '*' 1024 '*' 1024 '*' 1024`
    else
        ret=$size_value
    fi
}

#get app number 
get_app_num()
{
    numbers=`echo $app_name | sed -e "s:[ \t]:\n:g" | grep "^$1[:$]" | awk -F: '{print $2}'`
    num_nim=`echo $numbers|awk -F, '{print $1}'`
    num_max=`echo $numbers|awk -F, '{print $2}'`

    if [ "${num_nim}" = "" ];then
        num_min=1
    fi

    if [ "${num_max}" = "" ];then
        num_max=999999999
    fi
    
}

#check port
check_port()
{
    nc_cmd="/usr/bin/nc"
    if [ ! -f $nc_cmd ];then
        nc_cmd="/usr/bin/netcat"
    fi

    $nc_cmd -zn -w4 $1 $2
    if [ $? -ne 0 ];then
        for (( i=0 ; i<5 ; i++ ))
        do
            $nc_cmd -zn -w4 $1 $2
            if [ $? -eq 0 ];then return 0;fi
            sleep 1
        done
        #check VIP again
        if [ "$vip" != "" ];then
            for (( i=0 ; i<5 ; i++ ))
            do
                $nc_cmd -zn -w4 $vip $2
                if [ $? -eq 0 ];then return 0;fi
                sleep 1
            done
        fi
        err_port="$err_port $p"
        return 1 
    fi
    return 0
}

#check process
check_process()
{
    get_app_num $1
    app=`echo $1 | awk -F: '{print $1}'`
    #for custom binary app 
	num=`ps -f -C $app | fgrep -w $app | wc -l`
    #for script app
    if [[ ${num} -eq 0 ]];then
        num=`pgrep -xf '^((\S*/)?(perl|python|php|sh|bash)\s+)?(\S+/)?'$app'($|\s+.+$)'|wc -l`
    fi
    #for java app
    if [[ ${num} -eq 0 ]];then
        num=`pgrep -xf '^java\s+(.*)\s+'$app'($|\s+.+$)'|wc -l`
    fi 
    #check the app num
    if [ $num -lt ${num_min} -o $num -gt ${num_max} ];then
        err_app="$err_app $app"
        return 1
    fi
    return 0
}
#check if application is ok
check_app()
{
    if [ ! -f $runing_file ];then
        return 0
    fi

    if [ "$ip_type" = "0" ];then
        bind_ip=$ip_inner
    elif [ "$ip_type" = "1" ];then
        bind_ip=$ip_outer
    elif [ "$ip_type" = "2" ];then
        bind_ip="0.0.0.0"
    elif [ "$ip_type" = "3" ];then
        bind_ip=$vip
    elif [ "$ip_type" = "4" ];then
        bind_ip=127.0.0.1
    fi 
 
    ##todo: add application checking statement here
    err_app=""
    err_port=""

    run_config "monitor"

}

run_config "base_info"

#install path
if [ "$install_base" = "" ];then
    install_base="/usr/local/services"
fi

if [ "$install_path" = "" ];then
    if [ -z "$version" ]
    then
        install_path="$install_base/$name"
    else
        install_path="$install_base/$name-$version"
    fi
fi 

if [ "$log_dir" = "" ];then
    log_dir="$install_path/log"
fi
default_log="$install_path/log/default.log"

#link dir
link_dir="$HOME/services"
#backup path
backup_path="/data/backup"

#old version
if [ -L $link_dir/$name ];then
    old_ver=`ls -l $link_dir/$name | awk -F\> '{print $2}'`
else
    old_ver=""
fi

#app count
app_count=`echo "$app_name" | awk '{print NF}'`


#crontab configure
cron_conf="$install_path/admin/data/crontab.conf"
#clear disk configure
g_clear_conf="$install_path/admin/data/clear.conf"
#running file
runing_file="$install_path/admin/data/runing.tmp"

#md5sum result
md5_result="$install_path/admin/data/md5_result.lst"
#md5sum failed
md5_failed="$install_path/admin/data/md5_failed.lst"

ip_outer=`/sbin/ifconfig eth0 2>/dev/null |grep "inet addr:"|awk -F ":" '{ print $2 }'|awk '{ print $1 }'`
ip_inner=`/sbin/ifconfig eth1 2>/dev/null |grep "inet addr:"|awk -F ":" '{ print $2 }'|awk '{ print $1 }'`
if [ "${ip_inner}x" = "x" ];then
   #ip_inner=`/sbin/ip route|grep 'eth1'|grep 'scope link  src'|awk '{print $9}'|head -n 1`
   ip_inner=`/sbin/ip route|egrep 'src 172\.|src 10\.'|awk '{print $9}'|head -n 1`
fi
vip=`/sbin/ifconfig eth1:1 2>/dev/null |grep "inet addr:"|awk -F ":" '{ print $2 }'|awk '{ print $1 }'`

