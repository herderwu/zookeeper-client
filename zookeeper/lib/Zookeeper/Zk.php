<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 12/18/14
 * Time: 10:29 AM
 */

namespace Zookeeper;

use \Zookeeper;

Class Zk
{

    public $shmKey = '999999';
    public $path = '/live_nodes';
    //public $host = '127.0.0.1:2181';
    public $host = '127.0.0.1:2181';
    public $shmReadOnly = true;
    public $reConnectTimes = 1;
    public $enableLog = false;
    public $recv_timeout = 5000;

    private $_zookeeper = false;
    private $_shm = false;

    private $shm_size = 1024;
    private $shm_mode = 0666;

    const SAME_VALUES      = 0;
    const DIFFERENT_VALUES = 1;
    const NOT_CONNECTED    = 2;
    const ZK_IS_EMPTY      = 3;
    const SHM_IS_EMPTY     = 4;

    public function __construct($host = '', $watcher_cb = null, $recv_timeout = null)
    {
        if ($host)
            $this->host = $host;

        if(!empty($recv_timeout)){
            $this->recv_timeout = $recv_timeout;
        }

        if (!$this->shmKey) {
            $this->shmKey = ftok(__FILE__, 't');
        }
    }

    /**
     * For
     */
    public function run()
    {
        $this->setShareMemory();
        $this->watch();
        while (true) {
            sleep(5);
        }
    }

    /**
     * @return int
     */
    public function check()
    {
        if (in_array($this->getZookeeper()->getState(), array(0, 999)))
            return self::NOT_CONNECTED;

        $zkv = $this->getValue();
        if(empty($zkv))
            return self::ZK_IS_EMPTY;

        $smv = $this->getShareMemory();
        if(empty($smv))
            return self::SHM_IS_EMPTY;

        if($this->checkShareMemoryWithZookeeper($smv, $zkv))
            return self::DIFFERENT_VALUES;

        return self::SAME_VALUES;
    }

    public function getSolr()
    {
        $rs = $this->getValidSolrValue($this->getShareMemory());
        if(empty($rs)){
            $rs = $this->getValidSolrValue($this->getValue());
        }
        return !empty($rs) ? $rs : array();
    }

    public function getShmValue(){
        return array(
            'Original'=> shmop_read($this->getShm(), 0, shmop_size($this->getShm())),
            'IP'=> $this->getValidSolrValue($this->getShareMemory()),
        );

    }

    private function getValidSolrValue($value){
        if(empty($value))
            return false;
        $solr = explode(',', $value);
        $rs = array();
        foreach($solr as $val){
            if(substr($val, -5) == '_solr'){
                $rs[] = 'http://' . str_replace('_', '/', $val);
            }else{
                return false;
            }
        }
        return $rs;
    }

    public function addTaskToQueue($task_content) {
        $this->set('/solrcloud/task_queue/task_', json_encode($task_content), Zookeeper::SEQUENCE);
    }

    /**
     * @param $flags Zookeeper::EPHEMERAL means that znode will be removed when client disconnect.
     * Zookeeper::SEQUENCE means that a sequence string is going to be append to every znode name.
     * value: Zookeeper::EPHEMERAL/Zookeeper::SEQUENCE/Zookeeper::EPHEMERAL | Zookeeper::SEQUENCE
     */
    public function set($path, $value, $flags = null) {
      $zk = $this->getZookeeper();
      if (!$zk->exists($path)) {
        $this->makePath($path);
        $this->makeNode($path, $value, array(), $flags);
      } else {
        $zk->set($path, $value);
      }
    }

    private function makePath($path, $value = '') {
      $zk = $this->getZookeeper();
      $parts = explode('/', $path);
      $parts = array_filter($parts);
      $subpath = '';
      while (count($parts) > 1) {
        $subpath .= '/' . array_shift($parts);
        if (!$zk->exists($subpath)) {
          $this->makeNode($subpath, $value);
        }
      }
    }
    private function makeNode($path, $value, array $params = array(), $flags = null) {
      if ($zk = $this->getZookeeper()){
        if (empty($params)) {
          $params = array(
            array(
              'perms'  => Zookeeper::PERM_ALL,
              'scheme' => 'world',
              'id'     => 'anyone',
            )
          );
        }

        return $zk->create($path, $value, $params, $flags);
      }
      return false;
    }


    public function get($path) {
      $zk = $this->getZookeeper();
      if (!$zk->exists($path)) {
        return null;
      }
      return $zk->get($path);
    }

    /**
     * List the children of the given path, i.e. the name of the directories
     * within the current node, if any
     *
     * @param string $path the path to the node
     *
     * @return array the subpaths within the given node
     */
    public function getChildren($path) {
        $zk = $this->getZookeeper();
        if (strlen($path) > 1 && preg_match('@/$@', $path)) {
            // remove trailing /
            $path = substr($path, 0, -1);
        }
        return $zk->getChildren($path);
    }

    public function deleteNode($path){
      if ($zk = $this->getZookeeper()){
        if(!$zk->exists($path))
        {
          return null;
        }
        else
        {
          return $zk->delete($path);
        }
      }
      return false;

    }

    private function getValue($watch = null)
    {
        if ($zk = $this->getZookeeper()){
            if(is_callable($watch))
                $val = $zk->getChildren($this->path, $watch);
            else
                $val = $zk->getChildren($this->path);
            if(is_array($val))
                return implode(',', $val);
        }
        return false;
    }

    private function getZookeeper()
    {
        if (!$this->_zookeeper) {
            $this->log('Create Zookeeper: ' . $this->host);
            $this->_zookeeper = new Zookeeper($this->host, null, $this->recv_timeout);
            Zookeeper::setDebugLevel(0);
        }
        $times = $this->reConnectTimes;
        while ($times--) {
            $state = $this->_zookeeper->getState();
            if ($state == Zookeeper::CONNECTED_STATE) {
                return $this->_zookeeper;
            }
            if ($times)
                sleep(1);
        }
        return $this->_zookeeper;
    }

    private function getShm()
    {
        if (!$this->_shm) {
            $this->log('Create shm: ' . $this->shmKey);
            $this->_shm = shmop_open($this->shmKey, ($this->shmReadOnly ? 'a' : "c"), $this->shm_mode, $this->shm_size);
        }
        return $this->_shm;
    }

    private function checkShareMemoryWithZookeeper($smv, $zkv)
    {
        if(!empty($smv) && !empty($zkv)){
            $smvs = explode(',', $smv);
            $zkvs = explode(',', $zkv);
            return !(!array_diff($smvs, $zkvs) && !array_diff($zkvs, $smvs));
        }
        return $smv != $zkv;
    }

    private function getShareMemory()
    {
        if ($this->getShm() === false)
            return false;
        $block = shmop_read($this->getShm(), 0, shmop_size($this->getShm()));
        if ($block && preg_match('/^\|([^|]+)\|/', $block, $match)) {
            return $match[1];
        }
        return false;
    }

    private function setShareMemory($zkv = null)
    {
        if ($this->getShm() === false)
            return false;

        $zkv = $zkv ? $zkv : $this->getValue($this->path);
        $smv = $this->getShareMemory();

        if ($this->checkShareMemoryWithZookeeper($smv, $zkv)) {
            $this->log('Set Share Memory: ' . $zkv);
            return shmop_write($this->getShm(), '|' . $zkv . '|', 0);
        }
        return false;
    }

    public function watcher($event_type, $stat, $path)
    {
        $this->log('Trigger Watch: ' . $path);
        $zkv = $this->watch();
        $this->setShareMemory($zkv);
    }

    public function watch()
    {
        $this->log('Register Watch: ' . $this->path);
        return $this->getValue(array($this, 'watcher'));
    }

    private function log($msg, $errorCode = 0)
    {
        if (!$this->enableLog)
            return;

        $type = array('Info', 'Error');
        echo "[$type[$errorCode]] " . $msg . PHP_EOL;
    }

    public function shutdown()
    {
        if ($this->getShm() !== false)
            shmop_close($this->getShm());
        $this->log('Shutdown', 1);
        exit(0);
    }
}
