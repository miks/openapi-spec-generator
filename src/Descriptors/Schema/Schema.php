<?php


namespace LaravelJsonApi\OpenApiSpec\Descriptors\Schema;


use GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use LaravelJsonApi\Contracts\Schema\Field;
use LaravelJsonApi\Contracts\Schema\PolymorphicRelation;
use LaravelJsonApi\Contracts\Schema\Schema as JASchema;
use LaravelJsonApi\Contracts\Schema\Sortable;
use LaravelJsonApi\Core\Resources\JsonApiResource;
use LaravelJsonApi\Core\Support\Str;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\ArrayList;
use LaravelJsonApi\Eloquent\Fields\Attribute;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Map;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Relations\Relation;
use LaravelJsonApi\Eloquent\Pagination\CursorPagination;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\NonEloquent\Fields\Attribute as NonEloquentAttribute;
use LaravelJsonApi\OpenApiSpec\Builders\Paths\Operation\SchemaBuilder;
use LaravelJsonApi\OpenApiSpec\Contracts\Descriptors\Schema\PaginationDescriptor;
use LaravelJsonApi\OpenApiSpec\Contracts\Descriptors\Schema\SortablesDescriptor;
use LaravelJsonApi\OpenApiSpec\Contracts\Descriptors\SchemaDescriptor;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema as OASchema;
use LaravelJsonApi\OpenApiSpec\Descriptors\Descriptor;
use LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters;
use LaravelJsonApi\Eloquent;
use LaravelJsonApi\OpenApiSpec\Route;
use LaravelJsonApi\Contracts\Schema\Relation as RelationContract;

class Schema extends Descriptor implements SchemaDescriptor, SortablesDescriptor, PaginationDescriptor
{

    protected array $filterDescriptors = [
      Eloquent\Filters\WhereIdIn::class => Filters\WhereIdIn::class,
      Eloquent\Filters\WhereIn::class => Filters\WhereIn::class,
      Eloquent\Filters\Scope::class => Filters\Scope::class,
      Eloquent\Filters\WithTrashed::class => Filters\WithTrashed::class,
      Eloquent\Filters\Where::class => Filters\Where::class,
    ];

    /**
     * @param  \LaravelJsonApi\Contracts\Schema\Schema  $schema
     * @param  string  $objectId
     * @param  string  $type
     * @param  string  $name
     *
     * @return OASchema
     * @throws InvalidArgumentException
     */
    public function fetch(
      JASchema $schema,
      string $objectId,
      string $type,
      string $name
    ): OASchema {

        $resource = $this->generator
          ->resources()
          ->resource($schema::model());

        $fields = $this->fields($schema->fields(), $resource);
        $properties = [
          OASchema::string('type')
            ->title('type')
            ->default($type),
          OASchema::string('id')
            ->example($resource->id()),
          OASchema::object('attributes')
            ->properties(...$fields->get('attributes')),
        ];

        if ($fields->has('relationships')) {
            $properties[] = OASchema::object('relationships')
              ->properties(...$fields->get('relationships'));
        }
        return OASchema::object($objectId)
          ->title('Resource/'.ucfirst($name).'/Fetch')
          ->required('type', 'id', 'attributes')
          ->properties(...$properties);
    }

    /**
     * @param  \LaravelJsonApi\OpenApiSpec\Route  $route
     *
     * @return OASchema
     * @throws InvalidArgumentException
     */
    public function store(Route $route): OASchema
    {
        $objectId = SchemaBuilder::objectId($route);

        $resource = $this->generator->resources()
          ->resource($route->schema()::model());

        $fields = $this->fields($route->schema()->fields(), $resource);
        $properties = [
            OASchema::string('type')
                ->title('type')
                ->default($route->name()),
            OASchema::object('attributes')
                ->properties(...$fields->get('attributes')),
        ];

        if ($fields->has('relationships')) {
            $properties[] = OASchema::object('relationships')
                ->properties(...$fields->get('relationships'));
        }

        return OASchema::object($objectId)
          ->title('Resource/'.ucfirst($route->name(true))."/Store")
          ->required('type', 'attributes')
          ->properties(...$properties);
    }

