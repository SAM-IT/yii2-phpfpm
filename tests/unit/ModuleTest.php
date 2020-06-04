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

        $context = $this->module->createBuildContext(__FUNCTION__);
        $directory = $context->getDirectory();

        $dockerFile = file_get_contents("$directory/Dockerfile");

        $fileName = \preg_replace('#.*ADD (.+?) /php-fpm\.conf.*#s', '$2', $dockerFile);
        $this->assertFileExists($directory . '/' . $fileName);

        $fileName = \preg_replace('#.*ADD (.+?) /entrypoint\.sh.*#s', '$2', $dockerFile);
        $this->assertFileExists($directory . '/' . $fileName);

    }

    public function testAdditionalSetters(): void
    {
        $this->module->extensions = ['def'];
        $this->module->additionalExtensions = ['abc'];

        $this->module->fpmConfig = ['a' => 'c'];
        $this->module->additionalFpmConfig = ['a' => 'b'];
        $this->assertSame(['a' => 'b'], $this->module->fpmConfig);


        $this->module->poolConfig = ['a' => 'b', 'c' => 'd'];
        $this->module->additionalPoolConfig = ['e' => 'f'];
        $this->assertSame(['a' => 'b', 'c' => 'd', 'e' => 'f'], $this->module->poolConfig);
    }

}
