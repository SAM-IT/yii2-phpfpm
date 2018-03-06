<?php
declare(strict_types=1);

namespace SamIT\Yii2\PhpFpm\controllers;


use Docker\API\Model\BuildInfo;
use Docker\Context\Context;
use Docker\Context\ContextBuilder;
use Docker\Docker;
use Docker\Stream\BuildStream;
use SamIT\Yii2\PhpFpm\Module;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Class MigrateController
 * @package SamIT\Yii2\PhpFpm\controllers
 * @property Module $module
 */
class MigrateController extends \yii\console\controllers\MigrateController
{

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

    public function beforeAction($action)
    {
        // Obtain lock.
        $this->stdout('Waiting for lock...', Console::FG_CYAN);
        if (!$this->module->getLock()) {
            $this->stdout("FAIL\n", Console::FG_RED);
            return false;
        }

        $this->stdout("OK\n", Console::FG_GREEN);
        return true;
    }

    public function actionUp($limit = 0)
    {
        $command = "/project/{$this->module->getConsoleEntryScript()} migrate/up $limit";
        $result = ExitCode::OK;
        \passthru($command, $result);
        return $result;
    }





}