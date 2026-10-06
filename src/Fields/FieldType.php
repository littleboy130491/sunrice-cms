<?php

declare(strict_types=1);

namespace Sunrice\Fields;

/**
 * A field type. Field definitions are stored as JSON arrays:
 * {handle, type, label, instructions, required, validation, translatable, config}.
 */
abstract class FieldType
{
    /**
     * The type identifier stored in field definitions (e.g. 'text').
     */
    abstract public static function type(): string;

    /**
     * Laravel validation rules for this value, merged with the
     * definition's `required`/`validation` keys by BlueprintSchema.
     *
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    public function rules(array $field): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public function defaultValue(array $field): mixed
    {
        return $field['config']['default'] ?? null;
    }

    /**
     * Clean the posted value before storage.
     *
     * @param  array<string, mixed>  $field
     */
    public function normalize(mixed $value, array $field): mixed
    {
        return $value;
    }

    /**
     * Turn the stored value into the frontend value.
     *
     * @param  array<string, mixed>  $field
     */
    public function hydrate(mixed $value, array $field, HydrationContext $ctx): mixed
    {
        return $value;
    }

    /**
     * Records this value points at (assets, entries, terms) used to
     * rebuild the references table.
     *
     * @param  array<string, mixed>  $field
     * @return array<int, array{target_type: string, target_id: int, field_path?: string}>
     */
    public function references(mixed $value, array $field): array
    {
        return [];
    }

    /**
     * JSON schema handed to the React admin for this field definition.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    public function toAdminSchema(array $field): array
    {
        return $field;
    }

    /**
     * Whether values of this type differ per language when the field
     * definition doesn't set `translatable` itself. Text types default to
     * true; containers default to true so their children decide.
     */
    public function translatableByDefault(): bool
    {
        return false;
    }

    /**
     * Whether admin tables can filter on this field.
     */
    public function filterable(): bool
    {
        return $this->sortCast() !== null;
    }

    /**
     * Sort cast used by JsonField for ordering/filtering ('string' |
     * 'number' | 'date' | null).
     */
    public function sortCast(): ?string
    {
        return null;
    }

    /**
     * The type's own settings as a list of field definitions, rendered
     * by the blueprint builder UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function settingsSchema(): array
    {
        return [];
    }

    // ---- helpers for container types --------------------------------------

    protected function registry(): FieldRegistry
    {
        return app(FieldRegistry::class);
    }

    /** @param array<string, mixed> $definition */
    protected function field(array $definition): FieldType
    {
        return $this->registry()->get($definition['type'] ?? 'text');
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, array<string, mixed>>
     */
    protected function children(array $field): array
    {
        return $field['config']['fields'] ?? [];
    }
}
