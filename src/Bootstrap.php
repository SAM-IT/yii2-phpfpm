<?php

namespace SamIT\Yii2\PhpFpm;

use yii\base\Application;
use yii\base\BootstrapInterface;

class Bootstrap implements BootstrapInterface
{
    /**
     * Bootstrap method to be called during application bootstrap stage.
     * @param Application $app the application currently running
     */
    public function bootstrap($app)
    {
        if ($app instanceof \yii\console\Application) {
            if (!$app->hasModule("phpFpm")) {
                $app->setModule("phpFpm", [
                    'class' => Module::class
                ]);
            }
        }
    }
}