<?php

declare(strict_types=1);

namespace App\Actions\Guestbook;

use App\Core\Cache\Cache;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\Csrf;
use App\Repositories\GuestbookRepository;

class ShowGuestbookAction
{
    public function __construct(
        private readonly GuestbookRepository $repository,
        private readonly Cache $cache,
        private readonly Csrf $csrf
    ) {
    }

    public function __invoke(Request $request): Response
    {
        try {
            $page = max(1, (int)$request->getQueryParam('page', 1));
            $perPage = 10;

            $cacheKey = $this->generateCacheKey($page);

            $content = $this->cache->remember($cacheKey, 3600, function() use ($page, $perPage) {
                return $this->renderGuestbookPage($page, $perPage);
            });

            return response()->html($content);
        } catch (\Throwable $e) {
            app_log('Gästebuch-Fehler', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 'error');

            return response()->html($this->renderErrorPage(
                'Ein technischer Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.'
            ));
        }
    }

    private function generateCacheKey(int $page): string
    {
        return "guestbook_page_{$page}_v" . config('app.version', '1');
    }

    private function renderGuestbookPage(int $page, int $perPage): string
    {
        $pagination = $this->repository->getEntriesPaginated($page, $perPage);

        return $this->createFullPageHtml($pagination);
    }

    private function createFullPageHtml($pagination): string
    {
        $csrfToken = $this->csrf->generateToken();

        // Temporärer Debug-Code
        app_log('CSRF Token Debug', [
            'token' => $csrfToken,
            'session_csrf' => $this->csrf->getStoredToken(), // Neue Methode hinzufügen
        ], 'debug');

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Gästebuch</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 20px; 
            line-height: 1.6; 
        }
        .guestbook-entries {
            margin-bottom: 30px;
        }
        .entry {
            background-color: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .entry-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .entry-name {
            font-weight: bold;
        }
        .entry-date {
            color: #666;
            font-size: 0.8em;
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .pagination a {
            margin: 0 5px;
            padding: 5px 10px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
        }
        .form-container {
            background-color: #f4f4f4;
            padding: 20px;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .submit-btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h1>Gästebuch</h1>CSRFTOKEN:
$csrfToken
    <div class="guestbook-entries">
        {$this->renderEntries($pagination->getItems())}
    </div>

    {$this->renderPagination($pagination->getLinks())}

    <div class="form-container">
        <h2>Neuer Eintrag</h2>
        <form action="{route('guestbook.store')}" method="POST">
            <input type="hidden" name="csrf_token" value="{$csrfToken}">
            
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required maxlength="100">
            </div>
            
            <div class="form-group">
                <label for="email">E-Mail:</label>
                <input type="email" id="email" name="email" required maxlength="255">
            </div>
            
            <div class="form-group">
                <label for="message">Nachricht:</label>
                <textarea id="message" name="message" rows="5" required maxlength="500"></textarea>
            </div>
            
            <button type="submit" class="submit-btn">Eintrag absenden</button>
        </form>
    </div>
</body>
</html>
HTML;
    }

    private function renderEntries(array $entries): string
    {
        if (empty($entries)) {
            return '<p>Noch keine Einträge vorhanden.</p>';
        }

        $entriesHtml = array_map(function($entry) {
            return sprintf(
                '<div class="entry">
                    <div class="entry-header">
                        <span class="entry-name">%s</span>
                        <span class="entry-date">%s</span>
                    </div>
                    <div class="entry-message">%s</div>
                </div>',
                e($entry['name']),
                e($entry['created_at']),
                nl2br(e($entry['message']))
            );
        }, $entries);

        return implode('', $entriesHtml);
    }

    private function renderPagination(array $links): string
    {
        if (empty($links)) {
            return '';
        }

        $paginationHtml = '<div class="pagination">';
        foreach ($links as $link) {
            $activeClass = $link['active'] ? 'active' : '';
            $paginationHtml .= sprintf(
                '<a href="%s" class="%s">%d</a>',
                e($link['url']),
                $activeClass,
                $link['page']
            );
        }
        $paginationHtml .= '</div>';

        return $paginationHtml;
    }

    private function renderErrorPage(string $message): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Fehler</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            text-align: center; 
            padding: 50px; 
        }
        .error { 
            color: red; 
            font-size: 18px; 
        }
    </style>
</head>
<body>
    <h1>Fehler</h1>
    <p class="error">{$message}</p>
    <a href="{route('guestbook.show')}">Zurück zum Gästebuch</a>
</body>
</html>
HTML;
    }
}