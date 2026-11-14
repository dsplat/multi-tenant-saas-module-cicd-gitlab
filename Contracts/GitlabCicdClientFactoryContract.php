<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\CicdGitlab\Contracts;

use MultiTenantSaas\Modules\Integration\Models\IntegrationConnection;

/**
 * 按 integration_connections 构造 GitLab 读取客户端。
 */
interface GitlabCicdClientFactoryContract
{
    public function forConnection(IntegrationConnection $connection): GitlabCicdClientContract;
}
