<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Handler;

use LosMiddleware\ApiServer\Entity\Collection;
use LosMiddleware\ApiServer\Entity\Entity;
use LosMiddleware\ApiServer\Entity\EntityInterface;
use LosMiddleware\ApiServer\Exception\MethodNotAllowedException;
use LosMiddleware\ApiServer\Exception\RuntimeException;
use LosMiddleware\ApiServer\Exception\ValidationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\EmptyResponse;
use Mezzio\Hal\HalResponseFactory;
use Mezzio\Hal\Metadata\RouteBasedCollectionMetadata;
use Mezzio\Hal\ResourceGenerator;
use Mezzio\Helper\UrlHelper;
use Laminas\InputFilter\InputFilterAwareInterface;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;

abstract class AbstractRestHandler implements RequestHandlerInterface
{
    const IDENTIFIER_NAME = 'id';

    protected Entity $entityPrototype;
    protected Request $request;
    protected UrlHelper $urlHelper;
    protected int $itemCountPerPage = 25;
    protected ProblemDetailsResponseFactory $problemDetailsResponseFactory;
    private ResourceGenerator $resourceGenerator;
    private HalResponseFactory $responseFactory;

    /**
     * AbstractRestAction constructor.
     * @param UrlHelper $urlHelper
     * @param ProblemDetailsResponseFactory $problemDetailsResponseFactory
     * @param ResourceGenerator $resourceGenerator
     * @param HalResponseFactory $responseFactory
     */
    public function __construct(
        UrlHelper $urlHelper,
        ProblemDetailsResponseFactory $problemDetailsResponseFactory,
        ResourceGenerator $resourceGenerator,
        HalResponseFactory $responseFactory
    ) {
        $this->urlHelper = $urlHelper;
        $this->problemDetailsResponseFactory = $problemDetailsResponseFactory;
        $this->resourceGenerator = $resourceGenerator;
        $this->responseFactory = $responseFactory;
    }

    /**
     * Method to return the resource name for collections generation
     *
     * @return string
     */
    public function getResourceName() : string
    {
        $tokens = explode('\\', get_class($this));
        $className = end($tokens);
        return strtolower(str_replace('Handler', '', $className));
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $requestMethod = strtoupper($request->getMethod());
        $this->request = $request;

        try {
            return $this->handleMethods($requestMethod);
        } catch (RuntimeException $ex) {
            return $this->problemDetailsResponseFactory->createResponseFromThrowable($this->request, $ex);
        }
    }

