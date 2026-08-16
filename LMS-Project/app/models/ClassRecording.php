<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';

class ClassRecording
{
    public static function findByClassId(int $classId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT cr.*, cs.title AS class_title, cs.status AS class_status, cs.start_datetime, cs.end_datetime,
                    cs.start_time_utc, cs.end_time_utc, cs.scheduled_timezone, cs.actual_start_time,
                    cs.actual_end_time, cs.actual_duration, cs.actual_duration_minutes,
                    t.name AS teacher_name, t.email AS teacher_email
             FROM class_recordings cr
             INNER JOIN class_sessions cs ON cs.id = cr.class_id
             INNER JOIN users t ON t.id = cr.teacher_id
             WHERE cr.class_id = :class_id
             LIMIT 1'
        );
        $stmt->execute(['class_id' => $classId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function upsertForClass(int $classId, int $teacherId, array $data): void
    {
        $pdo = Database::connection();

        // Uniqueness guard: if this Drive file ID is already attached to a DIFFERENT class,
        // do not overwrite that association. One Drive file = one class.
        $incomingFileId = trim((string) ($data['recording_file_id'] ?? ''));
        if ($incomingFileId !== '') {
            $check = $pdo->prepare(
                'SELECT class_id FROM class_recordings WHERE recording_file_id = :file_id LIMIT 1'
            );
            $check->execute(['file_id' => $incomingFileId]);
            $existingClassId = $check->fetchColumn();
            if ($existingClassId !== false && (int) $existingClassId !== $classId) {
                // Another class already owns this file — skip to prevent mis-assignment.
                error_log(sprintf(
                    '[ClassRecording] Skipping upsert for class %d: file_id "%s" already belongs to class %d.',
                    $classId,
                    $incomingFileId,
                    (int) $existingClassId
                ));
                return;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO class_recordings
                (class_id, teacher_id, recording_url, recording_file_id, recording_title, recording_duration, visible_to_student, sync_status, source)
             VALUES
                (:class_id, :teacher_id, :recording_url, :recording_file_id, :recording_title, :recording_duration, :visible_to_student, :sync_status, :source)
             ON DUPLICATE KEY UPDATE
                teacher_id = VALUES(teacher_id),
                recording_url = VALUES(recording_url),
                recording_file_id = VALUES(recording_file_id),
                recording_title = VALUES(recording_title),
                recording_duration = VALUES(recording_duration),
                visible_to_student = VALUES(visible_to_student),
                sync_status = VALUES(sync_status),
                source = VALUES(source),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'recording_url' => $data['recording_url'] ?? null,
            'recording_file_id' => $data['recording_file_id'] ?? null,
            'recording_title' => $data['recording_title'] ?? null,
            'recording_duration' => $data['recording_duration'] ?? null,
            'visible_to_student' => $data['visible_to_student'] ?? 'no',
            'sync_status' => $data['sync_status'] ?? 'pending',
            'source' => $data['source'] ?? 'google_drive',
        ]);
    }

    public static function setVisibility(int $recordingId, string $visibleToStudent): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE class_recordings
             SET visible_to_student = :visible_to_student
             WHERE id = :id'
        );
        $stmt->execute([
            'visible_to_student' => $visibleToStudent === 'yes' ? 'yes' : 'no',
            'id' => $recordingId,
        ]);
    }

    /**
     * When Drive sync has not created a row yet, admins can still set default visibility
     * (applied when sync upserts using existingVisibilityForClass).
     */
    public static function setVisibilityForClass(int $classId, string $visibleToStudent): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, teacher_id FROM class_sessions WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $classId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $vis = $visibleToStudent === 'yes' ? 'yes' : 'no';
        $teacherId = (int) ($row['teacher_id'] ?? 0);

        $check = $pdo->prepare('SELECT id FROM class_recordings WHERE class_id = :class_id LIMIT 1');
        $check->execute(['class_id' => $classId]);
        $existing = $check->fetch();

        if ($existing) {
            $pdo->prepare(
                'UPDATE class_recordings SET visible_to_student = :v WHERE class_id = :class_id'
            )->execute(['v' => $vis, 'class_id' => $classId]);

            return;
        }

