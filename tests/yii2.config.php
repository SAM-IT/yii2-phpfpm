<?php

return [
    'class' => \yii\console\Application::class,
    'id' => 'yii2-phpfpm-test',
    'basePath' => __DIR__ . '/../src',
    'extensions' => [
        [
            'name' => 'yii2-phpfpm',
            'version' => 'test',
            'bootstrap' => \SamIT\Yii2\PhpFpm\Bootstrap::class,
//            'alias' =>
        ]

    ]
];