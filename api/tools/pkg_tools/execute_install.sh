#!/bin/bash
# ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
# execute_install.sh
# 功能: 调用exp脚本安装pkg_path指定的包到ip指定的主机
#
# 参数: 参数顺序无关
#       1) pkg_path     [必填]  安装包路径
#       2) ip           [必填]  主机ip
#       3) passuser     [可选]  ssh连接使用的用户;password或者passuser为空使用root
#       4) password     [可选]  passuser不为空时使用该密码ssh连接
#       5) param_list   [可选]  install.sh传递的参数
#
# 返回: 0:安装完成; 非0值,错误码
# 错误码:   1,脚本数参数不正确
#           2,pkg_path指定的安装包无效或不完整
#           3,
# 作者:
# +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

# variable
cur_path=$(dirname $(which $0))
cd $cur_path

errno=0
errmsg='success'

instance_app_name=""
tmp_path="/tmp/$$/"
mkdir -p $tmp_path
chmod 777 $tmp_path


source $cur_path/public_function.sh

# functions
# check_param
# 脚本输入参数检查:仅检查必填参数是否为空
function check_param()
{
    if [[ -z "$pkg_path" ]];then
        errno=1
        errmsg="parameter list missed"
        debug_log "$(basename $0):$FUNCNAME $errmsg"
        user_log "参数不完整"
        return $errno
    fi
    return 0
}


# check rename list
function check_rename_list()
{
    instance_app_name=`cat $pkg_path/init.xml | grep '^app_name=' | head -n 1 | sed -e 's/app_name=//g' -e 's/"//g'`
    if [ "x${rename_list}" = "x" ];then
        return 0
    fi

    local fcnt=`echo "${rename_list}" | awk -F"#" '{print NF}'`
    if [ $fcnt -ne 2 ];then
        errmsg="rename_list格式不正确:实例名称#原进程名:替换后进程名,原进程名:替换后进程名"
        return 1;
    fi
	return 0
    local app_list=`echo "${rename_list}" | awk -F"#" '{print $2}' | sed -e 's/,/ /g'`
    for app_info in $app_list
    do
        app_old=`echo $app_info | awk -F":" '{print $1}'`
        app_new=`echo $app_info | awk -F":" '{print $2}'`
        if [ "x${app_old}" = "x" -o  "x${app_new}" = "x" ];then
            errmsg="rename_list格式不正确:实例名称#原进程名:替换后进程名,原进程名:替换后进程名"
            return 1
        fi
        cat $pkg_path/init.xml | grep '^app_name=' | grep -q -w "$app_old"
        if [ $? -ne 0 ];then
            errmsg="进程名${app_old}不存在,无法完成替换"
            return 1
        fi
        instance_app_name=`echo $instance_app_name | sed -e "s/$app_old/$app_new/"`
    done
}

# check_install_pkg
# 检查安装包完整性
function check_install_pkg()
{
    check_pkg $pkg_path
    if [ $? -ne 0 ];then 
        errno=2
        errmsg="check_pgk failed"
        debug_log "$(basename $0):$FUNCNAME $errmsg"
        user_log "ERROR:安装包结构不完整!"
        return $errno
    fi
    return 0
}
function checkSystemUser()
{
    pkg_tmp_path=$1
    user_t=`cat $pkg_tmp_path/init.xml | grep '^user' | head -n 1`
    eval "$user_t"
    pkg_user=$user
    if [ "$pkg_user" != "root" ];then
        awk -F ':' '{print $1}' /etc/passwd|grep $pkg_user >/dev/null 2>&1
        if [ $? -ne 0 ];then
            awk -F ':' '{print $1}' /etc/passwd|grep "mqq" >/dev/null 2>&1
            if [ $? -eq 0 ];then
                sed -i "s:^user=\"$pkg_user\":user=\"mqq\":" $pkg_tmp_path/init.xml
                user_t=`cat $pkg_tmp_path/init.xml | grep '^user' | head -n 1`
                eval "$user_t"
                pkg_user=$user
            fi
        fi
    fi
}
function changeUser()
{
    pkg_tmp_path=$1
    new_user=$2
    user_t=`cat $pkg_tmp_path/init.xml | grep '^user' | head -n 1`
    eval "$user_t"
    org_user=$user
    awk -F ':' '{print $1}' /etc/passwd|grep $new_user >/dev/null 2>&1
    if [ $? -eq 0 ];then
        sed -i "s:^user=\"$pkg_user\":user=\"$new_user\":" $pkg_tmp_path/init.xml
        user_t=`cat $pkg_tmp_path/init.xml | grep '^user' | head -n 1`
        eval "$user_t"
        pkg_user=$user
        return 0
    fi
    errno=2
    errmsg="the user $new_user not exists"
    debug_log "$(basename $0):$FUNCNAME $errmsg"
    user_log "ERROR:the user $new_user not exists!"
    return $errno
}


