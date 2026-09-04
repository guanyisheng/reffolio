<?php
/**
 * 文件存储默认配置（仅作结构模板）
 *
 * 实际值请在后台「存储设置」页面由用户自行填写并保存。
 * 不要在此文件写入真实密钥或桶信息。
 */
declare(strict_types=1);

return [
    // 存储驱动：local | cos（可在后台修改）
    'driver' => 'local',

    'local' => [
        'base_dir'   => 'uploads',
        'url_prefix' => '/uploads',
    ],

    // 腾讯云 COS — 全部留空，由用户在后台填写
    'cos' => [
        'secret_id'          => '',
        'secret_key'         => '',
        'bucket'             => '',
        'region'             => '',
        'acl'                => 'private',
        'domain'             => '',
        'scheme'             => 'https',
        'prefix'             => '',
        'signed_url_expires' => 7200,
        'cdn_domain'         => '',
    ],
];
