<?php
namespace LosMiddleware\ApiServer;

use LosMiddleware\ApiServer\Auth;

class ConfigProvider
{
    /**
     * @return array
     */
    public function __invoke()
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * @return array
     */
    public function getDependencies()
    {
        return [
            'factories' => [
                Auth\Auth::class => Auth\AuthFactory::class,
            ],
        ];
    }
}
