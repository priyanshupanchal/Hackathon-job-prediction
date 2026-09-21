-- ============================================================
-- FIX: Create missing student_details and prediction_results tables
-- Run this in phpMyAdmin → SQL tab (does NOT touch existing registration data)
-- ============================================================

USE `hackathon-employability-ml`;

-- ============================================================
-- Student Detail Form table (with AI Readiness Score columns)
-- ============================================================
CREATE TABLE IF NOT EXISTS `student_details` (
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
    -- AI Readiness Score Fields
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
CREATE TABLE IF NOT EXISTS `prediction_results` (
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

-- Verify tables were created
SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'hackathon-employability-ml'
ORDER BY TABLE_NAME;
