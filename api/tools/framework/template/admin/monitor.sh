#! /bin/sh

###file_ver=2.0.3

PATH=$PATH:.

#monitor the application
#create by leonlaili,2006-12-6

####### Custom variables begin  ########
##todo: add custom variables here
#get script path
dir_pre=$(dirname $(which $0))
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
    return 
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



#resolve the problems of application
resolve_app()
{

    #发送告警信息
    #report "Monitor: restart [process:${err_app}][port:${err_port}]"

    ##todo: add custom statement here

    run_config "resolve"

    return
}



###### Main Begin ########
if [ "$1" = "--help" ];then
    print_help
    exit 0
fi

load_lib
check_user
check_params
check_app
if [ "$err_app" != "" -o "$err_port" != "" ];then
    resolve_app
fi

if [ "$err_app" != "" ];then
    err_app_list=`echo "$err_app" | sed -e 's/ /,/g' -e 's/^,//' -e 's/,$//'`
    #rpt_info 'app' "$err_app_list" "restart"
fi

if [ "$err_port" != "" ];then
    err_port_list=`echo "$err_port" | sed -e 's/ /,/g' -e 's/^,//' -e 's/,$//'`
    #rpt_info 'port' "$err_port_list" "restart"
fi
###### Main End   ########
