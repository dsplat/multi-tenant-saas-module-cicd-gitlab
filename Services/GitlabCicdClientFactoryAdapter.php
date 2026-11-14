<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\CicdGitlab\Services;

use MultiTenantSaas\Modules\CicdGitlab\Contracts\GitlabCicdClientContract;
use MultiTenantSaas\Modules\CicdGitlab\Contracts\GitlabCicdClientFactoryContract;
use MultiTenantSaas\Modules\Gitlab\Services\GitlabClientFactory;
use MultiTenantSaas\Modules\Integration\Models\IntegrationConnection;

/**
 * 复用 GitLab 模块的连接/SSRF/重试实现，返回收窄后的读取客户端。
 */
final class GitlabCicdClientFactoryAdapter implements GitlabCicdClientFactoryContract
{
    public function __construct(private readonly GitlabClientFactory $factory) {}

    public function forConnection(IntegrationConnection $connection): GitlabCicdClientContract
    {
        return new GitlabCicdClientAdapter($this->factory->forConnection($connection));
    }
}
