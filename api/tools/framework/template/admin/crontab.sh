#! /bin/sh

###file_ver=2.0.3

PATH=$PATH:.

#edit crontab
#create by leonlaili,2006-12-6

####### Custom variables begin  ########
##todo: add custom variables here
#get script path
dir_pre=$(dirname $(which $0))
cron_file=$2
cron_ok="ok"
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
    echo "Usage: crontab.sh <add|delete> <temp_file>"
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

#backup crontab
backup_crontab()
{
    create_tmp_file $install_path/admin/data/backup cron.bak > /dev/null
    cron_file_bak=$ret

    cp $cron_file $cron_file_bak

    if [ $? -ne 0 ];then cron_ok="";fi
}

#clear configure
delete_crontab()
{
    #clear monitor script
    ##todo: make sure following statement as same as start.sh

    get_config "crontab"
    if [ $? -ne 0 ];then
        return 0
    fi
    cron_conf=$ret

    cron_file_tmp=`create_tmp_file $install_path/admin/data/tmp`
    while read tmp
    do
        tmp="`echo "$tmp" | sed -e "s:#INSTALL_PATH:$install_path:g"`"
        tmp2="`echo "$tmp" | sed -e "s:*:\\\\\\*:g"`"

        cat $cron_file | grep -v "^${tmp2}$" > $cron_file_tmp 
        if [ $? -ne 0 ];then cron_ok="";fi
        cat $cron_file_tmp > $cron_file
        if [ $? -ne 0 ];then cron_ok="";fi 
    done < $cron_conf

    rm $cron_file_tmp
    rm $cron_conf
}

#add configure
add_crontab()
{
    #clear monitor script
    ##todo: make sure following statement as same as start.sh

    get_config "crontab"
    if [ $? -ne 0 ];then
        return 0
    fi
    cron_conf=$ret

    while read tmp 
    do
        tmp="`echo "$tmp" | sed -e "s:#INSTALL_PATH:$install_path:g"`"
        tmp2="`echo "$tmp" | sed -e "s:*:\\\\\\*:g"`"

        cat $cron_file | grep "^${tmp2}$" > /dev/null 2>&1
        if [ $? -eq 0 ];then continue;fi

        echo $tmp | grep "^##SYS" > /dev/null 2>&1
        if [ $? -eq 0 ];then continue;fi

        echo "$tmp" >> $cron_file
        if [ $? -ne 0 ];then cron_ok="";fi 
    done < $cron_conf

    rm $cron_conf
}

###### Main Begin ########
if [ "$1" = "--help" ];then
    print_help
    exit 1
fi

if [ $# -lt 2 ];then
    print_help
    exit 1
fi 

load_lib
check_user
check_params

cmd=$1

backup_crontab
if [ "$cmd" = "add" ];then
    add_crontab
elif [ "$cmd" = "delete" ];then
    delete_crontab
else
    print_help
fi
###### Main End   ########
