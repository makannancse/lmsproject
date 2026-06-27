<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

class StudentReport
{
    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO student_reports (
                student_id, teacher_id, email, student_name, teacher_name, subject,
                overall_performance, concept_understanding, application_ability,
                homework_completion, attention_level, participation_level, behaviour,
                subjects_addressed, future_focus, recommended_focus,
                study_strategies, additional_support, overall_feedback, report_date, pdf_path
             ) VALUES (
                :student_id, :teacher_id, :email, :student_name, :teacher_name, :subject,
                :overall_performance, :concept_understanding, :application_ability,
                :homework_completion, :attention_level, :participation_level, :behaviour,
                :subjects_addressed, :future_focus, :recommended_focus,
                :study_strategies, :additional_support, :overall_feedback, :report_date, :pdf_path
             )'
        );
        $stmt->execute([
            'student_id' => (int) $data['student_id'],
            'teacher_id' => (int) $data['teacher_id'],
            'email' => (string) $data['email'],
            'student_name' => (string) $data['student_name'],
            'teacher_name' => (string) $data['teacher_name'],
            'subject' => (string) $data['subject'],
            'overall_performance' => (string) $data['overall_performance'],
            'concept_understanding' => (string) $data['concept_understanding'],
            'application_ability' => (string) $data['application_ability'],
            'homework_completion' => (string) $data['homework_completion'],
            'attention_level' => (string) $data['attention_level'],
            'participation_level' => (string) $data['participation_level'],
            'behaviour' => (string) $data['behaviour'],
            'subjects_addressed' => (string) $data['subjects_addressed'],
            'future_focus' => (string) $data['future_focus'],
            'recommended_focus' => (string) $data['recommended_focus'],
            'study_strategies' => (string) $data['study_strategies'],
            'additional_support' => (string) $data['additional_support'],
            'overall_feedback' => (string) $data['overall_feedback'],
            'report_date' => (string) $data['report_date'],
            'pdf_path' => (string) ($data['pdf_path'] ?? ''),
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updatePdfPath(int $reportId, string $pdfPath): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE student_reports SET pdf_path = :p WHERE id = :id');
        $stmt->execute(['p' => $pdfPath, 'id' => $reportId]);
    }

    public static function getStudentProfile(int $studentId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, s.parent_email
             FROM users u
             LEFT JOIN students s ON s.user_id = u.id
             WHERE u.id = :id AND u.role = "student"
             LIMIT 1'
        );
        $stmt->execute(['id' => $studentId]);
        $row = $stmt->fetch() ?: null;
        return $row;
    }

    public static function getTeacherProfile(int $teacherId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = :id AND role = "teacher" LIMIT 1');
        $stmt->execute(['id' => $teacherId]);
        $row = $stmt->fetch() ?: null;
        return $row;
    }

    public static function teacherStudents(int $teacherId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, s.parent_email
             FROM teacher_students ts
             INNER JOIN users u ON u.id = ts.student_id
             LEFT JOIN students s ON s.user_id = u.id
             WHERE ts.teacher_id = :tid
             ORDER BY u.name'
        );
        $stmt->execute(['tid' => $teacherId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function allTeachers(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT id, name, email FROM users WHERE role = "teacher" AND status = "active" ORDER BY name'
        );
        return $stmt->fetchAll() ?: [];
    }

    public static function allStudents(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT u.id, u.name, u.email, s.parent_email
             FROM users u
             LEFT JOIN students s ON s.user_id = u.id
             WHERE u.role = "student" AND u.status = "active"
             ORDER BY u.name'
        );
        return $stmt->fetchAll() ?: [];
    }

    public static function listForUser(string $role, int $userId, array $filters = [], ?int $limit = null, ?int $offset = null): array
    {
        [$sql, $params] = self::buildListQuery($role, $userId, $filters);
        $sql .= ' ORDER BY sr.report_date DESC, sr.created_at DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
            $stmt->bindValue(':offset', max(0, $offset ?? 0), \PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public static function countForUser(string $role, int $userId, array $filters = []): int
    {
        [$sql, $params] = self::buildListQuery($role, $userId, $filters, true);
        $pdo = Database::connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @return array{0: string, 1: array<string, scalar>}
     */
    private static function buildListQuery(string $role, int $userId, array $filters, bool $countOnly = false): array
    {
        $sql = $countOnly ? 'SELECT COUNT(*) FROM student_reports sr' : 'SELECT sr.* FROM student_reports sr';
        $where = [];
        $params = [];

        if ($role === 'teacher') {
            $where[] = 'sr.student_id IN (SELECT student_id FROM teacher_students WHERE teacher_id = :uid)';
            $params['uid'] = $userId;
        } elseif ($role === 'student') {
            $where[] = 'sr.student_id = :uid';
            $params['uid'] = $userId;
        }

        $studentId = (int) ($filters['student_id'] ?? 0);
        $teacherId = (int) ($filters['teacher_id'] ?? 0);
        $subject = trim((string) ($filters['subject'] ?? ''));
        $fromDate = trim((string) ($filters['from_date'] ?? ''));
        $toDate = trim((string) ($filters['to_date'] ?? ''));

        if ($studentId > 0) {
            $where[] = 'sr.student_id = :student_id';
            $params['student_id'] = $studentId;
        }
        if ($teacherId > 0) {
            $where[] = 'sr.teacher_id = :teacher_id';
            $params['teacher_id'] = $teacherId;
        }
        if ($subject !== '') {
            $where[] = 'sr.subject LIKE :subject';
            $params['subject'] = '%' . $subject . '%';
        }
        if ($fromDate !== '') {
            $where[] = 'sr.report_date >= :from_date';
            $params['from_date'] = $fromDate;
        }
        if ($toDate !== '') {
            $where[] = 'sr.report_date <= :to_date';
            $params['to_date'] = $toDate;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        if (!$countOnly) {
            // ORDER BY appended by listForUser
        }

        return [$sql, $params];
    }

    public static function findByIdForUser(int $id, string $role, int $userId): ?array
    {
        $pdo = Database::connection();
        $sql = 'SELECT sr.* FROM student_reports sr WHERE sr.id = :id';
        $params = ['id' => $id];
        if ($role === 'teacher') {
            $sql .= ' AND sr.student_id IN (SELECT student_id FROM teacher_students WHERE teacher_id = :uid)';
            $params['uid'] = $userId;
        } elseif ($role === 'student') {
            $sql .= ' AND sr.student_id = :uid';
            $params['uid'] = $userId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: null;
        return $row;
    }
}
