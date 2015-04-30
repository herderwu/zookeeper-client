#!/usr/bin/php
host=$(hostname)
currentpath=$(cd "$(dirname "$0")"; pwd)"/"
mail="abc@qq.com"
watchname="zkWatch.php"
watchpath=${currentpath}${watchname}
checkpath=${currentpath}"zkCheck.php"
count=`ps ef | grep ${watchname} | grep -v grep | wc -l`
php -f ${checkpath}
isequal=$?
if [ $count -gt 0 ]; then
  if [ $isequal -gt 0 ]
  then
   kill -s 15 `ps aux | grep ${watchname} | grep -v grep |awk '{print $2}'`
   sleep 1s
   nohup php ${watchpath} >/dev/null 2>&1 &
  fi
  #mail	if status > 0
  if [ $isequal -eq 1 ]
  then
    echo "restarted zookeeper watcher"|mailx -s ${host}" - Share memory value was incorrect" ${mail}
  elif [ $isequal -eq 2 ]
  then
    echo "restarted zookeeper watcher"|mailx -s ${host}" - Zookeeper call failed" ${mail}
  elif [ $isequal -eq 3 ]
  then
    echo "restarted zookeeper watcher"|mailx -s ${host}" - Solr service was unvailable" ${mail}
  elif [ $isequal -eq 4 ]
  then
    echo "restarted zookeeper watcher"|mailx -s ${host}" - Share memory was unvailable" ${mail}
  fi
else
  nohup php ${watchpath} >/dev/null 2>&1 &
  echo "started zookeeper watcher"|mailx -s ${host}" - Zookeeper watcher not found" ${mail}
fi
