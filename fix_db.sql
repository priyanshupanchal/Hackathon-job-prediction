-- ============================================================
-- FIX SCRIPT: Duplicate entry '0' for PRIMARY KEY
-- Run this in phpMyAdmin → SQL tab
-- ============================================================

USE `hackathon-employability-ml`;

-- Step 1: Delete any corrupt row with id = 0 (if it exists)
DELETE FROM `registration` WHERE `id` = 0;

-- Step 2: Ensure the id column has AUTO_INCREMENT properly set
ALTER TABLE `registration`
    MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

-- Step 3: Reset AUTO_INCREMENT to a safe value
ALTER TABLE `registration` AUTO_INCREMENT = 1;

-- Verify the fix
SELECT AUTO_INCREMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'hackathon-employability-ml'
  AND TABLE_NAME   = 'registration';
