<?php
declare(strict_types=1);

namespace SamIT\Yii2\PhpFpm\helpers;

use Symfony\Component\Filesystem\Filesystem;

class Context
{
    private string $directory;
    private Filesystem $filesystem;
    public function __construct(?string $temp = null)
    {
        $this->filesystem = new Filesystem();
        $dir = $temp ?? sys_get_temp_dir();
        // Random file name:
        $name = "$dir/context_" . bin2hex(random_bytes(20));
        if (file_exists("$dir/$name")) {
            die('collision');
        }
        $this->filesystem->mkdir($name, 0777, false);
        $this->directory = $name;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function __destruct()
    {
//        passthru("rm -r {$this->directory}");
    }

    public function command(string $command): void
    {
        $this->filesystem->appendToFile("{$this->directory}/Dockerfile", "$command\n");
    }

    public function add(string $path, string $content): void
    {
        $filename = 'file_' . hash('sha256', $content);
        $this->filesystem->dumpFile("{$this->directory}/$filename", $content);
        $this->command("ADD $filename $path");
    }

    public function copyFromLayer(string $path, string $sourceLayer, string $source): void
    {
        $this->command("COPY --from={$sourceLayer} {$source} {$path}");
    }

    public function addFile(string $path, string $source): void
    {
        $name = 'disk_'. hash('sha256', realpath($source));
        if (is_dir($source)) {
            $this->filesystem->mirror($source, "{$this->directory}/$name");
        } else {
            $this->filesystem->copy($source, "{$this->directory}/$name");
        }

        $this->command("ADD $name $path");
    }

    public function run(string $command): void
    {
        $this->command("RUN $command");
    }

    public function from(string $image): void
    {
        $this->command("FROM $image");
    }

    public function addUrl(string $path, string $url): void
    {
        $this->command("ADD $url $path");
    }

    public function volume(string $path): void
    {
        $this->command("VOLUME $path");
    }

    public function entrypoint(array $entrypoint): void
    {
        $this->command("ENTRYPOINT [" . implode(', ', $entrypoint));
    }

    public function env(string $name, $value): void
    {
        $this->command("ENV $name $value");
    }
}
