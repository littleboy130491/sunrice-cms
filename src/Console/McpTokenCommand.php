<?php

declare(strict_types=1);

namespace Sunrice\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Models\ApiToken;

/**
 * sunrice:mcp-token — creates (or lists / revokes) a user's access token
 * for AI agents connecting to the MCP server.
 */
class McpTokenCommand extends Command
{
    protected $signature = 'sunrice:mcp-token
        {email : The user the agent acts as}
        {--name=AI agent : A name to recognise the token by}
        {--list : List the user\'s tokens instead}
        {--revoke= : Revoke the token with this id}';

    protected $description = 'Create an access token for AI agents (MCP) acting as a user';

    public function handle(): int
    {
        /** @var class-string<Model> $model */
        $model = config('sunrice.auth.user_model');
        $user = $model::query()->where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->components->error('No user with that email.');

            return self::FAILURE;
        }

        if ($this->option('list')) {
            $this->table(['ID', 'Name', 'Last used', 'Created'], ApiToken::query()->where('user_id', $user->getKey())->get()
                ->map(fn (ApiToken $t) => [$t->id, $t->name, $t->last_used_at === null ? 'never' : $t->last_used_at->diffForHumans(), $t->created_at?->toDateString()])->all());

            return self::SUCCESS;
        }

        if ($this->option('revoke') !== null) {
            $deleted = ApiToken::query()->where('user_id', $user->getKey())->whereKey((int) $this->option('revoke'))->delete();
            $deleted ? $this->components->info('Token revoked.') : $this->components->error('No such token for this user.');

            return $deleted ? self::SUCCESS : self::FAILURE;
        }

        if (! method_exists($user, 'can') || ! $user->can('sunrice.ai-access')) {
            $this->components->warn('This user lacks the "Connect AI agents" permission (sunrice.ai-access): the token won\'t work until a role gives it.');
        }

        [, $plain] = ApiToken::issue($user->getKey(), (string) $this->option('name'));

        $this->components->info('Token created. It is shown only once:');
        $this->line($plain);
        $this->newLine();
        $this->line('MCP endpoint: '.url('/'.trim((string) config('sunrice.mcp.path', 'mcp'), '/')));
        $this->line('Send it as the header  Authorization: Bearer '.$plain);

        return self::SUCCESS;
    }
}
