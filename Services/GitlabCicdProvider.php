<?php

declare(strict_types=1);

namespace MultiTenantSaas\Modules\CicdGitlab\Services;

use MultiTenantSaas\Exceptions\DomainException;
use MultiTenantSaas\Exceptions\ServiceUnavailableException;
use MultiTenantSaas\Modules\Cicd\Contracts\CicdProviderContract;
use MultiTenantSaas\Modules\Cicd\Models\Repository;
use MultiTenantSaas\Modules\Cicd\Models\Workspace;
use MultiTenantSaas\Modules\CicdGitlab\Contracts\GitlabCicdClientContract;
use MultiTenantSaas\Modules\CicdGitlab\Contracts\GitlabCicdClientFactoryContract;
use MultiTenantSaas\Modules\Integration\Models\IntegrationConnection;

/**
 * GitLab provider：分组项目 → 仓库，项目流水线/发布/部署。
 *
 * 只做字段映射；租户/连接由 Workspace.connection_id 决定，客户端由
 * GitlabClientFactory 构造（token/SSRF/重试仍在 GitLab 模块内）。
 */
final class GitlabCicdProvider implements CicdProviderContract
{
    public function __construct(
        private readonly GitlabCicdClientFactoryContract $factory,
        private readonly int $perPage = 100,
        private readonly int $maxPages = 20,
    ) {}

    public function key(): string
    {
        return 'gitlab';
    }

    public function capabilities(): array
    {
        return [
            'forge' => true,
            'executor' => true,
            'control' => false,
            'resources' => ['repositories', 'pipelines', 'releases', 'deployments'],
        ];
    }

    public function fetchRepositories(Workspace $workspace): iterable
    {
        $client = $this->clientFor($workspace);
        $groupId = (string) ($workspace->external_group_id ?: $workspace->group_path);

        if ($groupId === '') {
            throw new DomainException('workspace_group_missing');
        }

        $projects = $this->paginate(fn (int $page): array => $client->listGroupProjects($groupId, [
            'per_page' => $this->perPage,
            'page' => $page,
            'include_subgroups' => true,
            'order_by' => 'last_activity_at',
            'sort' => 'desc',
        ]));

        $rows = [];

        foreach ($projects as $project) {
            if (empty($project['id'])) {
                continue;
            }

            $rows[] = [
                'external_id' => (string) $project['id'],
                'path' => (string) ($project['path_with_namespace'] ?? $project['path'] ?? ''),
                'name' => (string) ($project['name'] ?? $project['path'] ?? ''),
                'default_branch' => $project['default_branch'] ?? null,
                'web_url' => $project['web_url'] ?? null,
                'archived' => (bool) ($project['archived'] ?? false),
                'last_commit_sha' => $project['last_commit']['id'] ?? null,
                'last_activity_at' => $project['last_activity_at'] ?? null,
                'metadata' => [
                    'namespace' => $project['namespace']['full_path'] ?? null,
                    'visibility' => $project['visibility'] ?? null,
                ],
            ];
        }

        return $rows;
    }

    public function fetchPipelines(Repository $repository): iterable
    {
        $client = $this->clientFor($this->workspaceFor($repository));

        $pipelines = $this->paginate(fn (int $page): array => $client->listPipelines($repository->external_id, [
            'per_page' => $this->perPage,
            'page' => $page,
            'order_by' => 'id',
            'sort' => 'desc',
        ]));

        $rows = [];

        foreach ($pipelines as $pipeline) {
            if (empty($pipeline['id'])) {
                continue;
            }

            $rows[] = [
                'external_id' => (string) $pipeline['id'],
                'ref' => $pipeline['ref'] ?? null,
                'commit_sha' => $pipeline['sha'] ?? null,
                'status' => (string) ($pipeline['status'] ?? 'unknown'),
                'source' => $pipeline['source'] ?? null,
                'duration' => isset($pipeline['duration']) ? (int) $pipeline['duration'] : null,
                'started_at' => $pipeline['created_at'] ?? null,
                'finished_at' => $pipeline['updated_at'] ?? null,
                'web_url' => $pipeline['web_url'] ?? null,
                'metadata' => [
                    'user' => $pipeline['user']['username'] ?? null,
                ],
            ];
        }

        return $rows;
    }