    /**
     * @param  \LaravelJsonApi\OpenApiSpec\Route  $route
     *
     * @return OASchema
     * @throws InvalidArgumentException
     */
    public function update(Route $route): OASchema
    {
        $objectId = SchemaBuilder::objectId($route);
        $resource = $this->generator->resources()
          ->resource($route->schema()::model());

        $fields = $this->fields($route->schema()->fields(), $resource);
        $properties = [
            OASchema::string('type')
                ->title('type')
                ->default($route->name()),
            OASchema::string('id')
                ->example($resource->id()),
            OASchema::object('attributes')
                ->properties(...$fields->get('attributes')),
        ];

        if ($fields->has('relationships')) {
            $properties[] = OASchema::object('relationships')
                ->properties(...$fields->get('relationships'));
        }

        return OASchema::object($objectId)
          ->title('Resource/'.ucfirst($route->name(true)).'/Update')
          ->properties(...$properties)
          ->required('type', 'id', 'attributes');
    }

    /**
     * @throws InvalidArgumentException
     */
    public function fetchRelationship(Route $route): OASchema
    {
        if(!$route->isPolymorphic()){
            $resource = $this->generator->resources()
              ->resource($route->inversSchema()::model());
        }
        else{
            $resource = $this->generator->resources()
              ->resource(Arr::first($route->inversSchemas())::model());
        }

      $inverseRelation = $route->relation() !== null ? $route->relation()->inverse() : null;
      return $this->relationshipData($route->relation(), $resource,
        $inverseRelation)
          ->title('Resource/'.ucfirst($route->name(true)).'/Relationship/'.ucfirst($route->relationName()).'/Fetch');
    }

    /**
     * @throws InvalidArgumentException
     */
    public function updateRelationship(Route $route): OASchema
    {
        if(!$route->isPolymorphic()){
            $resource = $this->generator->resources()
              ->resource($route->inversSchema()::model());
        }
        else{
            $resource = $this->generator->resources()
              ->resource(Arr::first($route->inversSchemas())::model());
        }

        $inverseRelation = $route->relation() !== null ? $route->relation()->inverse() : null;
        $relation = $route->relation();

        $dataSchema = $this
          ->relationshipData(
            $relation,
            $resource,
            $inverseRelation
          );

        if ($relation instanceof Eloquent\Fields\Relations\ToMany) {
            $dataSchema = OASchema::array('data')
              ->items($dataSchema);
        }

        return $dataSchema->title('Resource/'.ucfirst($route->name(true)).'/Relationship/'.ucfirst($route->relationName()).'/Update');
    }

    /**
     * @throws InvalidArgumentException
     */
    public function attachRelationship(Route $route): OASchema
    {
        if(!$route->isPolymorphic()){
            $resource = $this->generator->resources()
              ->resource($route->inversSchema()::model());
        }
        else{
            $resource = $this->generator->resources()
              ->resource(Arr::first($route->inversSchemas())::model());
        }

        $inverseRelation = $route->relation() !== null ? $route->relation()->inverse() : null;

        $relation = $route->relation();

        $dataSchema = $this
          ->relationshipData(
            $relation,
            $resource,
            $inverseRelation
          );

        if ($relation instanceof Eloquent\Fields\Relations\ToMany) {
            $dataSchema = OASchema::array('data')
              ->items($dataSchema);
        }

        return $dataSchema->title('Resource/'.ucfirst($route->name(true)).'/Relationship/'.ucfirst($route->relationName()).'/Attach');
    }

    /**
     * @throws InvalidArgumentException
     */
    public function detachRelationship(Route $route): OASchema
    {
        if(!$route->isPolymorphic()){
            $resource = $this->generator->resources()
              ->resource($route->inversSchema()::model());
        }
        else{
            $resource = $this->generator->resources()
              ->resource(Arr::first($route->inversSchemas())::model());
        }
      $inverseRelation = $route->relation() !== null ? $route->relation()->inverse() : null;
        return $this->relationshipData($route->relation(), $resource,
          $inverseRelation)
          ->title('Resource/'.ucfirst($route->name(true)).'/Relationship/'.ucfirst($route->relationName()).'/Detach');
    }

    /**
     * @throws InvalidArgumentException
     */
    public function fetchPolymorphicRelationship(
      Route $route,
      $objectId
    ): OASchema {
        $resource = $this->generator->resources()
          ->resource($route->schema()::model());

        $inverseRelation = $route->relation() !== null ? $route->relation()->inverse() : null;
        return $this->relationshipData($route->relation(), $resource,
          $inverseRelation)
          ->objectId($objectId)
          ->title('Resource/'.ucfirst($route->name(true)).'/Relationship/'.ucfirst($route->relationName()).'/Fetch');
    }

    /**
     * @return \GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter[]
     */
    public function sortables($route): array
    {
        $fields = collect($route->schema()->sortFields())
          ->merge(collect($route->schema()->sortables())
            ->map(function (Sortable $sortable) {
                return $sortable->sortField();
            })->whereNotNull())
          ->map(function (string $field) {
              return [$field, '-'.$field];
          })->flatten()->toArray();

        return [
          Parameter::query('sort')
            ->name('sort')
            ->schema(OASchema::array()
              ->items(OASchema::string()->enum(...$fields))
            )
            ->allowEmptyValue(false)
            ->required(false),
        ];
    }

