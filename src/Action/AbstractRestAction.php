<?php
namespace LosMiddleware\ApiServer\Action;

use LosMiddleware\ApiProblem\Model\ApiProblem;
use LosMiddleware\ApiServer\Entity\Collection;
use LosMiddleware\ApiServer\Entity\Entity;
use LosMiddleware\ApiServer\Exception\MethodNotAllowedException;
use LosMiddleware\ApiServer\Exception\ValidationException;
use Nocarrier\Hal;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Expressive\Router\RouterInterface;
use Zend\InputFilter\InputFilterAwareInterface;
use Zend\Stratigility\MiddlewareInterface;

abstract class AbstractRestAction implements MiddlewareInterface
{
    const IDENTIFIER_NAME = 'id';

    /**
     * @var AbstractEntity
     */
    protected $entityPrototype;

    protected $parsedData;

    protected $request;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * Method to return the resource name for collections generation
     *
     * @return string
     */
    abstract public function getResourceName() : string;

    /**
     * @param Request $request
     * @param Response $response
     * @param null|callable $next
     * @return null|Response
     */
    public function __invoke(Request $request, Response $response, callable $next = null)
    {
        $requestMethod = strtoupper($request->getMethod());
        $id = $request->getAttribute(static::IDENTIFIER_NAME);

        $this->request = $request;

        switch ($requestMethod) {
            case 'GET':
                return isset($id)
                    ? $this->handleFetch($id)
                    : $this->handleFetchAll();
                break;
            case 'POST':
                if (isset($id)) {
                    throw new MethodNotAllowedException('Invalid entity operation POST', 405);
                }
                return $this->handlePost();
                break;
            case 'PUT':
                return isset($id)
                    ? $this->handleUpdate($id)
                    : $this->handleUpdateList();
                break;
            case 'PATCH':
                return isset($id)
                    ? $this->handlePatch($id)
                    : $this->handlePatchList();
                break;
            case 'DELETE':
                return isset($id)
                    ? $this->handleDelete($id)
                    : $this->handleDeleteList();
                break;
            case 'HEAD':
                return $this->head();
                break;
            case 'OPTIONS':
                return $this->options();
                break;
            default:
                return $next($request, $response);
                break;
        }
    }

    protected function parseBody(Request $request) : array
    {
        $data = json_decode($request->getBody(), true);

        if (!is_array($data)) {
            $data = [];
        }

        if ($this->entityPrototype == null || !($this->entityPrototype instanceof InputFilterAwareInterface)) {
            return $data;
        }

        if (strtoupper($request->getMethod()) == 'PATCH') {
            $this->entityPrototype->getInputFilter()->setValidationGroup(array_keys($data));
        }

        if (!$this->entityPrototype->getInputFilter()->setData($data)->isValid()) {
            throw new ValidationException(null, 422, null, ['validation_messages' => $this->entityPrototype->getInputFilter()->getMessages()]);
        }

        $values = $this->entityPrototype->getInputFilter()->getValues();

        $parsed = [];
        foreach ($values as $key => $value) {
            if (array_key_exists($key, $data)) {
                $parsed[$key] = $value;
            }
        }
        return $parsed;
    }

    protected function generateUrl($id = null) : string
    {
        $route = $this->router->match($this->request)->getMatchedRouteName();
        if (!$route) {
            return (string)$this->request->getUri();
        }
        if ($id !== null) {
            $path = $this->router->generateUri($route, [static::IDENTIFIER_NAME => $id]);
        } else {
            $path = $this->router->generateUri($route);
        }
        return (string)$this->request->getUri()->withPath($path);
    }

    protected function generateResponse($entity, $code = 200)
    {
        //Just an entity
        if ($entity instanceof Entity) {
            $entityArray = $entity->getArrayCopy();
            $hal = new Hal($this->generateUrl($entityArray[static::IDENTIFIER_NAME] ?? null), $entityArray);

            return new JsonResponse(json_decode($hal->asJson(),true), $code);
        }

        if (!($entity instanceof Collection)) {
            throw new \InvalidArgumentException('Method result must be either an Entity or Collection');
        }

        //Collections
        $hal = new Hal($this->generateUrl());

        $entities = $entity->getCurrentItems();
        foreach ($entities as $tok) {
            $entityArray = $tok->getArrayCopy();
            $halEntity = new Hal($this->generateUrl($entityArray[static::IDENTIFIER_NAME] ?? null), $entityArray);
            $hal->addResource($this->getResourceName(), $halEntity);
        }
        $hal->setData([
            'page_count' => ceil(max($entity->getTotalItemCount() / $entity->getItemCountPerPage(), 1)),
            'page_size' => 25,
            'total_items' => $entity->getTotalItemCount(),
            'page' => 1,
        ]);

        return new JsonResponse(json_decode($hal->asJson()), $code);
    }

    /**
     * Gets one entity and creates a Hal response
     *
     * @param mixed $id
     * @return \LosMiddleware\ApiProblem\Model\ApiProblem|\Zend\Diactoros\Response\JsonResponse
     */
    protected function handleFetch($id)
    {
        $entity = $this->fetch($id);
        return $this->generateResponse($entity);
    }

    protected function handleFetchAll()
    {
        $list = $this->fetchAll();
        return $this->generateResponse($list);
    }

    protected function handlePost()
    {
        $data = $this->parseBody($this->request);
        $entity = $this->create($data);

        return $this->generateResponse($entity, 201);
    }

    protected function handleUpdate($id)
    {
        $data = $this->parseBody($this->request);
        $entity = $this->update($id, $data);

        return $this->generateResponse($entity);
    }

    protected function handleUpdateList()
    {
        $data = $this->parseBody($this->request);
        $list = $this->updateList($data);

        return $this->generateResponse($list);
    }

    protected function handlePatch($id)
    {
        $data = $this->parseBody($this->request);
        $entity = $this->patch($id, $data);

        return $this->generateResponse($entity);
    }

    protected function handlePatchList()
    {
        $data = $this->parseBody($this->request);
        $list = $this->patchList($data);

        return $this->generateResponse($list);
    }

    protected function handleDelete($id)
    {
        $this->delete($id);
        return new JsonResponse(null, 204);
    }

    protected function handleDeleteList()
    {
        $this->deleteList();
        return new JsonResponse(null, 204);
    }

    public function fetch($id) : Entity
    {
        throw new MethodNotAllowedException('Method not allowed', 405);
    }

    public function fetchAll() : Collection
    {
        throw new MethodNotAllowedException('Method not allowed', 405);
    }

    public function create(array $data) : Entity
    {
        throw new MethodNotAllowedException('Method not allowed', 405);
    }

    public function update($id, array $data) : Entity
    {
        throw new MethodNotAllowedException('Method not allowed', 405);
    }

    public function updateList(array $data) : Collection
    {
        throw new MethodNotAllowedException('Method not allowed', 405);
    }

    public function delete($id)
    {
        throw new MethodNotAllowedException('Method not allowed', 405);
    }

    public function deleteList()
    {
        throw new MethodNotAllowedException('Method not allowed', 405);
    }

    public function head()
    {
        throw new MethodNotAllowedException('Method not allowed', 405);
    }

    public function options()
    {
        throw new MethodNotAllowedException('Method not allowed', 405);
    }

    public function patch($id, array $data) : Entity
    {
        throw new MethodNotAllowedException('Method not allowed', 405);
    }

    public function patchList(array $data) : Collection
    {
        throw new MethodNotAllowedException('Method not allowed', 405);
    }
}
