#!/bin/bash

# PATH="/usr/bin:.:$PATH"
# export $PATH
export PATH=".:"$PATH
path=$1

if [ "x$path" = "x" ];then
    echo "you should put the 'path' param."
    exit 1
fi

if [ ! -d $path ];then
    echo "$path is not dir."
    exit 1
fi

cd $path

#检查是否存在管道文件/块设备文件/字符设备文件
c=`find . -type p | wc -l`
if [ "$c" != '0' ];then
    echo "目录中存在管道文件，请检查。"
    exit 1
fi

c=`find . -type b | wc -l`
if [ "$c" != '0' ];then
    echo "目录中存在块设备文件，请检查。"
    exit 1
fi

c=`find . -type c | wc -l`
if [ "$c" != '0' ];then
    echo "目录中存在字符设备文件，请检查。"
    exit 1
fi

exit 0
