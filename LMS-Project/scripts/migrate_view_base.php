<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dirs = [
    $root . '/app/views',
    $root . '/homework',
    $root . '/reports',
    $root . '/payments',
    $root . '/public',
];

$replacements = [
    "\$base = defined('BASE_PATH') ? BASE_PATH : '';" => '$base = appWebPath();',
    "(defined('BASE_PATH') ? BASE_PATH : '')" => 'appWebPath()',
    '<?= BASE_PATH ?>' => '<?= appWebPath() ?>',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }
        $original = $content;
        foreach ($replacements as $from => $to) {
            $content = str_replace($from, $to, $content);
        }
        if ($content !== $original) {
            file_put_contents($path, $content);
            echo "Updated: {$path}\n";
        }
    }
}

echo "Done.\n";
