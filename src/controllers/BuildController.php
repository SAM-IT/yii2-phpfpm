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
        $buildStream = $docker->imageBuild($context->toStream(), [
            't' => $this->module->image
        ], Docker::FETCH_STREAM);
        $buildStream->onFrame(function(BuildInfo $buildInfo) {
            echo $buildInfo->getStream();
        });

        $buildStream->wait();
        echo "Wait finished\n";
        $buildStream->wait();

    }




}