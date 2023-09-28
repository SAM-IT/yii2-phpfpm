<?php

declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

final class Yii extends \yii\BaseYii
{
}

\Yii::$container = new \yii\di\Container();
