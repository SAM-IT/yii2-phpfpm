<?php

declare(strict_types=1);

namespace tests;

use SamIT\Docker\Context;
use yii\base\UnknownPropertyException;

final class ModuleTest extends \Codeception\Test\Unit
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
        $this->module->createBuildContext(new Context(), __FUNCTION__, __DIR__);
    }

    public function testSpecialSetters(): void
    {
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
        $this->module->createBuildContext($context = new Context(), dirname(\Yii::getAlias('@app')));
        $directory = $context->getDirectory();


        $dockerFile = file_get_contents("$directory/Dockerfile");

        $fileName = \preg_replace('#.*ADD (.+?) /php-fpm\.conf.*#s', '$2', $dockerFile);
        $this->assertFileExists($directory . '/' . $fileName);

        $fileName = \preg_replace('#.*ADD (.+?) /entrypoint\.sh.*#s', '$2', $dockerFile);
        $this->assertFileExists($directory . '/' . $fileName);
    }

    public function testBuildUsesConfiguredBaseImage(): void
    {
        $this->module->baseImage = 'test1234:5678';
        $this->module->createBuildContext($context = new Context(), dirname(\Yii::getAlias('@app')));
        $directory = $context->getDirectory();

        $lines = file("$directory/Dockerfile", FILE_IGNORE_NEW_LINES + FILE_SKIP_EMPTY_LINES);
        // Find last line that starts with FROM.
        $lastFrom = null;
        foreach ($lines as $line) {
            if (preg_match('/^FROM/', $line)) {
                $lastFrom = $line;
            }
        }
        $this->assertNotNull($lastFrom);
        $this->assertMatchesRegularExpression("/^FROM\s*{$this->module->baseImage}/", $lastFrom);
    }

    public function testAdditionalSetters(): void
    {
        $this->module->fpmConfig = ['a' => 'c'];
        $this->module->additionalFpmConfig = ['a' => 'b'];
        $this->assertSame(['a' => 'b'], $this->module->fpmConfig);

        $this->module->poolConfig = ['a' => 'b', 'c' => 'd'];
        $this->module->additionalPoolConfig = ['e' => 'f'];
        $this->assertSame(['a' => 'b', 'c' => 'd', 'e' => 'f'], $this->module->poolConfig);
    }
}
