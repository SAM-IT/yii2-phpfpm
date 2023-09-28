<?php

declare(strict_types=1);

namespace SamIT\Yii2\PhpFpm\controllers;

use SamIT\Docker\Context;
use SamIT\Yii2\PhpFpm\Module;
use Symfony\Component\Filesystem\Filesystem;
use yii\console\Controller;
use yii\helpers\Console;

/**
 * Class BuildController
 * @package SamIT\Yii2\PhpFpm\controllers
 * @property Module $module
 */
final class BuildController extends Controller
{
    public $defaultAction = 'create-context';

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
        $sourcePath = \Yii::getAlias('@app');
        if (!$sourcePath) {
            throw new \RuntimeException('Could not find source path');
        }
        $this->module->createBuildContext($context, $sourcePath);

        $filesystem->mirror($context->getDirectory(), $targetPath);
    }

    public function actionTestClient(): void
    {
        $this->stdout("It seems the console client works!\n", Console::FG_GREEN);
    }

    public function options($actionID): array
    {
        $result = parent::options($actionID);
        switch ($actionID) {
            case 'create-context':
            case 'build':
                $result[] = 'tag';
                break;
        }
        return $result;
    }

    public function stdout($string): int
    {
        if ($this->isColorEnabled()) {
            $args = \func_get_args();
            \array_shift($args);
            $string = Console::ansiFormat($string, $args);
        }

        return \strlen($string);
    }
}
