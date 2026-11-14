<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\CicdGitlab\Services;

use MultiTenantSaas\Modules\CicdGitlab\Contracts\GitlabCicdClientContract;
use MultiTenantSaas\Modules\Gitlab\Contracts\GitlabClientContract;

/**
 * 把完整的 GitLab client 收窄成 CicdGitlab 需要的四个列表方法。
 */
final class GitlabCicdClientAdapter implements GitlabCicdClientContract
{
    public function __construct(private readonly GitlabClientContract $client) {}

    public function listGroupProjects(int|string $groupId, array $query = []): array
    {
        return $this->client->listGroupProjects($groupId, $query);
    }

    public function listPipelines(int|string $projectId, array $query = []): array
    {
        return $this->client->listPipelines($projectId, $query);
    }

    public function listReleases(int|string $projectId, array $query = []): array
    {
        return $this->client->listReleases($projectId, $query);
    }

    public function listDeployments(int|string $projectId, array $query = []): array
    {
        return $this->client->listDeployments($projectId, $query);
    }
}
