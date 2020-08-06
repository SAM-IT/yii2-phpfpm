<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\MockObject\Stub\Stub;
use SamIT\Docker\Context;
use yii\base\UnknownPropertyException;

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
    public function testBuildInvalidSourceDir(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->module->createBuildContext(new Context(), __FUNCTION__, '/test/does/not/exist');
    }

    public function testBuildEntryscriptOutsideSourceDir(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->module->createBuildContext(new Context(), __FUNCTION__, '/tmp');
    }

    public function testBuildInitializationCommands(): void
    {
        $this->module->initializationCommands[] = $test = md5('cool stuff');
        $stub = $this->createMock(Context::class);
        $files = [];
        $stub->method('add')->will($this->returnCallback(function (string $file, string $source) use (&$files) {
            $files[$file] = $source;
        }));

        $stub->method('entrypoint')->will($this->returnCallback(function (array $entrypoint) use (&$entryFile) {
            $entryFile = $entrypoint[0];
        }));

        $this->module->createBuildContext($stub, __FUNCTION__, dirname(\Yii::getAlias('@app')));
        $this->assertArrayHasKey($entryFile, $files);
        $this->assertStringContainsString($test, $files[$entryFile]);
    }
    public function testBuildMandatoryVariablesInEntrypoint(): void
    {
        $this->module->environmentVariables[] = $test = md5('abc');
        $stub = $this->createMock(Context::class);
        $files = [];
        $stub->method('add')->will($this->returnCallback(function (string $file, string $source) use (&$files) {
            $files[$file] = $source;
        }));

        $stub->method('entrypoint')->will($this->returnCallback(function (array $entrypoint) use (&$entryFile) {
            $entryFile = $entrypoint[0];
        }));

        $this->module->createBuildContext($stub, __FUNCTION__, dirname(\Yii::getAlias('@app')));
        $this->assertArrayHasKey($entryFile, $files);
        $this->assertStringContainsString($test, $files[$entryFile]);
    }

    public function testSpecialSetters(): void
    {
        $this->module->extensions = ['test'];
        $this->module->additionalExtensions = ['abc'];
        $this->assertSame(['test', 'abc'], $this->module->extensions);

        $this->module->poolConfig = ['d' => 'b'];
        $this->module->additionalPoolConfig = ['b' => 'c'];
        $this->assertSame(['d' => 'b', 'b' => 'c'], $this->module->poolConfig);

        // Test the standard yii setter
        $this->module->basePath = '/tmp';
        $this->assertSame('/tmp', $this->module->getBasePath());
    }

    public function testInvalidSpecialSetter(): void
    {
        $this->expectException(UnknownPropertyException::class);
        $this->module->additionalInvalid = ['abc'];
    }

    public function testBuild(): void
    {

        $this->module->createBuildContext($context = new Context(), __FUNCTION__, dirname(\Yii::getAlias('@app')));
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
