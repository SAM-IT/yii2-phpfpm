<?php
declare(strict_types=1);

use Docker\API\Model\BuildInfo;
use Docker\API\Normalizer\NormalizerFactory;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

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


    public function testStreamDecode(): void
    {

        $stream = new \GuzzleHttp\Psr7\BufferStream();
        $serializer = new Serializer(NormalizerFactory::create(), [
            new JsonEncoder(new JsonEncode(), new JsonDecode())
        ]);
        $buildStream = new \Docker\Stream\BuildStream($stream, $serializer);

        $counter = 0;
        /** @var BuildInfo[] $frames */
        $frames = [];
        $buildStream->onFrame(function(BuildInfo $info) use (&$counter, &$frames): void {
            $counter++;
            $frames[] = $info;
        });
        $stream->write(\file_get_contents(__DIR__ . '/../data/simple.txt'));
        $buildStream->wait();
        $this->assertSame(1, $counter);



        /** @var BuildInfo $frame */
        $frame = \array_pop($frames);
//        var_dump(bin2hex($frame->getStream()));
//        var_dump(array_slice($frames, -1)[0]);
//        die();
    }


    public function testAdditionalSetters(): void
    {
        $this->module->packages = ['def'];
        $this->module->additionalPackages = ['abc'];
        $this->assertEquals(['def', 'abc'], $this->module->packages);

        $this->module->extensions = ['def'];
        $this->module->additionalExtensions = ['abc'];

        $this->module->fpmConfig = ['a' => 'c'];
        $this->module->additionalFpmConfig = ['a' => 'b'];
        $this->assertEquals(['a' => 'b'], $this->module->fpmConfig);


        $this->module->poolConfig = ['a' => 'b', 'c' => 'd'];
        $this->module->additionalPoolConfig = ['e' => 'f'];
        $this->assertEquals(['a' => 'b', 'c' => 'd', 'e' => 'f'], $this->module->poolConfig);
    }

}
