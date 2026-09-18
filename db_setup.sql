-- ============================================================
-- Hackathon Job Readiness Prediction System
-- Database Setup Script
-- Import this file in phpMyAdmin → Import tab to set up.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `hackathon-employability-ml`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `hackathon-employability-ml`;

-- Drop table if re-running setup
DROP TABLE IF EXISTS `registration`;

CREATE TABLE `registration` (
    `id`          INT(11)      NOT NULL AUTO_INCREMENT,
    `FirstName`   VARCHAR(100) NOT NULL,
    `LastName`    VARCHAR(100) NOT NULL,
    `Email`       VARCHAR(255) NOT NULL UNIQUE,
    `Mobile`      VARCHAR(15)  NOT NULL,
    `Gender`      VARCHAR(20)  NOT NULL,
    `DateOfBirth` DATE         NOT NULL,
    `Address`     TEXT         NOT NULL,
    `City`        VARCHAR(100) NOT NULL,
    `Areapin`     CHAR(6)      NOT NULL,
    `Password`    VARCHAR(255) NOT NULL,   -- bcrypt hash (60 chars+)
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Optional: insert a test user (password = Test@1234)
-- You can delete this row after confirming login works.
-- ============================================================
INSERT INTO `registration`
    (`FirstName`, `LastName`, `Email`, `Mobile`, `Gender`, `DateOfBirth`, `Address`, `City`, `Areapin`, `Password`)
VALUES (
    'Test',
    'User',
    'test@example.com',
    '9876543210',
    'Male',
    '2000-01-01',
    '123 Demo Street',
    'Delhi',
    '110001',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'  -- bcrypt of "Test@1234"
);

-- ============================================================
-- Student Detail Form table (with AI Readiness Score columns)
-- ============================================================
DROP TABLE IF EXISTS `prediction_results`;
DROP TABLE IF EXISTS `student_details`;

CREATE TABLE `student_details` (
    `id`                    INT(11)      NOT NULL AUTO_INCREMENT,
    `user_id`               INT(11)      NOT NULL UNIQUE,
    -- Academic
    `degree`                VARCHAR(150) NOT NULL DEFAULT '',
    `college`               VARCHAR(255) NOT NULL DEFAULT '',
    `cgpa`                  DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    `graduation_year`       YEAR         NOT NULL,
    -- Profile JSON blobs
    `skills`                JSON         DEFAULT NULL,
    `experience`            JSON         DEFAULT NULL,
    `certificates`          JSON         DEFAULT NULL,
    `cv_filename`           VARCHAR(255) DEFAULT NULL,
    -- ── AI Readiness Score Fields (Step 5) ───────────────────
    `age`                   TINYINT      NOT NULL DEFAULT 22,
    -- Skill scores (0-10)
    `python_score`          DECIMAL(4,1) NOT NULL DEFAULT 5.0,
    `sql_score`             DECIMAL(4,1) NOT NULL DEFAULT 5.0,
    `statistics_score`      DECIMAL(4,1) NOT NULL DEFAULT 5.0,
    `ml_score`              DECIMAL(4,1) NOT NULL DEFAULT 5.0,
    `dl_score`              DECIMAL(4,1) NOT NULL DEFAULT 5.0,
    `genai_score`           DECIMAL(4,1) NOT NULL DEFAULT 5.0,
    -- Project counts
    `ml_projects`           TINYINT      NOT NULL DEFAULT 0,
    `end_to_end_projects`   TINYINT      NOT NULL DEFAULT 0,
    `deployed_projects`     TINYINT      NOT NULL DEFAULT 0,
    -- Competition & hackathon
    `kaggle_competitions`   TINYINT      NOT NULL DEFAULT 0,
    `best_competition_rank` SMALLINT     DEFAULT NULL,
    `hackathons_attended`   TINYINT      NOT NULL DEFAULT 0,
    `hackathons_won`        TINYINT      NOT NULL DEFAULT 0,
    `finalist_status`       TINYINT      NOT NULL DEFAULT 0,
    -- Career exposure
    `github_projects`       TINYINT      NOT NULL DEFAULT 0,
    `internship_months`     TINYINT      NOT NULL DEFAULT 0,
    `certifications`        TINYINT      NOT NULL DEFAULT 0,
    -- Interview readiness (0-10)
    `ml_interview_score`    DECIMAL(4,1) NOT NULL DEFAULT 5.0,
    `communication_score`   DECIMAL(4,1) NOT NULL DEFAULT 5.0,
    `dsa_score`             DECIMAL(4,1) NOT NULL DEFAULT 5.0,
    `resume_score`          DECIMAL(4,1) NOT NULL DEFAULT 5.0,
    `mock_interview_score`  DECIMAL(4,1) NOT NULL DEFAULT 5.0,
    -- Timestamps
    `created_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `registration` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Prediction Results Cache (stores last AI prediction per user)
-- ============================================================
CREATE TABLE `prediction_results` (
    `id`                        INT(11)      NOT NULL AUTO_INCREMENT,
    `user_id`                   INT(11)      NOT NULL UNIQUE,
    `job_ready`                 TINYINT(1)   NOT NULL DEFAULT 0,
    `job_ready_probability`     DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    `career_track`              TINYINT      NOT NULL DEFAULT 5,
    `career_track_label`        VARCHAR(100) NOT NULL DEFAULT '',
    `career_track_probabilities` JSON        DEFAULT NULL,
    `top_positive_factors`      JSON         DEFAULT NULL,
    `areas_to_improve`          JSON         DEFAULT NULL,
    `predicted_at`              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_pred_user` FOREIGN KEY (`user_id`) REFERENCES `registration` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
