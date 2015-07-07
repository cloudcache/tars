#!/bin/bash
PATH="/usr/bin:.:$PATH"
export $PATH

cur_path=$(dirname $(basename $0))

dest_file=$1
if [ ! -f $dest_file ];then
    echo "result%%failed%%$dest_file not exit"
    exit 1
fi

echo $dest_file | grep -q -E "\.tar\.gz$|\.tgz$"
if [ $? -eq 0 ];then
    tar -tvf $dest_file > tar_file_list.$$ 
    while read type user size wDdate wTime file tail
    do
        echo $type | grep -q "^d"
        if [ $? -eq 0 ];
        then
            echo $file | grep "\.svn/$"
            if [ $? -eq 0 ];then
                #压缩包中不能存在.svn目录
                echo "result%%failed%%$dest_file contains .svn." 
                exit 2 
            fi
        fi
        echo $type | grep -q "^p"
        if [ $? -eq 0 ];then
            #压缩包中不能存在管道文件
            echo "result%%failed%%$dest_file contains p type:$file"
            exit 3 
        fi
    done < tar_file_list.$$ 
    rm tar_file_list.$$
fi

echo "result%%success%%$dest_file" 
exit 0