# do_download
# 下载程序包到/tmp目录
function do_download()
{
    user_log "正在下载程序包..."

    pkg_name=`basename "${pkg_path}-install"`
    for (( i=0;i<3;i++ ))
    do
        get_rsyncd_svr 
        local func_ret=$?
        if [ $func_ret -ne 0 ];then
            errno=4
            errmsg="get rsync svr error"
            user_log "$errmsg"
            exit_proc "$errno" "$errmsg"
        fi   
	    rsync -a ${rsyncd_svr_ip}::pkg_home/pkg${pkg_path}"-install.tar.gz" $tmp_path 
	    rsync_ret=$?
    	if [ $? -eq 0 ] 
    	then
    	   break
    	fi
    done
    if [ $rsync_ret -ne 0 ];then
            errno=5
            errmsg="down load file error"
            user_log "$errmsg"
            exit_proc "$errno" "$errmsg"
    fi
	
    cd $tmp_path 
    tar zxf "${tmp_path}${pkg_name}.tar.gz"
    instance_name="${rename_list%%#*}"
    if [ "${instance_name}x" != "x" ];then
        mv ${tmp_path}${pkg_name} "${tmp_path}${pkg_name}-${instance_name}"
        pkg_name="${pkg_name}-${instance_name}"
        pkg_tmp_path="${tmp_path}${pkg_name}"
    else
        pkg_tmp_path="${tmp_path}${pkg_name}"
    fi
    #pkg_user实际包的用户
    if [[ -z $pkg_user ]];then
        user_t=`cat $pkg_tmp_path/init.xml | grep '^user' | head -n 1 `
        eval "$user_t"
        pkg_user=$user
    fi

     #ignore some file 
     if [ -f "${pkg_tmp_path}/update.conf.${task_id}" ];then
        cp "${pkg_tmp_path}/update.conf" "${pkg_tmp_path}/update.conf.org"
        cp "${pkg_tmp_path}/update.conf.${task_id}" "${pkg_tmp_path}/update.conf"
     fi

    if [ "${new_user}x" != "x" -a "$new_user" != "$pkg_user" ];then
        changeUser $pkg_tmp_path $new_user
        if [ $? -ne 0 ];then
            errno=5
            errmsg="change to user $new_user faild"
            user_log  "$errmsg"
            exit_proc "$errno" "$errmsg"
        fi
    fi
    if [ "${new_user}x" == "x" ];then
        checkSystemUser $pkg_tmp_path
    fi

     chown -R ${pkg_user}:users  $pkg_tmp_path; 
     if [ `whoami` = $pkg_user ];then
        mkdir -p ~/install > /dev/null 2>&1; cd ~/install/; rm -rf $pkg_name> /dev/null 2>&1; cp -ar $pkg_tmp_path ~/install/
        user_path=`cd ~;pwd`
     else
        su ${pkg_user} -c "mkdir -p ~/install > /dev/null 2>&1; cd ~/install/; rm -rf $pkg_name> /dev/null 2>&1; cp -ar $pkg_tmp_path ~/install/" 
        user_path=`su ${pkg_user} -c "cd ~;pwd"`
     fi
     rm -rf $pkg_tmp_path
     rm -rf $pkg_tmp_path.tar.gz

    pkg_path="${user_path}/install/${pkg_name}"
    return 0 
}

function formate_argv()
{
    install_exp=$cur_path/pkg_install_root.exp

    if [ "x${start_on_complete}" = "xtrue" ];then
        if [ "x${param_list}" = "x" ];then
            param_list="start_on_complete=true"
        else
            param_list="${param_list} start_on_complete=true"
        fi
    fi
    if [ "x${rename_list}" != "x" ];then
        if [ "x${param_list}" = "x" ];then
            param_list="rename_list='${rename_list}'"
        else
            param_list="${param_list} rename_list='${rename_list}'"
        fi
    fi

    if [ "x${instance_id}" != "x" ];then
        if [ "x${param_list}" = "x" ];then
            param_list="instance_id=${instance_id}"
        else
            param_list="${param_list} instance_id=${instance_id}"
        fi
    fi

    if [ "x${install_path}" != "x" ];then
        if [ "x${param_list}" = "x" ];then
            param_list="install_path=${install_path}"
        else
            param_list="${param_list} install_path=${install_path}"
        fi
    fi
    if [ "x${install_base}" != "x" ];then
        if [ "x${param_list}" = "x" ];then
            param_list="install_base=${install_base}"
        else
            param_list="${param_list} install_base=${install_base}"
        fi
    fi

}


