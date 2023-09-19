<?php

declare(strict_types=1);

namespace Los\ApiServer\Handler;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\InputFilter\InputFilterAwareInterface;
use Los\ApiServer\Entity\Collection;
use Los\ApiServer\Entity\Entity;
use Los\ApiServer\Entity\EntityInterface;
use Los\ApiServer\Exception\MethodNotAllowedException;
use Los\ApiServer\Exception\RuntimeException;
use Los\ApiServer\Exception\ValidationException;
use Mezzio\Hal\HalResponseFactory;
use Mezzio\Hal\Metadata\RouteBasedCollectionMetadata;
use Mezzio\Hal\ResourceGenerator;
use Mezzio\Helper\UrlHelper;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function assert;
use function end;
use function explode;
use function is_array;
use function str_replace;
use function strtolower;
use function strtoupper;

abstract class AbstractRestHandler implements RequestHandlerInterface
{
    public const IDENTIFIER_NAME = 'id';

    protected Entity $entityPrototype;
    protected Request $request;
    protected int $itemCountPerPage = 25;

    public function __construct(
        protected UrlHelper $urlHelper,
        protected ProblemDetailsResponseFactory $problemDetailsResponseFactory,
        private ResourceGenerator $resourceGenerator,
        private HalResponseFactory $responseFactory,
    ) {
    }

    /**
     * Method to return the resource name for collections generation
     */
    public function getResourceName(): string
    {
        $tokens    = explode('\\', static::class);
        $className = end($tokens);

        return strtolower(str_replace('Handler', '', $className));
    }

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

    protected function handleMethods(string $requestMethod): Response
    {
        $id = $this->request->getAttribute(self::IDENTIFIER_NAME);

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
     * @return array
     *
     * @throws ValidationException
     */
    protected function validateBody(): array
    {
        $data = $this->request->getParsedBody();

        if (! is_array($data)) {
            $data = [];
        }

        if (! isset($this->entityPrototype) || ! ($this->entityPrototype instanceof InputFilterAwareInterface)) {
            return $data;
        }

        if (strtoupper($this->request->getMethod()) === 'PATCH') {
            $this->entityPrototype->getInputFilter()->setValidationGroup(array_keys($data));
        }

        if (! $this->entityPrototype->getInputFilter()->setData($data)->isValid()) {
            throw ValidationException::fromMessages($this->entityPrototype->getInputFilter()->getMessages());
        }

        $values = $this->entityPrototype->getInputFilter()->getValues();

        $parsed = [];
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $parsed[$key] = $value;
        }

        return $parsed;
    }

    /**
     * Generates a proper response based on the Entity ot Collection
     */
    protected function generateResponse($entity, int $code = 200): Response
    {
        if ($entity instanceof Entity) {
            $resource = $this->resourceGenerator->fromObject($entity, $this->request);

            return $this->responseFactory->createResponse($this->request, $resource);
        }

        $queryParams = $this->request->getQueryParams();

        $metadataMap = $this->resourceGenerator->getMetadataMap();
        $metadata    = $metadataMap->get($entity::class);
        assert($metadata instanceof RouteBasedCollectionMetadata);
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
     */
    protected function handleFetch(mixed $id): Response
    {
        $entity = $this->fetch($id);

        return $this->generateResponse($entity);
    }

    /**
     * Fetch a collection
     */
    protected function handleFetchAll(): Response
    {
        $list = $this->fetchAll();

        return $this->generateResponse($list);
    }

    /**
     * Create a new Entity
     */
    protected function handlePost(): Response
    {
        $data   = $this->validateBody();
        $entity = $this->create($data);

        return $this->generateResponse($entity, 201);
    }

    /**
     * Update an Entity
     */
    protected function handleUpdate(mixed $id): Response
    {
        $data   = $this->validateBody();
        $entity = $this->update($id, $data);

        return $this->generateResponse($entity);
    }

    /**
     * Update a collection
     */
    protected function handleUpdateList(): Response
    {
        $data = $this->validateBody();
        $list = $this->updateList($data);

        return $this->generateResponse($list);
    }

    /**
     * Update some properties from an Entity
     */
    protected function handlePatch(mixed $id): Response
    {
        $data   = $this->validateBody();
        $entity = $this->patch($id, $data);

        return $this->generateResponse($entity);
    }

    /**
     * Updates some properties from a Collection
     */
    protected function handlePatchList(): Response
    {
        $data = $this->validateBody();
        $list = $this->patchList($data);

        return $this->generateResponse($list);
    }

    /**
     * Delete an Entity
     *
     * @return EmptyResponse
     */
    protected function handleDelete(mixed $id): Response
    {
        $this->delete($id);

        return new EmptyResponse(204);
    }

    /**
     * Delete a Collection
     *
     * @return EmptyResponse
     */
    protected function handleDeleteList(): Response
    {
        $this->deleteList();

        return new EmptyResponse(204);
    }

    /** @param array $where */
    public function fetch(mixed $id, array $where = []): EntityInterface
    {
        throw MethodNotAllowedException::create();
    }

    /**
     * @param array $where
     * @param array $options
     */
    public function fetchAll(array $where = [], array $options = []): Collection
    {
        throw MethodNotAllowedException::create();
    }

    /** @param array $data */
    public function create(array $data): EntityInterface
    {
        throw MethodNotAllowedException::create();
    }

    /**
     * @param array $data
     * @param array $where
     */
    public function update(mixed $id, array $data, array $where = []): EntityInterface
    {
        throw MethodNotAllowedException::create();
    }

    public function updateList(array $data): Collection
    {
        throw MethodNotAllowedException::create();
    }

    /** @param array $where */
    public function delete(mixed $id, array $where = []): void
    {
        throw MethodNotAllowedException::create();
    }

    public function deleteList(): void
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
     * @param array $data
     * @param array $where
     */
    public function patch(mixed $id, array $data, array $where = []): EntityInterface
    {
        throw MethodNotAllowedException::create();
    }

    public function patchList(array $data): Collection
    {
        throw MethodNotAllowedException::create();
    }
}
