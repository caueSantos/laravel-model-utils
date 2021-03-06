<?php

namespace CaueSantos\LaravelModelUtils\Traits;

use ErrorException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

trait RelationshipsTrait
{

    /**
     * @return array
     * @throws ReflectionException
     */
    public function relationships(): array
    {

        $model = new static;

        $relationships = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {

            if (($method->class != get_class($model) ||
                !empty($method->getParameters()) ||
                $method->getName() == __FUNCTION__)) {
                continue;
            }

            if ($method->hasReturnType() && str_contains($method->getReturnType()->getName(), 'Illuminate\\Database\\Eloquent\\Relations')) {

                /** @var BelongsTo $return */
                $return = $method->invoke($model);
                $relationType = (new ReflectionClass($return))->getShortName();

                $foreignKey = $return->getRelated()->getForeignKey();
                if($relationType === 'BelongTo'){
                    $foreignKey = $return->getForeignKeyName();
                }

                try {
                    $pivot = [
                        'table' => $return->getTable() ?? null,
                        'foreign_key' => $return->getForeignPivotKeyName() ?? null,
                        'related_key' => $return->getRelatedPivotKeyName() ?? null
                    ];
                } catch (\Exception $e) {
                    $pivot = [];
                }

                $relationships[$method->getName()] = [
                    'name' => $method->getName(),
                    'type' => (new ReflectionClass($return))->getShortName(),
                    'model' => (new ReflectionClass($return->getRelated()))->getName(),
                    'table' => $return->getRelated()->getTable(),
                    'primary' => $return->getRelated()->getKeyName(),
                    'foreign_key' => $foreignKey,
                    'pivot' => $pivot
                ];

            }

        }

        return $relationships;
    }

    /**
     * @param $relation_name
     * @return array|bool
     * @throws ReflectionException
     */
    public function hasDefinedRelation($relation_name): array|bool
    {

        $relationsToLook = explode('.', $relation_name);
        $result = [];

        $model = $this;

        foreach ($relationsToLook as $relationToLook) {

            if (isset($model->relationships()[$relationToLook])) {
                $result[$relationToLook] = $model->relationships()[$relationToLook];
                $model = new $result[$relationToLook]['model'];
            } else {
                break;
            }

        }

        return count($result) === count($relationsToLook) ? $result : false;

    }

}