    /**
     * @param  \LaravelJsonApi\OpenApiSpec\Route  $route
     *
     * @return array
     */
    public function includes(Route $route): array
    {
        return [
            Parameter::query('includes')
                ->name('include')
                ->description('Comma-separated list of relationships to include')
                ->required(false)
                ->allowEmptyValue(false)
                ->schema(OASchema::string()),
        ];
    }

    /**
     * @param  \LaravelJsonApi\OpenApiSpec\Route  $route
     *
     * @return array
     */
    public function pagination(Route $route): array
    {
        $pagination = $route->schema()->pagination();
        if ($pagination instanceof PagePagination) {
            return [
              Parameter::query('pageSize')
                ->name('page[size]')
                ->description('The page size for paginated results')
                ->required(false)
                ->allowEmptyValue(false)
                ->schema(OASchema::integer()),
              Parameter::query('pageNumber')
                ->name('page[number]')
                ->description('The page number for paginated results')
                ->required(false)
                ->allowEmptyValue(false)
                ->schema(OASchema::integer()),
            ];
        }

        if ($pagination instanceof CursorPagination) {
            return [
              Parameter::query('pageLimit')
                ->name('page[limit]')
                ->description('The page limit for paginated results')
                ->required(false)
                ->allowEmptyValue(false)
                ->schema(OASchema::integer()),
              Parameter::query('pageAfter')
                ->name('page[after]')
                ->description('The page offset for paginated results')
                ->required(false)
                ->allowEmptyValue(false)
                ->schema(OASchema::string()),
              Parameter::query('pageBefore')
                ->name('page[before]')
                ->description('The page offset for paginated results')
                ->required(false)
                ->allowEmptyValue(false)
                ->schema(OASchema::string()),
            ];
        }

        return [];
    }

    /**
     * @param $route
     *
     * @return \GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter[]
     */
    public function filters($route): array
    {
        return collect($route->schema()->filters())
          ->map(function (Eloquent\Contracts\Filter $filterInstance) use ($route
          ) {
            $descriptor = $this->getDescriptor($filterInstance);
            return (new $descriptor($this->generator, $route, $filterInstance))->filter();
          })
          ->flatten()
          ->toArray();
    }

    /**
     * @param  \LaravelJsonApi\Contracts\Schema\Field[]  $fields
     * @param JsonApiResource $resource
     *
     * @return \Illuminate\Support\Collection
     */
    protected function fields(
      array $fields,
      JsonApiResource $resource
    ): Collection {
        return collect($fields)
          ->mapToGroups(function (Field $field) {
              switch (true) {
                case $field instanceof Attribute:
                  $key = 'attributes';
                      break;
                  case $field instanceof NonEloquentAttribute:
                      $key = 'attributes';
                      break;
                  case $field instanceof Attribute:
                      $key = 'attributes';
                      break;
                case $field instanceof RelationContract:
                  $key = 'relationships';
                  break;
                default:
                  $key = 'unknown';
                }
                return [$key => $field];
          })
          ->map(function ($fields, $type) use ($resource) {
              switch ($type) {
                case 'attributes':
                  return $this->attributes($fields, $resource);
                case 'relationships':
                  return $this->relationships($fields, $resource);
                default:
                  return null;
              }
          });
    }

    /**
     * @return Schema[]
     */
    protected function attributes(
      Collection $fields,
      JsonApiResource $example
    ): array {
        return $fields
          ->filter(fn($field) => ! ($field instanceof ID))
          ->map(function (Field $field) use ($example) {
              $fieldId = $field->name();
              switch (true) {
                case $field instanceof Boolean:
                    $fieldDataType = OASchema::boolean($fieldId);
                    break;
                case $field instanceof Number:
                    $fieldDataType = OASchema::number($fieldId);
                    break;
                case $field instanceof ArrayList:
                    $fieldDataType = OASchema::array($fieldId);
                    break;
                case $field instanceof ArrayHash:
                case $field instanceof Map:
                   $fieldDataType = OASchema::object($fieldId);
                   break;
                default:
                  $fieldDataType = OASchema::string($fieldId);
              }

              $schema = $fieldDataType->title($field->name());

              $exampleValue = $this->attributeExampleValue($example, $field);

              if ($exampleValue) {
                  $schema = $schema->example($exampleValue);
              }

              if (method_exists($field, 'isReadOnly') && $field->isReadOnly(null)) {
                  $schema = $schema->readOnly(true);
              }

              return $schema;
          })->toArray();
    }

