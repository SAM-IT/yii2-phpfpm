<?php
declare(strict_types=1);

namespace SamIT\Yii2\PhpFpm;

use SamIT\Docker\Context;
use yii\base\InvalidConfigException;
use yii\base\UnknownPropertyException;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;

/**
 * Class Module
 * @package SamIT\Yii2\PhpFpm
 * @property-write string[] $additionalExtensions
 * @property-write string|int[] $additionalPoolConfig
 * @property-write string[] $additionalPhpConfig
 * @property-write string[] $additionalFpmConfig
 */
class Module extends \yii\base\Module
{
    /**
     * The variables will be written to /runtime/env.json as JSON, where your application can read them.
     * @var string[] List of required environment variables. If one is missing the container will exit.
     *
     */
    public $environmentVariables = [];

    /**
     * @var array Pool directives
     * @see http://php.net/manual/en/install.fpm.configuration.php
     *
     */
    public $poolConfig = [
        'user' => 'nobody',
        'group' => 'nobody',
        'listen' => 9000,
        'pm' => 'dynamic',
        'pm.max_children' => 40,
        'pm.start_servers' => 3,
        'pm.min_spare_servers' => 1,
        'pm.max_spare_servers' => 3,
        'access.log' => '/proc/self/fd/2',
        'clear_env' => 'yes',
        'catch_workers_output' => 'yes'
    ];

    /**
     * @var array PHP configuration, supplied via php_admin_value in fpm config.
     */
    public $phpConfig = [
        'upload_max_filesize' => '20M',
        'post_max_size' => '25M'
    ];

    /**
     * @var array Global directives
     * @see http://php.net/manual/en/install.fpm.configuration.php
     *
     */
    public $fpmConfig = [
        'error_log' => '/proc/self/fd/2',
        'daemonize' => 'no',
    ];

    /**
     * List of php extensions to install
     */
    public $extensions = [
        'ctype',
        'gd',
        'iconv',
        'intl',
        'json',
        'mbstring',
        'session',
        'pdo_mysql',
        'session',
        'curl'
    ];

    /**
     * @var string The name of the created image.
     */
    public $image;

    /**
     * @var string The tag of the created image.
     */
    public $tag = 'latest';

    /**
     * @var bool wheter to push successful builds.
     */
    public $push = false;

    /**
     * @var string Location of composer.json / composer.lock
     */
    public $composerFilePath = '@app/../';

    /**
     * @var string[] List of console commands that are executed upon container launch.
     */
    public $initializationCommands = [];
    /**
     * @return string A PHP-FPM config file.
     */
    protected function createFpmConfig()
    {
        $config = [];
        // Add global directives.
        $config[] = '[global]';
        foreach ($this->fpmConfig as $key => $value) {
            $config[] = "$key = $value";
        }

        // Add pool directives.
        $poolConfig = $this->poolConfig;
        foreach ($this->phpConfig as $key => $value) {
            $poolConfig["php_admin_value[$key]"] = $value;
        }

        $config[] = '[www]';
        foreach ($poolConfig as $key => $value) {
            $config[] = "$key = $value";
        }

        return \implode("\n", $config);
    }

    /**
     * @return string A shell script that checks for existence of (non-empty) variables and runs php-fpm.
     */
    private function createEntrypoint(string $entryScript): string
    {
        // Get the route.
        $result = [];
        $result[] = '#!/bin/sh';
        // Check for variables.
        foreach ($this->environmentVariables as $name) {
            $result[] = \strtr('if [ -z "${name}" && ! -f "$SECRET_DIR/{name}" ]; then echo "Variable \${name} is required."; exit 1; fi', [
                '{name}' => $name
            ]);
        }

        // Check if runtime directory is writable.
        $result[] = <<<SH
su nobody -s /bin/touch /runtime/testfile && rm /runtime/testfile;
if [ $? -ne 0 ]; then
  echo Runtime directory is not writable;
  exit 1
fi
SH;



        // Check if runtime is a tmpfs.
        $message = Console::ansiFormat('/runtime should really be a tmpfs.', [Console::FG_RED]);
        $result[] = <<<SH
grep 'tmpfs /runtime' /proc/mounts;
if [ $? -ne 0 ]; then
  echo $message;
fi
SH;
        $result[] = <<<SH
su nobody -s /bin/touch /runtime/env.json
(test -d \$SECRET_DIR && cd \$SECRET_DIR && find * -type f -exec jq -sR '{(input_filename):.}' {} \; ) | jq -s 'env+add' > /runtime/env.json
if [ $? -ne 0 ]; then
  echo "failed to store env in /runtime/env.json";
  exit 1
fi
SH;


        foreach ($this->initializationCommands as $route) {
            $result[] = "$entryScript $route --interactive=0 || exit";
        }
        $result[] = 'exec php-fpm --force-stderr --fpm-config /php-fpm.conf';
        return \implode("\n", $result);
    }

