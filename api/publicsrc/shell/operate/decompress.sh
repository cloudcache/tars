#!/bin/bash
PATH="/usr/bin:.:$PATH"
export $PATH

dest_file=$1
if [ ! -f $dest_file ];then
    echo "result%%failed%%$dest_file not exit"
    exit 1
fi

echo $dest_file | grep -q -E "\.tar\.gz$|\.tgz$"
if [ $? -ne 0 ];then
    echo "result%%failed%%$dest_file not tar.gz, tgz"
    exit 1
fi

old_pwd=`pwd`

cd $(dirname $dest_file)
tar zxf $dest_file
if [ $? -eq 0 ];then
    rm $(basename $dest_file)
else
    echo "result%%decompress failed"
    exit 1
fi

cd $old_pwd

echo "result%%success"
