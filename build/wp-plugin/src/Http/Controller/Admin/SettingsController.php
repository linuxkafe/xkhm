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
        $groups = $this->settings->grouped();

        // A seccao pedida; por omissao, a primeira com definicoes.
        $section = (string)($request->getQueryParams()['section'] ?? '');

        if ($section === '' || !isset($groups[$section])) {
            foreach ($groups as $key => $group) {
                if ($group['settings'] !== []) {
                    $section = $key;
                    break;
                }
            }
        }

        $body = AdminLayout::header('Configuração')
            . $this->missingReport()
            . $this->sectionNavigation($groups, $section)
            . $this->group($groups[$section] ?? null, $section);

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
     * The navigation of the sections — the WHMCS's configuration pages.
     *
     * @param array<string, array{label: string, settings: list<array<string, mixed>>}> $groups
     */
    private function sectionNavigation(array $groups, string $current): string
    {
        $items = '';

        foreach ($groups as $key => $group) {
            if ($group['settings'] === []) {
                continue;
            }

            $active = $key === $current ? ' class="active"' : '';

            $items .= sprintf(
                '<li><a href="/admin/config.php?section=%s"%s>%s <span class="admin-section-count">%d</span></a></li>',
                $this->e((string)$key),
                $active,
                $this->e($group['label']),
                count($group['settings'])
            );
        }

        return '<nav class="admin-sections" aria-label="Secções da configuração">'
            . '<ul>' . $items . '</ul></nav>';
    }

    /**
     * The settings of one section, with a form.
     *
     * @param array{label: string, settings: list<array<string, mixed>>}|null $group
     */
    private function group(?array $group, string $section): string
    {
        if ($group === null || $group['settings'] === []) {
            return '<p class="text-muted">Esta secção não tem definições.</p>';
        }

        $rows = '';
        foreach ($group['settings'] as $setting) {
            $rows .= $this->settingRow($setting);
        }

        return '<section class="panel"><div class="panel-heading">'
            . $this->e($group['label']) . '</div><div class="panel-body">'
            . '<form method="post" action="/admin/config.php">'
            . '<input type="hidden" name="section" value="' . $this->e($section) . '">'
            . '<div class="table-scroll"><table class="table table-striped"><tbody>' . $rows . '</tbody></table></div>'
            . '<div class="admin-actions">'
            . '<button type="submit" class="btn btn-primary">Guardar</button>'
            . '</div>'
            . '</form>'
            . '</div></section>';
    }

    /**
     * One setting: editable, or read-only.
     *
     * @param array{key: string, label?: string, value: string, editable: bool} $setting
     */
    private function settingRow(array $setting): string
    {
        $key = $this->e($setting['key']);
        $value = $this->e($setting['value']);

        $name = $this->e($setting['label'] ?? $setting['key']);

        if (!$setting['editable']) {
            return sprintf(
                '<tr><td><span class="admin-setting-name">%s</span>'
                . '<code class="admin-setting-key">%s</code></td><td>%s</td></tr>',
                $name,
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
            '<tr><td><span class="admin-setting-name">%s</span>'
            . '<code class="admin-setting-key">%s</code></td><td>%s</td></tr>',
            $name,
            $key,
            $input
        );
    }
}
