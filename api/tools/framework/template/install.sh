#! /bin/sh
###file_ver=2.0.9
## add echo AUTOBAT for AOS install
## modified by marshalliu 2009-01-09
#create by leonlaili,2006-12-6

export PATH=$PATH:/usr/bin:/usr/sbin:/sbin:/bin:.
#get script path
pkg_base_path=$(dirname $(which $0))


#######
#load common functions
# arguments: NULL
# return: 0
# globals: $pkg_base_path
######
load_lib()
{
    
    dir_pre=$pkg_base_path/admin
    common_file=$pkg_base_path/admin/common.sh
    if [ -f $common_file ];then
        . $common_file
    fi
}


#update base info in init.xml
update_config()
{
    conf_file=$pkg_base_path/init.xml
    item=$1
    value=$2

    max_row=`grep -n "^</base_info>\$" $pkg_base_path/init.xml | awk -F: '{print $1}' | tail -n 1`
    if [ "$max_row" = "" ];then
        echo "Error: can not find item base_info"
        return 1
    fi
    
    item_row=`grep -n "^${item}=" $conf_file | awk -F: '{print $1}' | head -n 1`
    if [ "$item_row" = "" ];then
        echo "Error: can not find item $item"
        return 1
    fi  
    
    rows=`wc -l $conf_file | awk '{print $1}'`
    #lastRow=`tail -n 1 $conf_file`
    #rows=`grep -n "^${lastRow}\$" $conf_file | awk -F: '{print $1}' | head -n 1`
    if [ $item_row -ge $max_row ];then
        echo "Error: can not find item $item"
        return 1
    fi  
    
    tmp_file=$pkg_base_path/`date +%s%N`${RANDOM}.tmp
    
    head -n $(( $item_row -1 )) $conf_file > $tmp_file
    echo "${item}=\"${value}\"" >> $tmp_file 
    #tail -n $(( $rows - $item_row )) $conf_file >> $tmp_file
    sed "1,${item_row}d" $conf_file >> $tmp_file
    cp $tmp_file $conf_file
    rm $tmp_file 
}

#check current user
check_user()
{
    if [ "`whoami`" != "$user" ];then
        echo "Only $user can execute this script"
        errmsg="current user: $(whoami) do not match package user: $user"
        errcode=1
        print_result
		exit 1
    fi
}

##add install log
#log()
#{
#    log_file=${pkg_base_path}/install.log
#    echo $log_file
#    echo "[`date "+%Y-%m-%d %H:%M:%S"`] $*" | tee -a $log_file
#}

#print help information
print_help()
{
    ##todo: output help information here
    echo "Usage: install.sh [install_path]"
    echo "note: the application will be installed in install_path/app_name-app_version"
}

#check script parameters
check_params()
{
	local err_para=""
    ok="true"
    if [ "$install_base" = "" ];then
        ok="false"
		err_para="install_base"
    fi
    ##todo: add addition parameters checking statement here...
    if [ "$name" = "" ];then
        ok="false"
		err_para="name $err_para"
    fi

    if [ "$app_name" = "" ];then
        ok="false"
		err_para="app_name $err_para"
    fi

    if [ "$user" = "" ];then
        ok="false"
		err_para="user $err_para"
    fi
    
    if [ "$ok" != "true" ];then
        echo "Some of the parameters are invalid. para: $err_para"
        errmsg="config error, para: $err_para"
        errcode=1
        print_result
        exit 1
    fi    
}


#check old version of the application
check_old_version()
{
    if [ -d $install_path -a "$force_install" != "true" ];then
        check_valid_pkg $install_path
        if [ $? -eq 0 ];then
            log $install_log "Sorry, you have installed the same application yet"
            log $install_log "Directory $install_path exists,install failed"
            if [ "$start_on_complete" = "true" ];then
                $install_path/admin/restart.sh all force
            fi
            errmsg="package exists"
            errcode=5
            print_result
            exit 1
        fi
    fi
}
check_valid_pkg()
{

    pkg_path=$1
    if [ -d $pkg_path -a -d "${pkg_path}/admin" -a -f "${pkg_path}/init.xml" ];then
        return 0
    else
        return 1
    fi
}



on_start()
{
    ##todo: add any statment you want when installation start
    #init install directory
    log $install_log "Install start"
    if [ ! -d "$install_path" ];then
        mkdir -p $install_path
        if [ $? -ne 0 ];then
            log $install_log "Can not create directory: $install_path"
            log $install_log "Install failed"
            errmsg="Can not create $install_path"
            errcode=1
            print_result
            exit 1
        fi
        log $install_log "Create directory: $install_path"
    fi
    chmod 755 $install_path

    run_config "install_on_start"

    return 0
}

