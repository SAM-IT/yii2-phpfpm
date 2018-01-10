<?php
declare(strict_types=1);

class MigrateControllerTest extends \Codeception\Test\Unit
{
    /**
     * @var \SamIT\Yii2\PhpFpm\controllers\BuildController
     */
    protected $controller;

    public function _before(): void
    {
        parent::_before();
        $this->controller = \Yii::$app->getModule('phpFpm')->createControllerByID('migrate');
    }

    // tests
    public function testBeforeActionNoLock(): void
    {
        $this->controller->module->migrationsUseMutex = true;
        $action = $this->controller->createAction('up');
        $this->expectOutputRegex('/Waiting for lock.*FAIL/');
        $this->assertFalse($this->controller->beforeAction($action));
   }


}
