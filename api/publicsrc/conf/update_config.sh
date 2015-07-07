#!/bin/sh
org_ip="192.168.1.1"
inner_ip=`/sbin/ifconfig eth0 2>/dev/null |grep "inet addr:"|awk -F ":" '{ print $2 }'|awk '{ print $1 }'`
if [ "${inner_ip}x" = "x" ];then
   inner_ip=`/sbin/ip route|egrep 'src 172\.|src 10\.'|awk '{print $9}'|head -n 1`
fi
config_list='/data/webroot_tars/tars.qq.com/pkg_opensrc/command/config.php
/data/webroot_tars/tars.qq.com/pkg_opensrc/publicsrc/conf/Conf.ini
/data/webroot_tars/tars.qq.com/pkg_opensrc/pkgworker/conf/Conf.ini
/data/webroot_tars/tars.qq.com/open-pkg/tars.ini
/data/pkg/pkg_home/pkg_tools/report.conf
/data/pkg/pkg_home/pkg_tools/rsyncd.conf'
for file in $config_list
do
    sed -i "s/$org_ig/$inner_ip/g" $file
done


