#! /bin/sh

###file_ver=2.0.6

PATH=$PATH:.

#install the application
#create by leonlaili,2006-12-28

####### Custom variables begin  ########
##todo: add custom variables here
#get script path
dir_pre=$(dirname $(which $0))
pkg_base_path="${dir_pre}/../"
err_count=0
cmd=$1
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
        exit
    fi
}

#print help information
print_help()
{
    ##todo: output help information here
    echo "Usage : md5sum.sh build|check"
    echo "Params: build, create md5sum"
    echo "        check, verify md5sum"
}

#check script parameters
check_params()
{
    ok="true"
    if [ "$cmd" = "" ];then
        ok="false"
    fi
    ##todo: add addition parameters checking statement here...
    
    if [ "$ok" != "true" ];then
        echo "Some of the parameters are invalid. "
        print_help
        exit
    fi    
}

parse_md5_files()
{
    if [ ! -f "$1" ];then
        return 1
    fi

    cd $install_path

    create_tmp_file $install_path/admin/data/tmp md5 > /dev/null
    md5_files=$ret
    create_tmp_file $install_path/admin/data/tmp md5 > /dev/null
    tmp_white=$ret
    create_tmp_file $install_path/admin/data/tmp md5 > /dev/null
    tmp_black=$ret

    hour=`date '+%k'`
    expr $hour : [1-9][0-9]$ > /dev/null 2>&1
    if [ $? -ne 0 ];then hour=24;fi

    while read tmp tail
    do
       find_tmp_dir=`dirname "$tmp"`
       find_tmp_file=`basename "$tmp"`
       if [ "$tail" != "-" ];then
           if [ $hour -eq 5 -o "$cmd" = "build" ];then 
               find $find_tmp_dir -path "$find_tmp_dir/$find_tmp_file" -maxdepth 1 -size "-50000k" -type f 2>/dev/null | sed -e "s:^\./::" >> $tmp_black 2>/dev/null
           else
               find $find_tmp_dir -path "$find_tmp_dir/$find_tmp_file" -maxdepth 1 -size "-50000k" -type f -mmin "-60" 2>/dev/null | sed -e "s:^\./::" >> $tmp_black 2>/dev/null
           fi
       else
           find $find_tmp_dir -path "$find_tmp_dir/$find_tmp_file" -maxdepth 1 -size "-50000k" -type f 2>/dev/null | sed -e "s:^\./::" >> $tmp_white 2>/dev/null
       fi
    done < $1

    cat $tmp_black | sort | uniq >> "${tmp_black}.tmp"
    cat $tmp_white | sort | uniq >> "${tmp_white}.tmp"
    diff "${tmp_black}.tmp" "${tmp_white}.tmp" | grep "^<" | sed -e "s:^<[ \t]*::" > $md5_files

    rm -f $tmp_black $tmp_white "${tmp_black}.tmp" "$tmp_white.tmp" 2>/dev/null
}

#create md5sum of files
build()
{
    #check file list
    if [ ! -f "$md5_files" ];then
        return
    fi

    # add the md5sum to md5.lst according to list.lst
    tmp_dir=`pwd`
    cd $install_path

    # backup md5 result
    if [ -f $md5_result ];then
        create_tmp_file "$install_path/admin/data/backup" "`basename $md5_result`" > /dev/null
        cp $md5_result $ret
        md5_result_bak=$ret
        rm $md5_result
    fi

    # backup md5 failed
    if [ -f $md5_failed ];then
        create_tmp_file "$install_path/admin/data/backup" "`basename $md5_failed`" > /dev/null
        cp $md5_failed $ret
    fi

    # build md5 result
    while read FILE tail
    do
       if [ -f "$FILE" ];then
           md5sum $FILE >> $md5_result
       fi
    done < $md5_files

    if [ -f "$md5_result" -a -f "$md5_result_bak" ];then
        if [ "`md5sum $md5_result | awk '{print $1}'`" = "`md5sum $md5_result_bak | awk '{print $1}'`" ];then
            rm $md5_result_bak
        fi
    fi

    cd - >/dev/null

    echo "md5sum build successful!"

    cd $tmp_dir
}

get_file_type()
{
    if [ ! -f $1 ];then
        return 1
    fi

    file_type=""
    file_info=""

    file_tmp=$1

    tail_list=".pid$|.log$|.tmp$|.dat$|.data$|core|.stat$"
    echo $file_tmp | egrep "$tail_list" > /dev/null
    if [ $? -eq 0 ];then
        file_type="1"
        return 0
    fi

    index=1
    file_info=`file -b $file_tmp | awk -F, '{print $1}'`
    type_key="ELF shell excutable link text"
    for key in $type_key
    do
        echo $file_info | grep "$key" > /dev/null
        if [ $? -eq 0 ];then
            file_type=$(( $index + 1 ))
            return 0
        fi
        index=$(( $index + 1 ))
    done

    file_type="0"
    return 0
}

