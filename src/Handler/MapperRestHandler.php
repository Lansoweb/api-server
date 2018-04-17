<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Handler;

use LosMiddleware\ApiServer\Entity\Collection;
use LosMiddleware\ApiServer\Entity\Entity;
use LosMiddleware\ApiServer\Entity\EntityInterface;
use LosMiddleware\ApiServer\Exception\NotFoundException;
use LosMiddleware\ApiServer\Mapper\MapperInterface;
use Zend\Expressive\Hal\HalResponseFactory;
use Zend\Expressive\Hal\ResourceGenerator;
use Zend\Expressive\Helper\UrlHelper;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

abstract class MapperRestHandler extends AbstractRestHandler
{
    const SORT_BY = self::IDENTIFIER_NAME;

    /** @var MapperInterface */
    protected $mapper;

    /** @var int */
    protected $limitItemsPerPage = 25;

    public function __construct(
        MapperInterface $mapper,
        Entity $entityPrototype,
        UrlHelper $urlHelper,
        ProblemDetailsResponseFactory $problemDetailsResponseFactory,
        ResourceGenerator $resourceGenerator,
        HalResponseFactory $responseFactory
    ) {
        parent::__construct($urlHelper, $problemDetailsResponseFactory, $resourceGenerator, $responseFactory);

        $this->mapper = $mapper;
        $this->entityPrototype = $entityPrototype;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Handler\AbstractRestHandler::getResourceName()
     */
    public function getResourceName(): string
    {
        $tokens = explode('\\', get_class($this));
        return strtolower(str_replace('Handler', '', end($tokens)));
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Handler\AbstractRestHandler::create()
     */
    public function create(array $data): EntityInterface
    {
        $entity = clone $this->entityPrototype;

        $entity->exchangeArray($data);

        $this->mapper->insert($entity);

        return $entity;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Handler\AbstractRestHandler::fetch()
     */
    public function fetch($id): EntityInterface
    {
        $where = [static::IDENTIFIER_NAME => $id];

        $entity = $this->mapper->findOneBy($where);
        if ($entity === null) {
            throw NotFoundException::create();
        }

        $query = $this->request->getQueryParams();
        $fields = $query['fields'] ?? '';
        if (! empty($fields)) {
            $entity->setFields(explode(',', $fields));
        }
        return $entity;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Handler\AbstractRestHandler::fetchAll()
     */
    public function fetchAll(array $where = []): Collection
    {
        $query = $this->request->getQueryParams();

        /** @var Collection $collection */
        $collection = $this->mapper->findBy($query, $query);

        $page = (int) ($query['page'] ?? 1);

        $collection->setItemCountPerPage(25);
        $collection->setCurrentPageNumber($page);

        $fields = $query['fields'] ?? '';
        if (! empty($fields)) {
            $this->mapper->setFields(explode(',', $fields));
        }

        return $collection;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Handler\AbstractRestHandler::delete()
     */
    public function delete($id)
    {
        $entity = $this->mapper->findById($id);
        if ($entity === null) {
            throw NotFoundException::create();
        }

        $this->mapper->delete($entity);
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Handler\AbstractRestHandler::patch()
     */
    public function patch($id, array $data): EntityInterface
    {
        $entity = $this->mapper->findById($id);
        if ($entity === null) {
            throw NotFoundException::create();
        }

        $data = $entity->filterData($data);
        $data = $entity->prepareDataForStorage($data);

        $this->mapper->update($data, $entity);

        return $entity;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Handler\AbstractRestHandler::update()
     */
    public function update($id, array $data): EntityInterface
    {
        $entity = $this->mapper->findById($id);
        if ($entity === null) {
            throw NotFoundException::create();
        }

        $data = $entity->filterData($data);
        $data = $entity->prepareDataForStorage($data);

        $this->mapper->update($data, $entity);

        return $entity;
    }
}
