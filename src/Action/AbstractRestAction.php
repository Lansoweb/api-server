<?php
namespace LosMiddleware\ApiServer\Action;

use LosMiddleware\ApiProblem\Model\ApiProblem;
use LosMiddleware\ApiServer\Entity\Entity;
use LosMiddleware\ApiServer\Exception\MethodNotAllowedException;
use Nocarrier\Hal;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Zend\Diactoros\Response\JsonResponse;
use Zend\InputFilter\InputFilterAwareInterface;
use Zend\Stratigility\MiddlewareInterface;
use LosMiddleware\ApiServer\Entity\Collection;

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
                $return = isset($id)
                    ? $this->handleGet($id)
                    : $this->handleGetList();
                break;
            case 'POST':
                $return = isset($id)
                    ? new ApiProblem(405, 'Invalid entity operation POST')
                    : $this->handlePost();
                break;
            case 'PUT':
                $return = isset($id)
                    ? $this->handleUpdate($id)
                    : $this->handleUpdateList();
                break;
            case 'PATCH':
                $return = isset($id)
                    ? $this->handlePatch($id)
                    : $this->handlePatchList();
                break;
            case 'DELETE':
                $return = isset($id)
                    ? $this->handleDelete($id)
                    : $this->handleDeleteList();
                break;
            case 'HEAD':
                $return = $this->head();
                break;
            case 'OPTIONS':
                $return = $this->options();
                break;
            default:
                $return = $next($request, $response);
                break;
        }

        if ($return instanceof ApiProblem) {
            return new JsonResponse($return->toArray(), $return->status);
        }

        return $return;
    }

    protected function parseBody(Request $request)
    {
        if ($this->entityPrototype == null || !($this->entityPrototype instanceof InputFilterAwareInterface)) {
            return;
        }

        $data = json_decode($request->getBody(), true);
        if (empty($data)) {
            $data = [];
        }
        if (!$this->entityPrototype->getInputFilter()->setData($data)->isValid($data)) {
            return new ApiProblem(
                422,
                [],
                null,
                null,
                ['validation_messages' => $this->entityPrototype->getInputFilter()->getMessages()]);
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
     * Gets one entity and creates a Hal response
     *
     * @param mixed $id
     * @return \LosMiddleware\ApiProblem\Model\ApiProblem|\Zend\Diactoros\Response\JsonResponse
     */
    protected function handleGet($id)
    {
        try {
            $entity = $this->get($id);
        } catch (\Exception $ex) {
            return new ApiProblem($ex->getCode() ?? 500, $ex->getMessage());
        }

        $hal = new Hal($this->request->getUri()->__toString(), $entity->getArrayCopy());

        return new JsonResponse(json_decode($hal->asJson(),true));
    }

    protected function handleGetList()
    {
        try {
            $list = $this->getList();
        } catch (\Exception $ex) {
            return new ApiProblem($ex->getCode() ?? 500, $ex->getMessage());
        }

        $hal = new Hal($this->request->getUri()->__toString());

        $entities = $list->getCurrentItems();
        foreach ($entities as $entity) {
            $halEntity = new Hal($this->request->getUri()->__toString(), $entity->getArrayCopy());
            $hal->addResource($this->getResourceName(), $halEntity);
        }
        $hal->setData([
            'page_count' => ceil(max($list->getTotalItemCount() / $list->getItemCountPerPage(), 1)),
            'page_size' => 25,
            'total_items' => $list->getTotalItemCount(),
            'page' => 1,
        ]);

        return new JsonResponse(json_decode($hal->asJson()));
    }

    protected function handlePost()
    {
        $data = $this->parseBody($this->request);
        if ($data instanceof ApiProblem) {
            return $data;
        }

        try {
            $entity = $this->create($data);
        } catch (\Exception $ex) {
            return new ApiProblem($ex->getCode() ?? 500, $ex->getMessage());
        }

        $hal = new Hal($this->request->getUri()->__toString(), $entity->getArrayCopy());

        return new JsonResponse(json_decode($hal->asJson(),true), 201);
    }

    protected function handleUpdate($id)
    {
        $data = $this->parseBody($this->request);
        if ($data instanceof ApiProblem) {
            return $data;
        }

        try {
            $entity = $this->update($id, $data);
        } catch (\Exception $ex) {
            return new ApiProblem($ex->getCode() ?? 500, $ex->getMessage());
        }

        $hal = new Hal($this->request->getUri()->__toString(), $entity->getArrayCopy());

        return new JsonResponse(json_decode($hal->asJson(),true), 200);
    }

    protected function handleUpdateList()
    {
        $data = $this->parseBody($this->request);
        if ($data instanceof ApiProblem) {
            return $data;
        }

        try {
            $list = $this->updateList($data);
        } catch (\Exception $ex) {
            return new ApiProblem($ex->getCode() ?? 500, $ex->getMessage());
        }

        $hal = new Hal($this->request->getUri()->__toString());

        $entities = $list->getCurrentItems();
        foreach ($entities as $entity) {
            $halEntity = new Hal($this->request->getUri()->__toString(), $entity->getArrayCopy());
            $hal->addResource($this->getResourceName(), $halEntity);
        }
        $hal->setData([
            'page_count' => ceil(max($list->getTotalItemCount() / $list->getItemCountPerPage(), 1)),
            'page_size' => 25,
            'total_items' => $list->getTotalItemCount(),
            'page' => 1,
        ]);

        return new JsonResponse(json_decode($hal->asJson()));
    }

    protected function handlePatch($id)
    {
        //TODO: validade only submitted fields
        //$data = $this->parseBody($this->request);
        $data = json_decode($this->request->getBody(), true);
        if ($data instanceof ApiProblem) {
            return $data;
        }

        try {
            $entity = $this->patch($id, $data);
        } catch (\Exception $ex) {
            return new ApiProblem($ex->getCode() ?? 500, $ex->getMessage());
        }

        $hal = new Hal($this->request->getUri()->__toString(), $entity->getArrayCopy());

        return new JsonResponse(json_decode($hal->asJson(),true), 200);
    }

    protected function handlePatchList()
    {
        //TODO: validade only submitted fields
        //$data = $this->parseBody($this->request);
        $data = json_decode($this->request->getBody(), true);
        if ($data instanceof ApiProblem) {
            return $data;
        }

        try {
            $list = $this->patchList($data);
        } catch (\Exception $ex) {
            return new ApiProblem($ex->getCode() ?? 500, $ex->getMessage());
        }

        $hal = new Hal($this->request->getUri()->__toString());

        $entities = $list->getCurrentItems();
        foreach ($entities as $entity) {
            $halEntity = new Hal($this->request->getUri()->__toString(), $entity->getArrayCopy());
            $hal->addResource($this->getResourceName(), $halEntity);
        }
        $hal->setData([
            'page_count' => ceil(max($list->getTotalItemCount() / $list->getItemCountPerPage(), 1)),
            'page_size' => 25,
            'total_items' => $list->getTotalItemCount(),
            'page' => 1,
        ]);

        return new JsonResponse(json_decode($hal->asJson()));
    }

    protected function handleDelete($id)
    {
        try {
            $this->delete($id);
        } catch (\Exception $ex) {
            return new ApiProblem($ex->getCode() ?? 500, $ex->getMessage());
        }

        return new JsonResponse(null, 204);
    }

    protected function handleDeleteList()
    {
        try {
            $this->deleteList();
        } catch (\Exception $ex) {
            return new ApiProblem($ex->getCode() ?? 500, $ex->getMessage());
        }

        return new JsonResponse(null, 204);
    }

    public function get($id) : Entity
    {
        throw new MethodNotAllowedException('Method not allowed', 405);
    }

    public function getList() : Collection
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
