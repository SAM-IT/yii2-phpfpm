<?php


namespace SamIT\Yii2\PhpFpm\controllers;


use Docker\API\Model\BuildInfo;
use Docker\Context\Context;
use Docker\Context\ContextBuilder;
use Docker\Docker;
use Docker\Stream\BuildStream;
use SamIT\Yii2\PhpFpm\Module;
use yii\console\Controller;

/**
 * Class BuildController
 * @package SamIT\Yii2\PhpFpm\controllers
 * @property Module $module
 */
class BuildController extends Controller
{
    public $defaultAction = 'build';

    public function actionBuild()
    {
        $docker = Docker::create();

        $context = $this->module->createBuildContext();
        /** @var BuildStream $buildStream */
        $buildStream = $docker->imageBuild($context->toStream(), [], Docker::FETCH_STREAM);
        $buildStream->onFrame(function(BuildInfo $buildInfo) {
            echo "stream: " . $buildInfo->getStream() . "\n";
            echo "status: " . $buildInfo->getStatus() . "\n";
            echo "error: " . $buildInfo->getError() . "\n";
            if ($buildInfo->getErrorDetail() !== null) {
                echo "detail: " . $buildInfo->getErrorDetail()->getCode() . ' - ' . $buildInfo->getErrorDetail()->getMessage() . "\n";
            }
            echo "\n";
        });

        $buildStream->wait();
        echo "Wait finished\n";
        $buildStream->wait();

    }




}