function do_install()
{
    user_log "执行安装..."
    debug_log "$FUNCNAME install.sh  $param_list"
    install_log=${tmp_path}/install_log.${pkg_name}.$$
    old_path=`pwd`
    if [ `whoami` = $pkg_user ];then
        version=`cd ~/install/$pkg_name;cat admin/data/version.ini|awk '{print $3}'|head -n 1`
        cd ~/install/$pkg_name;echo [`date '+%Y-%m-%d %H:%M:%S'`] $version >admin/data/version.ini
        ~/install/$pkg_name/before_install.sh 2>/dev/null; cd ~/install/$pkg_name; ./install.sh $param_list >$install_log
        cd ~/install/ && find ./ -maxdepth 1 -mindepth 1 -type d  -mtime +30 |xargs rm -fr 
    else
        version=`su ${pkg_user} -c "cd ~/install/$pkg_name;cat admin/data/version.ini|awk '{print \\$3}'|head -n 1"`
        su ${pkg_user} -c "cd ~/install/$pkg_name;echo [`date '+%Y-%m-%d %H:%M:%S'`] $version >admin/data/version.ini"
        su ${pkg_user} -c "~/install/$pkg_name/before_install.sh 2>/dev/null; cd ~/install/$pkg_name; ./install.sh $param_list " >"$install_log"
        su ${pkg_user} -c 'cd ~/install/ && find ./ -maxdepth 1 -mindepth 1 -type d  -mtime +30 |xargs rm -fr '
    fi
	cd $old_path
	
	result_line=`cat $install_log | grep -a "result%%" | tail -n 1`
	ins_ip=`echo $result_line | awk -F"%%" '{print $2}'`
	ins_name=`echo $result_line | awk -F"%%" '{print $3}'`
	ins_installPath=`echo $result_line | awk -F"%%" '{print $4}'`
	ins_operation=`echo $result_line | awk -F"%%" '{print $5}'`
	ins_result=`echo $result_line | awk -F"%%" '{print $6}'`
	ins_startStopResult=`echo $result_line | awk -F"%%" '{print $7}'`
	debug_log "$instance_id ${result_line}"

    echo "%%installPath%%${ins_installPath}%%"
    echo "%%resultLine%%${result_line}"
    cat $install_log | grep -q "Install complete"
    local install_result=$?
    if [[ $install_result -ne 0 ]];then
         errno=7
         #errmsg="install failed"
         errmsg=$ins_result
         user_log "包安装失败..."
         cat $install_log | grep '^\['
         cat $install_log  >> $user_log_file
    else
        cat $install_log | grep '^\[' >> $user_log_file 
    fi
    if [ -d "${ins_installPath}/bin"  -a -f "${ins_installPath}/bin/install_callback.sh" ];then
        cd ${ins_installPath}/bin
        ./install_callback.sh 2>&1 >/dev/null &
    fi
    return $install_result
}


# ----------  main  ---------
debug_log "$(basename $0) $*"
for((i=1;i<=$#;i++))
do
    arg=`echo ${!i}`
    xname=`echo $arg|awk -F "=" '{print $1}'`
    xvalue=`echo $arg|sed "s/$xname=//"|tr -d "\r"`
    eval "$xname=\$xvalue"
done

#仅仅校验是否有必要的参数
check_param
exit_code=$?
if [ $exit_code -ne 0 ];then
    user_log "$errmsg"
    exit_proc "$errno" "$errmsg"
fi
#下载程序包
do_download
exit_code=$?
if [ $exit_code -ne 0 ];then
    errno=12
    errmsg='上传失败,请重试'
    user_log "$errmsg"
    exit_proc "$errno" "$errmsg"
fi

#安装时是允许改变进程名的,此函数就是处理这种情况的
#仅仅校验参数是否合法
check_rename_list
if [ $? -ne 0 ];then
    errno=10
    user_log "$errmsg"
    exit_proc "$errno" "$errmsg"
    exit
fi


#格式化参数列表param_list,到目标机安装需要此列表
formate_argv

# 检查安装包完整性
check_install_pkg
exit_code=$?
if [ $exit_code -ne 0 ];then
    errno=10
    errmsg='安装包检查失败,重试或联系管理员'
    user_log "$errmsg"
    exit_proc "$errno" "$errmsg"
fi
#pkg_user实际包的用户
if [[ -z $pkg_user ]];then
    cat $pkg_path/init.xml | grep '^user' | head -n 1 > $$.tmp
    source $$.tmp
    pkg_user=$user
    rm $$.tmp
fi

do_install
exit_code=$?
if [ $exit_code -ne 0 ];then
    error=13
    if [ -z $errmsg ];then
        errmsg="安装失败"
    fi
    exit_proc "$errno" "$errmsg"
fi


cd $cur_path
#./pkgScript/scanLog.sh 
user_log "$ip install success"
debug_log "$(basename $0) $ip install success"
exit_proc "$errno" "$errmsg"

