#!/bin/bash
ip_inner=`/sbin/ifconfig eth1 2>/dev/null |grep "inet addr:"|awk -F ":" '{ print $2 }'|awk '{ print $1 }'`

cur_path=$(dirname $(which $0))


install_path=$1
if [ -z $install_path ];then
    echo "result%%failed%%${ip_inner}%%missing install_path"
    exit 1
fi

if [ ! -d $install_path ];then
    echo "result%%failed%%${ip_inner}%%install_path:$install_path not existes"
    exit 1
fi

if [ ! -f $install_path/init.xml ];then
    echo "result%%failed%%${ip_inner}%%init.xml not existes"
    exit 1
fi

user_name=$(cat $install_path/init.xml | grep '^user=\"*\"' | head -n 1 | sed -e 's/user=//g' -e 's/^\"//g' -e 's/\"$//g')


source $cur_path/report.conf
sed -i s/report_ip=192.168.1.1/report_ip="${rpt_ip}:${rpt_port}"/ $install_path/admin/uninstall.sh
#su $user_name -c "$cur_path/runinstall_pkg.exp $install_path"
sed -i 's:rpt_info$:#rpt_info:' $install_path/admin/uninstall.sh
if [ `whoami` = $user_name ];then
    echo 'yes' | $install_path/admin/uninstall.sh all
else 
    su $user_name -c "echo 'yes' | $install_path/admin/uninstall.sh all"
fi

result="success"
if [ -d $install_path ];then
    result="failed"
fi

cd /usr/local
ls -l | grep "^l" | grep '\->' | awk '{print $(NF-2), $NF}' > /tmp/uninstall.sh.$$
while read tdir ldir tail
do
    if [ -L "$tdir" -a ! -e "$ldir" ];then
        rm $tdir
    fi  
done < /tmp/uninstall.sh.$$

cd /usr/local/tars
ls -l | grep "^l" | grep '\->' | awk '{print $(NF-2), $NF}' > /tmp/uninstall.sh.$$
while read tdir ldir tail
do
    if [ -L "$tdir" -a ! -e "$ldir" ];then
        rm $tdir
    fi  
done < /tmp/uninstall.sh.$$

rm /tmp/uninstall.sh.$$

echo "result%%${result}%%${ip_inner}%%${result}%%"


