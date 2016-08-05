<?php
/**
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace LosMiddleware\ApiServer\Helper;

use InvalidArgumentException;
use Zend\Expressive\Router\Exception\RuntimeException as RouterException;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Helper\UrlHelper as ZendUrlHelper;

class UrlHelper extends ZendUrlHelper
{
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
            throw new Exception\RuntimeException(
                'Attempting to use matched result when none was injected; aborting'
            );
        }

        $basePath = $this->getBasePath();

        if ($route === null) {
            if ($basePath === '/') {
                return $this->router->generateUri($name, $params);
            }
            return ($basePath !== '/' ? $basePath : '') . $this->generateUriFromResult($params, $result);
        }

        if ($this->result) {
            $params = $this->mergeParams($route, $result, $params);
        }

        return ($basePath !== '/' ? $basePath : '') . $this->router->generateUri($route, $params);
    }

}
