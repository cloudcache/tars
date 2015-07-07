#! /bin/sh

###file_ver=2.0.3

PATH=$PATH:.

#uninstall the application
#create by leonlaili,2006-12-6

####### Custom variables begin  ########
##todo: add custom variables here
cmd=$1
#get script path
dir_pre=$(dirname $(which $0))
pkg_base_path="${dir_pre}/../"
####### Custom variables end    ########

#load common functions
load_lib()
{
    common_file=$dir_pre/common.sh
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
    # echo ....
    echo "Useage: uninstall.sh all|separate|revert"
}

#check script parameters
check_params()
{
    ok="true"
    ##todo: add addition parameters checking statement here...
    if [ "$cmd" = "" ];then
        ok="false"
    fi
    
    if [ "$ok" != "true" ];then
        echo "Some of the parameters are invalid. "
        print_help
        exit 1
    fi    
}

#clear file links
clear_link()
{
    ##todo: add statement to move file links here
    if [ -L $link_dir/$name ];then
        rm $link_dir/$name
    fi
}

#clear system configuration
clear_configuration()
{
    ##todo: add statement to clear system configuration
    get_boot_path
    boot_file=$ret
    tmp_file=`create_tmp_file $install_path/admin/data/tmp`
    if [ -w $boot_file ];then
        grep -v "#start $name" $boot_file | \
        grep -v "$install_path/admin/start.sh" > $tmp_file
        install $tmp_file $boot_file
        log $default_log "Clear auto start success"
    else
        log $default_log "Clear auto start failed"
    fi
    rm $tmp_file
}

#remove unnecesary files
remove_files()
{
    ##todo: add remove statement here
    #remove all files except log and conf
    echo "Remove files..."

    #backup_files
    mkdir -p $backup_path
    if [ $? -ne 0 ];then
        log $default_log "Make backup directory failed"
        exit 1
    fi

    create_tmp_file "$backup_path" "`basename $install_path`.bak." > /dev/null
    rm $ret
    tar_file=${ret}.tgz

    cd $install_path/../
    tar czvf $tar_file `basename $install_path`
    
    if [ $? -eq 0 ];then
        rm -rf $install_path
        return 0
    else
        return 1
    fi
}

#clear custom setting or configuration
clear_custom()
{
    ##todo: add any statement you need here
    return 0
}

#clear file popedom
clear_popedom()
{
    ##todo: add statment to change file popedom
    chmod ugo-x $install_path/admin/*.sh  >/dev/null 2>&1
    chmod ugo-x $install_path/bin/* >/dev/null 2>&1  
    
    chmod u+x $install_path/admin/uninstall.sh  >/dev/null 2>&1
}

#revert this application
revert()
{
    ##todo: add any statement to revert this application
    mv `dirname $install_path/admin` $install_path
    rm $link_dir/$name 2>/dev/null
    ln -s $install_path $link_dir/$name
    chmod u+x $install_path/admin/*.sh  >/dev/null 2>&1
    chmod u+x $install_path/bin/* >/dev/null 2>&1  

    get_boot_path
    boot_file=$ret
    if [ -w $boot_file ];then
        echo "#start $name" $boot_file >> $boot_file 
        echo "$install_path/admin/start.sh" >> $boot_file
        log $default_log "Set auto start success"
    else
        log $default_log "Set auto start failed"
    fi
}

confirm_uninstall()
{
    echo -n "х╥хор╙п╤ть$name?[yes/no]"
    read confirm
    if [ "$confirm" != "yes" ];then
        exit 1
    fi
}

#report package infomation

###### Main Begin ########
if [ "$1" = "--help" ];then
    print_help
    exit 0
fi

load_lib
check_params
check_user
if [ "$cmd" = "all" ];then
    confirm_uninstall
    $install_path/admin/stop.sh all
    clear_configuration
    clear_custom
    remove_files
    clear_link
    echo "uninstall complete"
elif [ "$cmd" = "separate" ];then
    confirm_uninstall
    $install_path/admin/stop.sh all
    clear_popedom
    clear_link
    clear_custom
    mv $install_path $backup_path
    echo "separate complete"
elif [ "$cmd" = "revert" ];then
    if [ ! -x $install_path/admin/start.sh ];then
        revert
        echo "revert complete"
    fi
fi
###### Main End   ########
