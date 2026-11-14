<?php

declare(strict_types=1);

return [
    'enabled' => true,

    // GitLab 列表 API 单页条数
    'per_page' => 100,

    // 单次同步最多翻页数，防止异常分页拖垮任务
    'max_pages' => 20,
];
