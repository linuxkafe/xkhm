<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Service\CronService;

/**
 * The billing cycle, over HTTP.
 *
 * The WHMCS runs its `cron.php` from the crontab, with a plain GET
 * and **no authentication** — anybody who knows the address can
 * trigger the billing of the whole installation. That is a hole, and
 * this one is closed: the request has to carry the token.
 *
 * The token comes from the environment (`XKHM_CRON_TOKEN`). When it
 * is not set, the route **refuses** — the cycle then only runs from
 * the command line, inside the container.
 */
class CronController
{
    public function __construct(
        private CronService $cron,
        private string $token
    ) {
    }

    /**
     * GET /cron.php
     */
    public function run(Request $request, Response $response): Response
    {
        if (!$this->authorised($request)) {
            $response->getBody()->write('O ciclo exige o token (XKHM_CRON_TOKEN).');

            return $response->withStatus(403);
        }

        $date = trim((string)($request->getQueryParams()['date'] ?? ''));

        $result = $this->cron->run($date !== '' ? $date : null);

        $response->getBody()->write(json_encode([
            'ok' => true,
            'data' => $result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Whether the request carries the token.
     */
    private function authorised(Request $request): bool
    {
        if ($this->token === '') {
            return false;
        }

        $given = (string)($request->getHeaderLine('X-Cron-Token'));

        if ($given === '') {
            $given = (string)($request->getQueryParams()['token'] ?? '');
        }

        return $given !== '' && hash_equals($this->token, $given);
    }
}
