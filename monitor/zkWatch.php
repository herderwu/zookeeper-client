<?php
require(dirname(dirname(__DIR__)) . '/vcb_lib/vendor/autoload.php');
require_once(dirname(dirname(__DIR__)) . '/sites/default/settings.php');

$zk = new Zookeeper\Zk();
register_shutdown_function(array($zk, 'shutdown'));
$zk->host = $conf["solr_config"]['zookeeper_host'] ?: '127.0.0.1:2181';
$zk->shmReadOnly    = false;
$zk->reConnectTimes = 3600;
$zk->enableLog = true;
$zk->run();