    protected function handleMethods(string $requestMethod) : Response
    {
        $id = $this->request->getAttribute(static::IDENTIFIER_NAME);

        switch ($requestMethod) {
            case 'GET':
                return isset($id)
                    ? $this->handleFetch($id)
                    : $this->handleFetchAll();
            case 'POST':
                if (isset($id)) {
                    throw MethodNotAllowedException::create('Method Not Allowed for Entity');
                }
                return $this->handlePost();
            case 'PUT':
                return isset($id)
                    ? $this->handleUpdate($id)
                    : $this->handleUpdateList();
            case 'PATCH':
                return isset($id)
                    ? $this->handlePatch($id)
                    : $this->handlePatchList();
            case 'DELETE':
                return isset($id)
                    ? $this->handleDelete($id)
                    : $this->handleDeleteList();
            case 'HEAD':
                return $this->head();
            case 'OPTIONS':
                return $this->options();
            default:
                throw MethodNotAllowedException::create();
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

        if (! is_array($data)) {
            $data = [];
        }

        if ($this->entityPrototype == null || ! ($this->entityPrototype instanceof InputFilterAwareInterface)) {
            return $data;
        }

        if (strtoupper($this->request->getMethod()) == 'PATCH') {
            $this->entityPrototype->getInputFilter()->setValidationGroup(array_keys($data));
        }

        if (! $this->entityPrototype->getInputFilter()->setData($data)->isValid()) {
            throw ValidationException::fromMessages($this->entityPrototype->getInputFilter()->getMessages());
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
     * Generates a proper response based on the Entity ot Collection
     */
    protected function generateResponse($entity, int $code = 200) : Response
    {
        if ($entity instanceof Entity) {
            $resource = $this->resourceGenerator->fromObject($entity, $this->request);
            return $this->responseFactory->createResponse($this->request, $resource);
        }

        $queryParams = $this->request->getQueryParams();

        $metadataMap = $this->resourceGenerator->getMetadataMap();
        /** @var RouteBasedCollectionMetadata $metadata */
        $metadata = $metadataMap->get(get_class($entity));
        $metadataQuery = $origMetadataQuery = $metadata->getQueryStringArguments();
        foreach ($queryParams as $key => $value) {
            $metadataQuery = array_merge($metadataQuery, [$key => $value]);
        }
        $metadata->setQueryStringArguments($metadataQuery);

        $resource = $this->resourceGenerator->fromObject($entity, $this->request);

        // Reset query string arguments
        $metadata->setQueryStringArguments($origMetadataQuery);

        $response = $this->responseFactory->createResponse($this->request, $resource);

        if ($code !== 200) {
            $response = $response->withStatus($code);
        }

        return $response;
    }

    /**
     * Fetch an Entity
     *
     * @param mixed $id
     * @return Response
     */
    protected function handleFetch($id) : Response
    {
        $entity = $this->fetch($id);
        return $this->generateResponse($entity);
    }

    /**
     * Fetch a collection
     *
     * @return Response
     */
    protected function handleFetchAll() : Response
    {
        $list = $this->fetchAll();
        return $this->generateResponse($list);
    }

    /**
     * Create a new Entity
     *
     * @return Response
     */
    protected function handlePost() : Response
    {
        $data = $this->validateBody();
        $entity = $this->create($data);

        return $this->generateResponse($entity, 201);
    }

    /**
     * Update an Entity
     *
     * @param mixed $id
     * @return Response
     */
    protected function handleUpdate($id) : Response
    {
        $data = $this->validateBody();
        $entity = $this->update($id, $data);

        return $this->generateResponse($entity);
    }

    /**
     * Update a collection
     *
     * @return Response
     */
    protected function handleUpdateList() : Response
    {
        $data = $this->validateBody();
        $list = $this->updateList($data);

        return $this->generateResponse($list);
    }

    /**
     * Update some properties from an Entity
     *
     * @param mixed $id
     * @return Response
     */
    protected function handlePatch($id) : Response
    {
        $data = $this->validateBody();
        $entity = $this->patch($id, $data);

        return $this->generateResponse($entity);
    }

    /**
     * Updates some properties from a Collection
     *
     * @return Response
     */
    protected function handlePatchList() : Response
    {
        $data = $this->validateBody();
        $list = $this->patchList($data);

        return $this->generateResponse($list);
    }

    /**
     * Delete an Entity
     *
     * @param mixed $id
     * @return EmptyResponse
     */
    protected function handleDelete($id) : Response
    {
        $this->delete($id);
        return new EmptyResponse(204);
    }

    /**
     * Delete a Collection
     *
     * @return EmptyResponse
     */
    protected function handleDeleteList() : Response
    {
        $this->deleteList();
        return new EmptyResponse(204);
    }

    /**
     * @param mixed $id
     * @param array $where
     * @return EntityInterface
     */
    public function fetch($id, array $where = []): EntityInterface
    {
        throw MethodNotAllowedException::create();
    }

    /**
     * @param array $where
     * @param array $options
     * @return Collection
     */
    public function fetchAll(array $where = [], array $options = []): Collection
    {
        throw MethodNotAllowedException::create();
    }

    /**
     * @param array $data
     * @return EntityInterface
     */
    public function create(array $data) : EntityInterface
    {
        throw MethodNotAllowedException::create();
    }

    /**
     * @param mixed $id
     * @param array $data
     * @param array $where
     * @return EntityInterface
     */
    public function update($id, array $data, array $where = []): EntityInterface
    {
        throw MethodNotAllowedException::create();
    }

    public function updateList(array $data) : Collection
    {
        throw MethodNotAllowedException::create();
    }

    /**
     * @param mixed $id
     * @param array $where
     */
    public function delete($id, array $where = [])
    {
        throw MethodNotAllowedException::create();
    }

    public function deleteList()
    {
        throw MethodNotAllowedException::create();
    }

    public function head(): Response
    {
        throw MethodNotAllowedException::create();
    }

    public function options(): Response
    {
        throw MethodNotAllowedException::create();
    }

    /**
     * @param mixed $id
     * @param array $data
     * @param array $where
     * @return EntityInterface
     */
    public function patch($id, array $data, array $where = []): EntityInterface
    {
        throw MethodNotAllowedException::create();
    }

    public function patchList(array $data) : Collection
    {
        throw MethodNotAllowedException::create();
    }
}
