<?php
declare(strict_types=1);

class ModuleTest extends \Codeception\Test\Unit
{
    /**
     * @var \SamIT\Yii2\PhpFpm\Module
     */
    protected $module;

    public function _before(): void
    {
        parent::_before();
        $this->module = \Yii::$app->getModule('phpFpm');
    }

    // tests
    public function testBuild(): void
    {

        $context = $this->module->createBuildContext();
        $directory = $context->getDirectory();

        $dockerFile = $context->getDockerfileContent();

        $fileName = \preg_replace('#.*ADD (.+?) /php-fpm\.conf.*#s', '$2', $dockerFile);
        $this->assertFileExists($directory . '/' . $fileName);

        $fileName = \preg_replace('#.*ADD (.+?) /entrypoint\.sh.*#s', '$2', $dockerFile);
        $this->assertFileExists($directory . '/' . $fileName);


        new \SamIT\Yii2\PhpFpm\ModuleBootstrap();
    }

    public function testNoMutex(): void
    {
        $this->assertFalse($this->module->getLock(1));
    }

    public function testMutex(): void
    {
        $this->module->setComponents([
            'mutex' => [
                'class' => \yii\mutex\FileMutex::class
            ]
        ]);
        $this->assertTrue($this->module->getLock(1));
        // Test you can't get 2.
        $start = \microtime(true);

        $this->assertFalse($this->module->getLock(2));

        $this->assertGreaterThan($start + 1, \microtime(true));

    }

}
