<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>🔧 Diagnostik BeethovenPets</title>';
echo '<script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-100 p-6 font-sans">';
echo '<div class="max-w-3xl mx-auto space-y-4">';
echo '<h1 class="text-3xl font-bold text-slate-800 mb-6">🔧 Diagnostik Instalasi BeethovenPets</h1>';

$ok = '<span style="color: #10b981; font-weight:700;">✅ OK</span>';
$fail = '<span style="color: #ef4444; font-weight:700;">❌ GAGAL</span>';
$warn = '<span style="color: #f59e0b; font-weight:700;">⚠️ PERINGATAN</span>';

function test_step($label, $fn) {
    global $ok, $fail;
    echo '<div class="bg-white p-4 rounded-xl shadow flex items-center justify-between">';
    echo '<span class="font-semibold text-slate-700">' . htmlspecialchars($label) . '</span>';
    try {
        $result = $fn();
        echo $result[0] ? $ok : $fail;
        if (!empty($result[1])) echo '</div><div class="mt-1 pl-4 text-sm text-slate-600">' . $result[1];
    } catch (Throwable $e) {
        echo $fail;
        echo '</div><div class="mt-1 pl-4 text-sm text-red-600 bg-red-50 p-3 rounded-lg">Exception: ' . htmlspecialchars($e->getMessage());
    }
    echo '</div>';
}

// STEP 1: PHP Version
test_step('1. PHP Version (min 8.0)', function() {
    $v = PHP_VERSION;
    return [version_compare($v, '8.0.0', '>='), "Terinstall: PHP $v"];
});

// STEP 2: Required extensions
test_step('2. Extensi PHP: PDO + MySQLi + MBString', function() {
    $pdo = extension_loaded('pdo') && extension_loaded('pdo_mysql');
    $mb = extension_loaded('mbstring');
    $json = extension_loaded('json');
    $all = $pdo && $mb && $json;
    $info = [];
    $info[] = $pdo ? 'PDO-MySQL ✔' : 'PDO-MySQL ❌';
    $info[] = $mb ? 'MBString ✔' : 'MBString ❌';
    $info[] = $json ? 'JSON ✔' : 'JSON ❌';
    return [$all, implode(' · ', $info)];
});

// STEP 3: Load config.php
test_step('3. Load includes/config.php', function() {
    $path = __DIR__ . '/includes/config.php';
    $exists = file_exists($path);
    if (!$exists) return [false, "File tidak ditemukan: $path"];
    require_once $path;
    $constants = ['SITE_URL','DB_HOST','DB_NAME','WHATSAPP_NUMBER'];
    $def = true; $missing = [];
    foreach ($constants as $c) { if (!defined($c)) { $def = false; $missing[] = $c; } }
    $info = "SITE_URL = " . htmlspecialchars(SITE_URL ?? 'UNDEF') . " · DB = " . htmlspecialchars(DB_NAME ?? 'UNDEF');
    if ($missing) $info .= " · KURANG: " . implode(',',$missing);
    return [$def, $info];
});

// STEP 4: Load db.php + pawDB()
test_step('4. Load includes/db.php & koneksi DB', function() {
    require_once __DIR__ . '/includes/db.php';
    if (!function_exists('pawDB')) return [false, 'pawDB() tidak terdefinisi'];
    $db = pawDB();
    $pdo = $db->getConnection();
    $stmt = $pdo->query("SELECT 1");
    return [true, 'Terhubung ke MySQL dengan PDO · Server info: ' . htmlspecialchars($pdo->getAttribute(PDO::ATTR_SERVER_VERSION))];
});

// STEP 5: Cek tabel ada
test_step('5. Cek tabel utama di database', function() {
    $tables = ['bookings','customers','pets','tracking_logs','admins','addons','routes','site_settings'];
    $db = pawDB(); $pdo = $db->getConnection();
    $missing = []; $info = [];
    foreach ($tables as $t) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$t`");
            $n = (int)$stmt->fetchColumn();
            $info[] = "$t ($n)";
        } catch (Throwable $e) {
            $missing[] = $t;
            $info[] = "$t (TIDAK ADA)";
        }
    }
    $msg = implode(' · ', $info);
    if ($missing) $msg .= '<br><span style="color:#f59e0b">⚠️ Import dulu: <code>database/beethovenpets_schema.sql</code> via phpMyAdmin Laragon.</span>';
    return [empty($missing), $msg];
});

// STEP 6: Load functions.php
test_step('6. Load includes/functions.php', function() {
    require_once __DIR__ . '/includes/functions.php';
    $fns = ['calculateEstimate','bookingStatusLabel','formatRupiah','setFlash','redirect'];
    $missing = [];
    foreach ($fns as $f) if (!function_exists($f)) $missing[] = $f;
    $info = "Semua helper: " . (empty($missing) ? '✔' : 'KURANG: ' . implode(',',$missing));
    if (function_exists('calculateEstimate')) {
        $est = calculateEstimate(['travel_type'=>'domestic_air','pet_type_booking'=>'medium','pet_weight_kg'=>15]);
        $info .= " · Test harga: " . htmlspecialchars(formatRupiah($est['min'])) . " – " . htmlspecialchars(formatRupiah($est['max']));
    }
    return [empty($missing), $info];
});

// STEP 7: init.php chain (test sederhana)
test_step('7. Full init.php bootstrap', function() {
    global $_DEF_COUNT;
    // tidak require lagi, sudah ter-load dari step 3-6
    $fns_ok = function_exists('pawDB') && function_exists('calculateEstimate') && defined('SITE_URL');
    $info = $fns_ok ? "Bootstrap lengkap: pawDB ✔ calculateEstimate ✔ SITE_URL ✔" : "Ada fungsi/konstanta yang hilang";
    return [$fns_ok, $info];
});

// STEP 8: Folders writable
test_step('8. Folder uploads/ & logs/ writable', function() {
    $u = is_writable(__DIR__ . '/uploads');
    $l = is_writable(__DIR__ . '/logs');
    $info = "uploads = " . ($u ? '✔ writable' : '❌ chmod 755 needed') . " · logs = " . ($l ? '✔ writable' : '❌ chmod 755 needed');
    return [$u && $l, $info];
});

echo '<div class="flex gap-3 mt-8 pb-12">';
echo '<a href="index.php" class="px-5 py-3 rounded-full bg-emerald-600 text-white font-semibold hover:bg-emerald-700">🔙 Coba Buka Dashboard</a>';
echo '<a href="test_db.php" class="px-5 py-3 rounded-full bg-slate-700 text-white font-semibold hover:bg-slate-800">🧪 Test DB Lanjutan</a>';
echo '<a href="pets-sale.php" class="px-5 py-3 rounded-full bg-amber-500 text-white font-semibold hover:bg-amber-600">🐶 Pets on Sale</a>';
echo '</div></div></body></html>';
