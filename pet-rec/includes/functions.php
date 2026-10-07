<?php
require_once __DIR__ . '/db.php';

/**
 * Helper Functions — Sitewide Utilities
 */

// ---------- FORMATTING ----------
function formatRupiah(float $amount): string {
    if ($amount >= 1000000) {
        $mil = round($amount / 1000000, 1);
        return 'Rp ' . number_format($mil, 1) . 'M';
    }
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function formatRupiahFull(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function formatDate(string $dateStr, string $format = 'D, M j, Y'): string {
    try {
        $d = new DateTime($dateStr);
        return $d->format($format);
    } catch (Exception $e) {
        return htmlspecialchars($dateStr);
    }
}

function formatDateTime(string $dateStr): string {
    try {
        $d = new DateTime($dateStr);
        return $d->format('M j, Y · g:i A');
    } catch (Exception $e) {
        return htmlspecialchars($dateStr);
    }
}

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function generateBookingCode(): string {
    return 'PV-' . date('Ymd') . '-' . str_pad(random_int(1, 999), 3, '0', STR_PAD_LEFT);
}

// ---------- PET / BOOKING HELPERS ----------
function petSizeLabel(string $code): string {
    $map = [
        'small' => 'Small Breed',
        'medium' => 'Medium Breed',
        'large' => 'Large Breed',
        'giant' => 'Giant Breed',
        'brachycephalic' => 'Brachycephalic',
    ];
    return $map[$code] ?? ucfirst($code);
}

function petSizeColor(string $code): string {
    $map = [
        'small' => '#4FB3A8',
        'medium' => '#0F2747',
        'large' => '#FF6B4A',
        'giant' => '#7C3AED',
        'brachycephalic' => '#D97706',
    ];
    return $map[$code] ?? '#666';
}

function travelTypeLabel(string $code): string {
    $map = [
        'domestic_air' => 'Domestic (Air)',
        'domestic_land' => 'Domestic (Land)',
        'international' => 'International',
        'combined' => 'Combined Multi-Modal',
    ];
    return $map[$code] ?? ucfirst(str_replace('_', ' ', $code));
}

function bookingStatusLabel(string $code): string {
    $map = [
        'inquiry' => 'New Inquiry',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'picked_up' => 'Picked Up',
        'origin_airport' => 'At Origin Airport',
        'in_flight' => 'In Flight',
        'transit' => 'Transit Hub',
        'in_transit' => 'In Transit',
        'destination_airport' => 'At Destination Airport',
        'out_for_delivery' => 'Out for Delivery',
        'arrived' => 'Arrived',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
    ];
    return $map[$code] ?? ucwords(str_replace('_', ' ', $code));
}

function bookingStatusColor(string $code): string {
    $map = [
        'inquiry' => '#3B82F6',
        'confirmed' => '#8B5CF6',
        'preparing' => '#F59E0B',
        'picked_up' => '#EC4899',
        'origin_airport' => '#14B8A6',
        'in_flight' => '#FF6B4A',
        'transit' => '#6366F1',
        'in_transit' => '#FF6B4A',
        'destination_airport' => '#4FB3A8',
        'out_for_delivery' => '#F97316',
        'arrived' => '#4FB3A8',
        'delivered' => '#10B981',
        'completed' => '#0F2747',
        'cancelled' => '#DC2626',
    ];
    return $map[$code] ?? '#666';
}

function paymentStatusLabel(string $code): string {
    $map = [
        'unpaid' => 'Unpaid',
        'deposit' => 'Deposit Paid',
        'paid' => 'Fully Paid',
        'refunded' => 'Refunded',
    ];
    return $map[$code] ?? ucfirst($code);
}

// ---------- PRICE CALCULATOR ----------
function calculateEstimate(array $data): array {
    try {
        $db = pawDB();
        $settings = [];
        $rows = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings WHERE setting_group = 'pricing'");
        foreach ($rows as $r) $settings[$r['setting_key']] = (float) $r['setting_value'];
    } catch (Throwable $e) {
        // Fallback multipliers if DB not connected
        $settings = [
            'price_weight_multiplier_small' => 1.0,
            'price_weight_multiplier_medium' => 1.4,
            'price_weight_multiplier_large' => 1.9,
            'price_weight_multiplier_giant' => 2.6,
            'price_weight_multiplier_brachy' => 1.8,
        ];
    }

    $travelType = $data['travel_type'] ?? 'domestic_air';
    $origin     = $data['origin_city'] ?? '';
    $destination= $data['destination_city'] ?? '';
    $petType    = $data['pet_type_booking'] ?? 'medium';
    $weight     = (float) ($data['pet_weight_kg'] ?? 15);
    $addonFlags = [
        'door' => !empty($data['addon_door']),
        'docs' => !empty($data['addon_docs']),
        'stay' => !empty($data['addon_stay']),
    ];

    $baseByType = [
        'domestic_land' => 1800000,
        'domestic_air'  => 3200000,
        'international' => 12500000,
        'combined'      => 7500000,
    ];
    $base = $baseByType[$travelType] ?? 3000000;

    if ($origin && $destination) {
        try {
            $db = pawDB();
            $match = $db->fetch(
                "SELECT base_price_idr FROM routes
                 WHERE is_active = 1
                   AND type = ?
                   AND (
                         (origin_city = ? AND destination_city = ?)
                      OR (origin_city = ? AND destination_city = ?)
                   )
                 ORDER BY (origin_city = ? AND destination_city = ?) DESC
                 LIMIT 1",
                [$travelType, $origin, $destination, $destination, $origin, $origin, $destination]
            );
            if ($match && !empty($match['base_price_idr'])) {
                $base = (float) $match['base_price_idr'];
            }
        } catch (Throwable $e) { /* use hardcoded fallback */ }
    }

    $sizeKey = "price_weight_multiplier_{$petType}";
    $sizeMultiplier = $settings[$sizeKey] ?? 1.4;

    $weightNormalizer = 0.6 + ($weight / 15) * 0.6;

    $addonCosts     = 0;
    $addonBreakdown = [];
    try {
        $db = pawDB();
        $dbAddons = $db->fetchAll("SELECT code, name, price_idr FROM addons WHERE is_active = 1");
    } catch (Throwable $e) { $dbAddons = []; }
    $defaultAddons = [
        'door' => ['name' => 'Door-to-Door Pickup & Delivery', 'price_idr' => 1200000],
        'docs' => ['name' => 'Vet & Travel Document Processing', 'price_idr' => 1800000],
        'stay' => ['name' => 'Transit Hotel / Boarding Stay', 'price_idr' => 950000],
    ];
    $lookup = [];
    foreach ($dbAddons as $a) $lookup[$a['code']] = $a;
    foreach (array_keys($addonFlags) as $code) {
        if (!$addonFlags[$code]) continue;
        $info = $lookup[$code] ?? $defaultAddons[$code];
        $price = (float) ($info['price_idr'] ?? 0);
        $addonCosts += $price;
        $addonBreakdown[$code] = ['name' => $info['name'] ?? $code, 'price' => $price];
    }

    $min = (int) round(($base * $sizeMultiplier * $weightNormalizer * 0.88) + $addonCosts, -5);
    $max = (int) round(($base * $sizeMultiplier * $weightNormalizer * 1.18) + $addonCosts, -5);

    return [
        'min' => $min,
        'max' => $max,
        'breakdown' => [
            'base_fare'        => $base,
            'size_multiplier'  => $sizeMultiplier,
            'weight_normalizer'=> round($weightNormalizer, 2),
            'addons_total'     => $addonCosts,
            'addons'           => $addonBreakdown,
        ],
    ];
}

// ---------- FLASH MESSAGES ----------
function setFlash(string $key, string $message, string $type = 'success'): void {
    $_SESSION['_flash'][$key] = ['message' => $message, 'type' => $type];
}

function getFlash(string $key) {
    if (!isset($_SESSION['_flash'][$key])) return null;
    $flash = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $flash;
}

function renderFlash(string $key): string {
    $flash = getFlash($key);
    if (!$flash) return '';
    $colors = [
        'success' => 'background:#D1FAE5;color:#065F46;border-color:#6EE7B7;',
        'error' => 'background:#FEE2E2;color:#991B1B;border-color:#FCA5A5;',
        'warning' => 'background:#FEF3C7;color:#92400E;border-color:#FCD34D;',
        'info' => 'background:#DBEAFE;color:#1E40AF;border-color:#93C5FD;',
    ];
    $style = $colors[$flash['type']] ?? $colors['info'];
    return '<div style="padding:16px 20px;border-radius:14px;border:1px solid;' . $style . 'font-weight:500;line-height:1.5;margin-bottom:20px;">' . e($flash['message']) . '</div>';
}

// ---------- AUTH HELPERS ----------
function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_id']);
}

function currentAdmin(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    try {
        return pawDB()->fetch("SELECT id, full_name, email, role, last_login FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
    } catch (Throwable $e) {
        return null;
    }
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: ' . SITE_URL . '/admin/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

// ---------- VALIDATION ----------
function isValidEmail(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function redirect(string $url, int $code = 302): void {
    header('Location: ' . $url, true, $code);
    exit;
}

// ---------- SITE SETTINGS ----------
function &_getSettingCacheRef(): array {
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = [];
            $rows = pawDB()->fetchAll("SELECT setting_key, setting_value FROM site_settings");
            foreach ($rows as $r) $cache[$r['setting_key']] = $r['setting_value'];
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}
function getSetting($key, $default = null) {
    if ($key === null) {
        // Flush cache (call with null)
        $c =& _getSettingCacheRef();
        $c = null;
        $c = _getSettingCacheRef();
        return null;
    }
    if (!is_string($key)) return $default;
    $cache =& _getSettingCacheRef();
    return $cache[$key] ?? $default;
}

// ---------- CONTACT INFO HELPERS (from DB settings, fallback to config defaults) ----------
function waNumber(): string {
    $v = (string) getSetting('whatsapp_number', '');
    if ($v === '' && defined('WHATSAPP_NUMBER')) $v = (string) WHATSAPP_NUMBER;
    return trim($v);
}
function waDigits(): string {
    return preg_replace('/\D+/', '', waNumber());
}
function wechatId(): string {
    $v = (string) getSetting('wechat_id', '');
    if ($v === '') $v = 'BeethovenPets_ID';
    return trim($v);
}
function telegramUsername(): string {
    $v = (string) getSetting('telegram_username', '');
    if ($v === '') $v = 'beethovenpets';
    return ltrim(trim($v), '@');
}
function telegramLink(): string {
    $u = telegramUsername();
    return $u ? 'https://t.me/' . $u : '#';
}

// ---------- LIVE DATE HELPER ----------
function liveDateBadge(): string {
    // Format: "Today · 12 OCT" (tanggal server live)
    return 'Today · ' . date('j M');
}
function activeJourneysCount(): int {
    // Generate variatif harian, antara 34-46
    $seed = (int) date('z') + 7;
    return 32 + ($seed % 15);
}

// ---------- SOCIAL MEDIA HELPERS ----------
function socialLink(string $name): string {
    $map = [
        'instagram' => ['key'=>'social_instagram','def'=>'https://instagram.com/beethovenpets'],
        'tiktok'    => ['key'=>'social_tiktok',   'def'=>'https://tiktok.com/@beethovenpets'],
        'facebook'  => ['key'=>'social_facebook', 'def'=>'https://facebook.com/beethovenpets'],
        'youtube'   => ['key'=>'social_youtube',  'def'=>'https://youtube.com/@beethovenpets'],
        'linkedin'  => ['key'=>'social_linkedin', 'def'=>'https://linkedin.com/company/beethovenpets'],
        'twitter'   => ['key'=>'social_twitter',  'def'=>'https://twitter.com/beethovenpets'],
        'whatsapp'  => ['key'=>null,               'def'=>'https://wa.me/'.waDigits()],
    ];
    if (!isset($map[$name])) return '#';
    if ($map[$name]['key'] === null) return $map[$name]['def'];
    $v = trim((string) getSetting($map[$name]['key'], ''));
    return $v !== '' ? $v : $map[$name]['def'];
}
function socialName(): string { return ''; }