    protected function attributeExampleValue($example, $field): mixed
    {
        if(!$example) {
            return null;
        }

        $exampleValue = null;

        if($field instanceof NonEloquentAttribute) {
            $exampleValue = $example->attributes(null)[$field->name()];
        }
        else if (isset($example[$field->name()])) {
            $exampleValue = $example[$field->name()];
        }

        if ($exampleValue instanceof \UnitEnum) {
            $exampleValue = $exampleValue->value;
        }

        return $exampleValue;
    }


    /**
     * @param  \Illuminate\Support\Collection  $relationships
     * @param JsonApiResource $example
     *
     * @return array
     * @todo Fix relation field names
     */
    protected function relationships(
      Collection $relationships,
      JsonApiResource $example
    ): array {
        return $relationships
          ->map(function (RelationContract $relation) use ($example) {
              return $this->relationship($relation, $example);
          })->toArray();

    }

    /**
     * @param RelationContract $relation
     * @param JsonApiResource $example
     * @param bool $includeData
     *
     * @return OASchema
     * @throws InvalidArgumentException
     */
    protected function relationship(
      RelationContract $relation,
      JsonApiResource $example,
      bool $includeData = true
    ): OASchema {
        $fieldId = $relation->name();

        $type = $relation->inverse();

        $linkSchema = $this->relationshipLinks($relation, $example, $type);

        $dataSchema = $this->relationshipData($relation, $example, $type);

        if ($relation instanceof Eloquent\Fields\Relations\ToMany) {
            $dataSchema = OASchema::array('data')
              ->items($dataSchema);
        }
        $schema = OASchema::object($fieldId)
          ->title($relation->name());

        if ($includeData) {
            return $schema->properties($dataSchema);
        } else {
            return $schema->properties($linkSchema);
        }
    }

    /**
     * @param RelationContract $relation
     * @param JsonApiResource $example
     * @param  string  $type
     *
     * @return OASchema
     * @throws InvalidArgumentException
     */
    protected function relationshipData(
      RelationContract $relation,
      JsonApiResource $example,
      string $type
    ): OASchema {
        if ($relation instanceof PolymorphicRelation) {

            // @todo Add examples for each available type
            $dataSchema = OASchema::object('data')
              ->title($relation->name())
              ->required('type', 'id')
              ->properties(
                OASchema::string('type')
                  ->title('type')
                  ->enum(...$relation->inverseTypes()),
                OASchema::string('id')
                  ->title('id')
              );
        } else {
            $dataSchema = OASchema::object('data')
              ->title($relation->name())
              ->required('type', 'id')
              ->properties(
                OASchema::string('type')
                  ->title('type')
                  ->default($type),
                OASchema::string('id')
                  ->title('id')
                  ->example($example->id())
              );
        }


        return $dataSchema;
    }

    /**
     * @param mixed  $relation
     * @param JsonApiResource $example
     * @param  string  $type
     *
     * @return OASchema
     */
    public function relationshipLinks(
      $relation,
      JsonApiResource $example,
      string $type
    ): OASchema {
        $relationName = $relation instanceof Relation ? $relation->relationName() : 'Non-eloquent relation';
        $name = Str::dasherize(Str::plural($relationName));

        /*
         * @todo Create real links
         */
        $relatedLink = $this->generator->server()->url([
          $name,
          $example->id(),
        ]);

        /*
         * @todo Create real links
         */
        $selfLink = $this->generator->server()->url([
          $name,
          $example->id(),
        ]);

        return OASchema::object('links')
          ->readOnly(true)
          ->properties(
            OASchema::string('related')
              ->title('related')
              ->example($relatedLink),
            OASchema::string('self')
              ->title('self')
              ->example($selfLink),
          );
    }

    /**
     * @param  \LaravelJsonApi\OpenApiSpec\Route  $route
     * @param JsonApiResource $resource
     *
     * @return array
     */
    protected function links(Route $route, JsonApiResource $resource): array
    {
        $url = $this->generator->server()->url([
          $route->name(),
          $resource->id(),
        ]);
        return [
          OASchema::string('self')
            ->title('self')
            ->example($url),
        ];
    }

    /**
     *
     * @todo Get descriptors from Attributes
     */
    protected function getDescriptor(Eloquent\Contracts\Filter $filter
    ): string {
        foreach ($this->filterDescriptors as $filterClass => $descriptor) {
            if ($filter instanceof $filterClass) {
                return $descriptor;
            }
        }
    }

}
