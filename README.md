 zookeeper of composer

#shell monitor
zk_monitor.sh(this file by jerry, other by ben)
run zkCheck.php, get the result code, restart zkWatch.php when neccssary
when zkWatch not run, or sharememory and zookeeper not same
#Zookeeper watcher monitor
*/1 * * * * /bin/sh zk_monitor.sh > /dev/null 2>&1


#zookeeper
1. php share memory: shmop
2. php zookeeper get
3. php zookeeper set(except this code, other code is write by ben)

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
