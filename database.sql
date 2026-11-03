-- =====================================================================
-- HIGH SCHOOL MANAGEMENT SYSTEM (HSMS)
-- Liberian High School Edition
-- Database: hsms_db
-- Engine: InnoDB  |  Charset: utf8mb4
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `hsms_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
USE `hsms_db`;

-- ---------------------------------------------------------------------
-- 1. users  (all logins — admin / teacher / student)
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)  NOT NULL,
  `email`         VARCHAR(120) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('admin','teacher','student') NOT NULL,
  `status`        ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `last_login`    DATETIME DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 2. settings  (school info, current session, grading scale)
-- ---------------------------------------------------------------------
CREATE TABLE `settings` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_name`      VARCHAR(150) NOT NULL DEFAULT 'High School',
  `school_address`   VARCHAR(255) DEFAULT NULL,
  `school_phone`     VARCHAR(50)  DEFAULT NULL,
  `school_email`     VARCHAR(120) DEFAULT NULL,
  `school_logo`      VARCHAR(255) DEFAULT NULL,
  `current_session`  VARCHAR(20)  NOT NULL DEFAULT '2025/2026',
  `current_semester` ENUM('1','2') NOT NULL DEFAULT '1',
  `current_marking`  ENUM('MP1','MP2','MP3','EXAM1','MP4','MP5','MP6','EXAM2')
                     NOT NULL DEFAULT 'MP1',
  `currency`         VARCHAR(10)  NOT NULL DEFAULT 'LRD',
  `currency_symbol`  VARCHAR(10)  NOT NULL DEFAULT 'L$',
  `passing_grade`    CHAR(1)      NOT NULL DEFAULT 'E',
  `grade_scale`      TEXT         DEFAULT NULL,  -- JSON grading scale
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. classes  (Grade 7A, Grade 10B, etc.)
-- ---------------------------------------------------------------------
CREATE TABLE `classes` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(50) NOT NULL,
  `grade_level`  TINYINT UNSIGNED NOT NULL,       -- 7 to 12
  `section`      CHAR(1) DEFAULT NULL,            -- A, B, C
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_name` (`name`),
  KEY `idx_class_grade` (`grade_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. subjects  (Math, English, Biology, etc.)
-- ---------------------------------------------------------------------
CREATE TABLE `subjects` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(20) NOT NULL,
  `name`        VARCHAR(100) NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subject_code` (`code`),
  UNIQUE KEY `uq_subject_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. teachers
-- ---------------------------------------------------------------------
CREATE TABLE `teachers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `staff_id`       VARCHAR(30) NOT NULL,           -- TCH/2025/0001
  `first_name`     VARCHAR(80) NOT NULL,
  `last_name`      VARCHAR(80) NOT NULL,
  `gender`         ENUM('male','female','other') DEFAULT NULL,
  `dob`            DATE DEFAULT NULL,
  `phone`          VARCHAR(30) DEFAULT NULL,
  `address`        VARCHAR(255) DEFAULT NULL,
  `county`         VARCHAR(50) DEFAULT NULL,
  `qualification`  VARCHAR(150) DEFAULT NULL,
  `photo`          VARCHAR(255) DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_staff_id` (`staff_id`),
  KEY `idx_teacher_user` (`user_id`),
  CONSTRAINT `fk_teacher_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6. students
-- ---------------------------------------------------------------------
CREATE TABLE `students` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `student_id`     VARCHAR(30) NOT NULL,           -- STU/2025/0001
  `first_name`     VARCHAR(80) NOT NULL,
  `last_name`      VARCHAR(80) NOT NULL,
  `gender`         ENUM('male','female','other') DEFAULT NULL,
  `dob`            DATE DEFAULT NULL,
  `class_id`       INT UNSIGNED DEFAULT NULL,
  `phone`          VARCHAR(30) DEFAULT NULL,
  `address`        VARCHAR(255) DEFAULT NULL,
  `county`         VARCHAR(50) DEFAULT NULL,
  `guardian_name`  VARCHAR(150) DEFAULT NULL,
  `guardian_phone` VARCHAR(30) DEFAULT NULL,
  `photo`          VARCHAR(255) DEFAULT NULL,
  `enrolled_at`    DATE DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_id` (`student_id`),
  KEY `idx_student_user` (`user_id`),
  KEY `idx_student_class` (`class_id`),
  CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. class_subject_teacher  (who teaches what where)
