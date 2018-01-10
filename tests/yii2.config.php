<?php
declare(strict_types=1);
return [
    'class' => \yii\console\Application::class,
    'id' => 'yii2-phpfpm-test',
    'basePath' => __DIR__ . '/../src',
    'runtimePath' => __DIR__ . '/_output/runtime',
    'modules' => [
        'phpFpm' => [
            'class' => \SamIT\Yii2\PhpFpm\Module::class,
            'extensions' => []
        ]
    ]
];