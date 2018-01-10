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
        $stream = $this->controller->createBuildStream([]);
        $stream->onFrame(function(BuildInfo $frame): void {
            codecept_debug($frame->getStream());
            $this->assertSame('', $frame->getError());
        });
        $stream->wait();
    }


}
