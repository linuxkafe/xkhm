<?php

declare(strict_types=1);

namespace Xkhm\Module\Server;

/**
 * cPanel/WHM server module.
 *
 * Uses the WHM API 1 (JSON) to create, suspend, terminate, and modify
 * hosting accounts on a cPanel/WHM server.
 *
 * Server config (from tblservers):
 *   hostname  – WHM hostname (e.g. server.example.com)
 *   username  – WHM username (usually root)
 *   password  – WHM password or API token
 *   accesshash – API token (alternative to password)
 *   port      – WHM port (default 2087)
 *   secure    – use HTTPS (default true)
 */
class Cpanel implements ServerModuleInterface
{
    private string $hostname;
    private string $username;
    private string $password;
    private string $accessHash;
    private int $port;
    private bool $secure;

    public function __construct(array $config)
    {
        $this->hostname   = $config['hostname'] ?? '';
        $this->username   = $config['username'] ?? 'root';
        $this->password   = $config['password'] ?? '';
        $this->accessHash = $config['accesshash'] ?? '';
        $this->port       = (int)($config['port'] ?? 2087);
        $this->secure     = (bool)($config['secure'] ?? true);
    }

    private function baseUrl(): string
    {
        $scheme = $this->secure ? 'https' : 'http';
        return "{$scheme}://{$this->hostname}:{$this->port}";
    }

    private function request(string $endpoint, array $params = []): array
    {
        $url = $this->baseUrl() . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($this->accessHash !== '') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: whm ' . $this->username . ' ' . $this->accessHash,
            ]);
        } else {
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            return ['success' => false, 'message' => "cURL error: {$error}"];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['success' => false, 'message' => "Invalid JSON response (HTTP {$httpCode})"];
        }

        return $data;
    }

    public function createAccount(array $params): array
    {
        $username = $params['username'] ?? '';
        $password = $params['password'] ?? '';
        $domain   = $params['domain'] ?? '';
        $plan     = $params['plan'] ?? 'default';
        $email    = $params['email'] ?? '';

        if ($username === '' || $domain === '') {
            return ['success' => false, 'message' => 'Username and domain are required'];
        }

        $result = $this->request('/json-api/createacct', [
            'username' => $username,
            'password' => $password,
            'domain'   => $domain,
            'plan'     => $plan,
            'contactemail' => $email,
        ]);

        $metadata = $result['metadata'] ?? [];
        $reason   = $metadata['reason'] ?? ($result['reason'] ?? 'Unknown error');

        if (($metadata['result'] ?? 0) == 1) {
            return ['success' => true, 'message' => $reason, 'username' => $username];
        }

        return ['success' => false, 'message' => $reason];
    }

    public function suspendAccount(string $username): array
    {
        $result = $this->request('/json-api/suspendacct', [
            'user'   => $username,
            'reason' => 'Suspended by XKHM',
        ]);

        $metadata = $result['metadata'] ?? [];
        $reason   = $metadata['reason'] ?? ($result['reason'] ?? 'Unknown error');

        if (($metadata['result'] ?? 0) == 1) {
            return ['success' => true, 'message' => $reason];
        }

        return ['success' => false, 'message' => $reason];
    }

    public function unsuspendAccount(string $username): array
    {
        $result = $this->request('/json-api/unsuspendacct', [
            'user' => $username,
        ]);

        $metadata = $result['metadata'] ?? [];
        $reason   = $metadata['reason'] ?? ($result['reason'] ?? 'Unknown error');

        if (($metadata['result'] ?? 0) == 1) {
            return ['success' => true, 'message' => $reason];
        }

        return ['success' => false, 'message' => $reason];
    }

    public function terminateAccount(string $username): array
    {
        $result = $this->request('/json-api/removeacct', [
            'user'   => $username,
            'keepdns' => 0,
        ]);

        $metadata = $result['metadata'] ?? [];
        $reason   = $metadata['reason'] ?? ($result['reason'] ?? 'Unknown error');

        if (($metadata['result'] ?? 0) == 1) {
            return ['success' => true, 'message' => $reason];
        }

        return ['success' => false, 'message' => $reason];
    }

    public function changePackage(string $username, string $package): array
    {
        $result = $this->request('/json-api/changepackage', [
            'user'    => $username,
            'pkg'     => $package,
        ]);

        $metadata = $result['metadata'] ?? [];
        $reason   = $metadata['reason'] ?? ($result['reason'] ?? 'Unknown error');

        if (($metadata['result'] ?? 0) == 1) {
            return ['success' => true, 'message' => $reason];
        }

        return ['success' => false, 'message' => $reason];
    }

    public function testConnection(): array
    {
        $result = $this->request('/json-api/applist', []);

        if (isset($result['metadata']['result']) && $result['metadata']['result'] == 1) {
            return ['success' => true, 'message' => 'Connection successful'];
        }

        // Fallback: try version endpoint
        $result = $this->request('/json-api/version', []);
        if (isset($result['version'])) {
            return ['success' => true, 'message' => 'WHM version: ' . $result['version']];
        }

        return ['success' => false, 'message' => $result['metadata']['reason'] ?? 'Connection failed'];
    }
}