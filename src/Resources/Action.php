<?php

declare(strict_types=1);

namespace Sunrice\Resources;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A button on a resource record ("Mark as paid", "Send invoice") or, with
 * bulk(), on the records selected in its list. Either runs a handler on
 * the server, or with url() opens an address (a PDF download, the record
 * on the site).
 *
 *     Action::make('mark_paid')
 *         ->label('Mark as paid')
 *         ->confirm('Mark this order as paid?')
 *         ->visible(fn (Order $order) => $order->status === 'pending')
 *         ->handle(fn (Order $order) => $order->markPaid())
 *         ->bulk();
 *
 * Who may run it: users with the resource's `edit` permission (or the one
 * named with ability()), the model policy's matching ability when it has
 * one, and the authorize() callback when given.
 */
class Action
{
    protected string $label;

    protected ?string $confirm = null;

    protected bool $destructive = false;

    protected bool $bulk = false;

    protected string $ability = 'edit';

    protected ?Closure $visible = null;

    protected ?Closure $authorize = null;

    protected ?Closure $handler = null;

    protected ?Closure $url = null;

    protected bool $newTab = false;

    protected function __construct(public readonly string $key)
    {
        if (preg_match('/^[a-z0-9_-]+$/', $key) !== 1 || $key === 'delete') {
            throw new InvalidArgumentException("Action key \"{$key}\": use lowercase letters, numbers, - and _ (\"delete\" is taken).");
        }
        $this->label = Str::headline($key);
    }

    public static function make(string $key): static
    {
        return new static($key); // @phpstan-ignore-line - late static binding intended
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /** Ask before running ("Refund this order?"). */
    public function confirm(string $question): static
    {
        $this->confirm = $question;

        return $this;
    }

    /** Show the button in red. */
    public function destructive(bool $destructive = true): static
    {
        $this->destructive = $destructive;

        return $this;
    }

    /** Also offer it for the records selected in the list; the handler runs once per record. */
    public function bulk(bool $bulk = true): static
    {
        $this->bulk = $bulk;

        return $this;
    }

    /** The resource permission needed: view, create, edit (default), delete or export. */
    public function ability(string $ability): static
    {
        $this->ability = $ability;

        return $this;
    }

    /**
     * Show it only for some records: fn (Model $record): bool. Hidden
     * records are refused too, and skipped in bulk runs.
     */
    public function visible(Closure $callback): static
    {
        $this->visible = $callback;

        return $this;
    }

    /** An extra check: fn ($user, Model $record): bool. */
    public function authorize(Closure $callback): static
    {
        $this->authorize = $callback;

        return $this;
    }

    /**
     * What it does: fn (Model $record). Return a message to show
     * ("Invoice sent."), or throw ActionFailed to show an error.
     */
    public function handle(Closure $handler): static
    {
        $this->handler = $handler;

        return $this;
    }

    /** Open an address instead of running a handler: fn (Model $record): string. */
    public function url(Closure $url, bool $newTab = true): static
    {
        $this->url = $url;
        $this->newTab = $newTab;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getAbility(): string
    {
        return $this->ability;
    }

    public function isBulk(): bool
    {
        return $this->bulk && $this->url === null;
    }

    /** Whether it opens an address (url()) rather than running a handler. */
    public function opensUrl(): bool
    {
        return $this->url !== null;
    }

    public function isVisibleFor(Model $record): bool
    {
        return $this->visible === null || (bool) ($this->visible)($record);
    }

    public function passesAuthorize(?Authenticatable $user, Model $record): bool
    {
        return $this->authorize === null || (bool) ($this->authorize)($user, $record);
    }

    /** Run the handler on one record; its message, if it returned one. */
    public function run(Model $record): ?string
    {
        if ($this->handler === null) {
            throw new InvalidArgumentException("Action \"{$this->key}\" has no handle() callback.");
        }
        $result = ($this->handler)($record);

        return is_string($result) && $result !== '' ? $result : null;
    }

    /**
     * For the admin: a record's button, or the list's bulk button (no record).
     *
     * @return array{key: string, label: string, confirm: string|null, destructive: bool, url: string|null, new_tab: bool}
     */
    public function toArray(?Model $record = null): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'confirm' => $this->confirm,
            'destructive' => $this->destructive,
            'url' => $this->url !== null && $record !== null ? (string) ($this->url)($record) : null,
            'new_tab' => $this->newTab,
        ];
    }
}
