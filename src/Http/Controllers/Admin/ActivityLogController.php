<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Activity\ActivityLogger;
use Sunrice\Admin\Table\Column;
use Sunrice\Admin\Table\TableQuery;
use Sunrice\Models\ActivityLog;

/**
 * Manage → Activity log: who created, changed or deleted what, filterable
 * by period, source (admin, AI agent, system), action, kind of thing and
 * user; old entries can be pruned.
 */
class ActivityLogController extends Controller
{
    /** Period filter: days back. */
    protected const PERIODS = ['1' => 'Last 24 hours', '7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'];

    public function index(Request $request): Response
    {
        Gate::authorize('sunrice.activity.view');

        $table = TableQuery::for(ActivityLog::query())
            ->searchUsing(fn (Builder $q, string $term) => $q->where(fn (Builder $q) => $q
                ->whereLike('subject_label', "%{$term}%")
                ->orWhereLike('user_name', "%{$term}%")
                ->orWhereLike('via', "%{$term}%")))
            ->filter('action', fn (Builder $q, mixed $value) => $q->where('action', $value))
            ->filter('subject_type', fn (Builder $q, mixed $value) => $q->where('subject_type', $value))
            ->filter('source', fn (Builder $q, mixed $value) => $q->where('source', $value))
            ->filter('user', fn (Builder $q, mixed $value) => $value === 'system' ? $q->whereNull('user_id') : $q->where('user_id', $value))
            ->filter('period', fn (Builder $q, mixed $value) => $q->where('created_at', '>=', now()->subDays(max(1, (int) $value))))
            ->sortable(['created_at'])
            ->apply($request);
        if ($table->meta()['sort'] === null) {
            $table->builder()->orderByDesc('created_at')->orderByDesc('id');
        }

        $rows = $table->defaultPerPage(TablePreferencesController::perPageFor($request, 'activity', 25))->paginate($request);

        return Inertia::render('Activity/Index', [
            'columns' => [
                new Column('created_at', 'Date', sortable: true),
                new Column('user', 'User'),
                new Column('source', 'Source'),
                new Column('action', 'Action', type: 'badge'),
                new Column('activity', 'Activity'),
                new Column('details', 'Details'),
            ],
            'rows' => $rows->through(function (Model $line): array {
                /** @var ActivityLog $line */
                return [
                    'id' => $line->id,
                    'created_at' => $line->created_at?->format('j M Y, H:i'),
                    'user' => $line->user_name ?? 'System',
                    'source' => $this->source($line),
                    'action' => $line->action,
                    'activity' => $this->sentence($line),
                    'details' => $this->details($line),
                ];
            }),
            'meta' => $table->meta(),
            'filters' => $this->filters(),
            'can' => ['prune' => $request->user()->can('sunrice.activity.prune')],
            'pruneDays' => (int) config('sunrice.activity.prune_days', 180),
        ]);
    }

    public function prune(Request $request, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('sunrice.activity.prune');
        $days = (int) $request->validate(['days' => ['required', 'integer', 'min:0', 'max:36500']])['days'];

        $deleted = ActivityLog::prune($days);
        $logger->record('pruned', 'Activity log', ['days' => $days, 'deleted' => $deleted]);

        return back()->with('success', "Deleted {$deleted} ".($deleted === 1 ? 'entry' : 'entries')." older than {$days} days.");
    }

    /** "Admin", "AI agent · Claude" or "System". */
    protected function source(ActivityLog $line): string
    {
        return match ($line->source) {
            'ai' => 'AI agent'.($line->via ? ' · '.$line->via : ''),
            'system' => 'System',
            'admin' => 'Admin',
            default => Str::headline($line->source),
        };
    }

    /** "Updated entry “About us (Pages)”". */
    protected function sentence(ActivityLog $line): string
    {
        $label = $line->subject_label ? '“'.$line->subject_label.'”' : '';
        if ($line->subject_type === 'system') {
            return trim(Str::ucfirst($line->action).' '.Str::lower((string) $line->subject_label));
        }

        return trim(Str::ucfirst($line->action).' '.$line->subject_type.' '.$label);
    }

    protected function details(ActivityLog $line): string
    {
        $properties = $line->properties ?? [];
        $parts = [];
        if (! empty($properties['changes'])) {
            $parts[] = implode(', ', (array) $properties['changes']);
        }
        if (isset($properties['days'], $properties['deleted'])) {
            $parts[] = "{$properties['deleted']} older than {$properties['days']} days";
        }
        if (! empty($properties['note'])) {
            $parts[] = (string) $properties['note'];
        }

        return Str::limit(implode('; ', $parts), 160) ?: '—';
    }

    /**
     * Filter choices from what the log holds.
     *
     * @return array<int, array{key: string, label: string, type: string, options: array<int, array{value: string, label: string}>}>
     */
    protected function filters(): array
    {
        $options = fn (string $column) => ActivityLog::query()->distinct()->orderBy($column)->pluck($column)
            ->filter()->map(fn ($v) => ['value' => (string) $v, 'label' => Str::ucfirst((string) $v)])->values()->all();

        $users = ActivityLog::query()->whereNotNull('user_id')->select('user_id', 'user_name')->distinct()->get()
            ->unique('user_id')->sortBy('user_name')
            ->map(fn (ActivityLog $l) => ['value' => (string) $l->user_id, 'label' => (string) ($l->user_name ?? '#'.$l->user_id)])->values()->all();

        return [
            ['key' => 'period', 'label' => 'Period', 'type' => 'select', 'options' => collect(self::PERIODS)->map(fn ($label, $days) => ['value' => (string) $days, 'label' => $label])->values()->all()],
            ['key' => 'source', 'label' => 'Source', 'type' => 'select', 'options' => [
                ['value' => 'admin', 'label' => 'Admin'],
                ['value' => 'ai', 'label' => 'AI agent'],
                ['value' => 'system', 'label' => 'System'],
            ]],
            ['key' => 'action', 'label' => 'Action', 'type' => 'select', 'options' => $options('action')],
            ['key' => 'subject_type', 'label' => 'Type', 'type' => 'select', 'options' => $options('subject_type')],
            ['key' => 'user', 'label' => 'User', 'type' => 'select', 'options' => [...$users, ['value' => 'system', 'label' => 'System']]],
        ];
    }
}