        $pdo->prepare(
            'INSERT INTO class_recordings (class_id, teacher_id, visible_to_student, sync_status, source)
             VALUES (:class_id, :teacher_id, :visible_to_student, "pending", "google_drive")'
        )->execute([
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'visible_to_student' => $vis,
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function listForAdmin(array $filters = [], ?int $limit = null, ?int $offset = null): array
    {
        [$sql, $params] = self::buildAdminListQuery($filters);
        $sql .= ' ORDER BY COALESCE(cr.updated_at, cs.completed_at, cs.actual_end_time, cs.end_datetime) DESC, cs.id DESC';
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

    public static function countForAdmin(array $filters = []): int
    {
        [$sql, $params] = self::buildAdminListQuery($filters, true);
        $pdo = Database::connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @return array{0: string, 1: array<string, scalar>}
     */
    private static function buildAdminListQuery(array $filters, bool $countOnly = false): array
    {
        $pdo = Database::connection();
        unset($pdo);
        $select = $countOnly
            ? 'SELECT COUNT(*)'
            : 'SELECT
                    COALESCE(cr.id, 0) AS id,
                    cs.id AS class_id,
                    cs.teacher_id,
                    cs.recording_enabled,
                    COALESCE(cr.recording_url, cs.recording_url) AS recording_url,
                    cr.recording_file_id,
                    COALESCE(cr.recording_title, cs.title) AS recording_title,
                    cr.recording_duration,
                    COALESCE(cr.visible_to_student, "no") AS visible_to_student,
                    CASE
                        WHEN cr.id IS NOT NULL THEN cr.sync_status
                        ELSE COALESCE(cs.recording_sync_status, "processing")
                    END AS sync_status,
                    COALESCE(cr.source, "google_drive") AS source,
                    cr.updated_at AS recording_row_updated,
                    cs.updated_at AS class_updated_at,
                    cs.title AS class_title,
                    cs.status AS class_status,
                    cs.start_datetime,
                    cs.start_time_utc,
                    cs.end_time_utc,
                    cs.scheduled_timezone,
                    cs.actual_start_time,
                    cs.actual_end_time,
                    cs.actual_duration,
                    cs.actual_duration_minutes,
                    cs.completed_at,
                    cs.recording_sync_error,
                    tga.recording_supported AS teacher_recording_supported,
                    tga.status AS teacher_google_status,
                    t.name AS teacher_name,
                    (
                        SELECT GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ", ")
                        FROM enrollments e
                        INNER JOIN users u ON u.id = e.student_id
                        WHERE e.class_id = cs.id AND e.status = "active"
                    ) AS student_names';
        $sql = $select . '
                FROM class_sessions cs
                INNER JOIN users t ON t.id = cs.teacher_id
                LEFT JOIN teacher_google_accounts tga ON tga.teacher_id = cs.teacher_id
                LEFT JOIN class_recordings cr ON cr.class_id = cs.id
                WHERE cs.status = "completed"';
        $where = [];
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(cs.title LIKE :q1 OR t.name LIKE :q2 OR EXISTS (
                SELECT 1
                FROM enrollments e2
                INNER JOIN users u2 ON u2.id = e2.student_id
                WHERE e2.class_id = cs.id AND (u2.name LIKE :q3 OR u2.email LIKE :q4)
            ))';
            $params['q1'] = '%' . $query . '%';
            $params['q2'] = '%' . $query . '%';
            $params['q3'] = '%' . $query . '%';
            $params['q4'] = '%' . $query . '%';
        }

        if (!empty($filters['teacher_id'])) {
            $where[] = 'cs.teacher_id = :teacher_id';
            $params['teacher_id'] = (int) $filters['teacher_id'];
        }

        if (!empty($filters['student_id'])) {
            $where[] = 'EXISTS (
                SELECT 1 FROM enrollments e3
                WHERE e3.class_id = cs.id AND e3.student_id = :student_id AND e3.status = "active"
            )';
            $params['student_id'] = (int) $filters['student_id'];
        }

        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        return [$sql, $params];
    }

    public static function listForTeacher(int $teacherId, int $limit = 12): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT cr.*, cs.title AS class_title, cs.status AS class_status, cs.start_datetime,
                    cs.start_time_utc, cs.end_time_utc, cs.scheduled_timezone, cs.actual_start_time,
                    cs.actual_end_time, cs.actual_duration, cs.actual_duration_minutes
             FROM class_recordings cr
             INNER JOIN class_sessions cs ON cs.id = cr.class_id
             WHERE cr.teacher_id = :teacher_id
             ORDER BY cr.updated_at DESC, cr.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':teacher_id', $teacherId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public static function listVisibleForStudent(int $studentId, int $limit = 12): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT cr.*, cs.title AS class_title, cs.start_datetime, cs.start_time_utc, cs.end_time_utc,
                    cs.scheduled_timezone, cs.actual_start_time, cs.actual_end_time,
                    cs.actual_duration, cs.actual_duration_minutes, t.name AS teacher_name
             FROM class_recordings cr
             INNER JOIN class_sessions cs ON cs.id = cr.class_id
             INNER JOIN enrollments e ON e.class_id = cr.class_id
             INNER JOIN users t ON t.id = cr.teacher_id
             WHERE e.student_id = :student_id
               AND e.status = "active"
               AND cr.visible_to_student = "yes"
               AND cr.recording_url IS NOT NULL
               AND TRIM(cr.recording_url) <> ""
             ORDER BY cr.updated_at DESC, cr.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':student_id', $studentId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
