<?php


namespace LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Example;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema as OASchema;


class Where extends FilterDescriptor
{

    /**
     *
     * @todo Pay attention to isSingular
     */
    public function filter(): array
    {
        $key = $this->filter->key();
        //$examples = collect($this->generator->resources()
          //->resources($this->route->schema()::model()))
          //->pluck($key)
          //->map(function ($f) {
               //@todo Watch out for ids?
                 //return Example::create($f)->value($f);
          //});
          $examples = [];

        return [
          Parameter::query()
            ->name("filter[{$this->filter->key()}]")
            ->description($this->description())
            ->required(false)
            ->allowEmptyValue(false)
            ->schema(OASchema::string()->default(''))
            ->examples(...$examples)
        ];
    }

    protected function description(): string
    {
        return 'Filters the records';
    }

}
