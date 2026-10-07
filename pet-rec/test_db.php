<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Test Koneksi Database BeethovenPets</h2>";

$host = 'localhost';
$dbname = 'pet';
$user = 'root';
$pass = '';

echo "<p>🔌 Mencoba konek ke MySQL: <strong>host=$host, db=$dbname, user=$user</strong></p>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "<p style='color:green;'>✅ BERHASIL: Terkoneksi ke database!</p>";

    $tables = ['bookings','customers','pets','tracking_logs','admins','addons','routes','site_settings'];
    echo "<h3>📋 Cek Tabel:</h3><ul>";
    foreach ($tables as $t) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$t`");
        $n = $stmt->fetchColumn();
        echo "<li>✅ <code>$t</code>: $n baris</li>";
    }
    echo "</ul>";

    echo "<p style='color:green;'>🎉 Database OK! <a href='index.php'>Klik sini buka situs</a></p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
}
