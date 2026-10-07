<?php
/**
// * BeethovenPets — Environment Configuration
 * FOR cPanel / aaPanel SHARED HOSTING: Change values below to match your hosting!
 */

// ============================================================
// DATABASE — Edit these to match your cPanel/aaPanel MySQL credentials
// ============================================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'pet');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// SITE SETTINGS
// ============================================================
define('SITE_NAME', 'BeethovenPets — Premium Pet Relocation Concierge');
$_scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_sub = '';
if (isset($_SERVER['SCRIPT_NAME']) && is_string($_SERVER['SCRIPT_NAME'])) {
    $_scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($_scriptDir !== '.' && $_scriptDir !== '') $_sub = $_scriptDir;
}
define('SITE_URL', $_scheme . '://' . $_host . $_sub);
define('SITE_EMAIL', 'hello@beethovenpets.com');
define('WHATSAPP_NUMBER', '+6281212345678');
define('ADMIN_PATH', '/admin');
define('TIMEZONE', 'Asia/Jakarta');

date_default_timezone_set(TIMEZONE);
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

session_start();
ob_start();

// ============================================================
// UPLOAD PATHS — make sure these folders are writable (chmod 755)
// ============================================================
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('UPLOAD_URL', SITE_URL . '/uploads');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB
