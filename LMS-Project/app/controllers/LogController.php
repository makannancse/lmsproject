<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/View.php';
require_once dirname(__DIR__) . '/lib/Pagination.php';
require_once dirname(__DIR__) . '/lib/LogViewer.php';

class LogController
{
    public static function auditIndex(): void
    {
        Auth::requireRole(['admin']);
        $req = Pagination::fromRequest();
        $result = LogViewer::readPage('audit', $req['offset'], $req['per_page']);
        $pagination = Pagination::meta($result['total'], $req['page'], $req['per_page']);

        View::render('admin/logs/audit', [
            'pageTitle' => 'Audit Logs',
            'lines' => $result['lines'],
            'pagination' => $pagination,
            'queryParams' => [],
        ]);
    }

    public static function emailIndex(): void
    {
        Auth::requireRole(['admin']);
        $file = trim((string) ($_GET['file'] ?? 'mail.log'));
        $allowed = LogViewer::emailLogFiles();
        if (!in_array($file, $allowed, true)) {
            $file = $allowed[0] ?? 'mail.log';
        }

        $req = Pagination::fromRequest();
        $result = LogViewer::readEmailPage($file, $req['offset'], $req['per_page']);
        $pagination = Pagination::meta($result['total'], $req['page'], $req['per_page']);

        View::render('admin/logs/email', [
            'pageTitle' => 'Email Logs',
            'lines' => $result['lines'],
            'pagination' => $pagination,
            'logFile' => $file,
            'logFiles' => $allowed,
            'queryParams' => ['file' => $file],
        ]);
    }
}