    public function fetchReleases(Repository $repository): iterable
    {
        $client = $this->clientFor($this->workspaceFor($repository));

        $releases = $this->paginate(fn (int $page): array => $client->listReleases($repository->external_id, [
            'per_page' => $this->perPage,
            'page' => $page,
        ]));

        $rows = [];

        foreach ($releases as $release) {
            $tag = (string) ($release['tag_name'] ?? $release['name'] ?? '');

            if ($tag === '') {
                continue;
            }

            $rows[] = [
                'external_id' => $tag,
                'version' => $this->normalizeVersion($tag),
                'commit_sha' => $release['commit']['id'] ?? null,
                'name' => $release['name'] ?? null,
                'notes' => $release['description'] ?? null,
                'artifact_url' => $release['assets']['links'][0]['url'] ?? null,
                'released_at' => $release['released_at'] ?? null,
                'web_url' => $release['_links']['self'] ?? null,
                'metadata' => [
                    'upcoming_release' => (bool) ($release['upcoming_release'] ?? false),
                ],
            ];
        }

        return $rows;
    }

    public function fetchDeployments(Repository $repository): iterable
    {
        $client = $this->clientFor($this->workspaceFor($repository));

        $deployments = $this->paginate(fn (int $page): array => $client->listDeployments($repository->external_id, [
            'per_page' => $this->perPage,
            'page' => $page,
            'order_by' => 'id',
            'sort' => 'desc',
        ]));

        $rows = [];

        foreach ($deployments as $deployment) {
            if (empty($deployment['id'])) {
                continue;
            }

            $rows[] = [
                'external_id' => (string) $deployment['id'],
                'environment' => $deployment['environment']['name'] ?? null,
                'status' => (string) ($deployment['status'] ?? 'unknown'),
                'deployed_at' => $deployment['updated_at'] ?? $deployment['created_at'] ?? null,
                'operator' => $deployment['user']['username'] ?? null,
                'web_url' => $deployment['web_url'] ?? null,
                'metadata' => [
                    'ref' => $deployment['ref'] ?? null,
                    'sha' => $deployment['sha'] ?? null,
                ],
            ];
        }

        return $rows;
    }

    private function normalizeVersion(string $tag): string
    {
        return preg_replace('/^v(?=\d)/i', '', $tag) ?? $tag;
    }

    private function clientFor(Workspace $workspace): GitlabCicdClientContract
    {
        $connectionId = $workspace->connection_id;

        if (! $connectionId) {
            throw new ServiceUnavailableException('workspace_connection_missing');
        }

        $connection = IntegrationConnection::query()
            ->where('tenant_id', $workspace->tenant_id)
            ->find($connectionId);

        if (! $connection) {
            throw new ServiceUnavailableException('workspace_connection_not_found');
        }

        if ((string) $connection->provider !== 'gitlab') {
            throw new DomainException('workspace_connection_not_gitlab');
        }

        return $this->factory->forConnection($connection);
    }

    private function workspaceFor(Repository $repository): Workspace
    {
        $workspace = Workspace::query()
            ->where('tenant_id', $repository->tenant_id)
            ->find($repository->workspace_id);

        if (! $workspace) {
            throw new ServiceUnavailableException('repository_workspace_not_found');
        }

        return $workspace;
    }

    /**
     * @param  callable(int): array<int, array<string, mixed>>  $fetch
     * @return array<int, array<string, mixed>>
     */
    private function paginate(callable $fetch): array
    {
        $rows = [];
        $page = 1;

        while ($page <= max(1, $this->maxPages)) {
            $batch = $fetch($page);

            if ($batch === []) {
                break;
            }

            foreach ($batch as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }

            if (count($batch) < max(1, $this->perPage)) {
                break;
            }

            $page++;
        }

        return $rows;
    }
}
