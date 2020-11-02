<?php
declare(strict_types=1);

namespace SamIT\Yii2\PhpFpm\controllers;

use SamIT\Docker\Context;
use SamIT\Docker\Docker;
use SamIT\Yii2\PhpFpm\Module;
use Symfony\Component\Filesystem\Filesystem;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\helpers\Console;

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

    /**
     * @param string $targetPath The path where the docker build context should be stored
     */
    public function actionCreateContext(string $targetPath): void
    {
        $filesystem = new Filesystem();
        if (!is_dir($targetPath)) {
            $filesystem->mkdir($targetPath);
        }

        $context = new Context();
        $this->module->createBuildContext($context, $this->tag, \Yii::getAlias('@app'));

        $filesystem->mirror($context->getDirectory(), $targetPath);
    }

    public function actionBuild(): void
    {
        if ($this->push && !isset($this->image)) {
            throw new InvalidConfigException("When using the push option, you must configure or provide image");
        }

        $params = [];

        if (isset($this->image)) {
            $params['t'] = "{$this->image}:{$this->tag}";
        }

        $context = new Context();
        $this->module->createBuildContext($context, $this->tag, \Yii::getAlias('@app'));

        $docker = new Docker();
        $docker->build($context, "{$this->image}:{$this->tag}");

        if ($this->push) {
            $docker->push("{$this->image}:{$this->tag}");
        }
    }

    public function actionTestClient(): void
    {
        $this->stdout("It seems the console client works!\n", Console::FG_GREEN);
    }


    public function options($actionID)
    {

        $result = parent::options($actionID);
        switch ($actionID) {
            case 'build':
                $result[] = 'push';
                $result[] = 'image';
                $result[] = 'tag';
                break;
        }
        return $result;
    }

    public function optionAliases()
    {
        $result = parent::optionAliases();
        $result['p'] = 'push';
        $result['t'] = 'tag';
        $result['i'] = 'image';
        return $result;
    }

    public function stdout($string)
    {
        if ($this->isColorEnabled()) {
            $args = \func_get_args();
            \array_shift($args);
            $string = Console::ansiFormat($string, $args);
        }

        echo $string;
        return \strlen($string);
    }
}
