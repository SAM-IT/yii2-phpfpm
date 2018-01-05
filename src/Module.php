<?php
declare(strict_types=1);

namespace SamIT\Yii2\PhpFpm;

use Docker\Context\Context;
use Docker\Context\ContextBuilder;
use Docker\Context\ContextInterface;
use yii\helpers\FileHelper;

class Module extends \yii\base\Module
{

    /**
     * @var bool Whether the container should attempt to run migrations on launch.
     */
    public $runMigrations = false;

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
     * @var Name and optionally tag of the image.
     * 
     */
    public $image;

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
  /project/protected/yiic migrate/up --interactive=0
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
        static $builder;
        $builder = new ContextBuilder();

        /**
         * BEGIN COMPOSER
         */
        $builder->from('composer');
        $builder->addFile('/build/composer.json', \Yii::getAlias($this->composerFilePath) .'/composer.json');
        if (\file_exists(\Yii::getAlias($this->composerFilePath) . '/composer.lock')) {
            $builder->addFile('/build/composer.lock', \Yii::getAlias($this->composerFilePath) . '/composer.lock');
        }

        $builder->run('cd /build && composer install --no-dev --no-autoloader --ignore-platform-reqs');


        // Add the actual source code.
        $root = \Yii::getAlias('@app');
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


        $builder->run('find /project | wc -l');
        return $builder->getContext();
    }
}