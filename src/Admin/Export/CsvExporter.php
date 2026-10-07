<?php

declare(strict_types=1);

namespace Sunrice\Admin\Export;

use Illuminate\Support\Facades\Response;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Sunrice\Admin\Table\Column;
use Sunrice\Admin\Table\TableQuery;
use Sunrice\Support\CsvCell;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a TableQuery result to CSV. Uses the same filters/search as
 * the table, ignores pagination; rows are fetched lazily in chunks.
 */
class CsvExporter
{
    /**
     * @param  array<int, Column>  $columns
     */
    public function download(TableQuery $table, array $columns, string $filename = 'export.csv'): StreamedResponse
    {
        return Response::streamDownload(function () use ($table, $columns): void {
            $writer = SimpleExcelWriter::create('php://output', 'csv');
            $writer->addHeader(array_map(fn (Column $c) => $c->label, $columns));

            $table->builder()->lazy(200)->each(function ($model) use ($writer, $columns): void {
                $writer->addRow($this->row($model, $columns));
            });

            $writer->close();
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array<int, Column>  $columns
     * @return array<int, string>
     */
    protected function row(mixed $model, array $columns): array
    {
        return array_map(function (Column $column) use ($model): string {
            $value = $this->value($model, $column->key);

            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            if (is_array($value) || is_object($value)) {
                return (string) CsvCell::safe((string) json_encode($value, JSON_UNESCAPED_UNICODE));
            }

            return (string) CsvCell::safe((string) ($value ?? ''));
        }, $columns);
    }

    /**
     * Resolves `key` or `data.key` / `relation.key` on a model.
     */
    protected function value(mixed $model, string $key): mixed
    {
        if (str_starts_with($key, 'data.')) {
            return data_get($model->data ?? [], substr($key, 5));
        }

        return data_get($model, $key);
    }
}
