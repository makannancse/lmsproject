<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = array_merge(
    glob($root . '/app/controllers/*.php') ?: [],
    [$root . '/app/routes.php', $root . '/app/lib/Auth.php']
);

$patternExit = '/header\(\'Location: \' \. \$base \. \'([^\']+)\'\);\s*\n\s*exit\s*;/';
$patternOnly = '/header\(\'Location: \' \. \$base \. \'([^\']+)\'\);/';

foreach ($files as $file) {
    if (!is_file($file)) {
        continue;
    }
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }
    $original = $content;
    $content = preg_replace($patternExit, "redirectTo('$1');", $content) ?? $content;
    $content = preg_replace($patternOnly, "redirectTo('$1');", $content) ?? $content;
    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Updated: {$file}\n";
    }
}

echo "Done.\n";
