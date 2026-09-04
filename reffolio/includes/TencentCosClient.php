<?php
/**
 * 腾讯云 COS 轻量客户端（无需 Composer SDK）
 * 支持：上传 PutObject、删除 DeleteObject、临时签名 URL、下载到本地
 */
declare(strict_types=1);

final class TencentCosClient
{
    private string $secretId;
    private string $secretKey;
    private string $bucket;
    private string $region;
    private string $domain;
    private string $scheme;
    private string $acl;
    private string $prefix;
    private int $signExpires;
    private string $cdnDomain;

    public function __construct(array $config)
    {
        $this->secretId   = trim((string) ($config['secret_id'] ?? ''));
        $this->secretKey  = trim((string) ($config['secret_key'] ?? ''));
        $this->bucket     = trim((string) ($config['bucket'] ?? ''));
        $this->region     = trim((string) ($config['region'] ?? ''));
        $this->domain     = trim((string) ($config['domain'] ?? ''));
        $this->scheme     = rtrim((string) ($config['scheme'] ?? 'https'), ':');
        $this->acl        = (string) ($config['acl'] ?? 'private');
        $this->prefix     = ltrim((string) ($config['prefix'] ?? ''), '/');
        if ($this->prefix !== '' && !str_ends_with($this->prefix, '/')) {
            $this->prefix .= '/';
        }
        $this->signExpires = max(60, (int) ($config['signed_url_expires'] ?? 7200));
        $this->cdnDomain   = trim((string) ($config['cdn_domain'] ?? ''));

        if ($this->secretId === '' || $this->secretKey === '') {
            throw new RuntimeException('请填写腾讯云 SecretId 与 SecretKey。');
        }
        if ($this->bucket === '' || $this->region === '' || $this->domain === '') {
            throw new RuntimeException('请填写完整的存储桶名称、地域与存储桶域名。');
        }
    }

    public function objectKey(string $relativePath): string
    {
        return $this->prefix . ltrim($relativePath, '/');
    }

