<?php
/**
 * 应用设置（键值）读写
 */
declare(strict_types=1);

function get_setting(string $key, ?string $default = null): ?string
{
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row === false) {
            return $default;
        }
        return $row['setting_value'];
    } catch (Throwable $e) {
        return $default;
    }
}

function set_setting(string $key, ?string $value): void
{
    global $pdo;
    $stmt = $pdo->prepare(
        'INSERT INTO app_settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), update_time = CURRENT_TIMESTAMP'
    );
    $stmt->execute([$key, $value]);
}

function get_settings_map(array $keys): array
{
    global $pdo;
    if (!$keys) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($placeholders)");
        $stmt->execute(array_values($keys));
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['setting_key']] = $row['setting_value'];
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * 从数据库覆盖存储配置（用户在后台填写的值优先）
 */
function merge_storage_settings(array $base): array
{
    $keys = [
        'storage.driver',
        'storage.cos.secret_id',
        'storage.cos.secret_key',
        'storage.cos.bucket',
        'storage.cos.region',
        'storage.cos.acl',
        'storage.cos.domain',
        'storage.cos.scheme',
        'storage.cos.prefix',
        'storage.cos.signed_url_expires',
        'storage.cos.cdn_domain',
    ];
    $map = get_settings_map($keys);

    if (isset($map['storage.driver']) && $map['storage.driver'] !== null && $map['storage.driver'] !== '') {
        $base['driver'] = $map['storage.driver'] === 'cos' ? 'cos' : 'local';
    }

    $cosMap = [
        'secret_id'          => 'storage.cos.secret_id',
        'secret_key'         => 'storage.cos.secret_key',
        'bucket'             => 'storage.cos.bucket',
        'region'             => 'storage.cos.region',
        'acl'                => 'storage.cos.acl',
        'domain'             => 'storage.cos.domain',
        'scheme'             => 'storage.cos.scheme',
        'prefix'             => 'storage.cos.prefix',
        'signed_url_expires' => 'storage.cos.signed_url_expires',
        'cdn_domain'         => 'storage.cos.cdn_domain',
    ];

    foreach ($cosMap as $field => $settingKey) {
        if (!array_key_exists($settingKey, $map)) {
            continue;
        }
        $val = $map[$settingKey];
        if ($field === 'signed_url_expires') {
            $base['cos'][$field] = max(60, (int) ($val !== null && $val !== '' ? $val : 7200));
        } elseif ($val !== null) {
            // 允许用户清空字段
            $base['cos'][$field] = $val;
        }
    }

    return $base;
}

function save_storage_settings(array $input): void
{
    $driver = ($input['driver'] ?? 'local') === 'cos' ? 'cos' : 'local';
    set_setting('storage.driver', $driver);

    $fields = [
        'secret_id'          => trim((string) ($input['secret_id'] ?? '')),
        'secret_key'         => trim((string) ($input['secret_key'] ?? '')),
        'bucket'             => trim((string) ($input['bucket'] ?? '')),
        'region'             => trim((string) ($input['region'] ?? '')),
        'acl'                => trim((string) ($input['acl'] ?? 'private')) ?: 'private',
        'domain'             => trim((string) ($input['domain'] ?? '')),
        'scheme'             => trim((string) ($input['scheme'] ?? 'https')) ?: 'https',
        'prefix'             => trim((string) ($input['prefix'] ?? '')),
        'signed_url_expires' => (string) max(60, (int) ($input['signed_url_expires'] ?? 7200)),
        'cdn_domain'         => trim((string) ($input['cdn_domain'] ?? '')),
    ];

    // secret_key 留空表示不修改已保存密钥
    if ($fields['secret_key'] === '') {
        unset($fields['secret_key']);
    }

    if ($driver === 'cos') {
        $required = ['secret_id', 'bucket', 'region', 'domain'];
        foreach ($required as $r) {
            if (($fields[$r] ?? '') === '') {
                // secret_id 若库里已有也可以
                if ($r === 'secret_id' && get_setting('storage.cos.secret_id')) {
                    continue;
                }
                throw new InvalidArgumentException('启用腾讯云 COS 时请填写：SecretId、存储桶名称、地域、存储桶域名。');
            }
        }
        if (!isset($fields['secret_key']) && !get_setting('storage.cos.secret_key')) {
            throw new InvalidArgumentException('请填写 SecretKey（SK）。');
        }
    }

    foreach ($fields as $field => $value) {
        set_setting('storage.cos.' . $field, $value);
    }
}
