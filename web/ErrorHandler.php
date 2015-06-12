<?php
/**
 * Created by PhpStorm.
 * User: lynn
 * Date: 15-6-12
 * Time: 下午3:57
 */

namespace lynn\swoole\yii\web;

use lynn\swoole\yii\AppServer;
use Yii;
use yii\web\HttpException;

class ErrorHandler extends \yii\web\ErrorHandler
{

    /**
     * @var string Used to reserve memory for fatal error handler.
     */
    private $_memoryReserve;

    /**
     * Register this error handler
     */
    public function register()
    {
        ini_set('display_errors', false);
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
        if ($this->memoryReserveSize > 0) {
            $this->_memoryReserve = str_repeat('x', $this->memoryReserveSize);
        }
        AppServer::getInstance()->on(AppServer::EVENT_AFTER_REQUEST,[[$this, 'handleFatalError']]);
    }


    protected function renderException($exception)
    {

        if (AppServer::$status == AppServer::STATUS_REQUEST_START) {
            if (Yii::$app->has('response')) {
                $response = Yii::$app->getResponse();
                $response->isSent = false;
            } else {
                $response = new Response();
            }
            $useErrorView = $response->format === Response::FORMAT_HTML && (!YII_DEBUG || $exception instanceof UserException);
            if ($useErrorView && $this->errorAction !== null) {
                $result = Yii::$app->runAction($this->errorAction);
                if ($result instanceof Response) {
                    $response = $result;
                } else {
                    $response->data = $result;
                }
            } elseif ($response->format === Response::FORMAT_HTML) {
                if (YII_ENV_TEST || isset(Yii::$app->request->swoole->server['HTTP_X_REQUESTED_WITH']) && Yii::$app->request->swoole->server['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    // AJAX request
                    $response->data = '<pre>' . $this->htmlEncode($this->convertExceptionToString($exception)) . '</pre>';
                } else {
                    // if there is an error during error rendering it's useful to
                    // display PHP error in debug mode instead of a blank screen
                    if (YII_DEBUG) {
                        ini_set('display_errors', 1);
                    }
                    $file = $useErrorView ? $this->errorView : $this->exceptionView;
                    $response->data = $this->renderFile($file, [
                        'exception' => $exception,
                    ]);
                }
            } elseif ($response->format === Response::FORMAT_RAW) {
                $response->data = $exception;
            } else {
                $response->data = $this->convertExceptionToArray($exception);
            }

            if ($exception instanceof HttpException) {
                $response->setStatusCode($exception->statusCode);
            } else {
                $response->setStatusCode(500);
            }
            $response->send();
        } else {
            Yii::error($exception, 'Exception');
        }
    }

}