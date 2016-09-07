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
use Zend\InputFilter\InputFilterAwareInterface;
use Zend\Stratigility\MiddlewareInterface;

abstract class AbstractRestAction implements MiddlewareInterface
{
    const IDENTIFIER_NAME = 'id';

    /**
     * @var Entity
     */
    protected $entityPrototype;

    protected $parsedData;

    protected $request;

    /**
     * @var \Zend\Expressive\Helper\UrlHelper
     */
    protected $urlHelper;

    protected $itemCountPerPage = 25;

    /**
     * Method to return the resource name for collections generation
     *
     * @return string
     */
    public function getResourceName() : string
    {
        return strtolower(str_replace('Action', '', end(explode('\\', get_class($this)))));
    }

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

    /**
     * Call the inputfilter to filter and validate data
     *
     * @throws ValidationException
     * @return array
     */
    protected function validateBody() : array
    {
        $data = $this->request->getParsedBody();

        if (!is_array($data)) {
            $data = [];
        }

        if ($this->entityPrototype == null || !($this->entityPrototype instanceof InputFilterAwareInterface)) {
            return $data;
        }

        if (strtoupper($this->request->getMethod()) == 'PATCH') {
            $this->entityPrototype->getInputFilter()->setValidationGroup(array_keys($data));
        }

        if (!$this->entityPrototype->getInputFilter()->setData($data)->isValid()) {
            throw new ValidationException(
                'Unprocessable Entity',
                422,
                null,
                ['validation_messages' => $this->entityPrototype->getInputFilter()->getMessages()]
            );
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

    /**
     * Generates an url for the entity or collection
     *
     * @param mixed $id
     * @return string
     */
    protected function generateUrl($id = null) : string
    {
        if (!$this->urlHelper) {
            return (string)$this->request->getUri();
        }

        if ($id !== null) {
            $path = $this->urlHelper->__invoke(null, [static::IDENTIFIER_NAME => $id]);
            parse_str($this->request->getUri()->getQuery(), $query);
            unset($query['page']);
            unset($query['sort']);
            unset($query['order']);
            return (string)$this->request->getUri()->withPath($path)->withQuery(http_build_query($query));
        }

        $path = $this->urlHelper->__invoke();
        return (string)$this->request->getUri()->withPath($path);
    }

    protected function addPaginatorLinks($entity, $hal)
    {
        $maxPages = ceil(max($entity->getTotalItemCount() / $entity->getItemCountPerPage(), 1));

        $path = $this->urlHelper->__invoke();
        parse_str($this->request->getUri()->getQuery(), $query);
        $query['page'] = $query['page'] ?? 1;

        $first = $query;
        if (isset($first['page']) && $first['page'] > 1) {
            unset($first['page']);
            $hal->addLink('first', (string)$this->request->getUri()->withPath($path)->withQuery(http_build_query($first)));
            $prev = $query;
            $prev['page'] = $prev['page'] - 1;
            if ($prev['page'] == 0) {
                unset($prev['page']);
            }
            $hal->addLink('previous', (string)$this->request->getUri()->withPath($path)->withQuery(http_build_query($prev)));
        }

        $next = $query;
        if (isset($next['page']) && $next['page'] + 1 < $maxPages) {
            $next['page'] = $next['page'] + 1;
            $hal->addLink('next', (string)$this->request->getUri()->withPath($path)->withQuery(http_build_query($next)));
        }

        if ($maxPages > 1) {
            $last = $query;
            $last['page'] = $maxPages;
            $hal->addLink('last', (string)$this->request->getUri()->withPath($path)->withQuery(http_build_query($last)));
        }
    }

    /**
     * Generates a proper response based on the Entity ot Collection
     *
     * @param Entity|Collection $entity
     * @param int $code
     * @throws \InvalidArgumentException
     * @return \Zend\Diactoros\Response\JsonResponse
     */
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

        $this->addPaginatorLinks($entity, $hal);

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
            'page' => $entity->getCurrentPageNumber(),
        ]);

        return new JsonResponse(json_decode($hal->asJson()), $code);
    }

    /**
     * Fetch an Entity
     *
     * @param mixed $id
     * @return \LosMiddleware\ApiProblem\Model\ApiProblem|\Zend\Diactoros\Response\JsonResponse
     */
    protected function handleFetch($id)
    {
        $entity = $this->fetch($id);
        return $this->generateResponse($entity);
    }

    /**
     * Fetch a collection
     *
     * @return \Zend\Diactoros\Response\JsonResponse
     */
    protected function handleFetchAll()
    {
        $list = $this->fetchAll();
        return $this->generateResponse($list);
    }

    /**
     * Create a new Entity
     *
     * @return \Zend\Diactoros\Response\JsonResponse
     */
    protected function handlePost()
    {
        $data = $this->validateBody();
        $entity = $this->create($data);

        return $this->generateResponse($entity, 201);
    }

    /**
     * Update an Entity
     *
     * @param mixed $id
     * @return \Zend\Diactoros\Response\JsonResponse
     */
    protected function handleUpdate($id)
    {
        $data = $this->validateBody();
        $entity = $this->update($id, $data);

        return $this->generateResponse($entity);
    }

    /**
     * Update a collection
     *
     * @return \Zend\Diactoros\Response\JsonResponse
     */
    protected function handleUpdateList()
    {
        $data = $this->validateBody();
        $list = $this->updateList($data);

        return $this->generateResponse($list);
    }

    /**
     * Update some properties from an Entity
     *
     * @param mixed $id
     * @return \Zend\Diactoros\Response\JsonResponse
     */
    protected function handlePatch($id)
    {
        $data = $this->validateBody();
        $entity = $this->patch($id, $data);

        return $this->generateResponse($entity);
    }

    /**
     * Updates some properties from a Collection
     *
     * @return \Zend\Diactoros\Response\JsonResponse
     */
    protected function handlePatchList()
    {
        $data = $this->validateBody();
        $list = $this->patchList($data);

        return $this->generateResponse($list);
    }

    /**
     * Delete an Entity
     *
     * @param mixed $id
     * @return \Zend\Diactoros\Response\JsonResponse
     */
    protected function handleDelete($id)
    {
        $this->delete($id);
        return new JsonResponse(null, 204);
    }

    /**
     * Delete a Collection

     * @return \Zend\Diactoros\Response\JsonResponse
     */
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
