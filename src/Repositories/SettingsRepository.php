<?php

namespace Envorra\LaravelSettings\Repositories;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Envorra\LaravelSettings\Enums\DataType;
use Envorra\LaravelSettings\Models\Setting;
use Envorra\LaravelSettings\Enums\SettingType;
use Envorra\LaravelSettings\Actions\Actionable;
use Envorra\LaravelSettings\Contracts\Repository;
use Envorra\LaravelSettings\Actions\CreateSettingForUser;
use Envorra\LaravelSettings\Collections\SettingsCollection;

/**
 * SettingsRepository
 *
 * @package Envorra\LaravelSettings
 */
class SettingsRepository implements Repository
{

    /**
     * @inheritDoc
     */
    public function __construct(
        protected ?SettingType $scopeSettingType = null,
        protected ?Model $scopeOwner = null,
        protected ?DataType $scopeDataType = null,
        protected ?Builder $query = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function app(
        ?DataType $scopeDataType = null,
        ?Builder $query = null
    ): static {
        return static::instance(
            scopeSettingType: SettingType::APP,
            scopeDataType: $scopeDataType,
            query: $query
        );
    }

    /**
     * @inheritDoc
     */
    public static function global(
        ?DataType $scopeDataType = null,
        ?Builder $query = null
    ): static {
        return static::instance(
            scopeSettingType: SettingType::GLOBAL,
            scopeDataType: $scopeDataType,
            query: $query
        );
    }

    /**
     * @inheritDoc
     */
    public static function instance(
        ?SettingType $scopeSettingType = null,
        ?Model $scopeOwner = null,
        ?DataType $scopeDataType = null,
        ?Builder $query = null,
    ): static {
        return new static($scopeSettingType, $scopeOwner, $scopeDataType, $query);
    }

    /**
     * @inheritDoc
     */
    public static function model(
        Model $scopeOwner,
        ?DataType $scopeDataType = null,
        ?Builder $query = null
    ): static {
        return static::instance(
            scopeSettingType: SettingType::MODEL,
            scopeOwner: $scopeOwner,
            scopeDataType: $scopeDataType,
            query: $query
        );
    }

    /**
     * @inheritDoc
     */
    public static function user(
        ?Model $scopeUser = null,
        ?DataType $scopeDataType = null,
        ?Builder $query = null
    ): static {
        return static::instance(
            scopeSettingType: SettingType::USER,
            scopeOwner: $scopeUser ?? Auth::user(),
            scopeDataType: $scopeDataType,
            query: $query
        );
    }

    /**
     * @inheritDoc
     */
    public function all(): SettingsCollection
    {
        return $this->normalizeCollection($this->query()->get());
    }

    /**
     * @inheritDoc
     */
    public function allOfDataType(DataType $dataType): SettingsCollection
    {
        return $this->normalizeCollection($this->query()->where('data_type', $dataType)->get());
    }

    /**
     * @inheritDoc
     */
    public function allOfSettingType(SettingType $settingType): SettingsCollection
    {
        return $this->normalizeCollection($this->query()->where('setting_type', $settingType)->get());
    }

    /**
     * @inheritDoc
     */
    public function allRelatedToModel(Model $model, array|SettingType $filterTypes = []): SettingsCollection
    {
        return $this->normalizeCollection(
            $this->filterQuery(
                query: $this->query()->whereMorphedTo('owner', $model),
                filterTypes: $filterTypes
            )->get()
        );
    }

    /**
     * @inheritDoc
     */
    public function find(string $key): ?Setting
    {
        $collection = new SettingsCollection($this->where('key', $key)->take(1)->get());
        return $collection->first();
    }

    /**
     * @inheritDoc
     */
    public function findOrFail(string $key): Setting
    {
        $collection = new SettingsCollection($this->where('key', $key)->take(1)->get());
        return $collection->firstOrFail();
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->find($key)?->value ?? $default;
    }

    /**
     * Get the model.
     *
     * @return Setting
     */
    public function getModel(): Setting
    {
        $settingsModel = config('laravel_settings.settings_model', Setting::class);
        return new $settingsModel();
    }

    /**
     * @inheritDoc
     */
    public function newQuery(): Builder
    {
        $this->query = $this->getModel()::query();

        if ($this->scopeSettingType) {
            $this->query->where('setting_type', $this->scopeSettingType);
        }

        if ($this->scopeOwner) {
            $this->query->whereMorphedTo('owner', $this->scopeOwner);
        }

        if ($this->scopeDataType) {
            $this->query->where('data_type', $this->scopeDataType);
        }

        return $this->query;
    }

    /**
     * @inheritDoc
     */
    public function query(): Builder
    {
        if (!$this->query) {
            $this->newQuery();
        }

        return $this->query;
    }

    /**
     * @inheritDoc
     */
    public function set(
        string $key,
        mixed $value,
        ?string $description = null,
        ?SettingType $settingType = null,
        ?DataType $dataType = null,
        ?Model $owner = null
    ): Setting {

        if ($setting = $this->find($key)) {
            $setting->value = $value;
            $setting->save();
            return $setting;
        }

        $action = new CreateSettingForUser();

        return $action->handle(new Actionable($this, $this->getModel(), []));
    }

    /**
     * @inheritDoc
     */
    public function where(string $field, mixed $operatorOrValue, mixed $valueOrNull = null): Builder
    {
        return $this->query()->where($field, $operatorOrValue, $valueOrNull);
    }

    /**
     * @inheritDoc
     */
    public function whereOwner(Model $owner): Builder
    {
        return $this->query()->whereMorphedTo('owner', $owner);
    }

    /**
     * Add where clauses to filter the query.
     *
     * @param  Builder            $query
     * @param  array|SettingType  $filterTypes
     * @return Builder
     */
    protected function filterQuery(Builder $query, array|SettingType $filterTypes = []): Builder
    {
        if (count($filterTypes)) {
            $query->where(function ($subQuery) use ($filterTypes) {
                $wheres = 1;
                foreach (Arr::wrap($filterTypes) as $type) {
                    $type = SettingType::make($type);
                    $whereMethod = $wheres === 1 ? 'where' : 'orWhere';
                    $subQuery->$whereMethod('setting_type', $type);
                    $wheres++;
                }
            });
        }

        return $query;
    }

    /**
     * Normalize the Collection to a SettingsCollection.
     *
     * @template TKey of array-key
     * @param  iterable<TKey, Setting|array>  $iterable
     * @return SettingsCollection
     */
    protected function normalizeCollection(iterable $iterable): SettingsCollection
    {
        return new SettingsCollection($iterable);
    }


}
