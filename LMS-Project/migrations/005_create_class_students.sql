CREATE TABLE IF NOT EXISTS class_students (
    class_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (class_id, student_id),
    CONSTRAINT fk_class_student_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_student_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);



