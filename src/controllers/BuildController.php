<?php
declare(strict_types=1);

namespace SamIT\Yii2\PhpFpm\controllers;


use Docker\API\Model\BuildInfo;
use Docker\Context\Context;
use Docker\Context\ContextBuilder;
use Docker\Context\ContextInterface;
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

    /**
     * @var string The name of the created image
     * If not explicitly set will take its default from module config.
     */
    public $image;

    /**
     * @var string The tag of the created image
     * If not explicitly set will take its default from module config.
     */
    public $tag;

    /**
     * @var bool whether to push the image after a successful build.
     * If not explicitly set will take its default from module config.
     */
    public $push;

    public function init(): void
    {
        parent::init();
        $this->push = $this->module->push;
        $this->image = $this->module->image;
        $this->tag = $this->module->tag;
    }

    public function actionBuild(): void
    {


        $params = [];
        if (isset($this->image)) {
            $params['t'] = "{$this->image}:{$this->tag}";
        }
        $buildStream = $this->createBuildStream($params);

        $buildStream->onFrame(function(BuildInfo $buildInfo): void {
            echo $buildInfo->getStream();
        });

        $buildStream->wait();
        echo "Wait finished\n";
        $buildStream->wait();
    }

    public function createBuildStream(array $params = []): BuildStream
    {
        $docker = Docker::create();
        $context = $this->module->createBuildContext();

        /** @var BuildStream $buildStream */
        $buildStream = $docker->imageBuild($context->toStream(), $params, Docker::FETCH_STREAM);
        return $buildStream;
    }

    public function options($actionID)
    {

        $result = parent::options($actionID);
        switch ($actionID) {
            case 'build':
//                $result[] = 'push';
                $result[] = 'image';
                $result[] = 'tag';
                break;

        }
        return $result;
    }

    public function optionAliases()
    {
        $result = parent::optionAliases();
//        $result['p'] = 'push';
        $result['t'] = 'tag';
        $result['i'] = 'image';
        return $result;
    }


}