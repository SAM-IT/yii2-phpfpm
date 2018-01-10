<?php
declare(strict_types=1);

class BuildControllerTest extends \Codeception\Test\Unit
{
    /**
     * @var \SamIT\Yii2\PhpFpm\controllers\BuildController
     */
    protected $controller;

    public function _before(): void
    {
        parent::_before();
        $this->controller = \Yii::$app->getModule('phpFpm')->createControllerByID('build');
    }

    // tests
    public function testBuild(): void
    {

        $this->controller->runAction('build');

    }


}
