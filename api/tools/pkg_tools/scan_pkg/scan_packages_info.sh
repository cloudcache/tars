#!/bin/sh

ip_inner=`/sbin/ifconfig eth1 2>/dev/null |grep "inet addr:"|awk -F ":" '{ print $2 }'|awk '{ print $1 }'`
if [ "${ip_inner}x" = "x" ];then
   ip_inner=`/sbin/ip route|egrep 'src 172\.|src 10\.'|awk '{print $9}'|head -n 1`
fi
cur_path=$(dirname $(which $0))
cd $cur_path

num=0

#判断路径是否是合法的包
function is_package_directory()
{
    local path=$1
    
    if [ -f "$path/init.xml" -a -d "$path/admin" ];then
        return 0
    else
        return 1
    fi
}

function is_serverbench_pack()
{
    local pkg_path=$1
    local bin_path="$pkg_path/bin/"
    local bin_files=`find "${bin_path}" -type f -print`
    if [[ "x${bin_files}" == "x" ]]; then
        return 1
    fi
    for f in ${bin_files}
    do
        strings "$f"  | grep -q -i -E "ServerBench|Serverbench|S\+\+|spp"
        if [ $? -eq 0 ]; then
            return 0
        fi
    done
    return 1
}

function scan_single_package()
{
	local pkg_path=$1
    is_package_directory "$pkg_path"
    if [ $? -eq 0 ];then
        #to get version and package path.
		install_path="unknown"
        install_path="$pkg_path"
        version="unknown"
        package_path="unknown"
		is_shell="unknown"
		pkg_name="unknown"
		pkg_app_name="unknown"
        install_time="unknown"

        vFile="$pkg_path/admin/data/version.ini"
        if [ -f "$vFile" ];then
            vInfo=`cat $vFile | tail -n1`
            version=`echo $vInfo | awk -F" " '{print $3}'`
            vInfo=`cat $vFile | head -n1`
            install_time=`echo $vInfo | sed -e "s/[][]//g" | awk '{print $1" "$2}'`
        fi

        sFile="$pkg_path/admin/data/source.ini"
        if [ -f "$sFile" ];then
            package_path=`cat $sFile`
        fi 

        cat "$pkg_path/init.xml" | grep "^is_shell" | grep -q "true"
        if [ $? -eq 0 ];then
            is_shell="true"
        else
            is_shell="false"
        fi

        cat $pkg_path/init.xml | grep '^name' | head -n 1 > ./$$.tmp
        source ./$$.tmp
        pkg_name=$name
        rm ./$$.tmp

        cat $pkg_path/init.xml | grep '^app_name' | head -n 1 > ./$$.tmp1
        source ./$$.tmp1
        pkg_app_name=$app_name
        rm ./$$.tmp1

	    cat $pkg_path/init.xml | grep '^port' | head -n 1 > ./$$.tmp
        source ./$$.tmp
        port=$port
        rm ./$$.tmp

        if [ "${port}x" == "x" ];then
            port="unknown"
        fi

        #is_serverbench_pack $pkg_path
        #if [ $? -eq 0 ];then
        #    is_spp="true"
        #else
        #    is_spp="false"
        #fi

        #echo "SCAN_PACKAGE_INFO: $install_path###$version###$package_path###$is_shell###$pkg_name###$pkg_app_name"
	data=$data"$install_path###$version###$package_path###$is_shell###$pkg_name###$pkg_app_name###$port###$install_time||"
	single_proc_data=`./remote_check_instance_stat.sh $pkg_path`
	proc_data=$proc_data$single_proc_data"||"

        if [ -d "$pkg_path/client" ];then
            scan_packages "$pkg_path/client" "false"
        fi
        if [ -L "$pkg_path/client" ];then
            scan_single_package "$pkg_path/client" 
        fi
    fi
}

function delete_ip_info()
{
    source $cur_path/../report.conf
    local url="http://${rpt_ip}:${rpt_port}/interface/PkgNet.class.php"
	local host="pkg.isd.com"

    curl --silent -d "action=delete" -d "ip=$ip_inner" -H "Host:$host" $url;
}

function scan_packages()
{
    num=$((num+1))
    local i=$num
    local scan_path=$1
    local scan_loop=$2
    ls -l --time-style=full-iso "${scan_path}/" | grep "^d" > ./scan_packages_path.$$.$i
    while read mod count user group size date time timezone path tail
    do
		scan_single_package "$scan_path/$path"
    done < ./scan_packages_path.$$.$i
    rm ./scan_packages_path.$$.$i
}

function urlencode()
{
    STR=$@
    if [ "${STR}x" == "x" ];then
	echo "nothing"
	return
    fi

    echo ${STR} | sed -e 's| |%20|g' \
    -e 's|!|%21|g' \
    -e 's|#|%23|g' \
    -e 's|\$|%24|g' \
    -e 's|%|%25|g' \
    -e 's|&|%26|g' \
    -e "s|'|%27|g" \
    -e 's|(|%28|g' \
    -e 's|)|%29|g' \
    -e 's|*|%2A|g' \
    -e 's|+|%2B|g' \
    -e 's|,|%2C|g' \
    -e 's|/|%2F|g' \
    -e 's|:|%3A|g' \
    -e 's|;|%3B|g' \
    -e 's|=|%3D|g' \
    -e 's|?|%3F|g' \
    -e 's|@|%40|g' \
    -e 's|\[|%5B|g' \
    -e 's|]|%5D|g'
}

#report
function ReportData()
{
    if [ "${data}x" == "x" -o "${proc_data}x" == "x" ];then
	#echo "no packages"
	return
    fi
	data=`urlencode $data`
	proc_data=`urlencode $proc_data`
	

    source $cur_path/../report.conf
    local url="http://${rpt_ip}:${rpt_port}/query/report_instance"
	local host="${rpt_host}"

    #echo "SCAN_PACKAGE_INFO: $install_path###$version###$package_path###$is_shell###$pkg_name###$pkg_app_name"

    curl -d "action=replace" -d "ip=$ip_inner" -d "data=$data" -H "Host:$host" $url;
    curl -d "action=process" -d "ip=$ip_inner" -d "data=$proc_data" -H "Host:$host" $url;
}

############## MAIN START #################
#delete old ip info
#delete_ip_info

#只需要扫描两个目录
scan_packages "/usr/local/tars" "true"
scan_packages "/usr/local" "true"
#
ReportData
