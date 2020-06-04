<?php
declare(strict_types=1);

namespace SamIT\Yii2\PhpFpm\helpers;


class Docker
{

    public function build(Context $context, string $tag)
    {
        passthru("docker build -t {$tag} {$context->getDirectory()}", $result);
        if ($result !== 0) {
            throw new \RuntimeException("Build failed", $result);
        }
    }

    public function push(string $tag)
    {
        passthru("docker push {$tag}", $result);
        if ($result !== 0) {
            throw new \RuntimeException("Push failed", $result);
        }
    }
}