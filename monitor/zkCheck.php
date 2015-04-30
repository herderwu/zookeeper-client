<?php
require(dirname(dirname(__DIR__)) . '/vcb_lib/vendor/autoload.php');
require_once(dirname(dirname(__DIR__)) . '/sites/default/settings.php');

$zk = new Zookeeper\Zk();
$zk->host = $conf["solr_config"]['zookeeper_host'] ?: '127.0.0.1:2181';
$zk->reConnectTimes = 10;
$status = $zk->check();
exit($status);
