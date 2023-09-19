<?php

declare(strict_types=1);

namespace Los\ApiServer\Handler;

use Los\ApiServer\Entity\Collection;
use Los\ApiServer\Entity\Entity;
use Los\ApiServer\Entity\EntityInterface;
use Los\ApiServer\Exception\NotFoundException;
use Los\ApiServer\Mapper\MapperInterface;
use Mezzio\Hal\HalResponseFactory;
use Mezzio\Hal\ResourceGenerator;
use Mezzio\Helper\UrlHelper;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;

use function array_key_exists;
use function array_merge;
use function end;
use function explode;
use function json_decode;
use function str_replace;
use function strtolower;

abstract class MapperRestHandler extends AbstractRestHandler
{
    public const SORT_BY = self::IDENTIFIER_NAME;

    protected int $limitItemsPerPage = 25;

    public function __construct(
        protected MapperInterface $mapper,
        Entity $entityPrototype,
        UrlHelper $urlHelper,
        ProblemDetailsResponseFactory $problemDetailsResponseFactory,
        ResourceGenerator $resourceGenerator,
        HalResponseFactory $responseFactory,
    ) {
        parent::__construct($urlHelper, $problemDetailsResponseFactory, $resourceGenerator, $responseFactory);

        $this->entityPrototype = $entityPrototype;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Los\ApiServer\Handler\AbstractRestHandler::getResourceName()
     */
    public function getResourceName(): string
    {
        $tokens = explode('\\', static::class);

        return strtolower(str_replace('Handler', '', end($tokens)));
    }

    /**
     * {@inheritDoc}
     *
     * @see \Los\ApiServer\Handler\AbstractRestHandler::create()
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
     *
     * @see \Los\ApiServer\Handler\AbstractRestHandler::fetch()
     */
    public function fetch($id, array $where = []): EntityInterface
    {
        $where = array_merge([self::IDENTIFIER_NAME => $id], $where);

        $entity = $this->mapper->findOneBy($where);
        if ($entity === null) {
            throw NotFoundException::create();
        }

        $query  = $this->request->getQueryParams();
        $fields = $query['fields'] ?? '';
        if (! empty($fields)) {
            $entity->setFields(explode(',', $fields));
        }

        return $entity;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Los\ApiServer\Handler\AbstractRestHandler::fetchAll()
     */
    public function fetchAll(array $where = [], array $options = []): Collection
    {
        $queryParams = $this->request->getQueryParams();
        $query       = array_key_exists('q', $queryParams) ? json_decode($queryParams['q'], true) : [];
        $hint        = array_key_exists('h', $queryParams) ? json_decode($queryParams['h'], true) : [];

        $where = array_merge($where, $query);
        $hint  = array_merge($options, $hint);

        $collection = $this->mapper->findBy($where, $hint);

        $page = (int) ($queryParams['page'] ?? $hint['page'] ?? 1);

        $collection->setItemCountPerPage(25);
        $collection->setCurrentPageNumber($page);

        $fields = $queryParams['fields'] ?? $hint['fields'] ?? '';
        if (! empty($fields)) {
            $this->mapper->setFields(explode(',', $fields));
        }

        return $collection;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Los\ApiServer\Handler\AbstractRestHandler::delete()
     */
    public function delete($id, array $where = []): void
    {
        $where = array_merge([self::IDENTIFIER_NAME => $id], $where);

        $entity = $this->mapper->findOneBy($where);
        if ($entity === null) {
            throw NotFoundException::create();
        }

        $this->mapper->delete($entity);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Los\ApiServer\Handler\AbstractRestHandler::patch()
     */
    public function patch($id, array $data, array $where = []): EntityInterface
    {
        $where = array_merge([self::IDENTIFIER_NAME => $id], $where);

        $entity = $this->mapper->findOneBy($where);
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
     *
     * @see \Los\ApiServer\Handler\AbstractRestHandler::update()
     */
    public function update($id, array $data, array $where = []): EntityInterface
    {
        $where = array_merge([self::IDENTIFIER_NAME => $id], $where);

        $entity = $this->mapper->findOneBy($where);
        if ($entity === null) {
            throw NotFoundException::create();
        }

        $data = $entity->filterData($data);
        $data = $entity->prepareDataForStorage($data);

        $this->mapper->update($data, $entity);

        return $entity;
    }
}
