GitLab CI/CD 适配（cicd_gitlab）
================================

把 GitLab 分组/仓库/流水线/release/deployment 映射成 `Cicd` 的 provider 数据。
无表、无路由、无权限点；由 `Cicd` 核心负责投影。

## 依赖

- `cicd`：Provider 契约与投影核心
- `gitlab`：GitLab client / 连接 / SSRF / 重试
- `integration`：`integration_connections`

## 工作方式

`CicdGitlabServiceProvider` 在启动时向 `CicdProviderRegistry` 注册 `GitlabCicdProvider`：

```php
$registry->register($this->app->make(GitlabCicdProvider::class));
```

`Cicd` 的 `cicd:sync` 按 `Workspace.provider_key = gitlab` 找到它，使用
`Workspace.connection_id` 对应的 `integration_connections` 构造 GitLab client。

## 映射

| Cicd | GitLab |
|---|---|
| Workspace.external_group_id | group id / path |
| Repository | `GET /groups/:id/projects` |
| Pipeline | `GET /projects/:id/pipelines` |
| Release | `GET /projects/:id/releases` |
| Deployment | `GET /projects/:id/deployments` |

分页：`per_page`（默认 100）+ `max_pages`（默认 20），由 `config/cicd_gitlab.php` 控制。

## 配置

```php
'per_page' => 100,
'max_pages' => 20,
```

## 边界

- 不执行构建、不触发 pipeline、不打 tag；只读投影。
- 连接缺失/provider 非 gitlab 时抛 `RuntimeException`，由 `CicdSyncService` 记为同步错误。
