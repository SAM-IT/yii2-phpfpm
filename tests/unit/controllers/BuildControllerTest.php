<?php
declare(strict_types=1);

use Docker\API\Model\BuildInfo;

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
    public function testCreateBuildStream(): void
    {
        $stream = $this->controller->createBuildStream([], __FUNCTION__);
        $stream->onFrame(function(BuildInfo $frame): void {
            $this->assertNull($frame->getError());
        });
        $stream->wait();
    }

    public function testBuildNoAuth(): void
    {
        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->controller->run('build');
    }

    public function testBuildInvalidAuth(): void
    {
        $this->expectExceptionMessageRegExp('/unauthorized: incorrect username or password/');
        /** @var \SamIT\Yii2\PhpFpm\controllers\BuildController $controller */
        $controller = \Yii::$app->getModule('phpFpm')->createControllerByID('build');
        $controller->user = 'test';
        $controller->password = 'password';

        $this->controller->run('build', ['user' => \md5(\random_bytes(5)), 'password' => \md5(\random_bytes(5))]);
    }

}
