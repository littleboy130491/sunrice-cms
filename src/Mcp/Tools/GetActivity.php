<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Models\ActivityLog;

#[IsReadOnly]
#[Description('Read the activity log: who created, changed or deleted what, newest first. Each line has the date, user, source (admin, ai = an AI agent with its access token\'s name in "via", system = console/queue), action, type, name and the fields that changed. Filter with since_days, source, action, type, user (name or email), mine (only changes made with your access token) and search. Use it to answer "what changed recently?" or "what did you do?". Needs the "View the activity log" permission.')]
class GetActivity extends SunriceTool
{
    protected string $name = 'get_activity';

    public function handle(Request $request): Response
    {
        $this->authorize('sunrice.activity.view');
        $args = $request->validate([
            'since_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'source' => ['nullable', 'in:admin,ai,system'],
            'action' => ['nullable', 'string', 'max:30'],
            'type' => ['nullable', 'string', 'max:40'],
            'user' => ['nullable', 'string', 'max:255'],
            'mine' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ActivityLog::query()->orderByDesc('created_at')->orderByDesc('id');
        if (! empty($args['since_days'])) {
            $query->where('created_at', '>=', now()->subDays((int) $args['since_days']));
        }
        foreach (['source' => 'source', 'action' => 'action', 'type' => 'subject_type'] as $arg => $column) {
            if (! empty($args[$arg])) {
                $query->where($column, $args[$arg]);
            }
        }
        if (! empty($args['user'])) {
            $user = $this->userId((string) $args['user']);
            $query->where(fn (Builder $q) => $q->where('user_name', $args['user'])->when($user !== null, fn (Builder $q) => $q->orWhere('user_id', $user)));
        }
        if (! empty($args['mine'])) {
            $token = request()->attributes->get('sunrice.api_token_name');
            $query->where('source', 'ai')->where('user_id', auth()->id())
                ->when(is_string($token), fn (Builder $q) => $q->where('via', $token));
        }
        if (! empty($args['search'])) {
            $term = '%'.$args['search'].'%';
            $query->where(fn (Builder $q) => $q->whereLike('subject_label', $term)->orWhereLike('user_name', $term)->orWhereLike('via', $term));
        }

        $lines = $query->limit((int) ($args['limit'] ?? 25))->get();

        return $this->json([
            'count' => $lines->count(),
            'activity' => $lines->map(fn (ActivityLog $line) => array_filter([
                'at' => $line->created_at?->toIso8601String(),
                'user' => $line->user_name ?? 'System',
                'source' => $line->source,
                'via' => $line->via,
                'action' => $line->action,
                'type' => $line->subject_type,
                'id' => $line->subject_id,
                'name' => $line->subject_label,
                'changes' => $line->properties['changes'] ?? null,
                'note' => $line->properties['note'] ?? null,
            ], fn ($value) => $value !== null))->all(),
        ]);
    }

    /** A user's id by email (or name). */
    protected function userId(string $user): mixed
    {
        /** @var class-string<Model> $model */
        $model = config('sunrice.auth.user_model');

        return $model::query()->where('email', $user)->orWhere('name', $user)->value((new $model)->getKeyName());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'since_days' => $schema->integer()->description('Only the last N days.'),
            'source' => $schema->string()->enum(['admin', 'ai', 'system'])->description('admin, ai (AI agents) or system.'),
            'action' => $schema->string()->description('created, updated, published, unpublished, trashed, restored, deleted, reordered, pruned…'),
            'type' => $schema->string()->description('entry, term, collection, taxonomy, blueprint, fieldset, form, global set, menu, asset, user, role, setting…'),
            'user' => $schema->string()->description('A user\'s email or name.'),
            'mine' => $schema->boolean()->description('Only changes made with your access token.'),
            'search' => $schema->string()->description('Text in the name of what changed, the user\'s name or the token name.'),
            'limit' => $schema->integer()->description('How many lines (default 25, at most 100).'),
        ];
    }
}
