<?php
declare(strict_types=1);

namespace SamIT\Yii2\PhpFpm;

use Docker\Context\Context;
use Docker\Context\ContextBuilder;
use yii\base\InvalidConfigException;
use yii\mutex\Mutex;

class Module extends \yii\base\Module
{

    /**
     * @var bool Whether the container should attempt to run migrations on launch.
     */
    public $runMigrations = false;

    /**
     * @var bool whether migrations should acquire a lock.
     * It must be configured in the 'mutex' component of this module or the application
     * Note that this mutex must be shared between all instances of your application.
     * Consider using something like redis or mysql mutex.
     */
    public $migrationsUseMutex = true;

    /**
     * The variables will be populated via the pool config.
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
     * @return string A PHP-FPM config file.
     */
    protected function createFpmConfig()
    {
        $config = [];
        // Add global directives.
        if (!empty($this->fpmConfig)) {
            $config[] = '[global]';
            foreach ($this->fpmConfig as $key => $value) {
                $config[] = "$key = $value";
            }
        }

        // Add pool directives.
        $poolConfig = $this->poolConfig;
        foreach($this->phpConfig as $key => $value) {
            $poolConfig["php_admin_value[$key]"] = $value;
        }

        foreach($this->environmentVariables as $name) {
            $poolConfig["env[$name]"] = "$$name";
        }

        if (!empty($poolConfig)) {
            $config[] = '[www]';
            foreach ($poolConfig as $key => $value) {
                $config[] = "$key = $value";
            }
        }

        return \implode("\n", $config);
    }

    /**
     * @return string A shell script that checks for existence of (non-empty) variables and runs php-fpm.
     */
    protected function createEntrypoint(): string
    {
        // Get the route.
        $route = "{$this->getUniqueId()}/migrate/up";
        $script = "/project/{$this->getConsoleEntryScript()}";
        $result = [];
        $result[] = '#!/bin/sh';
        // Check for variables.
        foreach($this->environmentVariables as $name) {
            $result[] = \strtr('if [ -z "${name}" ]; then echo "Variable \${name} is required."; exit 1; fi', [
                '{name}' => $name
            ]);
        }

        if ($this->runMigrations) {
            $result[] = <<<SH
ATTEMPTS=0
while [ \$ATTEMPTS -lt 10 ]; do
  # First run migrations.
  $script $route --interactive=0
  if [ $? -eq 0 ]; then
    echo "Migrations done";
    break;
  fi
  echo "Failed to run migrations, retrying in 10s.";
  sleep 10;
  let ATTEMPTS=ATTEMPTS+1
done

if [ \$ATTEMPTS -gt 9 ]; then
  echo "Migrations failed.."
  exit 1;
fi
SH;
        }

        $result[] = 'exec php-fpm7 --force-stderr --fpm-config /php-fpm.conf';
        return \implode("\n", $result);
    }

    public function createBuildContext(): Context
    {
        $builder = new ContextBuilder();

        /**
         * BEGIN COMPOSER
         */
        $builder->from('composer');
        $builder->addFile('/build/composer.json', \Yii::getAlias($this->composerFilePath) .'/composer.json');
        if (\file_exists(\Yii::getAlias($this->composerFilePath) . '/composer.lock')) {
            $builder->addFile('/build/composer.lock', \Yii::getAlias($this->composerFilePath) . '/composer.lock');
        }

        $builder->run('cd /build && composer install --no-dev --no-autoloader --ignore-platform-reqs --prefer-dist && rm -rf /root/.composer');


        // Add the actual source code.
        $root = \Yii::getAlias('@app');
        if (!\is_string($root)) {
            throw new \Exception('Alias @app must be defined.');
        }
        $builder->addFile('/build/' . \basename($root), $root);
        $builder->run('cd /build && composer dumpautoload -o');
        /**
         * END COMPOSER
         */


        $builder->from('alpine:edge');
        $packages = [
            'php7',
            'php7-fpm',
            'tini',
            'ca-certificates'
        ];
        foreach ($this->extensions as $extension) {
            $packages[] = "php7-$extension";
        }
        $builder->run('apk add --update --no-cache ' . \implode(' ', $packages));
        $builder->volume('/runtime');
        $builder->copy('--from=0 /build', '/project');
        $builder->add('/entrypoint.sh', $this->createEntrypoint());
        $builder->run('chmod +x /entrypoint.sh');
        $builder->add('/php-fpm.conf', $this->createFpmConfig());
        $builder->run("php-fpm7 --force-stderr --fpm-config /php-fpm.conf -t");
        $builder->entrypoint('["/sbin/tini", "--", "/entrypoint.sh"]');

        // Test if we can run a console command.
        $script = "[ -f /project/{$this->getConsoleEntryScript()} ]";
        $builder->run($script);


        return $builder->getContext();
    }

    public function getLock(int $timeout = 0)
    {
        if ($this->has('mutex')) {
            $mutex = $this->get('mutex');
            if ($mutex instanceof Mutex
                && $mutex->acquire(__CLASS__, $timeout)
            ) {
                \register_shutdown_function(function() use ($mutex): void {
                    $mutex->release(__CLASS__);
                });
                return true;
            }
        }
        return false;
    }

    /**
     * @throws InvalidConfigException in case the app is not configured as expected
     * @return string the relative path of the (console) entry script with respect to the project (not app) root.
     */
    private function getConsoleEntryScript(): string
    {
        $full = \array_slice(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), -1)[0]['file'];
        $relative = \strtr($full, [\dirname(\Yii::getAlias('@app')) => '']);
        if ($relative === $full){
            throw new InvalidConfigException("The console entry script must be located inside the @app directory.");
        }
        return \ltrim($relative, '/');
    }
}