-- ============================================================
-- Migration: Add AI Readiness Score columns to student_details
-- Run this in phpMyAdmin → SQL tab  (safe — no data is deleted)
-- ============================================================

USE `hackathon-employability-ml`;

ALTER TABLE `student_details`
    ADD COLUMN IF NOT EXISTS `age`                   TINYINT      NOT NULL DEFAULT 22         AFTER `cv_filename`,
    ADD COLUMN IF NOT EXISTS `python_score`          DECIMAL(4,1) NOT NULL DEFAULT 5.0        AFTER `age`,
    ADD COLUMN IF NOT EXISTS `sql_score`             DECIMAL(4,1) NOT NULL DEFAULT 5.0        AFTER `python_score`,
    ADD COLUMN IF NOT EXISTS `statistics_score`      DECIMAL(4,1) NOT NULL DEFAULT 5.0        AFTER `sql_score`,
    ADD COLUMN IF NOT EXISTS `ml_score`              DECIMAL(4,1) NOT NULL DEFAULT 5.0        AFTER `statistics_score`,
    ADD COLUMN IF NOT EXISTS `dl_score`              DECIMAL(4,1) NOT NULL DEFAULT 5.0        AFTER `ml_score`,
    ADD COLUMN IF NOT EXISTS `genai_score`           DECIMAL(4,1) NOT NULL DEFAULT 5.0        AFTER `dl_score`,
    ADD COLUMN IF NOT EXISTS `ml_projects`           TINYINT      NOT NULL DEFAULT 0          AFTER `genai_score`,
    ADD COLUMN IF NOT EXISTS `end_to_end_projects`   TINYINT      NOT NULL DEFAULT 0          AFTER `ml_projects`,
    ADD COLUMN IF NOT EXISTS `deployed_projects`     TINYINT      NOT NULL DEFAULT 0          AFTER `end_to_end_projects`,
    ADD COLUMN IF NOT EXISTS `kaggle_competitions`   TINYINT      NOT NULL DEFAULT 0          AFTER `deployed_projects`,
    ADD COLUMN IF NOT EXISTS `best_competition_rank` SMALLINT     DEFAULT NULL                AFTER `kaggle_competitions`,
    ADD COLUMN IF NOT EXISTS `hackathons_attended`   TINYINT      NOT NULL DEFAULT 0          AFTER `best_competition_rank`,
    ADD COLUMN IF NOT EXISTS `hackathons_won`        TINYINT      NOT NULL DEFAULT 0          AFTER `hackathons_attended`,
    ADD COLUMN IF NOT EXISTS `finalist_status`       TINYINT      NOT NULL DEFAULT 0          AFTER `hackathons_won`,
    ADD COLUMN IF NOT EXISTS `github_projects`       TINYINT      NOT NULL DEFAULT 0          AFTER `finalist_status`,
    ADD COLUMN IF NOT EXISTS `internship_months`     TINYINT      NOT NULL DEFAULT 0          AFTER `github_projects`,
    ADD COLUMN IF NOT EXISTS `certifications`        TINYINT      NOT NULL DEFAULT 0          AFTER `internship_months`,
    ADD COLUMN IF NOT EXISTS `ml_interview_score`    DECIMAL(4,1) NOT NULL DEFAULT 5.0        AFTER `certifications`,
    ADD COLUMN IF NOT EXISTS `communication_score`   DECIMAL(4,1) NOT NULL DEFAULT 5.0        AFTER `ml_interview_score`,
    ADD COLUMN IF NOT EXISTS `dsa_score`             DECIMAL(4,1) NOT NULL DEFAULT 5.0        AFTER `communication_score`,
    ADD COLUMN IF NOT EXISTS `resume_score`          DECIMAL(4,1) NOT NULL DEFAULT 5.0        AFTER `dsa_score`,
    ADD COLUMN IF NOT EXISTS `mock_interview_score`  DECIMAL(4,1) NOT NULL DEFAULT 5.0        AFTER `resume_score`;

-- ============================================================
-- Create prediction_results cache table (safe — IF NOT EXISTS)
-- ============================================================
CREATE TABLE IF NOT EXISTS `prediction_results` (
    `id`                         INT(11)      NOT NULL AUTO_INCREMENT,
    `user_id`                    INT(11)      NOT NULL UNIQUE,
    `job_ready`                  TINYINT(1)   NOT NULL DEFAULT 0,
    `job_ready_probability`      DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    `career_track`               TINYINT      NOT NULL DEFAULT 5,
    `career_track_label`         VARCHAR(100) NOT NULL DEFAULT '',
    `career_track_probabilities` JSON         DEFAULT NULL,
    `top_positive_factors`       JSON         DEFAULT NULL,
    `areas_to_improve`           JSON         DEFAULT NULL,
    `predicted_at`               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_pred_user` FOREIGN KEY (`user_id`) REFERENCES `registration` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
