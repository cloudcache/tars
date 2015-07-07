#!/bin/sh
while true 
    do 
    num=`ps -ef |grep pkgWorker|grep -v grep|wc -l`
    if [[ $num -lt 50 ]] 
    then 
        ./dealQueueTasks.php >/dev/null 2>&1 &  
    else 
        break
    fi
done