    /**
     * @param Context $context The context to use
     * @param string $version This is stored in the VERSION environment variable.
     * @param string $sourcePath This is the path where app source is stored
     * @throws InvalidConfigException
     */
    public function createBuildContext(
        Context $context,
        string $version,
        string $sourcePath
    ): void {
        if (!is_dir($sourcePath)) {
            throw new \InvalidArgumentException("$sourcePath does not exist or is not a directory");
        }

        $entryScript = "/project/{$this->getConsoleEntryScript($sourcePath)}";

        /**
         * BEGIN COMPOSER
         */
        $context->command('FROM composer');
        $context->addFile('/build/composer.json', \Yii::getAlias($this->composerFilePath) .'/composer.json');

        if (\file_exists(\Yii::getAlias($this->composerFilePath) . '/composer.lock')) {
            $context->addFile('/build/composer.lock', \Yii::getAlias($this->composerFilePath) . '/composer.lock');
        }

        $context->run('composer global require hirak/prestissimo');

        $context->run('cd /build && composer install --no-dev --no-autoloader --ignore-platform-reqs --prefer-dist');

        // Add the actual source code.
        $context->addFile('/build/' . \basename($sourcePath), $sourcePath);
        $context->run('cd /build && composer dumpautoload -o --no-dev');
        /**
         * END COMPOSER
         */

        $context->from('php:7.4-fpm-alpine');
        $context->run('apk add --update --no-cache jq');
        $context->addUrl("/usr/local/bin/", "https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions");
        $context->run("chmod +x /usr/local/bin/install-php-extensions");
        $context->run('install-php-extensions ' . implode(' ', $this->extensions));
        $context->run('mkdir /runtime && chown nobody:nobody /runtime');
        $context->volume('/runtime');
        $context->copyFromLayer("/project", "0", "/build");

        $context->add('/entrypoint.sh', $this->createEntrypoint($entryScript));

        $context->run('chmod +x /entrypoint.sh');

        $context->add('/php-fpm.conf', $this->createFpmConfig());

        $context->run("php-fpm --force-stderr --fpm-config /php-fpm.conf -t");

        $context->entrypoint(["/entrypoint.sh"]);

        $context->env('VERSION', $version);
        // Test if we can run a console command.
        $context->run("[ -f $entryScript ]");
    }

    /**
     * @throws \InvalidArgumentException in case the app is not configured as expected
     * @param string $sourcePath the path to the soruce files
     * @return string the relative path of the (console) entry script with respect to the project (not app) root.
     */
    private function getConsoleEntryScript(string $sourcePath): string
    {
        $full = \array_slice(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), -1)[0]['file'];
        $projectRoot = dirname($sourcePath);
        if (strncmp($projectRoot, $full, strlen($projectRoot)) !== 0) {
            throw new \InvalidArgumentException("The console entry script must be located inside the project root; $full is not in $projectRoot");
        }
        return \ltrim(substr($full, strlen($projectRoot)), '/');
    }


    public function __set($name, $value): void
    {
        if (\strncmp($name, 'additional', 10) !== 0) {
            parent::__set($name, $value);
            return;
        }


        $this->add(\lcfirst(\substr($name, 10)), $value);
    }

    private function add($name, array $value): void
    {
        if (!\property_exists($this, $name)) {
            throw new UnknownPropertyException("Unknown property $name");
        }
        $this->$name = ArrayHelper::merge($this->$name, $value);
    }
}
