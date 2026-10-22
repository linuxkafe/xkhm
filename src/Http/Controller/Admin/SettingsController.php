<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\AdminLayout;
use Xkhm\Service\SettingsService;

/**
 * The configuration of the installation.
 *
 * The settings live in `tblconfiguration` — the table the WHMCS keeps
 * them in, and the one that came with the imported database. They are
 * shown **grouped**, because a list of 235 lines is not a page; and the
 * ones that matter are **editable**, because a configuration nobody can
 * change is a configuration nobody can fix.
 *
 * The page also says **what is missing** for the payment notice to be a
 * proper document. The application does not invent those values — the
 * operator fills them in.
 */
class SettingsController extends AdminController
{
    public function __construct(
        \Xkhm\Repository\AdminPanelRepositoryInterface $repository,
        private SettingsService $settings
    ) {
        parent::__construct($repository);
    }

    /**
     * GET /admin/config.php
     */
    public function index(Request $request, Response $response): Response
    {
        $body = AdminLayout::header('Configuração')
            . $this->missingReport()
            . $this->groups();

        return $this->render($response, 'Configuração', 'config', $body, $this->adminName());
    }

    /**
     * POST /admin/config.php
     *
     * The edit of a group of settings.
     */
    public function save(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        $values = [];

        foreach ($data as $key => $value) {
            $key = (string)$key;

            // Only the settings on the explicit list can be changed.
            if (!in_array($key, SettingsService::EDITABLE, true)) {
                continue;
            }

            if (is_array($value)) {
                continue;
            }

            $values[$key] = trim((string)$value);
        }

        if ($values === []) {
            return $response
                ->withHeader('Location', '/admin/config.php?error=1')
                ->withStatus(302);
        }

        $this->settings->setMany($values);

        return $response
            ->withHeader('Location', '/admin/config.php?saved=1')
            ->withStatus(302);
    }

    /**
     * What is missing for the payment notice to be complete.
     */
    private function missingReport(): string
    {
        $missing = $this->settings->missing();

        if ($missing === []) {
            return '<div class="alert alert-success">'
                . 'A empresa tem os dados necessários para um documento de '
                . 'pagamento completo.</div>';
        }

        $rows = '';
        foreach ($missing as $item) {
            $rows .= sprintf(
                '<li><code>%s</code> — %s</li>',
                $this->e($item['key']),
                $this->e($item['why'])
            );
        }

        return '<section class="panel"><div class="panel-heading">'
            . 'O que falta para um documento de pagamento completo</div>'
            . '<div class="panel-body">'
            . '<p class="text-muted">'
            . count($missing) . ' definições em falta. Sem elas o documento '
            . 'sai incompleto — e a aplicação não as inventa.</p>'
            . '<ul class="admin-missing">' . $rows . '</ul>'
            . '</div></section>';
    }

    /**
     * The settings, grouped, with a form per group.
     */
    private function groups(): string
    {
        $groups = $this->settings->grouped();
        $html = '';

        foreach ($groups as $key => $group) {
            $settings = $group['settings'];

            if ($settings === []) {
                continue;
            }

            $rows = '';
            foreach ($settings as $setting) {
                $rows .= $this->settingRow($setting);
            }

            $html .= '<section class="panel"><div class="panel-heading">'
                . $this->e($group['label']) . '</div><div class="panel-body">'
                . '<form method="post" action="/admin/config.php">'
                . '<input type="hidden" name="group" value="' . $this->e($key) . '">'
                . '<table class="table table-striped"><tbody>' . $rows . '</tbody></table>'
                . '<div class="admin-actions">'
                . '<button type="submit" class="btn btn-primary">Guardar</button>'
                . '</div>'
                . '</form>'
                . '</div></section>';
        }

        return $html;
    }

    /**
     * One setting: editable, or read-only.
     *
     * @param array{key: string, value: string, editable: bool} $setting
     */
    private function settingRow(array $setting): string
    {
        $key = $this->e($setting['key']);
        $value = $this->e($setting['value']);

        if (!$setting['editable']) {
            return sprintf(
                '<tr><td><code>%s</code></td><td>%s</td></tr>',
                $key,
                $value === '' ? '<span class="text-muted">—</span>' : $value
            );
        }

        $input = sprintf(
            '<input type="text" name="%s" value="%s" class="form-control"'
            . ' aria-label="%s">',
            $key,
            $value,
            $key
        );

        return sprintf(
            '<tr><td><code>%s</code></td><td>%s</td></tr>',
            $key,
            $input
        );
    }
}
