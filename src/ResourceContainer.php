<?php


namespace LaravelJsonApi\OpenApiSpec;


use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\Core\Resources\JsonApiResource;

class ResourceContainer
{

    protected Server $server;

    /** @var \Illuminate\Support\Collection[] */
    protected array $resources = [];

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * @param  mixed  $model Model class as FQN, model instance or an Schema instance
     *
     * @return \LaravelJsonApi\Core\Resources\JsonApiResource
     */
    public function resource($model): JsonApiResource{
        $fqn = $this->getFQN($model);
        if(!isset($this->resource[$fqn])){
            $this->loadResources($fqn);
        }
        return $this->resources[$fqn]->first();
    }

    /**
     * @param  mixed  $model
     *
     * @return JsonApiResource[]
     */
    public function resources($model): array{
        $fqn = $this->getFQN($model);
        if(!isset($this->resource[$fqn])){
            $this->loadResources($fqn);
        }

        return $this->resources[$fqn]->toArray();
    }

    protected function getFQN($model): string{
        $fqn = $model;
        if($model instanceof Schema){
            $fqn = $model::model();
        }
        else if(is_object($model)) {
            $fqn = get_class($model);
        }
        return $fqn;
    }

    /**
     * @param  string  $model
     */
    protected function loadResources(string $model){
        $resources = null;
        $schema = $this->server->schemas()->schemaForModel($model);

        $documentationClass = preg_replace('/(.*)(Schema)$/', '${1}Documentation', get_class($schema));

        if (class_exists($documentationClass)) {
            $resources = $documentationClass::examples();
        } else if (method_exists($model, 'all')) {
            $resources = $model::all();
        }

        if ($resources != null) {
            $this->resources[$model] = $resources->map(function ($model) {
                return $this->server->resources()->create($model);
            })->take(3);
        }
    }
}
