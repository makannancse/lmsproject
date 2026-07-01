<?php

use function htmlspecialchars as h;

$base = appWebPath();
$role = $role ?? 'student';
$users = $users ?? [];
$searchQuery = $searchQuery ?? '';
$statusFilter = $statusFilter ?? '';

?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="me-auto">
        <h1 class="h4 mb-0">Users</h1>
        <p class="text-muted small mb-0">Search, filter, edit, and activate or deactivate accounts.</p>
    </div>
    <div class="flex-shrink-0">
        <div class="btn-group" role="group" aria-label="Create user">
            <a href="<?= h(path('admin/users/create-student')) ?>" class="btn btn-sm btn-primary">New Student</a>
            <a href="<?= h(path('admin/users/create-teacher')) ?>" class="btn btn-sm btn-outline-primary">New Teacher</a>
        </div>
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

<div class="card shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="get" action="<?= h($base . '/admin/users') ?>" class="row g-2 align-items-end no-app-loader">
            <input type="hidden" name="role" value="<?= h($role) ?>">
            <div class="col-md-5">
                <label class="form-label form-label-sm mb-0" for="userSearch">Search</label>
                <input type="search" name="q" id="userSearch" class="form-control form-control-sm"
                       value="<?= h($searchQuery) ?>" placeholder="Name, email, or phone">
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm mb-0" for="userStatus">Status</label>
                <select name="status" id="userStatus" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                <a href="<?= h($base . '/admin/users?role=' . urlencode($role)) ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Timezone</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" class="text-muted small">No users match your filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $uid = (int) ($u['id'] ?? 0);
                        $isActive = strtolower((string) ($u['status'] ?? 'active')) === 'active';
                        ?>
                        <tr>
                            <td><?= h($u['name']) ?></td>
                            <td><?= h($u['email']) ?></td>
                            <td><?= h((string) ($u['phone'] ?? '—')) ?></td>
                            <td><?= h($u['timezone']) ?></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="badge text-bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge text-bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-1">
                                    <a href="<?= h($base . '/admin/users/edit?id=' . $uid) ?>"
                                       class="btn btn-sm btn-outline-primary">Edit</a>
                                    <?php if ($isActive): ?>
                                        <form method="post"
                                              action="<?= h($base . '/admin/users/toggle-status') ?>"
                                              class="d-inline no-app-loader"
                                              data-confirm="1"
                                              data-confirm-title="Deactivate User?"
                                              data-confirm-text="This user will no longer be able to access the system."
                                              data-confirm-button="Deactivate"
                                              data-confirm-cancel="Cancel"
                                              data-confirm-icon="warning">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <input type="hidden" name="role" value="<?= h($role) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post"
                                              action="<?= h($base . '/admin/users/toggle-status') ?>"
                                              class="d-inline no-app-loader"
                                              data-confirm="1"
                                              data-confirm-title="Activate user?"
                                              data-confirm-text="This user will regain access to the LMS."
                                              data-confirm-button="Activate"
                                              data-confirm-cancel="Cancel"
                                              data-confirm-icon="question">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="role" value="<?= h($role) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success">Activate</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($role, ['student', 'teacher'], true)): ?>
                                        <form method="post"
                                              action="<?= h($base . '/admin/users/delete') ?>"
                                              class="d-inline no-app-loader"
                                              data-confirm="1"
                                              data-confirm-title="Delete Permanently?"
                                              data-confirm-text="This will permanently remove the user and related records. This cannot be undone."
                                              data-confirm-button="Delete Permanently"
                                              data-confirm-cancel="Cancel"
                                              data-confirm-icon="error">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="role" value="<?= h($role) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php renderPagination($pagination ?? null, $queryParams ?? []); ?>
</div>
