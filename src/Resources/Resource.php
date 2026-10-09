<?php

declare(strict_types=1);

namespace Sunrice\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Sunrice\Admin\Table\Column;

/**
 * Registers an existing Eloquent model for CMS management. A resource
 * defines its navigation, editing fields, table columns, filters,
 * sorting and actions; CRUD runs through the generic ResourceController.
 */
abstract class Resource
{
    /** @var class-string<Model> */
    public static string $model = Model::class;

    /**
     * Kebab plural of the model basename, e.g. Product -> products.
     */
    public static function key(): string
    {
        return Str::plural(Str::kebab(class_basename(static::$model)));
    }

    public static function label(): string
    {
        return Str::headline(Str::plural(class_basename(static::$model)));
    }

    public static function singularLabel(): string
    {
        return Str::headline(class_basename(static::$model));
    }

    public static function navigationGroup(): ?string
    {
        return null;
    }

    public static function navigationIcon(): ?string
    {
        return 'box';
    }

    /**
     * Editing fields for the create/edit form.
     *
     * @return array<int, ResourceField>
     */
    public static function fields(): array
    {
        return [];
    }

    /**
     * Index table columns (of \Sunrice\Admin\Table\Column).
     *
     * @return array<int, Column>
     */
    public static function columns(): array
    {
        return [];
    }

    /**
     * @return array<int, Filter>
     */
    public static function filters(): array
    {
        return [];
    }

    /**
     * Attribute names matched by the table search box.
     *
     * @return array<int, string>
     */
    public static function searchable(): array
    {
        return [];
    }

    public static function defaultSort(): ?string
    {
        return null;
    }

    /**
     * Validation rules for create/update.
     *
     * @return array<string, mixed>
     */
    public static function rules(?Model $record = null): array
    {
        return [];
    }

    /**
     * Scope hook applied to every resource query (index, edit, export).
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function query(Builder $query): Builder
    {
        return $query;
    }

    /**
     * Buttons on a record's page, and with bulk() on the list's selection
     * ("Mark as paid", "Send invoice", "Download PDF").
     *
     * @return array<int, Action>
     */
    public static function actions(): array
    {
        return [];
    }

    /** One of actions() by key. */
    public static function action(string $key): ?Action
    {
        foreach (static::actions() as $action) {
            if ($action->key === $key) {
                return $action;
            }
        }

        return null;
    }

    /**
     * Relations eager-loaded on the index table.
     *
     * @return array<int, string>
     */
    public static function with(): array
    {
        return [];
    }

    /** @return class-string<Model> */
    public static function model(): string
    {
        return static::$model;
    }

    /**
     * Blueprint-style admin schema for the React form.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function adminSchema(): array
    {
        return array_map(
            fn (ResourceField $field) => $field->toAdminField(),
            static::fields(),
        );
    }
}
