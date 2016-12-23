<?php
/**
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace LosMiddleware\ApiServer\Helper;

use LosMiddleware\ApiServer\Exception\RuntimeException;
use Zend\Expressive\Helper\UrlHelper as ZendUrlHelper;
use Zend\Expressive\Router\Exception\RuntimeException as RouterException;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouterInterface;

class UrlHelper extends ZendUrlHelper
{
    private $router2;


    public function __construct(RouterInterface $router)
    {
        parent::__construct($router);
        $this->router2 = $router;
    }

    /**
     * Generate a URL based on a given route.
     *
     * @param string $route
     * @param array $params
     * @return string
     * @throws Exception\RuntimeException if no route provided, and no result match
     *     present.
     * @throws Exception\RuntimeException if no route provided, and result match is a
     *     routing failure.
     * @throws RouterException if router cannot generate URI for given route.
     */
    public function __invoke($route = null, array $params = [])
    {
        $result = $this->getRouteResult();
        if ($route === null && $result === null) {
            throw new RuntimeException(
                'Attempting to use matched result when none was injected; aborting'
            );
        }

        $basePath = $this->getBasePath();

        if ($route === null) {
            return ($basePath !== '/' ? $basePath : '') . $this->generateUriFromResult2($params, $result);
        }

        if ($this->result) {
            $params = $this->mergeParams2($route, $result, $params);
        }

        return ($basePath !== '/' ? $basePath : '') . $this->router2->generateUri($route, $params);
    }

    private function generateUriFromResult2(array $params, RouteResult $result)
    {
        if ($result->isFailure()) {
            throw new RuntimeException(
                'Attempting to use matched result when routing failed; aborting'
                );
        }

        $name   = $result->getMatchedRouteName();
        $params = array_merge($result->getMatchedParams(), $params);
        return $this->router2->generateUri($name, $params);
    }

    private function mergeParams2($route, RouteResult $result, array $params)
    {
        if ($result->isFailure()) {
            return $params;
        }

        if ($result->getMatchedRouteName() !== $route) {
            return $params;
        }

        return array_merge($result->getMatchedParams(), $params);
    }

}
