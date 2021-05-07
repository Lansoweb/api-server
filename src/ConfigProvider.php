<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer;

use LosMiddleware\ApiServer\Auth;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'factories' => [
                Auth\AuthMiddleware::class => Auth\AuthFactory::class,
            ],
        ];
    }
}
