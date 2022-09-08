<?php

namespace Envorra\LaravelSettings\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Envorra\LaravelSettings\Models\Setting;
use Illuminate\Support\Traits\ForwardsCalls;
use Envorra\TypeHandler\Contracts\Types\Type;
use Envorra\LaravelSettings\Contracts\Repository;
use Envorra\LaravelSettings\Helpers\ConfigHelper;
use Envorra\LaravelSettings\Contracts\SettingType;
use Envorra\LaravelSettings\Contracts\SettingOwner;
use Envorra\LaravelSettings\Models\AbstractSettingModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Envorra\LaravelSettings\Resolvers\SettingTypeResolver;

/**
 * SettingsRepository
 *
 * @package Envorra\LaravelSettings\Repositories
 * @mixin Builder
 */
class SettingsRepository implements Repository
{
    use ForwardsCalls;

    protected Builder $builder;

    /**
     * @param  SettingType|null   $scopeSettingType
     * @param  SettingOwner|null  $scopeOwner
     * @param  Type|null          $scopeDataType
     */
    public function __construct(
        protected ?SettingType $scopeSettingType = null,
        protected ?SettingOwner $scopeOwner = null,
        protected ?Type $scopeDataType = null,
    ) {
        $this->initQueryBuilder();
    }

    /**
     * @inheritDoc
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return (new self)->$name(...$arguments);
    }

    /**
     * @inheritDoc
     */
    public function __call(string $name, array $arguments): mixed
    {
        $type = SettingTypeResolver::resolve($name);

        if($type instanceof SettingType) {
            return new self($type, ...$arguments);
        }

        return $this->forwardCallTo($this->builder, $name, $arguments);
    }

    /**
     * @inheritDoc
     */
    public static function modelClass(): string
    {
        return ConfigHelper::model();
    }

    /**
     * @inheritDoc
     */
    public static function model(): AbstractSettingModel
    {
        $class = static::modelClass();
        return new $class();
    }

    /**
     * @param  SettingOwner  $owner
     * @return Repository
     */
    public function addOwnerScope(SettingOwner $owner): Repository
    {
        $this->scopeOwner = $owner;
        $this->initQueryBuilder();
        return $this;
    }

    /**
     * @param  Type  $type
     * @return Repository
     */
    public function addDataTypeScope(Type $type): Repository
    {
        $this->scopeDataType = $type;
        $this->initQueryBuilder();
        return $this;
    }

    /**
     * @param  SettingType  $settingType
     * @return Repository
     */
    public function addSettingTypeScope(SettingType $settingType): Repository
    {
        $this->scopeSettingType = $settingType;
        $this->initQueryBuilder();
        return $this;
    }

    /**
     * @return void
     */
    protected function initQueryBuilder(): void
    {
        $this->builder = $this->newQuery();
    }

    /**
     * @inheritDoc
     */
    public function newQuery(): Builder
    {
        $model = static::model();
        $query = $this->scopeSettingType?->apply($model::query(), $model) ?? $model::query();

        if(!is_null($this->scopeOwner)) {
            $query->whereMorphedTo(ConfigHelper::ownerRelation(), $this->scopeOwner);
        }

        if(!is_null($this->scopeDataType)) {
            $query->where(ConfigHelper::dataTypeColumn(), $this->scopeDataType::type());
        }

        return $query;
    }

    /**
     * @inheritDoc
     */
    public function query(): Builder
    {
        return $this->builder;
    }

    /**
     * @inheritDoc
     */
    public function newInstance(): Repository
    {
        return new self($this->scopeSettingType, $this->scopeOwner, $this->scopeDataType);
    }

    /**
     * @inheritDoc
     */
    public function all(): Collection
    {
        return $this->newQuery()->get();
    }

    /**
     * @inheritDoc
     */
    public function findOrFail(string $key): AbstractSettingModel
    {
        if(!is_null($found = $this->find($key))) {
            return $found;
        }

        throw (new ModelNotFoundException)->setModel(static::modelClass());
    }

    /**
     * @inheritDoc
     */
    public function find(string $key): ?AbstractSettingModel
    {
        return $this->whereKey($key)->first();
    }

    /**
     * @inheritDoc
     */
    public function whereKey(string $key): static
    {
        return $this->where(ConfigHelper::keyColumn(), $key);
    }

    /**
     * @inheritDoc
     */
    public function where(mixed $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        $this->builder->where($column, $operator, $value, $boolean);
        return $this;
    }


    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $valueColumn = ConfigHelper::valueColumn();
        return $this->find($key)?->$valueColumn ?? $default;
    }
}
