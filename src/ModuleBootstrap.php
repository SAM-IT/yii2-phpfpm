<?php
declare(strict_types=1);
namespace SamIT\Yii2\PhpFpm;

use yii\base\Application;
use yii\base\BootstrapInterface;

class ModuleBootstrap implements BootstrapInterface
{
    /**
     * Bootstrap method to be called during application bootstrap stage.
     * @param Application $app the application currently running
     */
    public function bootstrap($app): void
    {
        if ($app instanceof \yii\console\Application) {
            if (!$app->hasModule("phpFpm")) {
                $app->setModule("phpFpm", [
                    'class' => Module::class,
                ]);
            }
        }
    }
}
