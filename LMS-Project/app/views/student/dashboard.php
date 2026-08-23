<?php

use function htmlspecialchars as h;

$base = appWebPath();
$studentName = (string) ($studentName ?? 'Student');
$studentTimezone = (string) ($studentTimezone ?? APP_TIMEZONE);
$assignedTeachers = $assignedTeachers ?? [];
$nextClass = $nextClass ?? null;
$upcomingCount = (int) ($upcomingCount ?? 0);
$homeworkItems = $homeworkItems ?? [];
$homeworkPending = (int) ($homeworkPending ?? 0);
$homeworkSubmitted = (int) ($homeworkSubmitted ?? 0);
$feedbackCount = (int) ($feedbackCount ?? 0);
$reportCount = (int) ($reportCount ?? 0);
$attendancePercent = $attendancePercent ?? null;
$recordings = $recordings ?? [];
$announcements = $announcements ?? [];
$hasBanner = (bool) ($hasBanner ?? false);
$bannerSrc = (string) ($bannerSrc ?? '');
$primaryTeacher = $assignedTeachers[0] ?? null;
?>

<div class="student-dashboard">
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="student-dash-card student-dash-card-hover">
                <div class="student-dash-card-header">
                    <h2><i class="fa-solid fa-user-tie me-2"></i>Assigned Teachers</h2>
                </div>
                <div class="student-dash-card-body">
                    <?php if ($assignedTeachers === []): ?>
                        <p class="text-muted mb-0">No teacher has been assigned to your classes yet.</p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($assignedTeachers as $teacher): ?>
                                <div class="col-md-6 col-xl-4">
                                    <div class="student-teacher-widget h-100">
                                        <div class="d-flex align-items-start gap-3">
                                            <span class="student-teacher-icon"><i class="fa-solid fa-chalkboard-user"></i></span>
                                            <div class="min-w-0">
                                                <div class="fw-semibold"><?= h((string) ($teacher['name'] ?? '')) ?></div>
                                                <div class="small text-muted mt-1">Class Name: <?= h((string) ($teacher['class_name'] ?? '—')) ?></div>
                                                <div class="small text-muted">Timezone: <?= h((string) ($teacher['timezone'] ?? '—')) ?></div>
                                                <?php if (!empty($teacher['email'])): ?>
                                                    <div class="small mt-2"><a href="mailto:<?= h((string) $teacher['email']) ?>"><?= h((string) $teacher['email']) ?></a></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="student-dash-card student-dash-card-hover h-100">
                <div class="student-dash-card-header">
                    <h2><i class="fa-solid fa-video me-2"></i>My Classes</h2>
                    <a href="<?= h(path('student/calendar')) ?>" class="small text-decoration-none">View all</a>
                </div>
                <div class="student-dash-card-body">
                    <?php if ($nextClass === null): ?>
                        <p class="text-muted mb-0">No upcoming classes scheduled. Check back soon or view your calendar.</p>
                    <?php else: ?>
                        <div class="student-next-class">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="student-next-class-label">Next Upcoming Class</div>
                                    <h3 class="h5 mb-2"><?= h((string) ($nextClass['title'] ?? 'Class')) ?></h3>
                                    <div class="small text-muted mb-1"><i class="fa-regular fa-clock me-1"></i><?= h(formatClassScheduledAt($nextClass, 'l, d M Y h:i A T')) ?></div>
                                    <div class="small text-muted"><i class="fa-solid fa-globe me-1"></i><?= h(formatClassScheduledTimezoneLabel($nextClass)) ?></div>
                                    <div class="small mt-2">Teacher: <?= h((string) ($nextClass['teacher_name'] ?? '')) ?></div>
                                    <?= teacherLateJoinNoticeHtml($nextClass, '', 'student') ?>
                                </div>
                                <?php if (!empty($nextClass['meeting_link']) && isJoinAllowedForStudent($nextClass)): ?>
                                    <a href="<?= h(path('join-class?class_id=' . (int) ($nextClass['id'] ?? 0))) ?>" class="btn btn-student-primary btn-lg px-4" target="_blank" rel="noopener">
                                        <i class="fa-solid fa-play me-2"></i>Join Class
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="student-dash-card student-dash-card-hover h-100">
                <div class="student-dash-card-header">
                    <h2><i class="fa-solid fa-chart-line me-2"></i>Performance</h2>
                </div>
                <div class="student-dash-card-body">
                    <div class="row g-3 text-center">
                        <div class="col-4">
                            <div class="student-stat-pill">
                                <div class="student-stat-value"><?= $feedbackCount ?></div>
                                <div class="student-stat-label">Feedback</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="student-stat-pill">
                                <div class="student-stat-value"><?= $reportCount ?></div>
                                <div class="student-stat-label">Reports</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="student-stat-pill">
                                <div class="student-stat-value"><?= $attendancePercent !== null ? h((string) $attendancePercent) . '%' : '—' ?></div>
                                <div class="student-stat-label">Attendance</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="student-dash-card student-dash-card-hover h-100">
                <div class="student-dash-card-header">
                    <h2><i class="fa-solid fa-book-open me-2"></i>Homework</h2>
                    <a href="<?= h(path('student/homework')) ?>" class="small text-decoration-none">View all</a>
                </div>
                <div class="student-dash-card-body">
                    <div class="d-flex gap-2 mb-3">
                        <span class="badge rounded-pill student-badge-pending"><?= $homeworkPending ?> Pending</span>
                        <span class="badge rounded-pill student-badge-done"><?= $homeworkSubmitted ?> Submitted</span>
                    </div>
                    <?php if ($homeworkItems === []): ?>
                        <p class="text-muted small mb-0">No homework assigned yet.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0 student-homework-list">
                            <?php foreach (array_slice($homeworkItems, 0, 4) as $hw): ?>
                                <li class="d-flex justify-content-between align-items-center gap-2 py-2 border-bottom">
                                    <span class="small fw-medium"><?= h((string) ($hw['title'] ?? 'Homework')) ?></span>
                                    <?php if (!empty($hw['is_submitted'])): ?>
                                        <span class="badge text-bg-success">Submitted</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="student-dash-card student-dash-card-hover h-100">
                <div class="student-dash-card-header">
                    <h2><i class="fa-solid fa-circle-play me-2"></i>Recordings</h2>
                </div>
                <div class="student-dash-card-body">
                    <?php if ($recordings === []): ?>
                        <p class="text-muted small mb-0">No approved recordings yet.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($recordings, 0, 3) as $recording): ?>
                            <div class="student-recording-item mb-3 pb-3 border-bottom">
                                <div class="fw-semibold small"><?= h((string) ($recording['recording_title'] ?? $recording['class_title'] ?? 'Recording')) ?></div>
                                <div class="text-muted small mb-2"><?= h((string) ($recording['teacher_name'] ?? '')) ?></div>
                                <a href="<?= h((string) ($recording['recording_url'] ?? '#')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-play me-1"></i>Watch Recording
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="student-dash-card student-dash-card-hover h-100">
                <div class="student-dash-card-header">
                    <h2><i class="fa-solid fa-bullhorn me-2"></i>Announcements</h2>
                </div>
                <div class="student-dash-card-body">
                    <?php foreach ($announcements as $item): ?>
                        <div class="student-announcement mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between gap-2 mb-1">
                                <strong class="small"><?= h((string) ($item['title'] ?? '')) ?></strong>
                                <span class="text-muted small"><?= h((string) ($item['date'] ?? '')) ?></span>
                            </div>
                            <p class="text-muted small mb-0"><?= h((string) ($item['body'] ?? '')) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="student-dashboard-banner-hero mb-4 student-dash-card" style='min-height: 550px;'>
        <?php if ($hasBanner && $bannerSrc !== ''): ?>
            <img src="<?= h($bannerSrc) ?>" alt="LearnWise welcome banner" class="student-dashboard-banner-image">
        <?php else: ?>
            <div class="student-dashboard-banner-fallback"></div>
        <?php endif; ?>
    </div>
</div>
