<?php

declare(strict_types=1);

class View
{
    public static function render(string $view, array $data = []): void
    {
        $viewFile = dirname(__DIR__) . '/views/' . $view . '.php';
        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo 'View not found';
            return;
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layout = dirname(__DIR__) . '/views/layouts/base.php';
        if (file_exists($layout)) {
            require $layout;
        } else {
            echo $content;
        }
    }
}



