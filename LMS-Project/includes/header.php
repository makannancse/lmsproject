<?php

use function htmlspecialchars as h;

$base = $base ?? (defined('BASE_PATH') ? BASE_PATH : '');
$pageTitle = $pageTitle ?? APP_NAME;
$localBootstrap = '/assets/css/bootstrap.min.css';
$bootstrapHref = file_exists(dirname(__DIR__) . '/public' . $localBootstrap)
    ? $localBootstrap
    : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h((string) $pageTitle) ?></title>
    <link rel="stylesheet" href="<?= h($bootstrapHref) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= h($base . '/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= h($base . '/assets/css/sweetalert-custom.css') ?>">
</head>
<body class="app-body bg-app">