-- ---------------------------------------------------------------------
CREATE TABLE `class_subject_teacher` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id`    INT UNSIGNED NOT NULL,
  `subject_id`  INT UNSIGNED NOT NULL,
  `teacher_id`  INT UNSIGNED NOT NULL,
  `session`     VARCHAR(20) NOT NULL DEFAULT '2025/2026',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cst` (`class_id`,`subject_id`,`session`),
  KEY `idx_cst_teacher` (`teacher_id`),
  CONSTRAINT `fk_cst_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cst_subject` FOREIGN KEY (`subject_id`)
    REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cst_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 8. grades  (one row per student/subject/session/marking_period)
-- ---------------------------------------------------------------------
CREATE TABLE `grades` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`     INT UNSIGNED NOT NULL,
  `subject_id`     INT UNSIGNED NOT NULL,
  `class_id`       INT UNSIGNED NOT NULL,
  `session`        VARCHAR(20) NOT NULL DEFAULT '2025/2026',
  `marking_period` ENUM('MP1','MP2','MP3','EXAM1','MP4','MP5','MP6','EXAM2') NOT NULL,
  `score`          DECIMAL(5,2) DEFAULT NULL,
  `grade_letter`   CHAR(1) DEFAULT NULL,
  `entered_by`     INT UNSIGNED DEFAULT NULL,       -- teacher id
  `locked_at`      DATETIME DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grade` (`student_id`,`subject_id`,`session`,`marking_period`),
  KEY `idx_grade_class` (`class_id`),
  KEY `idx_grade_subject` (`subject_id`),
  CONSTRAINT `fk_grade_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grade_subject` FOREIGN KEY (`subject_id`)
    REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grade_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grade_teacher` FOREIGN KEY (`entered_by`)
    REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 9. computed_grades  (sem1_avg, sem2_avg, yearly_avg)
-- ---------------------------------------------------------------------
CREATE TABLE `computed_grades` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`   INT UNSIGNED NOT NULL,
  `subject_id`   INT UNSIGNED NOT NULL,
  `class_id`     INT UNSIGNED NOT NULL,
  `session`      VARCHAR(20) NOT NULL,
  `sem1_avg`     DECIMAL(5,2) DEFAULT NULL,
  `sem2_avg`     DECIMAL(5,2) DEFAULT NULL,
  `yearly_avg`   DECIMAL(5,2) DEFAULT NULL,
  `grade_letter` CHAR(1) DEFAULT NULL,
  `computed_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_computed` (`student_id`,`subject_id`,`session`),
  KEY `idx_computed_class` (`class_id`),
  CONSTRAINT `fk_computed_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_computed_subject` FOREIGN KEY (`subject_id`)
    REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_computed_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 10. grade_audit  (every grade change logged)