report_change()
{
    tmp_file=$1
    if [ ! -f $tmp_file ];then
        return 1
    fi

    create_tmp_file "$install_path/admin/data/tmp" "report_list" > /dev/null
    report_list=$ret


    if [ -f $md5_failed ];then
        create_tmp_file "$install_path/admin/data/tmp" "report_list" > /dev/null
        report_tmp1=$ret
        create_tmp_file "$install_path/admin/data/tmp" "report_list" > /dev/null
        report_tmp2=$ret
        sort $md5_failed > $report_tmp1
        sort $tmp_file > $report_tmp2
 
        #report new error
        diff $report_tmp1 $report_tmp2 | grep ">" | sed -e "s:^>[ ]*::g" > $report_list
        #report resume error
        diff $report_tmp1 $report_tmp2 | grep "<" | sed -e "s:^<[ ]*::g" | awk '{print "resume",$2}' >> $report_list
        rm $report_tmp1 $report_tmp2
    else
        cat $tmp_file > $report_list
    fi

    #report_ip=192.168.1.1
    #url_head="http://$report_ip/cgi-bin/smitoneds?sCmd=ist&sTbl=pkgchgwbook&"
    #response_file="$install_path/admin/data/.md5_report.tmp"
    #wget_options="-T5 -t1 -O $response_file"
    #while read type file md5code tail
    #do
    #    echo "Report: $type $file $md5code"
    #    get_file_type $install_path/$file
    #    wget $wget_options "${url_head}sIpAddr=${ip_inner}&sPkgName=${name}&sType=${type}&sFile=${file}&sMd5=${md5code}&sFileType=${file_type}&sFileInfo=$file_info" > /dev/null 2>&1
    #    if [ $? -ne 0 ];then
    #        log "Md5 report failed: 连接请求错误"
    #    fi
    #    cat $response_file | grep "<resflg>" | grep "succ" > /dev/null
    #    if [ $? -ne 0 ];then
    #        echo "Md5 report failed: 内部请求错误"
    #    fi
    #done < $report_list

    rm $report_list $response_file 2>/dev/null
}

#verify md5sum of files
check()
{
    #check file list
    if [ ! -f "$md5_files" -o ! -f "$md5_result" ];then
        return 0
    fi

    cd $install_path

    failed_tmp=${md5_failed}.tmp.$RANDOM
    rm $failed_tmp 2>/dev/null
    touch $failed_tmp

    #check md5sum
    err_count=0
    while read FILE tail
    do
        if [ ! -f "$FILE" ];then continue;fi

        grep " ${FILE}$" $md5_result >/dev/null
        if [ $? -ne 0 ];then
            echo "new $FILE `md5sum $FILE | awk '{print $1}'`" >> $failed_tmp
            err_count=`expr $err_count + 1`
        else
            grep `md5sum $FILE` $md5_result >/dev/null 2>&1
            if [ $? -ne 0 ];then
                echo "changed $FILE `md5sum $FILE | awk '{print $1}'`" >> $failed_tmp
                err_count=`expr $err_count + 1`
            fi
        fi
    done < $md5_files

    # find deleted file
    for FILE in `cat $md5_result | awk '{print $2}'`
    do
        if [ ! -f "$FILE" ];then
            echo "deleted $FILE" >> $failed_tmp
            err_count=`expr $err_count + 1`
        fi
    done
    cd - >/dev/null

    report_change $failed_tmp

    if [ -f "$md5_failed" -a $err_count -gt 0 ];then
        md5_tmp1=`md5sum $md5_failed | awk '{print $1}'`
        md5_tmp2=`md5sum $failed_tmp | awk '{print $1}'`
        if [ "$md5_tmp1" != "$md5_tmp2" ];then
            backup_file $install_path/admin/data/backup $md5_failed
        fi
    fi

    cp $failed_tmp $md5_failed
    rm $failed_tmp 2>/dev/null

    return $err_count
}

#This function will be called when md5 verify failed
resolve_error()
{
    echo "Errors: $err_count"
    echo "---------------------------------------------------" 
    cat $md5_failed
    echo "---------------------------------------------------" 

    ##todo: add addition statment here
}

###### Main Begin ########
if [ "$1" = "--help" ];then
    print_help
    exit
fi

load_lib
check_user
check_params

get_config "md5"
md5_conf_files=$ret
if [ -f "$install_path/admin/data/md5_files.lst" ];then
    cat $install_path/admin/data/md5_files.lst >> $md5_conf_files
fi
parse_md5_files $md5_conf_files

if [ "$cmd" = "build" ];then
    build
elif [ "$cmd" = "check" ];then
    check
    if [ $? -ne 0 ];then
        resolve_error
        rm $md5_files $md5_conf_files > /dev/null 2>&1
        exit 1
    fi
else
    print_help
fi

rm $md5_files $md5_conf_files > /dev/null 2>&1
exit 0
