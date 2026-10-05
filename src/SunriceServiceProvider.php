<?php

declare(strict_types=1);

namespace Sunrice;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SunriceServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('sunrice')
            ->hasConfigFile()
            ->hasViews()
            ->discoversMigrations()
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Sunrice::class);
        $this->app->singleton(Fields\FieldRegistry::class, function (): Fields\FieldRegistry {
            $registry = new Fields\FieldRegistry;

            foreach ($this->builtinFieldTypes() as $type) {
                $registry->register($type);
            }

            return $registry;
        });
    }

    /**
     * @return array<int, class-string<Fields\FieldType>>
     */
    protected function builtinFieldTypes(): array
    {
        return [
            Fields\Types\Text::class,
            Fields\Types\Textarea::class,
            Fields\Types\RichText::class,
            Fields\Types\Number::class,
            Fields\Types\Toggle::class,
            Fields\Types\Select::class,
            Fields\Types\Date::class,
            Fields\Types\Link::class,
            Fields\Types\Asset::class,
            Fields\Types\Entries::class,
            Fields\Types\Terms::class,
            Fields\Types\Group::class,
            Fields\Types\Repeater::class,
            Fields\Types\Flexible::class,
            Fields\Types\FieldsetInclude::class,
            Fields\Types\File::class,
        ];
    }

    public function packageBooted(): void
    {
        $this->registerCommands();
        $this->registerRouteMacro();
        $this->registerSchedule();
        $this->registerPermissions();
    }

    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Console\PublishScheduledCommand::class,
            Console\RenameFieldCommand::class,
            Console\SyncPermissionsCommand::class,
            Console\InstallCommand::class,
        ]);
    }

    protected function registerRouteMacro(): void
    {
        // Frontend catch-all is registered after the host application's routes.
        $this->app->booted(function (): void {
            if (! config('sunrice.frontend.enabled', true)) {
                return;
            }

            \Illuminate\Support\Facades\Route::middleware(config('sunrice.frontend.middleware', ['web']))
                ->group(__DIR__.'/../routes/frontend.php');
        });
    }

    protected function registerSchedule(): void
    {
        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule): void {
            $schedule->command('sunrice:publish-scheduled')->everyMinute();
            $schedule->command('model:prune', ['--model' => \Sunrice\Models\FormSubmission::class])->daily();
        });
    }

    /**
     * Permission abilities: sunrice.<area>.<id>.<action> plus the
     * wildcard areas the admin middleware checks. The Super Admin
     * role bypasses every ability.
     */
    protected function registerPermissions(): void
    {
        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability): ?bool {
            $role = config('sunrice.super_admin_role');
            if ($role !== null && method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return true;
            }

            return null;
        });

        foreach ($this->policyMap() as $model => $policy) {
            \Illuminate\Support\Facades\Gate::policy($model, $policy);
        }

        $userModel = config('sunrice.auth.user_model');
        if (is_string($userModel)) {
            \Illuminate\Support\Facades\Gate::policy($userModel, \Sunrice\Policies\UserPolicy::class);
        }
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policyMap(): array
    {
        return [
            \Sunrice\Models\Entry::class => \Sunrice\Policies\EntryPolicy::class,
            \Sunrice\Models\Term::class => \Sunrice\Policies\TermPolicy::class,
            \Sunrice\Models\Asset::class => \Sunrice\Policies\AssetPolicy::class,
            \Sunrice\Models\Form::class => \Sunrice\Policies\FormPolicy::class,
            \Sunrice\Models\Collection::class => \Sunrice\Policies\StructurePolicy::class,
            \Sunrice\Models\Blueprint::class => \Sunrice\Policies\StructurePolicy::class,
            \Sunrice\Models\Fieldset::class => \Sunrice\Policies\StructurePolicy::class,
            \Sunrice\Models\Taxonomy::class => \Sunrice\Policies\StructurePolicy::class,
            \Sunrice\Models\Menu::class => \Sunrice\Policies\MenuPolicy::class,
            \Sunrice\Models\GlobalSet::class => \Sunrice\Policies\GlobalSetPolicy::class,
            \Spatie\Permission\Models\Role::class => \Sunrice\Policies\RolePolicy::class,
        ];
    }
}
