<?php
declare(strict_types=1);

namespace SamIT\Yii2\PhpFpm\controllers;


use Docker\API\Model\BuildInfo;
use Docker\API\Model\PushImageInfo;
use Docker\Context\Context;
use Docker\Context\ContextBuilder;
use Docker\Context\ContextInterface;
use Docker\Docker;
use Docker\Stream\BuildStream;
use Psr\Http\Message\ResponseInterface;
use SamIT\Yii2\PhpFpm\Module;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\helpers\Console;
use yii\web\Response;
use function Clue\StreamFilter\fun;

/**
 * Class BuildController
 * @package SamIT\Yii2\PhpFpm\controllers
 * @property Module $module
 */
class BuildController extends Controller
{
    public $defaultAction = 'build';

    /**
     * @var string The name of the created image
     * If not explicitly set will take its default from module config.
     */
    public $image;

    /**
     * @var string The tag of the created image
     * If not explicitly set will take its default from module config.
     */
    public $tag;

    /**
     * @var bool whether to push the image after a successful build.
     * If not explicitly set will take its default from module config.
     */
    public $push;

    /**
     * @var Docker
     */
    protected $docker;

    /**
     * @var string the user to authenticate against the repository
     */
    public $user;

    /**
     * @var string the password to authenticate against the repository
     */
    public $password;

    public function init(): void
    {
        parent::init();
        $this->docker = Docker::create();
        $this->push = $this->module->push;
        $this->image = $this->module->image;
        $this->tag = $this->module->tag;
    }

    public function actionBuild(): void
    {


        $params = [];
        if (isset($this->image)) {
            $name = "{$this->image}:{$this->tag}";
            $params['t'] = $name;
        }
        $buildStream = $this->createBuildStream($params);

        $buildStream->onFrame(function(BuildInfo $buildInfo): void {
            echo $buildInfo->getStream();
        });

        $buildStream->wait();
        echo "Wait finished\n";
        $buildStream->wait();

        if ($this->push) {
            if (!isset($name, $this->user, $this->password)) {
                throw new InvalidConfigException("When using the push option, you must configure or provide user, password and image");
            }
            $params = [
                'X-Registry-Auth' => \base64_encode(\GuzzleHttp\json_encode([
                    'username' => $this->user,
                    'password' => $this->password
                ]))
            ];
            $pushResult = $this->docker->imagePush($name, $params ?? [], Docker::FETCH_OBJECT);

            if ($pushResult instanceof ResponseInterface) {
                throw new \Exception($pushResult->getReasonPhrase() . ':' . $pushResult->getBody()->getContents(), $pushResult->getStatusCode());
            }
            /** @var PushImageInfo $pushInfo */
            $pushInfo = \array_pop($pushResult);

            if (!empty($pushInfo->getError())) {
                throw new \Exception($pushInfo->getError());
            }

        }
    }

    public function actionTestClient(): void
    {
        $this->stdout("It seems the console client works!\n", Console::FG_GREEN);
    }

    public function createBuildStream(array $params = []): BuildStream
    {

        $context = $this->module->createBuildContext();

        /** @var BuildStream $buildStream */
        $buildStream = $this->docker->imageBuild($context->toStream(), $params, Docker::FETCH_STREAM);
        return $buildStream;
    }

    public function options($actionID)
    {

        $result = parent::options($actionID);
        switch ($actionID) {
            case 'build':
                $result[] = 'push';
                $result[] = 'image';
                $result[] = 'tag';
                $result[] = 'user';
                $result[] = 'password';
                break;

        }
        return $result;
    }

    public function optionAliases()
    {
        $result = parent::optionAliases();
        $result['p'] = 'push';
        $result['t'] = 'tag';
        $result['i'] = 'image';
        $result['u'] = 'user';
        $result['P'] = 'password';
        return $result;
    }


}