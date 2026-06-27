<?php

declare(strict_types=1);

/**
 * Old bookmark: was public/admin/teacher_student_mapping.php (that folder broke /admin routing).
 */
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/lib/Auth.php';

Auth::startSession();

redirectTo('admin/teacher-students');
