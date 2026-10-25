<?php

declare(strict_types=1);

namespace Xkhm\Module\Server;

/**
 * Virtualmin server module.
 *
 * Uses the Virtualmin Remote API (remote.cgi) to create, suspend,
 * terminate, and modify virtual servers (domains).
 *
 * Server config (from tblservers):
 *   hostname  – Virtualmin server hostname
 *   username  – Virtualmin username (usually root)
 *   password  – Virtualmin password
 *   port      – Virtualmin port (default 10000)
 *   secure    – use HTTPS (default true)
 */
class Virtualmin implements ServerModuleInterface
{
    private string $hostname;
    private string $username;
    private string $password;
    private int $port;
    private bool $secure;

    public function __construct(array $config)
    {
        $this->hostname = $config['hostname'] ?? '';
        $this->username = $config['username'] ?? 'root';
        $this->password = $config['password'] ?? '';
        $this->port     = (int)($config['port'] ?? 10000);
        $this->secure   = (bool)($config['secure'] ?? true);
    }

    private function baseUrl(): string
    {
        $scheme = $this->secure ? 'https' : 'http';
        return "{$scheme}://{$this->hostname}:{$this->port}";
    }

    private function request(string $program, array $params = []): array
    {
        $params['program'] = $program;
        $params['json'] = 1;

        $url = $this->baseUrl() . '/virtual-server/remote.cgi?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            return ['success' => false, 'message' => "cURL error: {$error}"];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            // Virtualmin sometimes returns plain text
            if (stripos($response, 'success') !== false) {
                return ['success' => true, 'message' => trim($response)];
            }
            return ['success' => false, 'message' => "Invalid response (HTTP {$httpCode}): " . substr($response, 0, 200)];
        }

        $status = $data['status'] ?? '';
        $message = $data['message'] ?? ($data['error'] ?? 'Unknown');

        if ($status === 'success') {
            return ['success' => true, 'message' => $message, 'data' => $data];
        }

        return ['success' => false, 'message' => $message];
    }

    public function createAccount(array $params): array
    {
        $domain    = $params['domain'] ?? '';
        $username  = $params['username'] ?? '';
        $password  = $params['password'] ?? '';
        $plan      = $params['plan'] ?? '';
        $email     = $params['email'] ?? '';
        $features  = $params['features'] ?? '';

        if ($domain === '') {
            return ['success' => false, 'message' => 'Domain is required'];
        }

        $args = [
            'domain'    => $domain,
            'pass'      => $password,
            'email'     => $email ?: 'admin@' . $domain,
        ];

        if ($username !== '') {
            $args['user'] = $username;
        }
        if ($plan !== '') {
            $args['plan'] = $plan;
        }
        if ($features !== '') {
            $args['features'] = $features;
        }

        $result = $this->request('create-domain', $args);

        if ($result['success']) {
            return ['success' => true, 'message' => $result['message'], 'username' => $username ?: $domain];
        }

        return $result;
    }

    public function suspendAccount(string $username): array
    {
        // Virtualmin disables a domain
        $result = $this->request('disable-domain', [
            'domain' => $username,
        ]);

        return $result;
    }

    public function unsuspendAccount(string $username): array
    {
        $result = $this->request('enable-domain', [
            'domain' => $username,
        ]);

        return $result;
    }

    public function terminateAccount(string $username): array
    {
        $result = $this->request('delete-domain', [
            'domain' => $username,
        ]);

        return $result;
    }

    public function changePackage(string $username, string $package): array
    {
        $result = $this->request('modify-domain', [
            'domain' => $username,
            'plan'   => $package,
        ]);

        return $result;
    }

    public function testConnection(): array
    {
        $result = $this->request('list-domains', []);

        if ($result['success']) {
            $count = isset($result['data']['domains']) ? count($result['data']['domains']) : 0;
            return ['success' => true, 'message' => "Connection successful. Domains: {$count}"];
        }

        return $result;
    }
}