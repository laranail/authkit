<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PasskeyMorphMany extends HasMany
{
    protected string $morphType;

    protected string $morphClass;

    public function __construct(
        Builder $query,
        Model $parent,
        string $morphType,
        string $foreignKey,
        string $localKey,
    ) {
        $this->morphType = $morphType;
        $this->morphClass = $parent->getMorphClass();

        parent::__construct($query, $parent, $foreignKey, $localKey);
    }

    public function addConstraints()
    {
        if (static::$constraints) {
            $this->getRelationQuery()->where($this->morphType, $this->morphClass);

            parent::addConstraints();
        }
    }

    public function addEagerConstraints(array $models)
    {
        parent::addEagerConstraints($models);

        $this->getRelationQuery()->where($this->morphType, $this->morphClass);
    }

    public function forceCreate(array $attributes = [])
    {
        $attributes[$this->getForeignKeyName()] = $this->getParentKey();
        $attributes[$this->getMorphType()] = $this->morphClass;

        return $this->applyInverseRelationToModel($this->related->forceCreate($attributes));
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if (! empty($values) && ! is_array(array_first($values))) {
            $values = [$values];
        }

        foreach ($values as $key => $value) {
            $values[$key][$this->getMorphType()] = $this->morphClass;
        }

        return parent::upsert($values, $uniqueBy, $update);
    }

    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return parent::getRelationExistenceQuery($query, $parentQuery, $columns)->where(
            $query->qualifyColumn($this->getMorphType()),
            $this->morphClass,
        );
    }

    public function getQualifiedMorphType(): string
    {
        return $this->morphType;
    }

    public function getMorphType(): string
    {
        return last(explode('.', $this->morphType));
    }

    public function getMorphClass(): string
    {
        return $this->morphClass;
    }

    protected function setForeignAttributesForCreate(Model $model)
    {
        $model->{$this->getForeignKeyName()} = $this->getParentKey();
        $model->{$this->getMorphType()} = $this->morphClass;

        foreach ($this->getQuery()->pendingAttributes as $key => $value) {
            $attributes ??= $model->getAttributes();

            if (! array_key_exists($key, $attributes)) {
                $model->setAttribute($key, $value);
            }
        }

        $this->applyInverseRelationToModel($model);
    }
}
