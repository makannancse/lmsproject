<?php

use function htmlspecialchars as h;

$base = defined('BASE_PATH') ? BASE_PATH : '';

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Users</h1>
        <p class="text-muted small mb-0">Manage users by role.</p>
    </div>
    <div class="btn-group">
        <a href="<?= h($base . '/admin/users/create-student') ?>" class="btn btn-sm btn-primary">New Student</a>
        <a href="<?= h($base . '/admin/users/create-teacher') ?>" class="btn btn-sm btn-outline-primary">New Teacher</a>
    </div>
</div>

<ul class="nav nav-pills mb-3">
    <?php foreach (['student' => 'Students', 'teacher' => 'Teachers', 'admin' => 'Admins'] as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $role === $key ? 'active' : '' ?>"
               href="<?= h($base . '/admin/users?role=' . urlencode($key)) ?>"><?= h($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Timezone</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="5" class="text-muted small">No users for this role yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= h($u['name']) ?></td>
                            <td><?= h($u['email']) ?></td>
                            <td><?= h($u['role']) ?></td>
                            <td><?= h($u['timezone']) ?></td>
                            <td><?= h($u['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

