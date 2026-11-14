<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\CicdGitlab\Contracts;

/**
 * CicdGitlab 需要的最小 GitLab 读取面（便于测试与解耦）。
 */
interface GitlabCicdClientContract
{
    /** @return list<array<string, mixed>> */
    public function listGroupProjects(int|string $groupId, array $query = []): array;

    /** @return list<array<string, mixed>> */
    public function listPipelines(int|string $projectId, array $query = []): array;

    /** @return list<array<string, mixed>> */
    public function listReleases(int|string $projectId, array $query = []): array;

    /** @return list<array<string, mixed>> */
    public function listDeployments(int|string $projectId, array $query = []): array;
}
