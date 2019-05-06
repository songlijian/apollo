<?php

namespace Org\Apollo\Client;
use yii\base\Action;
class SyncApollo extends Action{

    public $saveDir;    //apollo文件生成目录

    public $apolloConfig = 'apolloConfig.*';   //apollo所有文件

    public $configDir;     //需要配置的目录

    public $mainName;     //生成的配置文件名称

    public $paramsName;   //生成的参数文件名称

    public $mainTemplateName;    //配置文件模板

    public $appId;         //appid

    public $nameSpaces;   //需要加载的命名空间

    public $server;     //服务器ip

    public $logClassOb;    //日志类

    public $findRuleLeft;

    public $findRuleRight;

    public $findDir = '*';

    public $route = 'syncApollo';

    public function run(){

        $cmd = 'ps aux|grep '.$this->route .' | grep -v "grep"|wc -l';
        $ret = shell_exec("$cmd");
        $ret = rtrim($ret, "\r\n");
        $ret = trim($ret);
        if($ret !== "0") {
            return true;
        }
        $callback = function () {

            $list = glob($this->saveDir.DIRECTORY_SEPARATOR.$this->apolloConfig);
            $apollo = [];
            foreach ($list as $l) {

                $config = require $l;

                if (is_array($config) && isset($config['configurations'])) {

                    $apollo =array_merge($apollo,$config['configurations']);
                }
            }
            if (!$apollo) {
                throw new Exception('Load Apollo Config Failed, no config available');
            }
            if(!empty($this->findDir) && is_array($this->findDir)){
                $config_dirs = [];
                foreach($this->findDir as $dir){
                    $config_dirs = array_merge($config_dirs,glob($this->saveDir.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$this->configDir));
                }
            }else{
                $config_dirs = glob($this->saveDir.DIRECTORY_SEPARATOR.$this->findDir.DIRECTORY_SEPARATOR.$this->configDir);
            }
            if($config_dirs && is_array($config_dirs)){
                foreach($config_dirs as $c){
                    if(file_exists($c.DIRECTORY_SEPARATOR.$this->mainTemplateName)){
                        $m_config = file_get_contents($c.DIRECTORY_SEPARATOR.$this->mainTemplateName);
                        foreach($apollo as $k => $v){
                            $m_config = preg_replace("/(".$this->findRuleLeft.$k.$this->findRuleRight.")/",$v,$m_config);
                        }
                        file_put_contents($c.DIRECTORY_SEPARATOR.$this->mainName, $m_config);
                        file_put_contents($c.DIRECTORY_SEPARATOR.$this->paramsName, "<?php\n    return ".var_export($apollo,TRUE).";");
                    }
                } 
            }

        };

        $apolloOb = new ApolloClient($this->server, $this->appId, $this->nameSpaces);
        $apolloOb->save_dir = $this->saveDir;
        ini_set('memory_limit','256M');
        $error = $apolloOb->start($callback); 
        if($error){
            call_user_func($this->logClassOb,$error);
        }
        return true;
    }
}