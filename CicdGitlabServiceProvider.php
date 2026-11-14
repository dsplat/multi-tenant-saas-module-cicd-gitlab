<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\CicdGitlab;

use MultiTenantSaas\Modules\Cicd\Services\CicdProviderRegistry;
use MultiTenantSaas\Modules\CicdGitlab\Contracts\GitlabCicdClientFactoryContract;
use MultiTenantSaas\Modules\CicdGitlab\Services\GitlabCicdClientFactoryAdapter;
use MultiTenantSaas\Modules\CicdGitlab\Services\GitlabCicdProvider;
use MultiTenantSaas\Modules\Contracts\ModuleServiceProvider;
use MultiTenantSaas\Modules\Gitlab\Services\GitlabClientFactory;

/**
 * GitLab CI/CD 适配：把 GitLab provider 注册进 CicdProviderRegistry。
 *
 * 无路由、无表、无权限点；只在 GitLab 模块可用时注册。
 */
class CicdGitlabServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'cicd_gitlab';

    protected function registerModuleBindings(): void
    {
        if (! class_exists(GitlabClientFactory::class) || ! class_exists(CicdProviderRegistry::class)) {
            return;
        }

        if (! $this->app->bound(GitlabClientFactory::class)) {
            return;
        }

        $this->app->singleton(
            GitlabCicdClientFactoryContract::class,
            fn ($app): GitlabCicdClientFactoryAdapter => new GitlabCicdClientFactoryAdapter(
                $app->make(GitlabClientFactory::class),
            ),
        );

        $this->app->singleton(GitlabCicdProvider::class, fn ($app): GitlabCicdProvider => new GitlabCicdProvider(
            $app->make(GitlabCicdClientFactoryContract::class),
            (int) config('cicd_gitlab.per_page', 100),
            (int) config('cicd_gitlab.max_pages', 20),
        ));
    }

    protected function bootModule(): void
    {
        if (! class_exists(CicdProviderRegistry::class)) {
            return;
        }

        if (! $this->app->bound(GitlabCicdClientFactoryContract::class)) {
            return;
        }

        $registry = $this->app->make(CicdProviderRegistry::class);

        if ($registry->get('gitlab') === null) {
            $registry->register($this->app->make(GitlabCicdProvider::class));
        }
    }
}
