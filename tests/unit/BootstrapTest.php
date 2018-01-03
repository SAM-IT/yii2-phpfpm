<?php


use Codeception\Test\Unit;

class BootstrapTest extends Unit
{
    // tests
    public function testModuleLoaded()
    {
        $modules = \Yii::$app->getModules();
        $this->assertNotEmpty($modules);
    }
}
