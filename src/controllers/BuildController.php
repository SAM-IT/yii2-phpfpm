<?php


namespace SamIT\Yii2\PhpFpm\controllers;


use Docker\API\Model\BuildInfo;
use Docker\Context\Context;
use Docker\Manager\ImageManager;
use SamIT\Yii2\PhpFpm\Module;
use yii\console\Controller;
use Docker\Docker;

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
        $buildDir = sys_get_temp_dir() . '/build' . date('Y-m-d\TH:i:s');
        mkdir($buildDir, true);
        file_put_contents($buildDir . '/php-fpm.conf', $this->module->createFpmConfig());
        file_put_contents($buildDir . '/entrypoint.sh', $this->module->createEntrypoint());
        chmod($buildDir . '/entrypoint.sh', "0755");
        file_put_contents($buildDir . '/Dockerfile', $this->module->createDockerFile());
        $docker = new Docker();
        $context = new Context($buildDir);
        passthru('ls -la $buildDir');
        $buildStream = $docker->getImageManager()->build($context->toStream(), [], ImageManager::FETCH_STREAM);
        $buildStream->onFrame(function(BuildInfo $buildInfo) {
            echo $buildInfo->getStream();
        });

        $buildStream->wait();
    }




}