#copy files
copy_files()
{
    log $install_log "Copying files..."
    ##todo: add copy statement here
    #example:

    if [ "$force_install" = "true" -a -f "$install_path/admin/stop.sh" ];then
        $install_path/admin/stop.sh all
    fi

    cp -a $pkg_base_path/* $install_path/
    if [ $? -ne 0 ];then
       log $install_log "cp files failed"
       errmsg="cp files failed $install_path"
       errcode=1
       print_result
       exit 1
    fi
        
    rm $install_path/install.sh
	
    rm $install_path/install.log

    ##todo: create log folder
    mkdir -p $log_dir 2>&1
    if [ "$log_dir" != "$install_path/log" ];then
        chown ${user}:users $log_dir >/dev/null 2>&1
        chmod 775 $log_dir >/dev/null 2>&1
        rm -rf $install_path/log
        ln -s $log_dir $install_path/log
    fi
}

#complie source code and install
complie_make()
{
    ##todo: add complie and make statement when needed
    
    ##Load make
    run_config "make"

    return 0
}

#substitute place holders in the files
substitute()
{
    #substitute the default place holders of files in admin
    log $install_log "Substitute the place holders of files in admin"

    subs_file="$install_path/admin/data/subs.lst"

    get_config "substitute"
    while read file_tmp tail
    do
       ls -d $install_path/$file_tmp >> $subs_file 2>/dev/null
    done < $ret
    rm $ret

    while read result tail
    do
        if [ ! -f "$result" ];then continue ;fi

        #check text file
        echo "`/usr/bin/file -b $result | awk -F, '{print $1}'`" | grep " text" > /dev/null
        if [ $? -ne 0 ];then continue;fi

        sed -e "s:#INSTALL_PATH:$install_path:g" \
            -e "s:#INSTALL_BASE:$install_base:g" \
            -e "s:#IP_INNER:$ip_inner:g" \
            -e "s:#IP_OUTER:$ip_outer:g" \
            -e "s:#IP_VIP:$ip_vip:g" \
            $result > $install_path/subs.tmp
        cp $install_path/subs.tmp "$result"
    done < $subs_file
    rm $subs_file
    rm $install_path/subs.tmp
}

#create link needed
link_files()
{
    mkdir -p $link_dir 2>/dev/null
    rm $link_dir/$name 2>/dev/null
    ln -s $install_path "$link_dir/$name" 2>/dev/null
    if [ $? -eq 0 ];then
        log $install_log "Create link $link_dir/$name success"
    else
        log $install_log "Create link $link_dir/$name failed"
    fi
    ##todo: add addition link statment here
    tmp=`pwd`

    #Load link script
    run_config "link"
    
    cd $tmp
}

#change files owner or popedom
update_popedom()
{
    chmod ug+x $install_path/admin/*.sh >/dev/null 2>&1
    chmod 777 $install_path/admin/data/tmp >/dev/null 2>&1
    chmod ug+x $install_path/bin/* >/dev/null 2>&1
    chown $user:users -R $install_path
    if [ $? -eq 0 ];then
        log $install_log "Change application files owner success"
    else
        log $install_log "Change application files owner failed"
    fi
    ##todo: add addition statement here
}

#on complete
on_complete()
{
    ##todo: add any statment you want when installation complete

    run_config "install_on_complete"

    return
}


print_result()
{
	op="install"
    echo 
	echo "result%%${ip_inner}%%${name}%%${install_path}%%${op}%%${errmsg}%%${error_app_list}%%"
    msg="ip=${ip_inner}&name=${name}&install_path=${install_path}&result=${errmsg}&err_app=${error_app_list}"
    #exit_proc $code $op $msg
    echo 
}

#check whether installation success?
check_installation()
{
    log "Install complete."
    errmsg="success"
    errcode=0
    if [ "$force_install" = "true" -a "$ok" = "true" -o "$start_on_complete" = "true" ];then
        start_app
    fi
    print_result 
    mv $pkg_base_path/install.log $log_dir
}

#start the application
start_app()
{
    log $install_log "Start application"
    $install_path/admin/start.sh all force >/dev/null 2>&1

    cat $install_path/init.xml | grep '^is_shell' | head -n 1 | grep -q 'true'
    if [ $? -eq 0 ];then return 0; fi
    # ¼ì²é½ø³Ì
    for app in `echo "$app_name" | sed -e "s/:[^ ]*//g"`
    do
        check_process $app
        err_app=`echo ${err_app}`
        if [ "${err_app}" = "" ];then
            log $install_log "$app start success"
        else
            if [ "${error_app_list}" = "" ];then
                error_app_list="${app}"
            else
                error_app_list="${error_app_list},${app}"
            fi
            log $install_log "$app start fail"
        fi
    done
    if [ "x${error_app_list}" != 'x' ];then
        error_app_list="${error_app_list} start fail"
    fi
}

