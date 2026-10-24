<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\Layout;
use Xkhm\Service\KnowledgebaseService;

class KnowledgebaseController
{
    private KnowledgebaseService $service;

    public function __construct(KnowledgebaseService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /knowledgebase.php
     */
    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $action = $queryParams['action'] ?? 'search';
        $search = $queryParams['search'] ?? '';

        $html = '<h2>Central de Ajuda</h2>';
        $html .= '<form method="get" class="form-inline">';
        $html .= '<input type="hidden" name="action" value="search">';
        $html .= '<input type="text" name="search" class="form-control" placeholder="Buscar artigos..." value="' . htmlspecialchars($search) . '">';
        $html .= '<button type="submit" class="btn btn-primary">Buscar</button>';
        $html .= '</form>';
        $html .= '<hr>';

        if ($action === 'search' && strlen($search) >= 2) {
            $html .= $this->renderSearchResults($search);
        } elseif ($action === 'article') {
            $articleId = (int)($queryParams['id'] ?? 0);
            $html .= $this->renderArticle($articleId);
        } else {
            $html .= $this->renderCategories();
        }

        $response->getBody()->write(Layout::render('Central de Ajuda', $html));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function renderCategories(): string
    {
        $categories = $this->service->getCategories();
        $html = '<h3>Categorias</h3>';
        $html .= '<div class="row">';

        foreach ($categories as $cat) {
            $count = count($this->service->getArticlesByCategory($cat['id'], 9999));
            $html .= '<div class="col-md-4">';
            $html .= '<div class="panel panel-default">';
            $html .= '<div class="panel-heading"><a href="/knowledgebase.php?action=category&id=' . (int)$cat['id'] . '">' .
                htmlspecialchars($cat['title']) . '</a></div>';
            $html .= '<div class="panel-body">';
            $html .= '<p>' . nl2br(htmlspecialchars($cat['description'])) . '</p>';
            $html .= '<p class="text-muted">' . $count . ' artigos</p>';
            $html .= '</div></div></div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function renderSearchResults(string $query): string
    {
        $articles = $this->service->searchArticles($query, 20);

        if (empty($articles)) {
            return '<div class="alert alert-info">Nenhum artigo encontrado para "' . htmlspecialchars($query) . '"</div>';
        }

        $html = '<h3>Resultados para "' . htmlspecialchars($query) . '"</h3>';
        $html .= '<table class="table table-striped">';
        $html .= '<thead><tr><th>Título</th><th>Categoria</th><th>Última atualização</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($articles as $article) {
            $html .= sprintf(
                '<tr><td><a href="/knowledgebase.php?action=article&id=%d">%s</a></td><td>%s</td><td>%s</td></tr>',
                $article['id'],
                htmlspecialchars($article['title']),
                htmlspecialchars($article['categorytitle'] ?? 'Geral'),
                $article['lastupdated'] ?? ''
            );
        }

        $html .= '</tbody></table>';
        return $html;
    }

    private function renderArticle(int $articleId): string
    {
        $article = $this->service->getArticle($articleId);

        if (!$article) {
            return '<div class="alert alert-danger">Artigo não encontrado</div>';
        }

        $html = '<div class="article-container">';
        $html .= '<h2>' . htmlspecialchars($article['title']) . '</h2>';
        $html .= '<p class="text-muted">Categoria: ' . htmlspecialchars($article['categorytitle'] ?? 'Geral') .
            ' | Atualizado: ' . ($article['lastupdated'] ?? '') . '</p>';
        $html .= '<hr>';
        $html .= '<div class="article-content">' . nl2br(htmlspecialchars($article['content'])) . '</div>';
        $html .= '<hr>';
        $html .= '<p><a href="/knowledgebase.php" class="btn btn-default">Voltar para Central de Ajuda</a></p>';
        $html .= '</div>';

        return $html;
    }
}
