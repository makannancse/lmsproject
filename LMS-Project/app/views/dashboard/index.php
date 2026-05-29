<?php

use function htmlspecialchars as h;

$user = Auth::user();
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h4 mb-0">Dashboard</h1>
                <p class="text-muted mb-0 small">Welcome back, <?= h((string) ($user['name'] ?? 'User')) ?>.</p>
            </div>
            <span class="badge text-bg-primary text-uppercase"><?= h((string) ($user['role'] ?? 'user')) ?></span>
        </div>
    </div>

    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-2">Upcoming Classes</h2>
                <p class="display-6 fw-semibold mb-0">0</p>
                <p class="small text-muted mb-0">Use the role-specific dashboard for live class tracking.</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-2">Completed</h2>
                <p class="display-6 fw-semibold mb-0">0</p>
                <p class="small text-muted mb-0">Actual duration now comes from real join/end activity.</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-2">Payouts</h2>
                <p class="display-6 fw-semibold mb-0"><?= h(formatCurrency(0)) ?></p>
                <p class="small text-muted mb-0">Use the payments dashboard for the full INR summary.</p>
            </div>
        </div>
    </div>
</div>
