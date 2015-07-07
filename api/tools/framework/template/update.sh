#! /bin/sh

# 增量升级脚本
export PATH=$PATH:./
cur_path=$(dirname $(which $0))
pkg_base_path=${cur_path}

log_file=""
install_path=""
UPDATE_OK="ok"
version_file=""

is_restart="${#}"

backup_path="/data/backup"


md5_conf=$cur_path/md5.conf

load_lib()
{
    local common_file=$install_path/admin/common.sh
    if [ -f $common_file ];then
        . $common_file
    else
        echo "load common lib failed ${common_file}"
        errmsg="load common lib failed ${common_file}"
        rpt_info
        exit 1
        
    fi
}

#递归复制目录或者文件(使用install)
install_cp()
{
    local source=$1
    local dist=$2
    local num=$3
    ls -l --time-style=long-iso $source > ./file_list.$$.$3
    while read mode linkCount user group size day time file tail
    do
        if [ ${#mode} -ne 10 ];then
            continue;
        fi
        echo $mode | grep -q ^d
        if [ $? -eq 0 ];then
            mkdir -p $dist/$file
            local num_mode=`get_num_mode $mode`
            chmod $num_mode $dist/$file
            install_cp $source/$file $dist/$file "${3}1"
        else
            echo $mode | grep -q ^-
            if [ $? -eq 0 ];then
                local num_mode=`get_num_mode $mode`
                install -p -m $num_mode $source/$file $dist/
            else
                cp -ar $source/$file $dist/
            fi
        fi
    done < ./file_list.$$.$3
    rm ./file_list.$$.$3
}

#mode转化为0644格式
get_num_mode()
{
    local str_mode=$1
    local mode='0'
    local temp=0
    local j=0
    local i=0
    for((i=1;i<10;i++))
    do
        s=${str_mode:$i:1}
        if [ "$s" == "r" ];then
            (( temp+=4 ))
        elif [ "$s" == "w" ];then
            (( temp+=2 ))
        elif [ "$s" == "x" ];then
            (( temp+=1 ))
        fi
        (( j+=1 ))
        if [ $j -eq 3 ];then
            mode="${mode}$temp"
            temp=0
            j=0
        fi
    done
    echo $mode
}

#加载update.conf文件中的配置
load_conf()
{
    local tmp_file=`create_tmp_file $cur_path`
    local num=`grep -n "##End" $cur_path/update.conf | head -n 1 | awk -F: '{print $1}'`
    if [ "$num" = "" ];then
        echo "load update.conf failed"
        echo -e "AUTOBAT\t${ip_inner}\t1 load update.conf failed"
        errmsg="load update.conf failed"
        rpt_info
        exit 1
    fi

    head -n $num $cur_path/update.conf > $tmp_file
    . $tmp_file
    rm $tmp_file

    if [ -f $cur_path/package.conf ];then
        . $cur_path/package.conf
    fi
}


pack_stop()
{
    app_to_stop=$1
    if [ "${app_to_stop}" = "" ];then
        app_to_stop="all"
    fi
    local waitTime=2
    local maxWaitTime=10
    log "Ready to stop..."
    tmp=`pwd`
    cd $install_path/admin
    ./stop.sh $app_to_stop
    cd $tmp
    if [ "${app_to_stop}" = "all" ];then
        sleep 2
    fi
    local app_name=""
    if [ "${app_to_stop}" = "all" ];then
        app_name=`cat $install_path/init.xml | grep '^app_name=' | sed -e 's/^app_name=//' -e 's/"//g'`
    else
        app_name=${app_to_stop}
    fi

    # 检查进程
    for app in `echo "$app_name" | sed -e "s/:[^ ]*//g"`
    do
        err_app=''
        check_process $app
        err_app=`echo $err_app`
        if [ "${err_app}" = "${app}" ];then
            log "$app stop success"
            continue
        fi
        
        local findApp='true'
        #第一次等待
        for ((i=0;i<4;i++))
        do
            if [ $waitTime -lt $maxWaitTime ];then
                waitTime=$(( $waitTime + 2 ))
                sleep 1 
                err_app=''
                check_process $app
                err_app=`echo $err_app`
                if [ "${err_app}" = "${app}" ];then
                    log "$app stop success"
                    findApp='false'
                    break 
                fi
            fi
        done

        if [ "$findApp" = "false" ];then
            continue; 
        fi
        
        err_count=$(( $err_count + 1 ))
        if [ "x${error_app_list}" = "x" ];then
            error_app_list="${app}"
        else
            error_app_list="${error_app_list},${app}"
        fi
        log "$app stop failed"
    done
    if [ "x${error_app_list}" != 'x' ];then
        error_app_list="${error_app_list} stop failed"
    fi
}

pack_start()
{
    app_to_start=$1
    if [ "${app_to_start}" = "" ];then
        app_to_start="all"
    fi
    local waitTime=2
    local maxWaitTime=10
    log "Ready to start..."
    tmp=`pwd`
    cd $install_path/admin
    ./start.sh $app_to_start force
    cd $tmp
	
    cat $install_path/init.xml | grep '^is_shell' | grep -q "true"
    if [ $? -eq 0 ];then
        return 0
    fi
	
    sleep 2

    if [ "${app_to_start}" = "all" ];then
        app_name=`cat $install_path/init.xml | grep '^app_name=' | sed -e 's/^app_name=//' -e 's/"//g'`
    else
        app_name=$app_to_start
    fi
    # 检查进程
    for app in `echo "$app_name" | sed -e "s/:[^ ]*//g"`
    do
        err_app=''
        check_process $app
        err_app=`echo $err_app`
        if [ "${err_app}" = "" ];then
            log "$app start success"
            continue
        fi

        findApp='false'
        #第一次等待
        for ((i=0;i<4;i++))
        do
            if [ $waitTime -lt $maxWaitTime ];then
                waitTime=$(( $waitTime + 2 ))
                sleep 2 

                err_app=''
                check_process $app
                err_app=`echo $err_app`
                if [ "${err_app}" = "" ];then
                    log "$app start success"
                    findApp='true'
                    break 
                fi
            fi
        done

        if [ "$findApp" = "true" ];then
            continue; 
        fi

        err_count=$(( $err_count + 1 ))
        if [ "x${error_app_list}" = "x" ];then
            error_app_list="${app}"
        else
            error_app_list="${error_app_list},${app}"
        fi
        log "$app start failed"
    done
    if [ "x${error_app_list}" != 'x' ];then
        error_app_list="${error_app_list} start failed"
    fi
    
}

pack_restart()
{
    if [ "${restart_app}" = "" ];then
        restart_app="all"
    fi
    restart_app=`echo $restart_app|sed 's/;/ /g'`
    for app_to_restart in $restart_app
    do
        pack_stop $app_to_restart
        pack_start $app_to_restart
    done
}

init()
{
    #检查运行用户
    if [ "$user" != "`whoami`" ];then
        err_msg="Only $user can run this script"
        return 1
    fi

    if [ ! -d "$install_path" ];then
        #查找程序安装目录
        install_path_tmp="`grep "^${user}:" /etc/passwd | awk -F: '{print $6}'`/services/${name}"

        if [ ! -L "$install_path_tmp" ];then
           err_msg="can not find package dir link"
           return 1
        fi
   
        install_path=`ls -l $install_path_tmp | tail -n 1 | awk '{print $NF}'`
    fi

    if [ ! -d $install_path/admin ];then
       err_msg="can not find package dir"
       return 1
    fi

    version_file=$install_path/admin/data/version.ini

    now_ver=`cat $version_file 2>/dev/null | tail -n 1 | awk '{print $NF}'`
    if [ "$now_ver" = "" ];then
        now_ver=$from
    fi
    ver_now=$to
    ver_old=$from
    if [ "$now_ver" = "$to" -a "$1" = "rollback" ];then
        return 0
    fi

    if [ "$now_ver" != "" -a "$now_ver" != "$from" ];then
       if [ "$now_ver" = "$to" -a "$is_restart" = "restart" ];then
           pack_restart
       else
           err_msg="update $from-$to not match current ver:$now_ver"
           log $err_msg
       fi
       return 1
    fi

    log_file="$install_path/log/update.log"

    if [ -d "$cur_path/backup_old/" ];then
        backup_path=$cur_path/backup_old
        return 0
    fi

    backup_path="$backup_path/${name}.`date +%Y%m%d%H%M%S`"
    #生成备份目录
    if [ ! -d /data/backup ];then
       mkdir -p /data/backup
       chown `whoami`.users /data/backup
       chmod 775 /data/backup
    fi
    log "$now_ver-$to"
    log "$backup_path"
    mkdir -p $backup_path
    if [ $? -ne 0 ];then
        err_msg="can not create $backup_path,please check write privilege"
        return 1
    fi
    rm $cur_path/backup_old 2>/dev/null
    ln -s $backup_path $cur_path/backup_old

    return 0
}

#更新init.xml配置文件
update_init_xml()
{
    if [ -f "$install_path/init.xml" -a -f "$cur_path/init.xml" ];then
        if [ "$update_start_stop" = "false" ];then
            #首先获取老的appname
            old_app_name=`grep "^app_name=\"" $cur_path/init.xml | head -n 1 | sed -e 's/app_name=//' -e 's/\"//g'`
            old_app_name_arr=(${old_app_name})
            old_size=${#old_app_name_arr[@]}
            
            new_app_name=`grep "^app_name=\"" $install_path/init.xml | head -n 1 | sed -e 's/app_name=//' -e 's/\"//g'`
            new_app_name_arr=(${new_app_name})
            new_size=${#new_app_name_arr[@]}
            if [ $old_size = $new_size ];then
                for((i = 0; i <old_size; ++i))
                do
                    o_app_name=${old_app_name_arr[i]}
                    o_app_name=`echo $o_app_name | awk -F":" '{print $1}'`
                    
                    n_app_name=${new_app_name_arr[i]}
                    n_app_name=`echo $n_app_name | awk -F":" '{print $1}'`
                    
                    log "update: from $o_app_name to $n_app_name in tag start"
                    cat $cur_path/init.xml | sed "/<start>/,/<\/start>/ s/${o_app_name}/${n_app_name}/" > $cur_path/init.xml.$$.xx
                    cat $cur_path/init.xml.$$.xx | grep -q "app_name"
                    if [ $? -ne 0 ]; then
                        rm $cur_path/init.xml.$$.xx > /dev/null 2>&1
                        log "update: from $o_app_name to $n_app_name in tag start failed"
                    else
                        mv $cur_path/init.xml.$$.xx $cur_path/init.xml
                    fi
                    
                    log "update: from $o_app_name to $n_app_name in tag stop"
                    cat $cur_path/init.xml | sed "/<stop>/,/<\/stop>/ s/${o_app_name}/${n_app_name}/" > $cur_path/init.xml.$$.yy
                    cat $cur_path/init.xml.$$.yy | grep -q "app_name"
                    if [ $? -ne 0 ]; then
                        rm $cur_path/init.xml.$$.yy > /dev/null 2>&1
                        log "update: from $o_app_name to $n_app_name in tag stop failed"
                    else
                        mv $cur_path/init.xml.$$.yy $cur_path/init.xml
                    fi
                done
            fi
        fi
        old_name=`grep "^name=\"" $install_path/init.xml | head -n 1 | sed -e 's/name=//' -e 's/\"//g'`
        if [ "$old_name" != "" ];then
            update_config "name" "$old_name"
        fi
        p_version=`grep "^version=\"" $install_path/init.xml | head -n 1 | sed -e 's/version=//' -e 's/\"//g'`
        if [ "$p_version" ];then
            update_config 'version' "$p_version"
        fi
        old_app=`grep "^app_name=\"" $install_path/init.xml | head -n 1 | sed -e 's/app_name=//' -e 's/\"//g'`
        if [ "$update_appname" != "true" ];then
            update_config "app_name" "$old_app"
        fi
        if [ "$update_port" = "false" ];then
            old_port=`grep "^port=\"" $install_path/init.xml | head -n 1 | sed -e 's/port=//' -e 's/\"//g'`
            update_config "port" "$old_port"
        fi
        update_config "user" "$user"
        update_config "install_path" "$install_path"
    fi
}

#report package infomation
rpt_info()
{
	print_result "${errmsg}"
}


print_result()
{
	local errmsg=$1
	echo
	echo "update%%${ip_inner}%%${name}%%${install_path}%%${op}%%${errmsg}%%${error_app_list}%%${ver_old}%%${ver_now}%%"
	echo
    msg="ip=${ip_inner}&name=${name}&install_path=${install_path}&op=${op}&result=${errmsg}&err_app=${error_app_list}&ver_old=${ver_old}&ver_new=${ver_now}"
    exit_proc "${code}" "${op}" "${msg}"
}   

#substitute place holders in the files
substitute()
{
    if [ $# -lt 1 ];then return 1;fi
    if [ "$1" = "/plugins/pkgMonitor/patch/crontab.sh" ];then return 0;fi
    subs_dst="$cur_path/$1"
    subs_tmp=`create_tmp_file $cur_path`

    #check text file
    echo "`/usr/bin/file -b $subs_dst | awk -F, '{print $1}'`" | grep " text" > /dev/null
    if [ $? -ne 0 ];then
        return 0
    fi

    sed -e "s:#INSTALL_PATH:$install_path:g" \
        -e "s:#INSTALL_BASE:$install_base:g" \
        -e "s:#USER:$user:g" \
        -e "s:#IP_INNER:$ip_inner:g" \
        -e "s:#IP_OUTER:$ip_outer:g" \
        $subs_dst > $subs_tmp
    if [ $? -ne 0 ];then return 1;fi

    mv $subs_tmp "$subs_dst"
    if [ $? -ne 0 ];then return 1;fi

    #更新替换后的md5sum到md5.conf
    local md5_old=`grep "^$1" $md5_conf | tail -n 1 | awk '{print $3}'`
    if [ "x$md5_old" = "x" ];then return 0; fi
    local md5_new=`md5sum $subs_dst | awk '{print $1}'`
    sed -e "s;${md5_old};$md5_new;" $md5_conf > $md5_conf.$$
    mv $md5_conf.$$ $md5_conf

    return 0
}

#备份安装目录下的文件
backup()
{
    if [ $# -lt 1 ];then return 0;fi

    if [ ! -f "$install_path/$1" -a ! -d "$install_path/$1" ];then
        return 0
    fi

    if [ -f "$backup_path/$1" -o -d "$backup_path/$1" ];then
        return 0
    fi

    log "Backup file:$1"
    temp_bak_path=`dirname $backup_path/$1`
    if [ ! -d $temp_bak_path ];then
        mkdir -p $temp_bak_path
    fi
    cp -ar $install_path/$1 $temp_bak_path/
    if [ $? -ne 0 ];then
        log "Backup $1 failed"
        return 1
    fi
    return 0
}

update_file()
{
    #add dir
    if [ -d $cur_path/$2  -a "$action" = "A" ];then
        mkdir -p $install_path/$2
        if [ -d $install_path/$2 ];then
            log "Add directory success: $2"
        else
            log "Add directory failed: $2"
            UPDATE_OK="false"
        fi
        return 
    fi

    #delete dir
    if [ -d $install_path/$2  -a "$action" = "D" ];then
        rm -rf $install_path/$2
        if [ ! -d $install_path/$2 ];then
            log "Delete directory success: $2"
        else
            log "Delete directory failed: $2"
            UPDATE_OK="false"
        fi
        return
    fi

    #add,modify file
    if [ -f "$cur_path/$2" -a \( "$action" = "M" -o "$action" = "A" \) ];then
        mkdir -p `dirname $install_path/$2`
        if [ "$install_cp" == "true" ];then
            log "install file $cur_path/$2 ..."
        #if [ "" == "" ];then
            str_mode=`ls -l $cur_path/$2 | awk '{print $1}'`
            num_mode=`get_num_mode $str_mode`
            install -p -m $num_mode $cur_path/$2 $install_path/$2 2>/dev/null
        else 
            cp -af $cur_path/$2 $install_path/$2 2>/dev/null
        fi
        md5_tmp1=`md5sum $cur_path/$2 | awk '{print $1}'`
        md5_tmp2=`md5sum $install_path/$2 | awk '{print $1}'`
        if [ "$md5_tmp1" = "$md5_tmp2" ];then
            log "Update file success: $2"
        else
            log "Update file failed: $2"
            UPDATE_OK="false"
        fi
        chmod -R 755 $install_path/$2
        return
    fi
    
    #delete file
    if [ -f "$install_path/$2" -a "$action" = "D" ];then
        rm -f $install_path/$2 2>/dev/null
        if [ ! -f $install_path/$2 ];then
            log "Delete file success: $2"
        else
            log "Delete file failed: $2"
            UPDATE_OK="false"
        fi
        return
    fi 
}

#升级
update()
{
    ver_now=$to
    ver_old=$from
    echo "" >> $cur_path/update.conf
    while read action file tail
    do
		#备份
        backup $file
        if [ $? -ne 0 ];then
            UPDATE_OK="false"
            continue
        fi
		#升级具体文件
        update_file $action $file
		#这里就应该判断,if [ "$UPDATE_OK" = "false" ];then
		if [ "$UPDATE_OK" = "false" ];then
            local msg="Update $action $file failed"
			log "${msg}" 
			echo  "${msg}"
			break
		else
            local msg="Update $action $file success"
			log "${msg}"
			echo  "${msg}"
			continue
		fi		
    done < $cur_path/update.conf
}
check_dest_md5()
{
    local file=$1
    if [ ! -f $install_path/$file ];then return 1; fi
    file=`echo $file | sed -e 's;^/;;'`
    local dst_md5_result="$install_path/admin/data/md5_result.lst"
    if [ ! -f $dst_md5_result ];then return 1; fi
    local md5_expectd=`cat $dst_md5_result | grep "$file$" | head -n 1 | awk '{print $1}'`
    local md5_dest=`md5sum $install_path/$file | awk '{print $1}'`
    test "$md5_expectd" = "$md5_dest"
    return $?
}

check_local_md5()
{
    local file=$1
    if [ $file = "/init.xml" ];then return 0; fi
    local md5_old=`grep "^$file" $md5_conf | head -n 1 | awk '{print $2}'`
    local md5_cur=`md5sum $install_path/$file | awk '{print $1}'`
    test "$md5_old" = "$md5_cur"
    return $?
}
pre_check()
{
    check_ok="OK"
    local error_list=""
    while read action file tail
    do
        if [ -f "$cur_path/$file" -a "$action" = "M" -a "$file" != "/init.xml" ];then
            md5_old=`grep "^$file" $md5_conf | head -n 1 | awk '{print $2}'`
            md5_new=`grep "^$file" $md5_conf | tail -n 1 | awk '{print $3}'`

            md5_src=`md5sum $cur_path/$file | awk '{print $1}'`
            md5_dst=`md5sum $install_path/$file | awk '{print $1}'`
            if [ "$md5_src" != "$md5_new" ];then
                log "$file transmit failed"
                check_ok="Fail"
                if [ -z "$error_list" ];then
                    error_list="$file"
                else
                    error_list="$error_list,$file"
                fi
            fi  
            substitute $file
            if [ $check_ok = "Fail" ];then continue; fi
            check_dest_md5 $file
            if [ $? -ne 0 ];then
                check_local_md5 $file
                if [ $? -ne 0 ];then
                    log "$file has changed,can not update"
                    if [ -z "$error_list" ];then
                        error_list="$file"
                    else
                         error_list="$error_list,$file"
                    fi
                    check_ok="Fail"
                fi
            fi

        fi
    done < $cur_path/update.conf
    if [ $check_ok = "Fail" ];then
        errmsg="$error_list"
    fi
}

substitute_all()
{
    while read action file tail
    do
        substitute $file
    done < $cur_path/update.conf
}

rollback()
{
    if [ ! -d $cur_path/backup_old/ ];then
        log "can not find backup file,rollback cancel"
        return 1
    fi

    ls $cur_path/backup_old/* 2>/dev/null
    if [ $? -ne 0 ];then return 0;fi
    pack_stop
    if [ "$install_cp" == "true" ];then
        log "install file.... "
    #if [ "" == "" ];then
        install_cp $cur_path/backup_old/ $install_path/
		ret=$?
    else
        #cp -ar $cur_path/backup_old/* $install_path/ 
        cp -arf $cur_path/backup_old/* $install_path/ 
		ret=$?
        if [ $ret -ne 0 ];then
            cp -dRrf $cur_path/backup_old/* $install_path/ 
		    ret=$?
        fi
    fi
    
	log "ret:$ret"

    return $ret
}

update_md5()
{
    tmp=`pwd`
    cd $install_path/admin
    ./md5sum.sh build
    cd $tmp
}


check_upload()
{
	echo "" >> $cur_path/update.conf
    while read action file tail
    do
		if [ "$action" = "M"  -o "$action" = "A" ];then
			if [ ! -e "$cur_path/$file" ];then
				errmsg="file $file upload failed"
				rpt_info
				exit 1			
			fi		
		fi
    done < $cur_path/update.conf
}

#-------------- Main Begin ----------------

#解决远程显示问题
echo ""
#加载update.conf文件中的配置

for((i=1;i<=$#;i++))
do
    arg=`echo ${!i}`
    echo $arg | grep -q '='
    if [ $? -ne 0 ];then continue; fi
    xname=`echo $arg|awk -F "=" '{print $1}'`
    xvalue=`echo $arg|sed "s/$xname=//"|tr -d "\r"`
    eval "$xname=\$xvalue"
done
load_lib
load_conf

op=update
init $*
if [ $? -ne 0 ];then
    log "$err_msg"
    errmsg="$err_msg"
    rpt_info
    exit 1
fi

chown -R $user:users $cur_path

#进行上传文件的检查
if [ "$1" != "rollback" ];then
	check_upload
fi

#执行前置脚本,升级前需要执行的操作
if [ -f "$cur_path/start.sh" ];then
    . $cur_path/start.sh
fi

if [ "x$stop" == "xtrue" ];then
    pack_stop
fi

if [ "$1" = "rollback" ];then
    op=rollback
    rollback
    if [ $? -eq 0 ];then
        log "Rollback sucess"
        echo "[`date "+%Y-%m-%d %H:%M:%S"`] $from" >> $version_file
        ver_now=$from
        ver_old=$to
        errmsg="success"
        update_md5
        pack_restart
        log "Rollback $to - $from"
        rpt_info
        exit 0
    else
        log "Rollback failed"
		if [ "x$stop" = "xtrue" ];then
			echo "Rollback failed, start now..."
			pack_start
		fi		
        exit 1
    fi
fi
substitute_all
update_init_xml
if [ "$1" != "force" ];then
    op=update
    pre_check
    if [ "$check_ok" != "OK" ];then
        log "file MD5 check failed, please use force update:$errmsg"
        errmsg="file MD5 check failed, please use force update:$errmsg"
		if [ "x$stop" = "xtrue" ];then
			pack_start
		fi
        rpt_info
        exit 1
    fi
else
    op=force
fi

#update_init_xml

log "Update start: $from - $to"
#升级
update

update_md5

if [ "x$restart" = "xtrue" -a "$UPDATE_OK" = "ok" ];then
    mkdir -p `dirname $version_file`
    echo "[`date "+%Y-%m-%d %H:%M:%S"`] $to" >> $version_file
    pack_restart
fi

if [ "$UPDATE_OK" = "ok" ];then
    mkdir -p `dirname $version_file`
    echo "[`date "+%Y-%m-%d %H:%M:%S"`] $to" >> $version_file
    if [ -f "$cur_path/finish.sh" ];then
        . $cur_path/finish.sh $install_path
    fi
    echo "Update success"
    errmsg="success"
    rpt_info
    exit 0
else
    if [ "x$stop" = "xtrue" ];then
        echo "Update failed, start now..."
        pack_start
    fi
    errmsg="Update failed"
    rpt_info
    exit 1
fi
