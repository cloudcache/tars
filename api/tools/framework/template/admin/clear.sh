#! /bin/sh
###file_ver=2.0.4

#clear environment
#create by leonlaili,2006-12-6

PATH=$PATH:.

####### Custom variables begin  ########
##todo: add custom variables here
#get script path
dir_pre=$(dirname $(which $0))
pkg_base_path="${dir_pre}/../"
####### Custom variables end    ########

#load common functions
load_lib()
{
    common_file=$pkg_base_path/admin/common.sh
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
    echo "Usage: clear.sh all|file|core|configure|custom"
}

#check script parameters
check_params()
{
    ok="true"
    ##todo: add addition parameters checking statement here...
    
    if [ "$ok" != "true" ];then
        echo "Some of the parameters are invalid. "
        print_help
        exit 1
    fi    
}

check_limit()
{
    if [ ! -d "${1}" ];then return 0;fi

    disk_limit=`echo "$2" | awk -F: '{print $1}' | sed -e "s:%::"`
    dir_limit=`echo "$2" | awk -F: '{print $2}' | sed -e "s:[Mm]::"`
    
    disk_size=`df -l ${1}/ | tail -n 1 | awk '{print $5}' | sed -e "s:%::"`
    dir_size=`du -ms ${1}/ | awk '{print $1}'`

    if [ "$disk_limit" = "" -o "$dir_limit" = "" -o "$disk_size" = "" -o "$dir_size" = "" ];then
       return 0
    fi

    echo "${disk_limit}${dir_limit}${disk_size}${dir_size}" | grep "[^0-9]" > /dev/null
    if [ $? -eq 0 ];then return 0;fi
    
    if [ $disk_limit -le $disk_size -o $dir_limit -le $dir_size ];then
        log $default_log "${1}空间使用越限：阀值[${2}] 实际分区占用${disk_size}% 目录大小${dir_size}M"
        return 1
    else
        return 0
    fi
}

