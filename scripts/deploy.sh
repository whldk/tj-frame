#!/bin/bash

SCRIPT="$0"
echo "script:${SCRIPT}"

SCRIPT_FOLDER=$(dirname $(readlink -f "$SCRIPT"))
echo "path:${SCRIPT_FOLDER}"

echo "start deploy crotab:"

if [ ! -e /var/spool/cron/ ];then
	mkdir -p /var/spool/cron/
fi

#创建定时文件
touch /var/spool/cron/root

#添加执行权限
chown root "${SCRIPT_FOLDER}/cron.sh"
chgrp root "${SCRIPT_FOLDER}/cron.sh"
chmod 754 "${SCRIPT_FOLDER}/cron.sh"

#删除已有的定时任务
SCRIPT_FOLDER_TMP=${SCRIPT_FOLDER//\//\\\/}
echo "sed -i /${SCRIPT_FOLDER_TMP}\/cron.sh/d /var/spool/cron/root"
sed -i "/${SCRIPT_FOLDER_TMP}\/cron.sh/d" /var/spool/cron/root

#添加定时任务
echo "*/1 * * * * bash ${SCRIPT_FOLDER}/cron.sh >/dev/null 2>&1" >> /var/spool/cron/root

#重载配置
systemctl restart crond
systemctl crond reload
systemctl status crond

#显示配置
crontab -u root -l

echo "done"