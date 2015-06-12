<?php
/**
 * Created by PhpStorm.
 * User: lynn
 * Date: 15-6-12
 * Time: 下午2:26
 */

namespace lynn\swoole\yii;


use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\web\Application;

class AppServer extends Component
{
    const EVENT_ON_INIT=0;
    const EVENT_BEFORE_WORKER_START=1;
    const EVENT_AFTER_WORKER_START=2;
    const EVENT_BEFORE_REQUEST=3;
    const EVENT_AFTER_REQUEST=4;
    const EVENT_BEFORE_WORKER_STOP=5;
    const EVENT_AFTER_WORKER_STOP=6;

    const STATUS_INIT=0;
    const STATUS_WORK_START=1;
    const STATUS_REQUEST_START=2;
    const STATUS_REQUEST_END=3;


    static $status=0;
    /**
     * @var array yii configure array
     */
    public $yiiConfig=[];
    /**
     * @var array persistent object name over request
     */
    public $persistent=[];

    /**
     * @var \swoole_http_server
     */
    public $server;


    public $application;

    /**
     * @var array persistent object
     */
    private $_persistentObject=[];
    /**
     * @var AppServer
     */
    private static $_instance;


    public $ip='0.0.0.0';
    public $port=9501;
    public $mode=3;
    public $tcp_or_udp=1;



    /**
     * @param array $config
     * @throws \Exception
     */
    public function __construct($config=[]){
        if(!isset($config['yii'])){
            throw new \Exception("No yii configure");
        }else{
            $this->yiiConfig=$config['yii'];
            unset($config['yii']);
        }if(!isset($config['worker'])){
            throw new \Exception("No swoole server configure");
        }
        $swooleConfig=isset($config['worker'])?$config['worker']:[];
        unset($config['worker']);
        parent::__construct($config);

        $this->server=new \swoole_http_server($this->ip,$this->port,$this->mode,$this->tcp_or_udp);
        $this->server->set($swooleConfig);
        self::$instance=$this;
        $this->init();
    }

    /**
     * init AppServer
     */
    public function init(){
        AppServer::$status=self::STATUS_INIT;
        $this->server->on('WorkerStart', [$this, 'startWorker']);
        $this->server->on('WorkerStop',[$this,'stopWorker']);
        $this->server->on('request', [$this, 'process']);
        $this->persistent=isset($this->yiiConfig['persistent'])?$this->yiiConfig['persistent']:[];
        $this->trigger(self::EVENT_ON_INIT);
    }

    /**
     * get AppServer Instance
     * @return AppServer
     * @throws \Exception
     */
    public static function getInstance(){
        if(self::$_instance==null){
            throw new \Exception('configure error,no instance!');
        }else{
            return self::$_instance;
        }

    }

    public function startWorker($server, $worker_id)
    {
        $this->trigger(self::EVENT_BEFORE_WORKER_START);
        AppServer::$status=self::STATUS_WORK_START;
        $config=[];
        foreach($this->yiiConfig['configList'] as $fileList){
            $config=ArrayHelper::merge($config,require($fileList));
        }
        foreach($this->yiiConfig['require'] as $fileList){
            require($fileList);
        }
        $this->yiiConfig=$config;
        $this->trigger(self::EVENT_AFTER_WORKER_START);
    }

    /**
     * @param $server
     * @param $worker_id
     */
    public function stopWorker($server,$worker_id){
        $this->trigger(self::EVENT_BEFORE_WORKER_STOP);
        $this->trigger(self::EVENT_AFTER_WORKER_STOP);
    }


    /**
     * @param \swoole_http_request $request
     * @param \swoole_http_response $response
     */
    public function process(\swoole_http_request $request, \swoole_http_response $response)
    {
        AppServer::$status=self::STATUS_REQUEST_START;
        $this->trigger(self::EVENT_BEFORE_REQUEST);
        $request->server=array_change_key_case($request->server,CASE_UPPER);
        $request->server['SCRIPT_FILENAME'] = 'index.php';
        $request->server['SCRIPT_NAME'] = 'index.php';
        unset($request->server['PHP_SELF']);
        $request->setGlobal();
        $config=$this->yiiConfig;
        foreach($this->persistent as $name){
            if(isset($this->_persistentObject[$name])){
                unset($config['components'][$name]);
                $config['components'][$name]=$this->_persistentObject[$name];
            }
        }
        $config['components']['response']['swoole']=$response;
        $app=new $this->application($config);
        $app->run();
        $this->trigger(self::EVENT_AFTER_REQUEST);
        foreach($this->persistent as $name){
            if(!isset($this->_persistentObject[$name])){
                $this->_persistentObject[$name]=$app[$name];
            }
        }
    }

}