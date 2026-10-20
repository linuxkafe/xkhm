<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Service\AnnouncementService;

class AnnouncementController
{
    private AnnouncementService $service;

    public function __construct(AnnouncementService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /announcements.php
     */
    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $announcementId = (int)($queryParams['id'] ?? 0);

        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Notícias - LINUXKAFE</title></head><body>';
        $html .= '<div class="container">';
        $html .= '<h2>Notícias</h2>';

        if ($announcementId > 0) {
            $announcement = $this->service->getAnnouncement($announcementId);
            if ($announcement) {
                $html .= '<div class="announcement">';
                $html .= '<h3>' . htmlspecialchars($announcement['title']) . '</h3>';
                $html .= '<p class="text-muted">Data: ' . $announcement['date'] . '</p>';
                $html .= '<hr>';
                $html .= '<div class="content">' . nl2br(htmlspecialchars($announcement['message'])) . '</div>';
                $html .= '<p><a href="/announcements.php" class="btn btn-default">Voltar</a></p>';
                $html .= '</div>';
            } else {
                $html .= '<div class="alert alert-danger">Anúncio não encontrado</div>';
            }
        } else {
            $announcements = $this->service->getRecentAnnouncements(10);
            if (empty($announcements)) {
                $html .= '<div class="alert alert-info">Nenhuma notícia no momento.</div>';
            } else {
                $html .= '<table class="table table-striped">';
                $html .= '<thead><tr><th>Data</th><th>Título</th></tr></thead><tbody>';
                foreach ($announcements as $ann) {
                    $html .= sprintf(
                        '<tr><td>%s</td><td><a href="/announcements.php?id=%d">%s</a></td></tr>',
                        $ann['date'],
                        $ann['id'],
                        htmlspecialchars($ann['title'])
                    );
                }
                $html .= '</tbody></table>';
            }
        }

        $html .= '</div></body></html>';
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
