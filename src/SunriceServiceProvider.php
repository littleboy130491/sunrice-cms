<?php

declare(strict_types=1);

namespace Sunrice;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\Permission\Models\Role;
use Sunrice\Http\Middleware\HandleSunriceInertiaRequests;
use Sunrice\Models\Asset;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\Fieldset;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;
use Sunrice\Models\GlobalSet;
use Sunrice\Models\Menu;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Policies\AssetPolicy;
use Sunrice\Policies\EntryPolicy;
use Sunrice\Policies\FormPolicy;
use Sunrice\Policies\GlobalSetPolicy;
use Sunrice\Policies\MenuPolicy;
use Sunrice\Policies\RolePolicy;
use Sunrice\Policies\StructurePolicy;
use Sunrice\Policies\TermPolicy;
use Sunrice\Policies\UserPolicy;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        $this->registerAdminRoutes();
        $this->registerRouteMacro();
        $this->registerSchedule();
        $this->registerPermissions();
        $this->registerExceptionRendering();
        $this->registerAssetPublishing();
    }

    protected function registerAdminRoutes(): void
    {
        Route::middleware(array_merge(
            ['web'],
            (array) config('sunrice.admin.middleware', []),
            [HandleSunriceInertiaRequests::class],
        ))
            ->prefix(config('sunrice.admin.path', 'cms'))
            ->name('sunrice.admin.')
            ->group(__DIR__.'/../routes/admin.php');
    }

    protected function registerAssetPublishing(): void
    {
        if (is_dir(__DIR__.'/../dist')) {
            $this->publishes([__DIR__.'/../dist' => public_path('vendor/sunrice')], 'sunrice-assets');
        }
    }

    /**
     * Render 403/404/500 through the Inertia Error page — only for
     * requests under the admin path.
     */
    protected function registerExceptionRendering(): void
    {
        $this->callAfterResolving(Handler::class, function ($handler): void {
            $handler->renderable(function (\Throwable $e, $request) {
                $path = config('sunrice.admin.path', 'cms');
                if (! $request->is($path) && ! $request->is($path.'/*')) {
                    return null;
                }

                // Let the framework handle auth redirects and validation.
                if ($e instanceof AuthenticationException
                    || $e instanceof ValidationException) {
                    return null;
                }

                $status = $e instanceof HttpExceptionInterface
                    ? $e->getStatusCode()
                    : 500;

                if (! in_array($status, [403, 404, 500], true)) {
                    return null;
                }

                return Inertia::render('Error', ['status' => $status])
                    ->rootView('sunrice::app')
                    ->toResponse($request)
                    ->setStatusCode($status);
            });
        });
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

            Route::middleware(config('sunrice.frontend.middleware', ['web']))
                ->group(__DIR__.'/../routes/frontend.php');
        });
    }

    protected function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function ($schedule): void {
            $schedule->command('sunrice:publish-scheduled')->everyMinute();
            $schedule->command('model:prune', ['--model' => FormSubmission::class])->daily();
        });
    }

    /**
     * Permission abilities: sunrice.<area>.<id>.<action> plus the
     * wildcard areas the admin middleware checks. The Super Admin
     * role bypasses every ability.
     */
    protected function registerPermissions(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            $role = config('sunrice.super_admin_role');
            if ($role !== null && method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return true;
            }

            return null;
        });

        foreach ($this->policyMap() as $model => $policy) {
            Gate::policy($model, $policy);
        }

        $userModel = config('sunrice.auth.user_model');
        if (is_string($userModel)) {
            Gate::policy($userModel, UserPolicy::class);
        }
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policyMap(): array
    {
        return [
            Entry::class => EntryPolicy::class,
            Term::class => TermPolicy::class,
            Asset::class => AssetPolicy::class,
            Form::class => FormPolicy::class,
            Collection::class => StructurePolicy::class,
            Blueprint::class => StructurePolicy::class,
            Fieldset::class => StructurePolicy::class,
            Taxonomy::class => StructurePolicy::class,
            Menu::class => MenuPolicy::class,
            GlobalSet::class => GlobalSetPolicy::class,
            Role::class => RolePolicy::class,
        ];
    }
}