    /**
     * 上传本地文件到 COS
     */
    public function putObject(string $localFile, string $objectKey, string $contentType = 'application/octet-stream'): void
    {
        $objectKey = ltrim($objectKey, '/');
        $body = file_get_contents($localFile);
        if ($body === false) {
            throw new RuntimeException('读取待上传文件失败。');
        }

        $headers = [
            'Host'           => $this->domain,
            'Content-Type'   => $contentType,
            'Content-Length' => (string) strlen($body),
            'Date'           => gmdate('D, d M Y H:i:s T'),
        ];

        if ($this->acl === 'private' || $this->acl === 'public-read') {
            $headers['x-cos-acl'] = $this->acl;
        }

        $authorization = $this->sign('put', '/' . $objectKey, [], $headers);
        $headers['Authorization'] = $authorization;

        $url = $this->scheme . '://' . $this->domain . '/' . $this->encodeKey($objectKey);
        $response = $this->request('PUT', $url, $headers, $body);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('COS 上传失败 HTTP ' . $response['status'] . '：' . mb_substr($response['body'], 0, 300));
        }
    }

    public function deleteObject(string $objectKey): void
    {
        $objectKey = ltrim($objectKey, '/');
        $headers = [
            'Host' => $this->domain,
            'Date' => gmdate('D, d M Y H:i:s T'),
        ];
        $authorization = $this->sign('delete', '/' . $objectKey, [], $headers);
        $headers['Authorization'] = $authorization;
        $url = $this->scheme . '://' . $this->domain . '/' . $this->encodeKey($objectKey);
        $this->request('DELETE', $url, $headers, null);
    }

    /**
     * 生成临时访问 URL（私有桶必需）
     */
    public function getSignedUrl(string $objectKey, ?int $expires = null): string
    {
        $objectKey = ltrim($objectKey, '/');
        $expires = $expires ?? $this->signExpires;
        $start = time() - 60;
        $end = $start + $expires;
        $keyTime = $start . ';' . $end;

        $host = $this->cdnDomain !== '' ? $this->cdnDomain : $this->domain;
        $httpMethod = 'get';
        $uriPathname = '/' . $objectKey;

        $headerMap = ['host' => strtolower($host)];
        ksort($headerMap);
        $headerList = implode(';', array_keys($headerMap));
        $httpHeaders = '';
        foreach ($headerMap as $k => $v) {
            $httpHeaders .= $k . '=' . rawurlencode($v) . '&';
        }
        $httpHeaders = rtrim($httpHeaders, '&');

        $urlParamList = '';
        $httpParameters = '';

        $formatString = $httpMethod . "\n" . $uriPathname . "\n" . $httpParameters . "\n" . $httpHeaders . "\n";
        $stringToSign = "sha1\n{$keyTime}\n" . sha1($formatString) . "\n";
        $signKey = hash_hmac('sha1', $keyTime, $this->secretKey, false);
        $signature = hash_hmac('sha1', $stringToSign, $signKey, false);

        $query = http_build_query([
            'q-sign-algorithm' => 'sha1',
            'q-ak'             => $this->secretId,
            'q-sign-time'      => $keyTime,
            'q-key-time'       => $keyTime,
            'q-header-list'    => $headerList,
            'q-url-param-list' => $urlParamList,
            'q-signature'      => $signature,
        ], '', '&', PHP_QUERY_RFC3986);

        return $this->scheme . '://' . $host . '/' . $this->encodeKey($objectKey) . '?' . $query;
    }

    /**
     * 生成浏览器直传用的 PUT 预签名 URL（不经过 PHP 服务器传文件）
     */
    public function getPresignedPutUrl(string $objectKey, string $contentType, ?int $expires = null): string
    {
        $objectKey = ltrim($objectKey, '/');
        $contentType = strtolower(trim($contentType));
        if ($contentType === '') {
            $contentType = 'application/octet-stream';
        }
        $expires = $expires ?? min($this->signExpires, 3600);
        $start = time() - 60;
        $end = $start + $expires;
        $keyTime = $start . ';' . $end;

        $host = $this->domain;
        $httpMethod = 'put';
        $uriPathname = '/' . $objectKey;

        $headerMap = [
            'host'         => strtolower($host),
            'content-type' => $contentType,
        ];
        ksort($headerMap);
        $headerList = implode(';', array_keys($headerMap));
        $httpHeaders = '';
        foreach ($headerMap as $k => $v) {
            $httpHeaders .= $k . '=' . rawurlencode($v) . '&';
        }
        $httpHeaders = rtrim($httpHeaders, '&');

        $urlParamList = '';
        $httpParameters = '';

        $formatString = $httpMethod . "\n" . $uriPathname . "\n" . $httpParameters . "\n" . $httpHeaders . "\n";
        $stringToSign = "sha1\n{$keyTime}\n" . sha1($formatString) . "\n";
        $signKey = hash_hmac('sha1', $keyTime, $this->secretKey, false);
        $signature = hash_hmac('sha1', $stringToSign, $signKey, false);

        $query = http_build_query([
            'q-sign-algorithm' => 'sha1',
            'q-ak'             => $this->secretId,
            'q-sign-time'      => $keyTime,
            'q-key-time'       => $keyTime,
            'q-header-list'    => $headerList,
            'q-url-param-list' => $urlParamList,
            'q-signature'      => $signature,
        ], '', '&', PHP_QUERY_RFC3986);

        return $this->scheme . '://' . $host . '/' . $this->encodeKey($objectKey) . '?' . $query;
    }

    /**
     * 下载对象到临时文件，返回本地路径
     */
    public function downloadToTemp(string $objectKey): string
    {
        $url = $this->getSignedUrl($objectKey);
        $tmp = tempnam(sys_get_temp_dir(), 'cosdl_');
        if ($tmp === false) {
            throw new RuntimeException('无法创建临时文件。');
        }

        $fp = fopen($tmp, 'wb');
        if ($fp === false) {
            throw new RuntimeException('无法写入临时文件。');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            fclose($fp);
            @unlink($tmp);
            throw new RuntimeException('curl 初始化失败。');
        }
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($ok === false || $status < 200 || $status >= 300) {
            @unlink($tmp);
            throw new RuntimeException('COS 下载失败：' . ($err ?: ('HTTP ' . $status)));
        }

        return $tmp;
    }

    private function sign(string $method, string $pathname, array $params, array $headers): string
    {
        $start = time() - 60;
        $end = $start + 3600;
        $keyTime = $start . ';' . $end;

        $headerMap = [];
        foreach ($headers as $k => $v) {
            $lk = strtolower($k);
            // 签名常用头
            // Content-Length 不参与签名，避免与实际传输不一致
            if (in_array($lk, ['host', 'content-type', 'content-md5'], true) || str_starts_with($lk, 'x-cos-')) {
                $headerMap[$lk] = (string) $v;
            }
        }
        ksort($headerMap);
        $headerList = implode(';', array_keys($headerMap));
        $httpHeaders = '';
        foreach ($headerMap as $k => $v) {
            $httpHeaders .= $k . '=' . rawurlencode($v) . '&';
        }
        $httpHeaders = rtrim($httpHeaders, '&');

        $paramMap = [];
        foreach ($params as $k => $v) {
            $paramMap[strtolower((string) $k)] = (string) $v;
        }
        ksort($paramMap);
        $urlParamList = implode(';', array_keys($paramMap));
        $httpParameters = '';
        foreach ($paramMap as $k => $v) {
            $httpParameters .= $k . '=' . rawurlencode($v) . '&';
        }
        $httpParameters = rtrim($httpParameters, '&');

        $formatString = strtolower($method) . "\n" . $pathname . "\n" . $httpParameters . "\n" . $httpHeaders . "\n";
        $stringToSign = "sha1\n{$keyTime}\n" . sha1($formatString) . "\n";
        $signKey = hash_hmac('sha1', $keyTime, $this->secretKey, false);
        $signature = hash_hmac('sha1', $stringToSign, $signKey, false);

        return 'q-sign-algorithm=sha1'
            . '&q-ak=' . $this->secretId
            . '&q-sign-time=' . $keyTime
            . '&q-key-time=' . $keyTime
            . '&q-header-list=' . $headerList
            . '&q-url-param-list=' . $urlParamList
            . '&q-signature=' . $signature;
    }

    private function encodeKey(string $key): string
    {
        $parts = explode('/', $key);
        $parts = array_map('rawurlencode', $parts);
        return implode('/', $parts);
    }

    private function request(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl 初始化失败，请确认已启用 curl 扩展。');
        }

        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $respBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($respBody === false) {
            throw new RuntimeException('COS 请求失败：' . $err);
        }

        return ['status' => $status, 'body' => $respBody];
    }
}