-- ---------------------------------------------------------------------
CREATE TABLE `grade_audit` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grade_id`      INT UNSIGNED DEFAULT NULL,
  `student_id`    INT UNSIGNED NOT NULL,
  `subject_id`    INT UNSIGNED NOT NULL,
  `session`       VARCHAR(20) NOT NULL,
  `marking_period` VARCHAR(10) NOT NULL,
  `old_score`     DECIMAL(5,2) DEFAULT NULL,
  `new_score`     DECIMAL(5,2) DEFAULT NULL,
  `changed_by`    INT UNSIGNED NOT NULL,
  `changed_by_role` ENUM('admin','teacher') NOT NULL,
  `reason`        VARCHAR(255) DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_student` (`student_id`),
  KEY `idx_audit_grade` (`grade_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 11. assignments
-- ---------------------------------------------------------------------
CREATE TABLE `assignments` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id`   INT UNSIGNED NOT NULL,
  `class_id`     INT UNSIGNED NOT NULL,
  `subject_id`   INT UNSIGNED NOT NULL,
  `title`        VARCHAR(200) NOT NULL,
  `description`  TEXT,
  `attachment`   VARCHAR(255) DEFAULT NULL,
  `due_date`     DATE DEFAULT NULL,
  `status`       ENUM('active','closed') NOT NULL DEFAULT 'active',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assign_class` (`class_id`),
  KEY `idx_assign_teacher` (`teacher_id`),
  CONSTRAINT `fk_assign_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_subject` FOREIGN KEY (`subject_id`)
    REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 12. assignment_views  (which students have seen it)
-- ---------------------------------------------------------------------
CREATE TABLE `assignment_views` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id`  INT UNSIGNED NOT NULL,
  `student_id`     INT UNSIGNED NOT NULL,
  `viewed_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_view` (`assignment_id`,`student_id`),
  CONSTRAINT `fk_view_assign` FOREIGN KEY (`assignment_id`)
    REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_view_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 13. news
-- ---------------------------------------------------------------------
CREATE TABLE `news` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(200) NOT NULL,
  `content`    TEXT NOT NULL,
  `category`   VARCHAR(50) DEFAULT 'General',
  `pinned`     TINYINT(1) NOT NULL DEFAULT 0,
  `author_id`  INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_news_author` (`author_id`),
  CONSTRAINT `fk_news_author` FOREIGN KEY (`author_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 14. excuses
-- ---------------------------------------------------------------------
CREATE TABLE `excuses` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`    INT UNSIGNED NOT NULL,
  `absence_date`  DATE NOT NULL,
  `reason`        TEXT NOT NULL,
  `attachment`    VARCHAR(255) DEFAULT NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note`    VARCHAR(255) DEFAULT NULL,
  `reviewed_by`   INT UNSIGNED DEFAULT NULL,
  `reviewed_at`   DATETIME DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_excuse_student` (`student_id`),
  KEY `idx_excuse_status` (`status`),
  CONSTRAINT `fk_excuse_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_excuse_admin` FOREIGN KEY (`reviewed_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 15. teacher_reports
-- ---------------------------------------------------------------------
CREATE TABLE `teacher_reports` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`  INT UNSIGNED NOT NULL,
  `teacher_id`  INT UNSIGNED NOT NULL,
  `subject_id`  INT UNSIGNED DEFAULT NULL,
  `message`     TEXT NOT NULL,
  `anonymous`   TINYINT(1) NOT NULL DEFAULT 0,
  `status`      ENUM('pending','reviewed','resolved') NOT NULL DEFAULT 'pending',
  `admin_note`  VARCHAR(255) DEFAULT NULL,
  `reviewed_by` INT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_teacher` (`teacher_id`),
  KEY `idx_report_student` (`student_id`),
  KEY `idx_report_status` (`status`),
  CONSTRAINT `fk_report_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_subject` FOREIGN KEY (`subject_id`)
    REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_admin` FOREIGN KEY (`reviewed_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 16. fees  (per-student per-term fee payments in LRD)
-- ---------------------------------------------------------------------
CREATE TABLE `fees` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`   INT UNSIGNED NOT NULL,
  `session`      VARCHAR(20) NOT NULL,
  `semester`     ENUM('1','2') NOT NULL,
  `amount_due`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance`      DECIMAL(12,2) GENERATED ALWAYS AS (`amount_due` - `amount_paid`) STORED,
  `due_date`     DATE DEFAULT NULL,
  `status`       ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `recorded_by`  INT UNSIGNED DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fee_student` (`student_id`),
  KEY `idx_fee_session` (`session`,`semester`),
  CONSTRAINT `fk_fee_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fee_admin` FOREIGN KEY (`recorded_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 17. transactions  (income / expenses in LRD)
-- ---------------------------------------------------------------------
CREATE TABLE `transactions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`          ENUM('income','expense') NOT NULL,
  `category`      VARCHAR(80) NOT NULL,
  `amount`        DECIMAL(12,2) NOT NULL,
  `description`   VARCHAR(255) DEFAULT NULL,
  `transaction_date` DATE NOT NULL,
  `recorded_by`   INT UNSIGNED NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tx_type` (`type`),
  KEY `idx_tx_date` (`transaction_date`),
  CONSTRAINT `fk_tx_user` FOREIGN KEY (`recorded_by`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 18. activity_log  (system-wide audit trail)
-- ---------------------------------------------------------------------
CREATE TABLE `activity_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `role`       VARCHAR(20) DEFAULT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `details`    TEXT,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_user` (`user_id`),
  KEY `idx_log_action` (`action`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Default settings row (only 1 row will exist)
INSERT INTO `settings`
  (`school_name`,`current_session`,`current_semester`,`current_marking`,
   `currency`,`currency_symbol`,`passing_grade`,`grade_scale`)
VALUES
  ('Liberian High School','2025/2026','1','MP1','LRD','L$','E',
   '{"A":[90,100],"B":[80,89],"C":[70,79],"D":[60,69],"E":[50,59],"F":[0,49]}');

-- Default admin  (username: admin  |  password: Admin@123)
INSERT INTO `users` (`username`,`email`,`password_hash`,`role`) VALUES
  ('admin','admin@school.edu.lr',
   '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlCS4bZJ18JuywdBmVXNbRuKZ2WpQy',
   'admin');

-- Classes (Grades 7–12, two sections each)
INSERT INTO `classes` (`name`,`grade_level`,`section`) VALUES
  ('Grade 7A',7,'A'),('Grade 7B',7,'B'),
  ('Grade 8A',8,'A'),('Grade 8B',8,'B'),
  ('Grade 9A',9,'A'),('Grade 9B',9,'B'),
  ('Grade 10A',10,'A'),('Grade 10B',10,'B'),
  ('Grade 11A',11,'A'),('Grade 11B',11,'B'),
  ('Grade 12A',12,'A'),('Grade 12B',12,'B');

-- Subjects (Liberian high school curriculum)
INSERT INTO `subjects` (`code`,`name`) VALUES
  ('MATH','Mathematics'),
  ('ENG','English Language'),
  ('BIO','Biology'),
  ('CHEM','Chemistry'),
  ('PHY','Physics'),
  ('CIV','Civics'),
  ('HIST','Liberian History'),
  ('GEO','Geography'),
  ('FRE','French'),
  ('PE','Physical Education'),
  ('ICT','Information Technology'),
  ('AGRI','Agriculture');

-- Sample news (posted by admin)
INSERT INTO `news` (`title`,`content`,`category`,`pinned`,`author_id`) VALUES
  ('Welcome to the 2025/2026 Academic Year',
   'Dear students, welcome back. First marking period begins September 2, 2025. Please ensure all fees are settled before mid-term.',
   'General',1,1),
  ('Mid-Term Exam Schedule Released',
   'The semester one exam schedule is now available. Please check with your class teacher.',
   'Academic',0,1);

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- END OF SCRIPT
-- =====================================================================





--- =====================================================================
-- SIX IMPORTANT QUERIES FOR REPORTS
-- ======================================================================

-- 1. Should return 18
SELECT COUNT(*) AS table_count
FROM information_schema.tables
WHERE table_schema = 'hsms_db';

-- 2. Should return 1 admin user with role='admin'
SELECT id, username, role FROM users;

-- 3. Should return 12 classes
SELECT COUNT(*) AS class_count FROM classes;

-- 4. Should return 12 subjects
SELECT COUNT(*) AS subject_count FROM subjects;

-- 5. Should return settings with session 2025/2026
SELECT school_name, current_session, current_semester, current_marking,
       currency, currency_symbol, passing_grade
FROM settings;

-- 6. Should return 2 news items
SELECT id, title, pinned FROM news;
