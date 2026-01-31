<?php
/**
 * Global Constants Configuration File
 * Final Year Project Management System (FYPMS)
 */

/* =========================
   Database Configuration
   ========================= */
define('DB_HOST', 'localhost');
define('DB_NAME', 'fypms_db');
define('DB_USER', 'root');
define('DB_PASS', '');

/* =========================
   Base URL Configuration
   =========================
   Project Path:
   C:\xampp\htdocs\fypms\final-year-project-management-system\
*/
define('BASE_URL', 'http://localhost/fypms/final-year-project-management-system/');

/* =========================
   Application Settings
   ========================= */
define('APP_NAME', 'Final Year Project Management System');
define('APP_VERSION', '1.0.0');

/* =========================
   Session Configuration
   ========================= */
define('SESSION_NAME', 'fypms_session');

/* =========================
   Security Settings
   ========================= */
const PASSWORD_HASH_ALGO = PASSWORD_BCRYPT;
const PASSWORD_HASH_COST = 12;

/* =========================
   Timezone
   ========================= */
date_default_timezone_set('Africa/Addis_Ababa');

/* =========================
   Error Reporting (Development)
   ========================= */
error_reporting(E_ALL);
ini_set('display_errors', 1);
// File Upload Settings
// File Upload Settings
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_TYPES', 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/zip,application/x-zip-compressed');
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/fypms/final-year-project-management-system/uploads/');
define('UPLOAD_URL', 'http://localhost/fypms/final-year-project-management-system/uploads/');
// CSV Import Settings
define('MAX_CSV_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_CSV_TYPES', ['text/csv', 'text/plain', 'application/vnd.ms-excel']);
define('IMPORT_DIR', UPLOAD_DIR . 'imports/');
define('ARCHIVE_DIR', UPLOAD_DIR . 'archive/');
define('ARCHIVE_URL', UPLOAD_URL . 'archive/');
define('MAX_OCR_SIZE', 10 * 1024 * 1024); // 10MB for scanned documents