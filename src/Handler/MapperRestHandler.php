<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Action;

use LosMiddleware\ApiServer\Entity\Collection;
use LosMiddleware\ApiServer\Entity\Entity;
use LosMiddleware\ApiServer\Entity\EntityInterface;
use LosMiddleware\ApiServer\Exception\RuntimeException;
use LosMiddleware\ApiServer\Mapper\MapperInterface;
use LosMiddleware\ApiServer\Paginator\MapperAdapter;
use Zend\Expressive\Helper\UrlHelper;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;
use Zend\Stdlib\ArrayObject;

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
        ProblemDetailsResponseFactory $problemDetailsResponseFactory
    ) {
        parent::__construct($urlHelper, $problemDetailsResponseFactory);

        $this->mapper = $mapper;
        $this->entityPrototype = $entityPrototype;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestHandler::getResourceName()
     */
    public function getResourceName(): string
    {
        $tokens = explode('\\', get_class($this));
        return strtolower(str_replace('Handler', '', end($tokens)));
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestHandler::create()
     */
    public function create(array $data): EntityInterface
    {
        $entity = clone $this->entityPrototype;

        $data = $entity->filterData($data);
        $data = $entity->prepareDataForStorage($data);

        $this->mapper->insert($data);

        return $entity;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestHandler::fetch()
     */
    public function fetch($id): EntityInterface
    {
        $where = [static::IDENTIFIER_NAME, $id];

        $query = $this->request->getQueryParams();

        $entity = $this->mapper->findOneBy($where);
        if ($entity === null) {
            throw new RuntimeException('Entity not found', 404);
        }

        $fields = $query['fields'] ?? '';
        if (! empty($fields)) {
            $entity->setFields(explode(',', $fields));
        }
        return $entity;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestHandler::fetchAll()
     */
    public function fetchAll(array $where = []): Collection
    {
        /* @var \Zend\Stdlib\Parameters $params */
        $params = $this->request->getQueryParams();

        $sortParam = $params['sort'] ?? static::SORT_BY;
        $sort = [$sortParam => $params['order'] ?? 'ASC'];

        $dbAdapter = new MapperAdapter($this->mapper, $where, $sort);
        $collection = new Collection($dbAdapter);

        $itemCountPerPage = $this->itemCountPerPage;
        if (array_key_exists('items_per_page', $params) && is_numeric($params['items_per_page'])) {
            $itemCountPerPage = min([$this->limitItemsPerPage, $params['items_per_page']]);
        }
        $collection->setItemCountPerPage($itemCountPerPage);
        $collection->setCurrentPageNumber($params['page'] ?? 1);

        return $collection;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestHandler::delete()
     */
    public function delete($id)
    {
        $entity = $this->mapper->findById($id);
        if ($entity === null) {
            throw new RuntimeException('Entity not found', 404);
        }

        $where = [static::IDENTIFIER_NAME => $id];

        $this->mapper->delete($where);
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestHandler::patch()
     */
    public function patch($id, array $data): EntityInterface
    {
        $entity = $this->mapper->findById($id);
        if ($entity === null) {
            throw new RuntimeException('Entity not found', 404);
        }

        $data = $entity->filterData($data);
        $data = $entity->prepareDataForStorage($data);
        $data = new ArrayObject($data);

        $where = [static::IDENTIFIER_NAME => $id];
        $this->mapper->update($data, $where);

        return $entity;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestHandler::update()
     */
    public function update($id, array $data): EntityInterface
    {
        $entity = $this->mapper->findById($id);
        if ($entity === null) {
            throw new RuntimeException('Entity not found', 404);
        }

        $data = $entity->filterData($data);
        $data = $entity->prepareDataForStorage($data);
        $data = new ArrayObject($data);

        $where = [static::IDENTIFIER_NAME => $id];

        $this->mapper->update($data, $where);

        return $entity;
    }
}
