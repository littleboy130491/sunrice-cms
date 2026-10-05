<?php

declare(strict_types=1);

namespace Sunrice\Admin\Table;

/**
 * Table column descriptor shared between the backend table queries
 * and the DataTable React component.
 */
readonly class Column implements \JsonSerializable
{
    /**
     * @param  string  $type  text | number | date | boolean | badge | json
     */
    public function __construct(
        public string $key,
        public string $label,
        public bool $sortable = false,
        public string $type = 'text',
    ) {}

    /**
     * @return array{key: string, label: string, sortable: bool, type: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'sortable' => $this->sortable,
            'type' => $this->type,
        ];
    }
}
