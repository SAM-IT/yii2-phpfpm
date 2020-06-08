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
    protected function createEntrypoint(): string
    {
        // Get the route.
        $script = "/project/{$this->getConsoleEntryScript()}";
        $result = [];
        $result[] = '#!/bin/sh';
        // Check for variables.
        foreach ($this->environmentVariables as $name) {
            $result[] = \strtr('if [ -z "${name}" ]; then echo "Variable \${name} is required."; exit 1; fi', [
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
jq -n env > /runtime/env.json
if [ $? -ne 0 ]; then
  echo "failed to store env in /runtime/env.json";
  exit 1
fi
SH;


        foreach ($this->initializationCommands as $route) {
            $result[] = "$script $route --interactive=0 || exit";
        }
        $result[] = 'exec php-fpm7 --force-stderr --fpm-config /php-fpm.conf';
        return \implode("\n", $result);
    }

    /**
     * @param string $version This is stored in the VERSION environment variable.
     * @throws InvalidConfigException
     * @return Context
     */
    public function createBuildContext(string $version): Context
    {
        $root = \Yii::getAlias('@app');
        if (!\is_string($root)) {
            throw new \Exception('Alias @app must be defined.');
        }

        $context = new Context();

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
        $context->addFile('/build/' . \basename($root), $root);
        $context->run('cd /build && composer dumpautoload -o --no-dev');
        /**
         * END COMPOSER
         */

        $context->from('php:7.4-fpm-alpine');
        $context->addUrl("/usr/local/bin/", "https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions");
        $context->run("chmod +x /usr/local/bin/install-php-extensions");
        $context->run('install-php-extensions ' . implode(' ', $this->extensions));
        $context->run('mkdir /runtime && chown nobody:nobody /runtime');
        $context->volume('/runtime');
        $context->copyFromLayer("/project", "0", "/build");

        $context->add('/entrypoint.sh', $this->createEntrypoint());

        $context->run('chmod +x /entrypoint.sh');

        $context->add('/php-fpm.conf', $this->createFpmConfig());

        $context->run("php-fpm --force-stderr --fpm-config /php-fpm.conf -t");

        $context->entrypoint(["/entrypoint.sh"]);

        $context->env('VERSION', $version);
        // Test if we can run a console command.
        if (\stripos($this->getConsoleEntryScript(), 'codecept') === false) {
            $script = "[ -f /project/{$this->getConsoleEntryScript()} ]";
            $context->run($script);
        }
        return $context;
    }

    /**
     * @throws InvalidConfigException in case the app is not configured as expected
     * @return string the relative path of the (console) entry script with respect to the project (not app) root.
     */
    public function getConsoleEntryScript(): string
    {
        $full = \array_slice(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), -1)[0]['file'];
        $relative = \strtr($full, [\dirname(\Yii::getAlias('@app')) => '']);
        if ($relative === $full) {
            throw new InvalidConfigException("The console entry script must be located inside the @app directory.");
        }
        return \ltrim($relative, '/');
    }


    public function __set($name, $value): void
    {
        if (\strncmp($name, 'additional', 10) === 0) {
            $this->add(\lcfirst(\substr($name, 10)), $value);
        } else {
            parent::__set($name, $value);
        }
    }

    private function add($name, array $value): void
    {
        if (!\property_exists($this, $name)) {
            throw new UnknownPropertyException("Unknown property $name");
        }
        $this->$name = ArrayHelper::merge($this->$name, $value);
    }
}
