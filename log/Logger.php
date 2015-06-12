<?php
/**
 * Created by PhpStorm.
 * User: lynn
 * Date: 15-6-12
 * Time: 下午3:29
 */

namespace lynn\swoole\yii\log;

use lynn\swoole\yii\AppServer;

class Logger extends \yii\log\Logger
{
    public function init(){

        AppServer::getInstance()->on(AppServer::EVENT_AFTER_WORKER_STOP,[$this,'flush',true]);
    }
}