delete_file()
{
    if [ $# -lt 4 ];then return 1;fi

    dir="$1"
    tmp_path="$1/$2" 
    param="$3"    
    limit="$4"

    check_limit $dir $limit
    if [ $? -eq 0 ];then
        return 0
    fi

    cmd="mtime"
    echo "$param" | grep "h$" > /dev/null
    if [ $? -eq 0 ];then
        cmd="mmin"
        param=`echo "$param" | sed -e "s/h//g"`
        param=`expr $param '*' 60`
    fi
    
    echo "$param" | grep "m$" > /dev/null
    if [ $? -eq 0 ];then
        cmd="mmin"
        param=`echo "$param" | sed -e "s/m//g"`
    fi  
        
    find ${dir}/ -path "$tmp_path" -$cmd "+$param" -print | xargs rm -rf
    if [ $? -ne 0 ];then
        report "Delete $dir/$file failed"
        return 1
    else
        log $default_log "Delete $dir/$file complete"
        return 0
    fi
}

clean_file()
{
    if [ $# -lt 4 ];then return 1;fi

    dir="$1"
    tmp_path="$1/$2"
    param="$3"
    limit="$4"

    check_limit $dir $limit
    if [ $? -eq 0 ];then
        return 0
    fi

    #老配置默认单位为K
    expr "$param" : "[0-9]\{1,\}$" > /dev/null
    if [ $? -eq 0 ];then
        to_bytes ${param}K
    else
        to_bytes ${param}
    fi

    if [ "$ret" = "0" ];then
        return 1
    fi

    clear_tmp_file=`create_tmp_file $install_path/admin/data/tmp`
    find ${dir}/ -path "$tmp_path" -type f -size "+${ret}c" > $clear_tmp_file
    if [ $? -ne 0 ];then
        rm $clear_tmp_file
        return 1
    fi
   
    for f in `cat $clear_tmp_file | grep -v "default.log"`
    do
         if [ ! -f $f ];then continue;fi
         echo "" > $f
    done

    rm $clear_tmp_file
    return $?
}

tar_file()
{
    if [ $# -lt 4 ];then return 1;fi
        
    dir="$1"
    tmp_path="./$2"
    param="$3"
    tar_tmp_file=`create_tmp_file $install_path/admin/data/tmp`
    limit="$4"

    check_limit $dir $limit
    if [ $? -eq 0 ];then
        rm $tar_tmp_file
        return 0
    fi

    cmd="mtime"
    echo "$param" | grep "h$" > /dev/null
    if [ $? -eq 0 ];then
        cmd="mmin"
        param=`echo "$param" | sed -e "s/h//g"`
        param=`expr $param '*' 60`
    fi

    echo "$param" | grep "m$" > /dev/null
    if [ $? -eq 0 ];then
        cmd="mmin"
        param=`echo "$param" | sed -e "s/m//g"`
    fi 

    cd $dir
    find ./ -path "$tmp_path" -$cmd "+$param" -print > $tar_tmp_file
    if [ $? -ne 0 ];then
        rm $tar_tmp_file
        return 1
    fi

    tar_file="clear.`date +%Y%m%d_%H%M%S`.tgz"
    
    target_files=`cat $tar_tmp_file`
    rm $tar_tmp_file

    if [ "$target_files" != "" ];then
        tar --remove-files -czvf ${tar_file} ${target_files}
    fi

    if [ $? -ne 0 ];then 
        cd - > /dev/null
        return 1
    fi

    cd - > /dev/null
    return 0
}

check_file()
{
    if [ $# -lt 3 ];then return 1;fi

    dir="$1"
    tmp_path="$1/$2"
    param="$3"

    to_bytes $param
    if [ "$ret" = "0" ];then
        return
    fi

    #检查超大文件
    large_files=`find ${dir}/ -path "$tmp_path" -type f -maxdepth 2 -size "+${ret}c"`
    if [ "$large_files" != "" ];then
        report "${1}包含大小超过${param}的文件" 41288
    fi

}

#clear old log files
clear_file()
{
    clear_conf=`create_tmp_file $install_path/admin/data/tmp`
    get_config "clear_file"
    cat $ret | grep -v "#" > $clear_conf
    if [ -f $g_clear_conf ];then
        cat $g_clear_conf | grep -v "#" >> $clear_conf
    fi
    rm $ret

    while read dir limit cmd param target tail
    do
        if [ "$dir" = "" ];then continue;fi

        dir=$install_path/$dir
 
        if [ "$cmd" = "delete" ];then
            delete_file $dir "$target" $param $limit
        elif [ "$cmd" = "clear" ];then
            clean_file $dir "$target" $param $limit
        elif [ "$cmd" = "tar" ];then
            tar_file $dir "$target" $param $limit
        elif [ "$cmd" = "warning" ];then
            check_file $dir "$target" $param $limit
        else
            continue
        fi
    done < $clear_conf

    rm $clear_conf
    return 0
}

#clear core files
clear_core()
{
    ##todo: add clear statement here
    find $install_path/bin -name "core.*" -exec rm {} \; > /dev/null 2>&1
}

#clear configure
clear_configure()
{
    export PATH="$install_path/admin:$PATH"
    export VISUAL="crontab.sh delete"
    crontab -e

    if [ $? -ne 0 ];then
        log $default_log "Set crontab failed"
    fi
}

clear_custom()
{
    ##todo: add addtion clear statement here
    return 0
}

###### Main Begin ########
if [ "$1" = "--help" ];then
    print_help
    exit 1
fi

if [ "$1" = "file" ];then
/bin/sleep $((RANDOM%110+65)).$((RANDOM%100))
fi

load_lib
check_user
check_params


cmd=$1

if [ "$cmd" = "all" ];then
    clear_file
    clear_core
    clear_configure
    clear_custom
elif [ "$cmd" = "file" ];then
    clear_file
elif [ "$cmd" = "core" ];then
    clear_core
elif [ "$cmd" = "configure" ];then
    clear_configure
elif [ "$cmd" = "custom" ];then
    clear_custom
else
    print_help
fi
###### Main End   ########
