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
class MigrateController extends Controller
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

    /**
     * Does a migration after acquiring a global lock
     * @param int $limit
     * @return int
     * @throws \yii\base\InvalidConfigException
     * @see \yii\console\controllers\MigrateController::migrateUp()
     */
    public function actionUp($limit = 0)
    {
        $command = "/project/{$this->module->getConsoleEntryScript()} migrate/up";
        if ($limit > 0) {
            $command .= " $limit";
        }

        if (!$this->interactive) {
            $command .= ' --interactive=0';
        }
        $this->stdout("Executing: $command\n", Console::FG_CYAN);
        $result = ExitCode::OK;
        \passthru($command, $result);
        return $result;
    }





}