# renew_app_name
renew_app_name()
{
    run_config "base_info"
    for app_info in $app_list; do
        old_app=`echo $app_info | awk -F":" '{print $1}'`
        new_app=`echo $app_info | awk -F":" '{print $2}'`
        if [ -z "$old_app" -o -z "$new_app" ]; then
            continue
        fi
        echo $app_name | grep -q -w "$old_app"
        if [ $? -ne 0 ]; then
            log $install_log "process name $old_app not exist,can not modify"
            continue
        fi
        log $install_log "modify app_name $old_app to $new_app"
        app_name2=`echo $app_name | sed "s/$old_app/$new_app/"`
        if [ "$app_name2" ]; then
            app_name="$app_name2"
        fi

        log $install_log "modify $old_app to $new_app in start tag"
        cat $pkg_base_path/init.xml | sed "/<start>/,/<\/start>/ s/${old_app}/${new_app}/" > $pkg_base_path/init.xml.$$.xx
        cat $pkg_base_path/init.xml.$$.xx | grep -q "app_name"
        if [ $? -ne 0 ]; then
            rm $pkg_base_path/init.xml.$$.xx > /dev/null 2>&1
            log $install_log "modify $old_app to $new_app in start tag failed"
        else
            mv $pkg_base_path/init.xml.$$.xx $pkg_base_path/init.xml
        fi

    done
    update_config 'app_name' "$app_name"
    run_config "base_info"
}

# link app list
link_app()
{
    local dirtmp=`pwd`
    for app_info in $app_list; do
        old_app=`echo $app_info | awk -F":" '{print $1}'`
        new_app=`echo $app_info | awk -F":" '{print $2}'`
        if [ -z "$old_app" -o -z "$new_app" ]; then
            continue
        fi
        cd $install_path/bin
        if [ ! -f $old_app ];then continue; fi
        ln -s $old_app $new_app > /dev/null 2>&1
    done
    cd $dirtmp
}

####### Framwork variables begin  ########

install_init()
{
    for((i=1;i<=$#;i++))
    do
        arg=`echo ${!i}`
        echo $arg | grep -q '='
        if [ $? -ne 0 ];then continue; fi
        xname=`echo $arg|awk -F "=" '{print $1}'`
        xvalue=`echo $arg|sed "s/${xname}=//"|tr -d "\r"`
        eval "${xname}=\${xvalue}"
        if [ "x${xname}" != "xrename_list" -a  "x${xname}" != "xstart_on_complete" ];then
            update_config "${xname}" "${xvalue}"
        fi
    done
    
    if [ "x${rename_list}" != "x" ]; then
        new_name=`echo "${rename_list}" | awk -F"#" '{print $1}'`
        name=$new_name
        if [ "$new_name" ]; then
            update_config 'name' "$new_name"
        fi
        app_list=`echo "${rename_list}" | awk -F"#" '{print $2}' | sed 's|,| |g'`
        if [ "x${app_list}" != "x" ]; then
            renew_app_name
        fi
    fi
    #install directory
    if [ "$install_base" = "" ];then
        install_base="/usr/local/services"
    fi
    if [ "$install_path" = "" ];then
        if [ -z "$version" ];then
            install_path="$install_base/$name"
        else
            install_path="$install_base/$name-$version"
        fi
        update_config 'install_path' "$install_path"
    fi
    link_dir="$HOME/services"
    install_log="${pkg_base_path}/install.log"
}


####### Framwork variables end    ########

###### Main Begin ########


if [ "$1" = "--help" ];then
    print_help
    exit
fi

load_lib
install_init $*
check_user
check_params
check_old_version
on_start
copy_files
complie_make
substitute
link_files
link_app
update_popedom
on_complete
log $install_log "Build files md5sum"
$install_path/admin/md5sum.sh build
check_installation
###### Main End   ########
