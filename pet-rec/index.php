<?php require_once __DIR__ . '/includes/init.php';

// ====== PREPARE CUSTOM LOGOS FROM ADMIN SETTINGS ======
$logosDir = __DIR__ . '/uploads/logos/';
$logoNavbarUrl = '';
$logoFooterUrl = '';
$_basePath = '';
if (isset($_SERVER['SCRIPT_NAME']) && is_string($_SERVER['SCRIPT_NAME'])) {
    $_d = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($_d !== '.' && $_d !== '') $_basePath = $_d;
}
$f = getSetting('logo_navbar','');
if ($f && is_file($logosDir . $f)) {
    $logoNavbarUrl = rtrim(SITE_URL,'/') . '/uploads/logos/' . rawurlencode($f);
    $logoNavbarUrlFallback = $_basePath . '/uploads/logos/' . rawurlencode($f);
}
$f = getSetting('logo_footer','');
if ($f && is_file($logosDir . $f)) {
    $logoFooterUrl = rtrim(SITE_URL,'/') . '/uploads/logos/' . rawurlencode($f);
    $logoFooterUrlFallback = $_basePath . '/uploads/logos/' . rawurlencode($f);
}

// Handle AJAX calculate estimate
if (!empty($_POST['action']) && $_POST['action'] === 'calculate_estimate') {
    header('Content-Type: application/json');
    $estimate = calculateEstimate($_POST);
    echo json_encode([
        'success' => true,
        'min_formatted' => formatRupiah($estimate['min']),
        'max_formatted' => formatRupiah($estimate['max']),
        'min_full' => formatRupiahFull($estimate['min']),
        'max_full' => formatRupiahFull($estimate['max']),
        'breakdown' => $estimate['breakdown'],
    ]);
    exit;
}

// Handle booking form submission
if (!empty($_POST['action']) && $_POST['action'] === 'submit_booking') {
    try {
        $db = pawDB();
        $conn = $db->getConnection();

        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $origin = trim($_POST['origin_city'] ?? '');
        $dest = trim($_POST['dest_city'] ?? '');
        $travelType = $_POST['travel_type'] ?? 'domestic_air';
        $travelDate = $_POST['travel_date'] ?? date('Y-m-d', strtotime('+7 days'));
        $petType = $_POST['pet_type_booking'] ?? 'medium';
        $petName = trim($_POST['pet_name'] ?? '');
        $petBreed = trim($_POST['pet_breed'] ?? '');
        $weight = (float)($_POST['pet_weight_kg'] ?? 15);
        $addonDoor = !empty($_POST['addon_door']) ? 1 : 0;
        $addonDocs = !empty($_POST['addon_docs']) ? 1 : 0;
        $addonStay = !empty($_POST['addon_stay']) ? 1 : 0;
        $notes = trim($_POST['special_instructions'] ?? '');

        if (!$fullName || !$email || !$phone || !$origin || !$dest) {
            setFlash('quote_form', 'Please complete all required fields before submitting.', 'error');
            redirect(SITE_URL . '/');
        }
        if (!isValidEmail($email)) {
            setFlash('quote_form', 'Please enter a valid email address.', 'error');
            redirect(SITE_URL . '/');
        }

        // 1. Upsert Customer
        $existingCustomer = $db->fetch("SELECT id FROM customers WHERE email = ?", [$email]);
        if ($existingCustomer) {
            $customerId = (int)$existingCustomer['id'];
            $db->execute("UPDATE customers SET full_name = ?, phone = ? WHERE id = ?", [$fullName, $phone, $customerId]);
        } else {
            $customerId = $db->execute(
                "INSERT INTO customers (full_name, email, phone) VALUES (?, ?, ?)",
                [$fullName, $email, $phone],
                true
            );
        }

        // 2. Create Pet
        $petId = $db->execute(
            "INSERT INTO pets (customer_id, name, breed, weight_kg, size_category) VALUES (?, ?, ?, ?, ?)",
            [$customerId, $petName ?: 'Unnamed Pet', $petBreed, $weight, $petType],
            true
        );

        // 3. Calculate Price
        $calc = calculateEstimate([
            'travel_type' => $travelType,
            'pet_type_booking' => $petType,
            'pet_weight_kg' => $weight,
            'addon_door' => $addonDoor,
            'addon_docs' => $addonDocs,
            'addon_stay' => $addonStay,
        ]);
        $estPrice = (int)round(($calc['min'] + $calc['max']) / 2, -5);

        // 4. Create Booking
        $bookingCode = generateBookingCode();
        $bookingId = $db->execute(
            "INSERT INTO bookings (booking_code, customer_id, pet_id, origin_city, destination_city, travel_type, travel_date,
                pet_type_booking, pet_weight_kg, addon_door, addon_docs, addon_stay, price_estimated_idr,
                special_instructions, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'inquiry')",
            [$bookingCode, $customerId, $petId, $origin, $dest, $travelType, $travelDate,
                $petType, $weight, $addonDoor, $addonDocs, $addonStay, $estPrice, $notes],
            true
        );

        // 5. Create initial tracking log
        $db->execute(
            "INSERT INTO tracking_logs (booking_id, status, title, description, location)
             VALUES (?, 'inquiry', 'Quote Request Received', ?, 'Online Quote Engine')",
            [$bookingId, "Customer {$fullName} requested estimate for {$origin} → {$dest}, {$weight}kg pet, {$travelType}."]
        );

        // 6. Try auto-email (silent fail — no SMTP dependency)
        @mail(
            $email,
            "[BeethovenPets] Your Quote Request {$bookingCode}",
            "Hello {$fullName},\n\nThank you for choosing BeethovenPets Premium Pet Relocation!\n\nYour booking reference: {$bookingCode}\nEstimated price range: " . formatRupiahFull($calc['min']) . " – " . formatRupiahFull($calc['max']) . "\n\nOur concierge team will contact you within 24 hours with a full personalized proposal.\n\nWarm regards,\nThe BeethovenPets Team\n" . waNumber(),
            "From: " . SITE_EMAIL . "\r\nReply-To: " . SITE_EMAIL . "\r\nContent-Type: text/plain; charset=UTF-8"
        );

        setFlash('quote_success', "✅ Quote submitted successfully! Your booking code is <strong>{$bookingCode}</strong>. We will contact you within 24 hours. Your estimated budget: " . formatRupiahFull($calc['min']) . " – " . formatRupiahFull($calc['max']) . ". Save this code to track your journey: <strong>{$bookingCode}</strong>", 'success');
        redirect(SITE_URL . '/booking-success.php?code=' . urlencode($bookingCode));

    } catch (Throwable $e) {
        error_log("Booking submission failed: " . $e->getMessage());
        setFlash('quote_form', 'Oops, something went wrong. Please try again or WhatsApp us at ' . waNumber() . '.', 'error');
        redirect(SITE_URL . '/');
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BeethovenPets — Premium Pet Relocation Concierge</title>
<meta name="description" content="Luxury door-to-door pet relocation. Domestic & international, vet documents, live tracking. Trusted by 500+ families.">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,300;0,400;0,500;0,600;0,700;0,900;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --cream:#FBF7F0; --cream-2:#F7F1E5; --cream-3:#EFE7D7; --navy:#0F2747; --navy-2:#172F52; --navy-3:#1E3A5F;
  --coral:#FF6B4A; --coral-2:#E55536; --coral-3:#FFB19E;
  --teal:#4FB3A8; --teal-soft:#E7F5F3; --ink:#14142B; --muted:#4A5462; --gold:#C9A962;
}
body { font-family:'Plus Jakarta Sans',sans-serif; background-color:var(--cream); color:var(--ink); }
.font-display { font-family:'Cormorant Garamond','Fraunces',serif; font-weight:500; letter-spacing:-0.01em; }
.font-editorial { font-family:'Fraunces',serif; }

/* Backgrounds */
.bg-cream { background-color:var(--cream); }
.bg-cream-2 { background-color:var(--cream-2); }
.bg-cream-3 { background-color:var(--cream-3); }
.bg-navy { background-color:var(--navy); }
.bg-coral { background-color:var(--coral); }
.bg-teal { background-color:var(--teal); }
.bg-gold { background-color:var(--gold); }
.bg-luxury {
  background-image:
    radial-gradient(ellipse 1000px 600px at 90% -10%, rgba(255,107,74,0.08), transparent 60%),
    radial-gradient(ellipse 800px 500px at -10% 30%, rgba(79,179,168,0.10), transparent 60%),
    radial-gradient(ellipse 900px 500px at 50% 120%, rgba(15,39,71,0.06), transparent 60%);
}

/* Text colors */
.text-navy { color:var(--navy); }
.text-coral { color:var(--coral); }
.text-teal { color:var(--teal); }
.text-gold { color:var(--gold); }
.text-muted { color:var(--muted); }
.text-cream { color:var(--cream); }

/* Buttons */
.btn { display:inline-flex; align-items:center; gap:10px; font-weight:600; transition:all 0.3s cubic-bezier(.2,.7,.3,1); border:0; cursor:pointer; }
.btn-primary { background:var(--coral); color:white; padding:16px 30px; border-radius:999px; box-shadow:0 10px 30px -10px rgba(255,107,74,0.6), 0 4px 12px rgba(255,107,74,0.25); }
.btn-primary:hover { background:var(--coral-2); transform:translateY(-2px); box-shadow:0 18px 45px -12px rgba(255,107,74,0.7), 0 6px 18px rgba(255,107,74,0.3); }
.btn-navy { background:var(--navy); color:var(--cream); padding:16px 30px; border-radius:999px; }
.btn-navy:hover { background:var(--navy-3); transform:translateY(-2px); }
.btn-outline { border:2px solid var(--navy); color:var(--navy); padding:14px 28px; border-radius:999px; background:transparent; }
.btn-outline:hover { background:var(--navy); color:var(--cream); transform:translateY(-2px); }
.btn-ghost-white { border:1.5px solid rgba(251,247,240,0.35); color:var(--cream); padding:14px 28px; border-radius:999px; }
.btn-ghost-white:hover { background:var(--cream); color:var(--navy); }

/* Cards */
.card-lux { background:white; border-radius:28px; box-shadow:0 10px 50px -20px rgba(15,39,71,0.18), 0 2px 8px rgba(15,39,71,0.04); border:1px solid rgba(239,231,215,0.6); }
.card-hover { transition:all 0.4s cubic-bezier(.2,.7,.3,1); }
.card-hover:hover { transform:translateY(-6px); box-shadow:0 30px 80px -25px rgba(15,39,71,0.25); }

/* Animations */
@keyframes float { 0%,100% { transform:translateY(0);} 50% { transform:translateY(-14px);} }
@keyframes floatSlow { 0%,100% { transform:translateY(0) rotate(-1deg);} 50% { transform:translateY(-10px) rotate(1.5deg);} }
@keyframes pulse { 0%,100% { opacity:1; transform:scale(1);} 50% { opacity:0.85; transform:scale(1.12);} }
@keyframes shimmer { 0% { background-position:-400px 0;} 100% { background-position:400px 0;} }
@keyframes pawDrift { 0%,100% { transform:translate(0,0) rotate(0deg); opacity:0.25;} 25% { transform:translate(8px,-12px) rotate(10deg); opacity:0.45;} 50% { transform:translate(0,-20px) rotate(-5deg); opacity:0.35;} 75% { transform:translate(-8px,-10px) rotate(-12deg); opacity:0.5;} }
@keyframes pawDrift2 { 0%,100% { transform:translate(0,0) rotate(0deg); opacity:0.22;} 33% { transform:translate(-10px,-16px) rotate(-8deg); opacity:0.4;} 66% { transform:translate(6px,-10px) rotate(12deg); opacity:0.3;} }
@keyframes tailWag { 0%,100% { transform:rotate(-12deg);} 50% { transform:rotate(12deg);} }
@keyframes sparkle { 0%,100% { opacity:0; transform:scale(0.6);} 50% { opacity:1; transform:scale(1);} }
@keyframes gentleSpin { 0% { transform:rotate(0deg);} 100% { transform:rotate(360deg);} }
.float { animation:float 5s ease-in-out infinite; }
.float-slow { animation:floatSlow 7s ease-in-out infinite; }
.pin-pulse { animation:pulse 2.4s ease-in-out infinite; }
.paw-drift { animation:pawDrift 8s ease-in-out infinite; }
.paw-drift-2 { animation:pawDrift2 9s ease-in-out infinite; }
.tail-wag { transform-origin:bottom left; animation:tailWag 0.8s ease-in-out infinite; }
.sparkle { animation:sparkle 2.2s ease-in-out infinite; }
.rotate-slow { animation:gentleSpin 30s linear infinite; }

/* Pet-themed decorative elements */
.pet-deco { position:absolute; pointer-events:none; z-index:1; opacity:0.55; }
.pet-deco svg { display:block; }
.paw-sm { width:28px; height:28px; }
.paw-md { width:42px; height:42px; }
.paw-lg { width:56px; height:56px; }
.bone-sm { width:36px; height:24px; }
.heart-pet { width:24px; height:24px; }
.sparkle-sm { width:18px; height:18px; }

/* Decorative */
.route-line { stroke:var(--coral); stroke-width:2; stroke-dasharray:6 6; fill:none; stroke-linecap:round; opacity:0.7; }
.divider-gold { height:1px; background:linear-gradient(90deg,transparent,rgba(201,169,98,0.55),transparent); }

/* Trust chip */
.trust-chip { display:inline-flex; align-items:center; gap:10px; padding:12px 20px; background:white; border-radius:999px; box-shadow:0 6px 24px -8px rgba(15,39,71,0.18); border:1px solid rgba(15,39,71,0.05); font-weight:600; font-size:14px; color:var(--navy); }

/* Quote engine inputs */
.field { display:flex; flex-direction:column; gap:8px; }
.label { font-size:13px; font-weight:700; color:var(--navy); display:flex; align-items:center; gap:6px; letter-spacing:0.01em; }
.input, .select {
  width:100%; padding:14px 18px; border-radius:16px; border:1.5px solid rgba(214,202,179,0.9);
  background:var(--cream); font-family:inherit; font-size:15px; color:var(--ink);
  transition:all 0.2s; appearance:none; cursor:pointer;
}
.input:focus, .select:focus { outline:none; border-color:var(--coral); background:white; box-shadow:0 0 0 5px rgba(255,107,74,0.10); }
select.select { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%230F2747' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 18px center; padding-right:46px; }

/* Premium ribbon/badge */
.ribbon-lux { display:inline-flex; align-items:center; gap:10px; padding:10px 20px; background:rgba(255,255,255,0.75); backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px); border:1px solid rgba(251,247,240,0.7); border-radius:999px; font-size:13px; font-weight:600; color:var(--navy); }
.ribbon-lux-on-dark { background:rgba(201,169,98,0.18); border:1.5px solid rgba(201,169,98,0.55); color:#FBF7F0; text-shadow:0 1px 2px rgba(0,0,0,0.25); font-weight:700; }
.ribbon-lux-on-dark .pin-pulse { background:#FFB19E !important; }

/* Stepper dots */
.step-dot { width:46px; height:46px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

/* Testimonial quote mark */
.quote-mark { font-family:'Fraunces',serif; font-size:90px; line-height:0.6; opacity:0.15; color:var(--navy); }

/* Chips/badges (CTA section) */
.chip { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:999px; font-size:12px; font-weight:700; letter-spacing:0.01em; }
.chip-teal { background:var(--teal-soft); color:var(--navy-3); border:1px solid rgba(79,179,168,0.25); }
.chip-coral { background:#FFEDE5; color:#C74A2C; border:1px solid rgba(255,107,74,0.25); }
.chip-gold { background:#FBF3DC; color:#8A6F2A; border:1px solid rgba(201,169,98,0.28); }

/* Range slider */
input[type=range] { accent-color:var(--coral); }

/* Hero image */
.hero-img-wrap { position:relative; border-radius:36px; overflow:hidden; box-shadow:0 40px 100px -30px rgba(15,39,71,0.35), 0 20px 50px -20px rgba(255,107,74,0.15); }
.hero-img-wrap::before { content:''; position:absolute; inset:0; background:linear-gradient(180deg, transparent 40%, rgba(15,39,71,0.35) 100%); z-index:1; }
.hero-img-wrap img { width:100%; height:100%; object-fit:cover; }

/* Booking pass tag */
.boarding-pass { background:linear-gradient(135deg, #FFFDF9 0%, #F7F1E5 100%); border:1.5px dashed var(--navy); border-radius:18px; position:relative; padding:16px 22px; }
.boarding-pass::after { content:''; position:absolute; right:28px; top:50%; transform:translateY(-50%); width:24px; height:24px; background:var(--cream); border-radius:50%; box-shadow:0 0 0 2px var(--navy); clip-path:circle(50% at 0% 50%); }

/* Nav link */
.nav-link { font-weight:500; color:var(--navy); position:relative; font-size:15px; }
.nav-link::after { content:''; position:absolute; left:0; bottom:-5px; width:0; height:2px; background:var(--coral); transition:width 0.3s; }
.nav-link:hover { color:var(--coral); }
.nav-link:hover::after { width:100%; }

/* Section eyebrow */
.eyebrow { display:inline-flex; align-items:center; gap:10px; padding:8px 16px; background:var(--teal-soft); border:1px solid rgba(79,179,168,0.25); border-radius:999px; font-size:12px; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--navy-3); }

/* Custom checkbox addon card */
.addon-card { display:flex; align-items:center; gap:14px; padding:16px; border-radius:18px; border:1.5px solid var(--cream-3); background:var(--cream); cursor:pointer; transition:all 0.2s; }
.addon-card:hover { border-color:rgba(255,107,74,0.4); background:white; transform:translateY(-2px); }
.addon-card.selected { border-color:var(--coral); background:white; box-shadow:0 6px 20px -6px rgba(255,107,74,0.3); }

/* Stat ring */
.stat-card { background:white; border-radius:24px; padding:32px 24px; border:1px solid var(--cream-3); text-align:center; transition:all 0.35s; }
.stat-card:hover { transform:translateY(-5px); box-shadow:0 20px 40px -10px rgba(15,39,71,0.15); border-color:rgba(255,107,74,0.2); }

/* Scroll anchor smooth */
html { scroll-behavior:smooth; }

/* Responsive tweaks */
@media (max-width: 768px) {
  .hero-img-wrap { border-radius:24px; }
  .ribbon-lux { padding:8px 14px; font-size:12px; }
}

/* === Mobile-specific fixes (400px viewport) === */
@media (max-width: 520px) {
  section { padding-top: 3rem !important; padding-bottom: 3rem !important; }
  .font-display.text-4xl, .font-display.text-5xl, .font-display.text-6xl,
  h2.font-display, h1.font-display { font-size: 32px !important; line-height: 1.12 !important; }
  .font-display.text-3xl { font-size: 26px !important; }
  .btn { padding: 13px 22px !important; font-size: 14px !important; gap: 8px !important; }
  .btn-primary, .btn-navy { min-height: 52px !important; }
  .btn-outline { padding: 12px 20px !important; }
  .stat-card { padding: 20px 14px !important; }
  .stat-card .font-display.text-4xl { font-size: 26px !important; }
  .trust-chip { padding: 10px 16px !important; font-size: 12px !important; gap: 8px !important; }
  .card-lux { padding: 22px !important; border-radius: 22px !important; }
  #gallery .grid-cols-2 { grid-template-columns: 1fr !important; }
  #gallery .grid-cols-2 > div { aspect-ratio: 4/3 !important; }
  footer .grid { grid-template-columns: 1fr !important; gap: 2.5rem !important; }
  footer .space-y-4 { gap: 0.75rem !important; }
  .eyebrow { font-size: 10px !important; padding: 6px 12px !important; letter-spacing: 0.1em !important; }
  .step-dot { width: 40px; height: 40px; }
  #quoteForm .grid { gap: 1rem !important; }
  .addon-card { padding: 12px !important; gap: 10px !important; }
  .pricePreview { padding: 16px !important; }
  #priceMin, #priceMax { font-size: 22px !important; }
}

/* New Pet-Themed Animations */
@keyframes kneadPaw { 0%,100% { transform:scale(1) rotate(0deg);} 25% { transform:scale(0.9) rotate(-5deg);} 50% { transform:scale(1.05) rotate(3deg);} 75% { transform:scale(0.92) rotate(-2deg);} }
@keyframes yarnRoll { 0% { transform:rotate(0deg) translateX(0);} 50% { transform:rotate(180deg) translateX(10px);} 100% { transform:rotate(360deg) translateX(0);} }
@keyframes fishSwim { 0%,100% { transform:translate(0,0) rotate(0deg); opacity:0.25;} 25% { transform:translate(12px,-8px) rotate(5deg); opacity:0.4;} 50% { transform:translate(0,-16px) rotate(-3deg); opacity:0.3;} 75% { transform:translate(-12px,-6px) rotate(8deg); opacity:0.45;} }
@keyframes catSit { 0%,100% { transform:translateY(0) scaleX(1);} 50% { transform:translateY(-6px) scaleX(0.98);} }
@keyframes petSilhouetteDrift { 0%,100% { transform:translate(0,0) rotate(-2deg); opacity:0.18;} 50% { transform:translate(10px,-18px) rotate(4deg); opacity:0.28;} }
@keyframes tinyBounce { 0%,100% { transform:translateY(0);} 50% { transform:translateY(-6px);} }
@keyframes sparkleBurst { 0%,100% { opacity:0; transform:scale(0.4) rotate(0deg);} 30% { opacity:1; transform:scale(1.1) rotate(90deg);} 60% { opacity:0.7; transform:scale(0.8) rotate(180deg);} }
@keyframes treatDrop { 0% { transform:translateY(-30px) rotate(-20deg); opacity:0;} 20% { opacity:1;} 80% { transform:translateY(10px) rotate(15deg); opacity:1;} 100% { transform:translateY(0) rotate(0deg); opacity:0.8;} }
.knead-paw { animation:kneadPaw 3.2s ease-in-out infinite; }
.yarn-roll { animation:yarnRoll 10s linear infinite; }
.fish-swim { animation:fishSwim 9s ease-in-out infinite; }
.cat-sit { animation:catSit 4s ease-in-out infinite; }
.pet-silhouette { animation:petSilhouetteDrift 11s ease-in-out infinite; }
.tiny-bounce { animation:tinyBounce 2.8s ease-in-out infinite; }
.sparkle-burst { animation:sparkleBurst 2.6s ease-in-out infinite; }
.treat-drop { animation:treatDrop 5s ease-out infinite; }

/* Pet decorative elements - additional sizes */
.yarn-sm { width:30px; height:30px; }
.yarn-md { width:44px; height:44px; }
.fish-sm { width:32px; height:20px; }
.fish-md { width:50px; height:30px; }
.cat-sil { width:38px; height:46px; }
.dog-sil { width:42px; height:40px; }
.treat-sm { width:22px; height:22px; }

/* ===== Hero Carousel Slider ===== */
.hero-carousel { position:relative; border-radius:36px; overflow:hidden; box-shadow:0 40px 100px -30px rgba(15,39,71,0.35), 0 20px 50px -20px rgba(255,107,74,0.15); aspect-ratio:4/5; }
.hero-carousel::before { content:''; position:absolute; inset:0; background:linear-gradient(180deg, transparent 40%, rgba(15,39,71,0.35) 100%); z-index:2; }
.hero-track { display:flex; height:100%; transition:transform 0.9s cubic-bezier(.2,.7,.3,1); }
.hero-slide { min-width:100%; height:100%; position:relative; }
.hero-slide img { width:100%; height:100%; object-fit:cover; display:block; }
.hero-nav { position:absolute; top:50%; transform:translateY(-50%); width:48px; height:48px; border-radius:50%; background:rgba(251,247,240,0.9); backdrop-filter:blur(10px); border:1px solid rgba(15,39,71,0.08); display:flex; align-items:center; justify-content:center; cursor:pointer; z-index:5; transition:all 0.3s; box-shadow:0 8px 24px -6px rgba(15,39,71,0.25); }
.hero-nav:hover { background:var(--coral); color:white; transform:translateY(-50%) scale(1.08); }
.hero-nav.prev { left:16px; }
.hero-nav.next { right:16px; }
.hero-nav svg { width:20px; height:20px; stroke-width:2.5; }
.hero-dots { position:absolute; bottom:18px; left:50%; transform:translateX(-50%); display:flex; gap:8px; z-index:5; }
.hero-dot { width:10px; height:10px; border-radius:50%; background:rgba(251,247,240,0.45); cursor:pointer; transition:all 0.35s; }
.hero-dot.active { width:28px; border-radius:999px; background:white; box-shadow:0 0 0 3px rgba(255,107,74,0.3); }
.hero-slide-badge { position:absolute; top:20px; left:20px; z-index:4; display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border-radius:999px; background:rgba(251,247,240,0.9); backdrop-filter:blur(10px); font-size:12px; font-weight:800; color:var(--navy); letter-spacing:0.04em; box-shadow:0 6px 20px -6px rgba(15,39,71,0.25); }
.hero-slide-caption { position:absolute; bottom:20px; left:20px; right:20px; z-index:4; padding:16px 20px; border-radius:20px; background:rgba(251,247,240,0.88); backdrop-filter:blur(12px); box-shadow:0 12px 36px -12px rgba(15,39,71,0.25); }
.hero-slide-caption h4 { font-family:'Cormorant Garamond',serif; font-size:20px; font-weight:600; color:var(--navy); margin-bottom:4px; line-height:1.15; }
.hero-slide-caption p { font-size:12px; color:var(--muted); font-weight:600; margin:0; }
@media (max-width: 768px) {
  .hero-carousel { border-radius:24px; }
  .hero-nav { width:40px; height:40px; }
  .hero-dots { bottom:14px; }
  .hero-slide-caption { padding:12px 14px; bottom:14px; left:14px; right:14px; }
  .hero-slide-caption h4 { font-size:16px; }
}

/* ===== Animated Number Counters ===== */
.stat-num { display:inline-block; font-variant-numeric: tabular-nums; }
.stat-card .suffix { display:inline-block; }

/* Gallery grid: image loading fallback */
.grid-img-wrap { position:relative; width:100%; height:100%; background:linear-gradient(135deg, var(--cream-3), var(--cream-2)); overflow:hidden; }
.grid-img-wrap::after { content:''; position:absolute; inset:0; background:linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.4) 50%, transparent 100%); animation:shimmer 1.6s linear infinite; background-size:400px 100%; z-index:1; }
.grid-img-wrap img.loaded { position:relative; z-index:2; }
.grid-img-wrap img.loaded + * { display:none; }

/* Floating Social Icons */
.floating-social {
  position: fixed;
  right: 24px;
  bottom: 24px;
  z-index: 80;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.float-btn {
  width: 58px;
  height: 58px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 12px 32px -8px rgba(15,39,71,0.35), 0 6px 18px rgba(15,39,71,0.18);
  transition: all 0.35s cubic-bezier(.2,.7,.3,1);
  cursor: pointer;
  position: relative;
}
.float-btn:hover { transform: translateY(-4px) scale(1.06); }
.float-btn::after {
  content: attr(data-label);
  position: absolute;
  right: 72px;
  top: 50%;
  transform: translateY(-50%);
  white-space: nowrap;
  font-size: 12px;
  font-weight: 700;
  padding: 8px 14px;
  border-radius: 999px;
  background: #0F2747;
  color: #FBF7F0;
  opacity: 0;
  pointer-events: none;
  transition: all 0.3s;
  box-shadow: 0 6px 20px -6px rgba(15,39,71,0.4);
}
.float-btn:hover::after { opacity: 1; right: 80px; }
.float-btn svg { width: 28px; height: 28px; }
@media (max-width: 640px) {
  .floating-social { right: 14px; bottom: 14px; gap: 10px; }
  .float-btn { width: 52px; height: 52px; }
  .float-btn::after { display: none; }
}
</style>
</head>
<body class="bg-luxury">

<!-- ===== NAVIGATION ===== -->
<nav class="sticky top-0 z-50 border-b border-[#EFE7D7]/70 bg-[rgba(251,247,240,0.85)] backdrop-blur-xl">
  <div class="max-w-7xl mx-auto px-6 lg:px-10">
    <div class="flex items-center justify-between h-24">
      <a href="<?= SITE_URL ?>/" class="flex items-center gap-4 group">
        <!-- CUSTOM NAVBAR LOGO (if uploaded in admin) OR default SVG reference logo -->
        <div id="navbarLogoBox" class="w-[80px] h-[80px] sm:w-[88px] sm:h-[88px] rounded-[22px] bg-white flex items-center justify-center transition-all group-hover:scale-105 shadow-lg shadow-navy/10 relative overflow-hidden border border-cream-3">
          <?php if (!empty($logoNavbarUrl)): ?>
            <img id="navbarLogoImg"
                 src="<?=e($logoNavbarUrl)?>"
                 data-fallback-url="<?=e($logoNavbarUrlFallback ?? '')?>"
                 data-fallback-inline='<?= json_encode('<svg viewBox="0 0 220 200" width="78" height="72" xmlns="http://www.w3.org/2000/svg"><circle cx="110" cy="85" r="62" fill="#20A3C4"/><path d="M70 48 Q 62 38 76 32 Q 94 26 112 32 Q 136 28 150 42 Q 162 58 156 72 L 142 76 Q 138 88 122 90 L 116 86 Q 108 96 94 94 Q 80 92 72 84 Z" fill="white"/><path d="M30 160 Q 8 108 40 52 Q 70 8 120 20" fill="none" stroke="#0F3F73" stroke-width="16" stroke-linecap="round"/></svg>') ?>'
                 alt="BeethovenPets Logo"
                 class="max-w-[94%] max-h-[94%] w-auto h-auto object-contain block"
            >
          <?php else: ?>
          <svg viewBox="0 0 220 200" width="80" height="72" xmlns="http://www.w3.org/2000/svg">
            <!-- ===== OUTER ORBIT SWOOSH (teal light, bottom left) ===== -->
            <path d="M45 155 Q 5 110 30 65 Q 50 28 95 20" fill="none" stroke="#25A7C4" stroke-width="14" stroke-linecap="round"/>
            <!-- ===== OUTER ORBIT SWOOSH (dark navy, bottom right) ===== -->
            <path d="M60 168 Q 120 195 185 155 Q 215 118 205 70 Q 198 38 170 22" fill="none" stroke="#0F3F73" stroke-width="14" stroke-linecap="round"/>
            <!-- ===== GLOBE (teal solid with white continents) ===== -->
            <circle cx="110" cy="85" r="62" fill="#20A3C4"/>
            <!-- White continent: Greenland / Canada top -->
            <path d="M68 48 Q 62 38 72 32 Q 84 25 94 28 Q 104 24 112 30 Q 120 28 128 36 Q 136 30 145 38 Q 154 44 156 56 Q 162 52 165 62 Q 160 68 155 70 L 148 66 Q 145 78 138 80 Q 135 86 128 88 L 125 84 Q 118 94 110 96 Q 100 94 98 88 Q 88 92 82 86 Q 78 90 72 84 Q 68 76 68 68 Q 60 66 60 58 Z" fill="#FFFFFF"/>
            <!-- White continent: Africa / Middle East -->
            <path d="M118 96 Q 124 100 122 108 Q 126 116 120 122 Q 118 130 112 134 Q 108 140 102 136 L 98 130 Q 94 126 96 120 Q 92 114 96 108 Q 100 104 106 104 Q 110 100 118 96 Z" fill="#FFFFFF" opacity="0.92"/>
            <!-- White continent: South America bottom -->
            <path d="M76 130 Q 70 136 74 144 Q 80 148 84 146 Q 90 148 90 140 Z" fill="#FFFFFF" opacity="0.88"/>
            <!-- ===== AIRPLANE (navy top-right) ===== -->
            <g transform="translate(165 42) rotate(18)">
              <path d="M-2 6 L 28 -2 L 32 2 L 20 10 L 30 14 L 30 17 L 20 17 L 22 24 L 18 26 L 10 20 L -2 22 Z" fill="#0F3F73"/>
            </g>
            <!-- ===== PET CRATE (dark navy, rounded) ===== -->
            <g transform="translate(48 88)">
              <!-- Outer crate shell -->
              <path d="M8 44 Q 4 44 4 38 L 2 18 Q 2 10 12 8 L 28 4 Q 40 2 52 6 Q 80 6 100 14 Q 114 20 118 32 Q 122 42 116 48 Q 110 54 98 56 L 20 56 Q 8 56 8 44 Z" fill="#0F3F73"/>
              <!-- Inner darker crate lining -->
              <path d="M14 42 Q 10 42 10 36 L 8 18 Q 8 14 16 12 L 32 8 Q 42 7 52 10 Q 76 10 94 18 Q 108 24 110 32 Q 114 40 110 46 Q 104 50 96 52 L 22 52 Q 14 52 14 42 Z" fill="#0A2C52"/>
              <!-- Left 3 vertical ventilation slots -->
              <rect x="18" y="22" width="5" height="20" rx="1.5" fill="#1E6FAE"/>
              <rect x="26" y="22" width="5" height="20" rx="1.5" fill="#1E6FAE"/>
              <rect x="34" y="22" width="5" height="20" rx="1.5" fill="#1E6FAE"/>
            </g>
            <!-- ===== GOLDEN RETRIEVER (upper body, left side of crate opening) ===== -->
            <g transform="translate(72 102)">
              <!-- Chest / body -->
              <path d="M4 46 L 4 30 Q 2 20 10 14 L 14 10 Q 16 2 24 2 Q 30 0 36 4 L 44 8 Q 54 14 56 24 L 58 32 L 58 46 Z" fill="#FBB03B"/>
              <!-- Lighter chest highlight -->
              <path d="M14 46 L 16 32 Q 20 22 30 20 L 36 24 L 34 46 Z" fill="#F9C56B"/>
              <!-- Neck fur lines -->
              <path d="M34 24 Q 40 30 40 40" fill="none" stroke="#E39219" stroke-width="1.2" stroke-linecap="round" opacity="0.75"/>
              <!-- Head -->
              <circle cx="40" cy="18" r="12" fill="#FBB03B"/>
              <!-- Snout -->
              <ellipse cx="45.5" cy="21" rx="6" ry="5.2" fill="#F9C56B"/>
              <!-- Left floppy ear -->
              <ellipse cx="30" cy="12" rx="4.8" ry="8" fill="#E39219" transform="rotate(-18 30 12)"/>
              <!-- Right floppy ear -->
              <ellipse cx="49" cy="10" rx="4.5" ry="7.5" fill="#E39219" transform="rotate(14 49 10)"/>
              <!-- Eyes -->
              <circle cx="37" cy="16" r="1.4" fill="#0A2C52"/>
              <circle cx="44.5" cy="15" r="1.4" fill="#0A2C52"/>
              <!-- Eye highlights -->
              <circle cx="37.4" cy="15.6" r="0.45" fill="#FFFFFF"/>
              <circle cx="44.9" cy="14.6" r="0.45" fill="#FFFFFF"/>
              <!-- Nose -->
              <ellipse cx="49.2" cy="20" rx="2" ry="1.6" fill="#0A2C52"/>
              <!-- Smile mouth -->
              <path d="M45 23 Q 48 26.5 51.5 23.5" fill="none" stroke="#0A2C52" stroke-width="1.1" stroke-linecap="round"/>
              <!-- Pink tongue -->
              <path d="M47.2 24.5 Q 48.5 27.5 49.8 24.8 Q 49 25.8 47.2 24.5 Z" fill="#FF6B7F"/>
            </g>
            <!-- ===== CAT (white w/ navy patch, right of dog, inside crate) ===== -->
            <g transform="translate(126 112)">
              <!-- Body -->
              <path d="M4 40 L 4 22 Q 2 12 12 8 Q 18 4 28 4 Q 34 8 34 14 L 34 40 Z" fill="#FFFFFF"/>
              <!-- Head -->
              <circle cx="20" cy="12" r="11" fill="#FFFFFF"/>
              <!-- Navy patch: left side of head + ear + back -->
              <path d="M12 14 Q 10 8 14 4 L 16 0 L 14 8 Q 16 12 16 18 Q 14 22 12 20 Q 10 18 12 14 Z" fill="#0A2C52"/>
              <!-- Back navy patch -->
              <path d="M4 22 Q 2 18 6 16 Q 12 14 14 20 Q 12 28 6 28 Q 3 26 4 22 Z" fill="#0A2C52" opacity="0.92"/>
              <!-- Left ear (pointed triangle) -->
              <path d="M11 4 L 8 -2 L 14 1 Z" fill="#FFFFFF"/>
              <path d="M11 4 L 8 -2 L 14 1 Z" fill="none" stroke="#0A2C52" stroke-width="0.8"/>
              <!-- Right ear (pointed triangle) -->
              <path d="M29 4 L 32 -2 L 26 1 Z" fill="#FFFFFF"/>
              <path d="M29 4 L 32 -2 L 26 1 Z" fill="none" stroke="#0A2C52" stroke-width="0.8"/>
              <!-- Eyes (left = small dot inside navy patch) -->
              <circle cx="15.5" cy="12.5" r="1.2" fill="#FFFFFF"/>
              <circle cx="24.5" cy="12" r="1.25" fill="#0A2C52"/>
              <circle cx="24.9" cy="11.6" r="0.4" fill="#FFFFFF"/>
              <!-- Nose -->
              <path d="M19 16 L 21.5 16 L 20.2 17.5 Z" fill="#FFB19E"/>
              <!-- Smile mouth -->
              <path d="M18.5 18 Q 20.2 20 22 18" fill="none" stroke="#0A2C52" stroke-width="0.9" stroke-linecap="round"/>
              <!-- Whiskers: 3 left + 3 right -->
              <line x1="6" y1="17" x2="13" y2="17.5" stroke="#0A2C52" stroke-width="0.55" stroke-linecap="round"/>
              <line x1="6.5" y1="19" x2="13" y2="18.8" stroke="#0A2C52" stroke-width="0.55" stroke-linecap="round"/>
              <line x1="7" y1="21" x2="13" y2="20" stroke="#0A2C52" stroke-width="0.55" stroke-linecap="round"/>
              <line x1="28" y1="17.5" x2="34.5" y2="17" stroke="#0A2C52" stroke-width="0.55" stroke-linecap="round"/>
              <line x1="28" y1="18.8" x2="34.5" y2="19" stroke="#0A2C52" stroke-width="0.55" stroke-linecap="round"/>
              <line x1="28" y1="20" x2="34.5" y2="21" stroke="#0A2C52" stroke-width="0.55" stroke-linecap="round"/>
            </g>
          </svg>
          <?php endif; ?>
        </div>
        <div>
          <div class="flex items-baseline gap-0">
            <span class="font-black text-[34px] sm:text-[38px] leading-none tracking-[-0.02em]" style="color:#0F3F73;font-family:'Plus Jakarta Sans',system-ui,sans-serif;">beethoven</span><span class="font-black text-[34px] sm:text-[38px] leading-none tracking-[-0.02em]" style="color:#FF9B2A;font-family:'Plus Jakarta Sans',system-ui,sans-serif;">pets</span>
          </div>
        </div>
      </a>

      <div class="hidden lg:flex items-center gap-10">
        <a href="#services" class="nav-link">Services</a>
        <a href="#gallery" class="nav-link">Gallery</a>
        <a href="pets-sale.php" class="nav-link">Pets on Sale</a>
        <a href="#stories" class="nav-link">Stories</a>
        <a href="#faq" class="nav-link">FAQ</a>
      </div>

      <div class="hidden lg:flex items-center gap-4">
        <a href="#gallery" class="btn-outline text-sm">Gallery</a>
        <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%20would%20like%20to%20inquire%20about%20pet%20relocation%20services." target="_blank" class="btn btn-primary text-sm">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
          Submit Quote
        </a>
      </div>

      <button id="menuBtn" class="lg:hidden w-11 h-11 rounded-xl border border-cream-3 flex items-center justify-center text-navy">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
    </div>

    <div id="mobileMenu" class="hidden lg:hidden border-t border-cream-3 py-5 flex flex-col gap-3">
      <a href="#services" class="nav-link py-2">Services</a>
      <a href="#gallery" class="nav-link py-2">Gallery</a>
      <a href="pets-sale.php" class="nav-link py-2">Pets on Sale</a>
      <a href="#stories" class="nav-link py-2">Stories</a>
      <a href="#faq" class="nav-link py-2">FAQ</a>
      <div class="flex flex-col gap-3 pt-3 border-t border-cream-3">
        <a href="#gallery" class="btn-outline text-sm justify-center">Gallery</a>
        <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%20would%20like%20to%20inquire%20about%20pet%20relocation%20services." target="_blank" class="btn btn-primary text-sm justify-center">Submit Quote</a>
      </div>
    </div>
  </div>
</nav>

<!-- ===== HERO ===== -->
<section class="relative py-14 pb-40 lg:py-24 lg:pb-56 overflow-visible">
  <!-- decorative SVGs -->
  <svg class="absolute top-32 left-6 hidden xl:block opacity-50" width="260" height="140" viewBox="0 0 260 140">
    <path class="route-line" d="M12,95 Q60,15 130,55 T250,35"/>
    <circle cx="12" cy="95" r="5" fill="#0F2747"/>
    <circle cx="250" cy="35" r="5" fill="#FF6B4A"/>
  </svg>
  <svg class="absolute bottom-52 right-0 hidden xl:block opacity-45" width="300" height="190" viewBox="0 0 300 190">
    <path class="route-line" d="M20,160 Q90,25 180,85 T285,55"/>
    <circle cx="20" cy="160" r="4.5" fill="#4FB3A8"/>
    <circle cx="180" cy="85" r="4.5" fill="#C9A962"/>
    <circle cx="285" cy="55" r="4.5" fill="#FF6B4A"/>
  </svg>

  <!-- Pet decorative: floating paws + sparkles + silhouettes + yarn + fish -->
  <div class="pet-deco paw-drift paw-lg" style="top:12%;left:3%;">
    <svg viewBox="0 0 24 24" fill="#FFB19E"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco paw-drift-2 paw-md" style="top:22%;right:6%;">
    <svg viewBox="0 0 24 24" fill="#4FB3A8"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco sparkle-sm sparkle" style="top:38%;left:8%;animation-delay:0.4s;">
    <svg viewBox="0 0 24 24" fill="#C9A962"><polygon points="12,2 14,10 22,10 16,15 18,23 12,18 6,23 8,15 2,10 10,10"/></svg>
  </div>
  <div class="pet-deco sparkle-sm sparkle" style="top:48%;right:10%;animation-delay:1.1s;">
    <svg viewBox="0 0 24 24" fill="#FF6B4A"><polygon points="12,2 14,10 22,10 16,15 18,23 12,18 6,23 8,15 2,10 10,10"/></svg>
  </div>
  <!-- Yarn ball + fish for cat lovers -->
  <div class="pet-deco yarn-md yarn-roll" style="bottom:30%;left:1%;opacity:0.42;animation-duration:13s;">
    <svg viewBox="0 0 24 24" fill="#C9A962"><circle cx="12" cy="12" r="9" fill="none" stroke="#C9A962" stroke-width="2"/><path d="M4 12 Q12 4 20 12 Q12 20 4 12" fill="none" stroke="#C9A962" stroke-width="1.8" stroke-linecap="round"/><path d="M12 3 Q12 12 12 21" fill="none" stroke="#C9A962" stroke-width="1.5" stroke-linecap="round"/><path d="M6 7 Q12 12 18 17" fill="none" stroke="#C9A962" stroke-width="1.3" stroke-linecap="round"/></svg>
  </div>
  <div class="pet-deco yarn-sm yarn-roll" style="top:65%;right:1%;opacity:0.38;animation-direction:reverse;animation-duration:17s;">
    <svg viewBox="0 0 24 24" fill="#4FB3A8"><circle cx="12" cy="12" r="9" fill="none" stroke="#4FB3A8" stroke-width="2"/><path d="M4 12 Q12 4 20 12 Q12 20 4 12" fill="none" stroke="#4FB3A8" stroke-width="1.7" stroke-linecap="round"/><path d="M12 3 Q12 12 12 21" fill="none" stroke="#4FB3A8" stroke-width="1.4" stroke-linecap="round"/></svg>
  </div>
  <div class="pet-deco fish-md fish-swim" style="bottom:22%;right:18%;animation-delay:0.6s;">
    <svg viewBox="0 0 24 14" fill="rgba(255,107,74,0.38)"><path d="M16 7 Q11 2 5 4 Q2 5 2 7 Q2 9 5 10 Q11 12 16 7Z"/><path d="M17 7 L22 2 L22 12 Z" fill="rgba(255,107,74,0.38)"/><circle cx="7.5" cy="6.8" r="1.2" fill="#0F2747" opacity="0.5"/></svg>
  </div>
  <div class="pet-deco fish-sm fish-swim" style="top:58%;left:16%;animation-delay:1.8s;animation-duration:10s;">
    <svg viewBox="0 0 24 14" fill="rgba(79,179,168,0.4)"><path d="M16 7 Q11 2 5 4 Q2 5 2 7 Q2 9 5 10 Q11 12 16 7Z"/><path d="M17 7 L22 2 L22 12 Z" fill="rgba(79,179,168,0.4)"/><circle cx="7.5" cy="6.8" r="1" fill="#0F2747" opacity="0.4"/></svg>
  </div>
  <!-- Cat silhouette (subtle) -->
  <div class="pet-deco cat-sil pet-silhouette" style="top:6%;left:22%;">
    <svg viewBox="0 0 24 30" fill="#0F2747"><path d="M4 10 L6 2 L10 7 L14 7 L18 2 L20 10 L22 20 L20 26 L4 26 L2 20 Z" opacity="0.06"/><circle cx="9.5" cy="16" r="1.1" fill="#0F2747" opacity="0.25"/><circle cx="14.5" cy="16" r="1.1" fill="#0F2747" opacity="0.25"/></svg>
  </div>
  <!-- Dog silhouette (subtle) -->
  <div class="pet-deco dog-sil pet-silhouette" style="bottom:10%;right:24%;animation-delay:1.2s;">
    <svg viewBox="0 0 30 28" fill="#FF6B4A"><path d="M5 10 Q2 5 6 4 Q10 3 12 6 L18 6 Q20 3 24 4 Q28 5 25 10 L26 16 L24 22 L20 24 L15 22 L10 24 L6 22 L4 16 Z" opacity="0.06"/><circle cx="11" cy="13" r="1" fill="#0F2747" opacity="0.2"/><circle cx="19" cy="13" r="1" fill="#0F2747" opacity="0.2"/><ellipse cx="15" cy="17" rx="1.3" ry="0.9" fill="#0F2747" opacity="0.15"/></svg>
  </div>
  <!-- Dropping treats -->
  <div class="pet-deco treat-sm treat-drop" style="top:4%;left:40%;animation-delay:0.7s;">
    <svg viewBox="0 0 24 24" fill="#FF6B4A"><circle cx="12" cy="12" r="4" opacity="0.45"/></svg>
  </div>
  <div class="pet-deco treat-sm treat-drop" style="top:72%;right:42%;animation-delay:1.9s;">
    <svg viewBox="0 0 24 24" fill="#C9A962"><path d="M12 3 L14 10 L21 12 L14 14 L12 21 L10 14 L3 12 L10 10 Z" opacity="0.5"/></svg>
  </div>
  <!-- Bone slow rotate -->
  <div class="pet-deco bone-sm rotate-slow" style="top:78%;left:32%;opacity:0.25;">
    <svg viewBox="0 0 24 24" fill="#C9A962"><path d="M4.7 11.3c-.5-.5-.5-1.2 0-1.7L8 6.3C7.8 5.6 8.3 5 9 5c.3 0 .6.1.8.3l1.8 1.1 2.8-.2c.5 0 1 .2 1.4.5L17.7 8.3l.5-1c.2-.4.6-.6 1-.6 1 0 1.7.8 1.7 1.7 0 .4-.2.8-.6 1l-1 .5 1.7 2.7c.3.4.5.9.5 1.4 0 .5-.1 1-.4 1.4l-.2 2.8 1.1 1.8c.2.2.3.5.3.8 0 .7-.6 1.2-1.3 1.2-.4 0-.7-.1-1-.3l-1-.5-2.7 1.7c-.4.3-.9.5-1.4.5-.5 0-1-.1-1.4-.4l-2.8-.2-1.1 1.8c-.2.2-.5.3-.8.3-.7 0-1.2-.6-1.2-1.3 0-.3.1-.6.3-.8l1.1-1.8-.2-2.8c-.3-.4-.4-.9-.4-1.4 0-.5.1-1 .4-1.4l1.7-2.7-1-.5c-.4-.2-.6-.6-.6-1 0-.7.6-1.4 1.4-1.4z"/></svg>
  </div>

  <div class="max-w-7xl mx-auto px-6 lg:px-10 relative">
    <div class="grid lg:grid-cols-2 gap-14 lg:gap-20 items-center">
      <!-- LEFT: TEXT -->
      <div>
        <div class="ribbon-lux mb-8 float">
          <span class="inline-block tail-wag">🐾</span>
          <span class="tracking-wide">★ Rated 4.9 / 5.0 by 320+ pet families</span>
        </div>

        <h1 class="font-display text-navy leading-[1.03] tracking-[-0.02em] text-[46px] sm:text-[58px] lg:text-[72px] mb-7">
          Your Pet's Safe<br>
          <span class="relative inline-block italic text-coral">
            New Journey
            <svg class="absolute -bottom-3 left-0 w-full" height="18" viewBox="0 0 400 18" preserveAspectRatio="none">
              <path d="M4 11 C80 1 200 1 396 10" stroke="#FF6B4A" stroke-width="4.5" fill="none" stroke-linecap="round"/>
            </svg>
          </span><br>
          Begins Here.
        </h1>

        <p class="text-lg text-muted leading-relaxed max-w-xl mb-10">
          Premium door-to-door pet relocation concierge — from <strong class="text-navy">vet-endorsed documents</strong> and <strong class="text-navy">IATA-approved travel crates</strong> to <strong class="text-navy">live GPS & video updates</strong>. Domestic or international, your four-legged family flies in safe hands.
        </p>

        <div class="flex flex-wrap gap-4 mb-12">
          <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%20would%20like%20to%20inquire%20about%20pet%20relocation%20services." target="_blank" class="btn btn-primary text-base">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
            Submit Quote via WhatsApp
          </a>
          <a href="#gallery" class="btn btn-navy text-base">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            Pet Gallery
          </a>
        </div>

        <!-- Floating trust chips -->
        <div class="flex flex-wrap gap-3 max-w-xl">
          <div class="trust-chip float">
            <span class="w-8 h-8 rounded-full bg-coral/10 flex items-center justify-center">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FF6B4A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </span>
            Door-to-Door Concierge
          </div>
          <div class="trust-chip float-slow">
            <span class="w-8 h-8 rounded-full bg-teal/12 flex items-center justify-center">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#4FB3A8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            </span>
            Vet Endorsed Documents
          </div>
          <div class="trust-chip" style="animation:float 4.5s ease-in-out infinite;">
            <span class="w-8 h-8 rounded-full bg-navy/10 flex items-center justify-center">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0F2747" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
            </span>
            24/7 Live Updates
          </div>
        </div>
      </div>

      <!-- RIGHT: PREMIUM HERO CAROUSEL SLIDER (5 slides) -->
      <div class="relative">
        <div class="hero-carousel aspect-[4/5] lg:aspect-[5/6]" id="heroCarousel">
          <div class="hero-track" id="heroTrack">
            <!-- SLIDE 1: Cute Golden Puppy Airport -->
            <div class="hero-slide">
              <div class="hero-slide-badge"><span class="pin-pulse w-2 h-2 rounded-full" style="background:#FF6B4A"></span> SLIDE 01 · Golden Baby</div>
              <img src="https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=ultra%20cute%20happy%20fluffy%20golden%20retriever%20baby%20puppy%20with%20chubby%20cheeks%20and%20big%20sparkling%20eyes%2C%20sitting%20next%20to%20mini%20luxury%20IATA%20approved%20pet%20crate%20in%20sunny%20modern%20airport%20lobby%2C%20designer%20luggage%20nearby%2C%20warm%20golden%20hour%20light%2C%20adorable%20premium%20pet%20travel%20photography%2C%20high%20detail&image_size=portrait_4_3" alt="Cute golden retriever puppy with IATA crate in airport lobby">
              <div class="hero-slide-caption"><h4>🐶 Golden Baby · Airport Lounge</h4><p>CGK ✈ SIN · Luxury in-airport chaperone service</p></div>
            </div>
            <!-- SLIDE 2: Cute Ragdoll Kitten First Class -->
            <div class="hero-slide">
              <div class="hero-slide-badge"><span class="pin-pulse w-2 h-2 rounded-full" style="background:#4FB3A8"></span> SLIDE 02 · Ragdoll Baby</div>
              <img src="https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=super%20adorable%20fluffy%20ragdoll%20baby%20kitten%20with%20stunning%20big%20blue%20eyes%20and%20chubby%20cheeks%20relaxing%20inside%20premium%20soft%20pink%20pet%20carrier%20bag%2C%20sitting%20on%20first%20class%20airport%20seat%2C%20tiny%20pink%20nose%2C%20cozy%20airport%20departure%20lounge%20background%2C%20ultra%20cute%20kitten%20travel%20photography&image_size=portrait_4_3" alt="Cute Ragdoll kitten in premium pet carrier in first class lounge">
              <div class="hero-slide-caption"><h4>🐱 Ragdoll Baby · First Class Travel</h4><p>CGK ✈ NRT · IATA-approved soft cabin carrier</p></div>
            </div>
            <!-- SLIDE 3: Cute Corgi Puppy Check-in -->
            <div class="hero-slide">
              <div class="hero-slide-badge"><span class="pin-pulse w-2 h-2 rounded-full" style="background:#C9A962"></span> SLIDE 03 · Corgi Baby</div>
              <img src="https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=extremely%20cute%20chubby%20welsh%20corgi%20pembroke%20baby%20puppy%20wearing%20mini%20navy%20blue%20pet%20travel%20bandana%20with%20tiny%20bat%20ears%20and%20happy%20tongue%20out%20smile%2C%20sitting%20at%20modern%20airport%20check%20in%20counter%20next%20to%20luxury%20IATA%20crate%2C%20warm%20lighting%2C%20adorable%20pet%20relocation%20photography&image_size=portrait_4_3" alt="Cute Corgi puppy at airport check-in counter with travel bandana">
              <div class="hero-slide-caption"><h4>🐶 Corgi Baby · Airport Check-in</h4><p>SIN ✈ DPS · Premium door-to-door concierge</p></div>
            </div>
            <!-- SLIDE 4: Cute British Shorthair Kitten -->
            <div class="hero-slide">
              <div class="hero-slide-badge"><span class="pin-pulse w-2 h-2 rounded-full" style="background:#FF6B4A"></span> SLIDE 04 · British Shorthair</div>
              <img src="https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=ultra%20cute%20chubby%20fluffy%20british%20shorthair%20baby%20kitten%20with%20round%20big%20golden%20eyes%20sitting%20inside%20transparent%20premium%20pet%20backpack%20carrier%2C%20curiously%20looking%20out%20the%20window%20at%20sunlit%20airport%20departure%20gate%2C%20blurry%20airplane%20in%20background%2C%20adorable%20kitten%20photography&image_size=portrait_4_3" alt="Cute British Shorthair kitten in pet backpack carrier at airport gate">
              <div class="hero-slide-caption"><h4>🐱 British Shorthair · Cabin Ready</h4><p>CGK ✈ KUL · Under-seat cabin pet transport</p></div>
            </div>
            <!-- SLIDE 5: Cute Siberian Husky Puppy with Heterochromia -->
            <div class="hero-slide">
              <div class="hero-slide-badge"><span class="pin-pulse w-2 h-2 rounded-full" style="background:#0F2747"></span> SLIDE 05 · Husky Baby</div>
              <img src="https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=super%20adorable%20fluffy%20siberian%20husky%20baby%20puppy%20with%20heterochromia%20two%20different%20colored%20eyes%20blue%20and%20brown%2C%20tiny%20smile%2C%20standing%20proudly%20next%20to%20luxury%20IATA%20pet%20cargo%20crate%20on%20airport%20runway%20at%20beautiful%20cinematic%20sunset%2C%20airplane%20silhouette%20background%2C%20premium%20cute%20pet%20relocation&image_size=portrait_4_3" alt="Cute Husky puppy with heterochromia eyes with IATA cargo crate on airport runway sunset">
              <div class="hero-slide-caption"><h4>🐶 Husky Baby · Sunset Runway</h4><p>CGK ✈ LAX · Long-haul climate-controlled cargo</p></div>
            </div>
          </div>

          <!-- Prev/Next Nav Buttons -->
          <button class="hero-nav prev" id="heroPrev" aria-label="Previous slide">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <button class="hero-nav next" id="heroNext" aria-label="Next slide">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </button>

          <!-- Slide Dots -->
          <div class="hero-dots" id="heroDots">
            <button class="hero-dot active" data-i="0" aria-label="Slide 1"></button>
            <button class="hero-dot" data-i="1" aria-label="Slide 2"></button>
            <button class="hero-dot" data-i="2" aria-label="Slide 3"></button>
            <button class="hero-dot" data-i="3" aria-label="Slide 4"></button>
            <button class="hero-dot" data-i="4" aria-label="Slide 5"></button>
          </div>
        </div>

<?php
  // ===== HERO OVERLAYS: 5 SLIDES DATASET (sync rute penerbangan / testimoni / pet cargo) =====
  $heroOverlays = [
    [
      'from' => 'CGK', 'to' => 'SIN', 'flight' => 'PV · 2041', 'status' => 'CONFIRMED',
      'crateNo' => '#A-77', 'crateStatus' => 'Cabin Ready',
      'rating' => '4.9',
      'quote' => '"They treated our Corgi like royalty from Jakarta to Tokyo — seamless."',
      'name' => 'Amanda Kusumo', 'initials' => 'AK', 'route' => 'Jakarta → Tokyo',
      'avatarBg' => 'from-[#4FB3A8] to-[#0F2747]',
    ],
    [
      'from' => 'CGK', 'to' => 'LAX', 'flight' => 'QR · 9582', 'status' => 'BOARDING',
      'crateNo' => '#C-23', 'crateStatus' => 'In Transit',
      'rating' => '5.0',
      'quote' => '"Our Husky arrived happy and healthy in Los Angeles after 26 hours — the updates were incredible."',
      'name' => 'Daniel Wijaya', 'initials' => 'DW', 'route' => 'Jakarta → Los Angeles',
      'avatarBg' => 'from-[#FF6B4A] to-[#C9A962]',
    ],
    [
      'from' => 'SIN', 'to' => 'DPS', 'flight' => 'SQ · 1047', 'status' => 'LANDED',
      'crateNo' => '#F-09', 'crateStatus' => 'Delivered',
      'rating' => '4.8',
      'quote' => '"Our Maine Coon moved from Singapore to Bali totally stress-free. Door-to-door service was exceptional."',
      'name' => 'Sarah Lim', 'initials' => 'SL', 'route' => 'Singapore → Bali',
      'avatarBg' => 'from-[#C9A962] to-[#0F2747]',
    ],
    [
      'from' => 'CGK', 'to' => 'AMS', 'flight' => 'KL · 8026', 'status' => 'CONFIRMED',
      'crateNo' => '#H-42', 'crateStatus' => 'Check-in',
      'rating' => '4.9',
      'quote' => '"Our Golden Retriever flew to Amsterdam. The vet escort service gave us total peace of mind."',
      'name' => 'Michelle Tanuwijaya', 'initials' => 'MT', 'route' => 'Jakarta → Amsterdam',
      'avatarBg' => 'from-[#4FB3A8] to-[#C9A962]',
    ],
    [
      'from' => 'KUL', 'to' => 'NRT', 'flight' => 'JL · 7031', 'status' => 'IN FLIGHT',
      'crateNo' => '#B-15', 'crateStatus' => 'Vet Escorted',
      'rating' => '5.0',
      'quote' => '"Shiba Inu arrived in Tokyo in perfect condition. We could track every step of the journey LIVE."',
      'name' => 'Hendra Soewarno', 'initials' => 'HS', 'route' => 'Kuala Lumpur → Tokyo',
      'avatarBg' => 'from-[#FF6B4A] to-[#4FB3A8]',
    ],
  ];
  $_today = liveDateBadge();
  $_ajC = activeJourneysCount();
?>

        <!-- TOP LEFT: BOARDING PASS - SLIDER 5 RUTES (🟥 MERAH) -->
        <div class="absolute top-8 left-8 boarding-pass max-w-[220px] float z-20" id="bpCard">
          <div class="flex items-center gap-2 mb-1.5 pr-10">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#0F2747" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>
            <span class="text-[10px] font-extrabold uppercase tracking-[0.2em] text-muted">Boarding Pass</span>
          </div>
          <div id="bp-slides" class="relative overflow-hidden" style="height:46px;">
            <?php foreach($heroOverlays as $o): ?>
            <div class="bp-slide absolute inset-0 transition-all duration-700 ease-out opacity-0 translate-x-4" data-status="<?=e($o['status'])?>">
              <div class="text-navy font-bold flex items-center gap-2 pr-10">
                <span class="text-base"><?=e($o['from'])?></span>
                <svg width="30" height="11" viewBox="0 0 34 12">
                  <path d="M2 6 L28 6" stroke="#FF6B4A" stroke-width="2" stroke-linecap="round"/>
                  <path d="M22 1 L32 6 L22 11" stroke="#FF6B4A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="text-base"><?=e($o['to'])?></span>
              </div>
              <div class="mt-1 pr-10 flex items-center justify-between">
                <span class="text-[11px] text-muted font-semibold"><?=e($o['flight'])?></span>
                <span class="text-[10px] font-bold" style="color:<?= in_array($o['status'],['IN FLIGHT','BOARDING']) ? '#0F2747' : '#FF6B4A'?>"><?=e($o['status'])?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- TOP RIGHT: LOCATION PIN - TANGGAL LIVE (⚫ HITAM) -->
        <div class="absolute -top-5 -right-5 z-20 float-slow">
          <div class="card-lux py-3 px-5 flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#FF9075] to-[#FF6B4A] flex items-center justify-center shadow-lg shadow-coral/40 pin-pulse">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            <div>
              <div class="text-[10px] font-bold text-muted uppercase tracking-wider"><?=e($_today)?></div>
              <div class="text-sm font-bold text-navy"><span class="stat-num" data-count="<?=(int)$_ajC?>"><?=$_ajC?></span> Active Journeys</div>
            </div>
          </div>
        </div>

        <!-- BOTTOM: CUSTOMER TESTIMONIAL - CAROUSEL 5 slide (🟡 KUNING) -->
        <div class="absolute -bottom-20 -left-10 z-20 hidden sm:block float" id="tCard">
          <div class="card-lux p-8 pr-28 max-w-[620px] relative overflow-hidden shadow-2xl" style="min-height:290px;">
            <div id="t-slides" class="relative" style="height:258px;">
              <?php foreach($heroOverlays as $o): ?>
              <div class="t-slide absolute inset-0 transition-all duration-700 ease-out opacity-0 translate-y-3">
                <div class="flex items-center gap-4 mb-4">
                  <div class="flex gap-1">
                    <?php for($i=0;$i<5;$i++): ?>
                      <svg width="22" height="22" viewBox="0 0 24 24" fill="#FF6B4A"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <?php endfor; ?>
                  </div>
                  <span class="font-display text-4xl font-semibold text-navy"><?=e($o['rating'])?></span>
                </div>
                <div class="font-editorial italic text-navy leading-snug mb-5 text-2xl" style="text-shadow:0 1px 2px rgba(15,39,71,0.12);"><?=$o['quote']?></div>
                <div class="flex items-center gap-5">
                  <div class="w-14 h-14 rounded-full bg-gradient-to-br <?=$o['avatarBg']?> flex items-center justify-center text-cream font-bold text-lg"><?=e($o['initials'])?></div>
                  <div>
                    <div class="font-bold text-navy text-lg"><?=e($o['name'])?></div>
                    <div class="text-sm text-muted font-semibold">Relocation: <?=e($o['route'])?></div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <!-- dots -->
            <div class="absolute bottom-4 right-6 flex gap-2.5">
              <?php for($i=0;$i<count($heroOverlays);$i++): ?>
                <span class="t-dot w-2.5 h-2.5 rounded-full bg-navy/25 transition-all" data-i="<?=$i?>"></span>
              <?php endfor; ?>
            </div>
          </div>
        </div>

        <!-- BOTTOM RIGHT: PET CARGO TAG - SLIDER 5 (🟩 HIJAU) -->
        <div class="absolute -bottom-5 right-10 z-20 float-slow" id="pcCard">
          <div class="relative">
            <div class="absolute -top-5 left-6 w-0.5 h-6 bg-navy/50 rounded-full"></div>
            <div id="pc-slides" class="relative overflow-hidden" style="height:34px;">
              <?php foreach($heroOverlays as $o): ?>
                <?php
                  $col = '#FF6B4A'; $txt = 'Pet Cargo · '.$o['crateNo'];
                  if (str_contains($o['crateStatus'],'Delivered')) $col = '#4FB3A8';
                  elseif (str_contains($o['crateStatus'],'Transit') || str_contains($o['crateStatus'],'Escorted')) $col = '#0F2747';
                  elseif (str_contains($o['crateStatus'],'Check')) $col = '#C9A962';
                ?>
                <div class="pc-slide absolute inset-x-0 top-0 transition-all duration-700 ease-out opacity-0 translate-y-2">
                  <div class="rounded-xl text-white px-5 py-2.5 font-bold text-xs tracking-[0.2em] uppercase shadow-xl flex items-center gap-2" style="background:<?=$col?>; box-shadow:0 10px 24px <?=$col?>55;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span class="whitespace-nowrap"><?=$txt?></span>
                  </div>
                  <div class="mt-1 text-center text-[10px] font-bold tracking-wider uppercase" style="color:<?=$col?>">⟡ <?=$o['crateStatus']?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== TRUST STATISTICS ROW ===== -->
    <div class="mt-20 lg:mt-32 grid grid-cols-2 md:grid-cols-4 gap-5" id="statsRow">
      <?php
        $stats = [
            ['num'=>500,'suffix'=>'+','label'=>'Happy Pets Relocated','icon'=>'🐾','color'=>'coral','bg'=>'#FFEDE5'],
            ['num'=>50,'suffix'=>'+','label'=>'Domestic & Intl Routes','icon'=>'✈️','color'=>'navy','bg'=>'#E6EEFA'],
            ['num'=>'24/7','suffix'=>'','label'=>'Live Journey Updates','icon'=>'📡','color'=>'teal','bg'=>'#E7F5F3','type'=>'static'],
            ['num'=>100,'suffix'=>'%','label'=>'Vet & IATA Certified','icon'=>'✅','color'=>'gold','bg'=>'#FBF3DC'],
        ];
        foreach ($stats as $s):
          $staticOrNum = isset($s['type']) && $s['type']==='static' ? 'data-static' : 'data-count="'.(int)$s['num'].'"';
      ?>
      <div class="stat-card">
        <div class="mx-auto mb-5 w-14 h-14 rounded-2xl flex items-center justify-center text-2xl" style="background:<?=$s['bg']?>"><?=$s['icon']?></div>
        <div class="font-display text-4xl lg:text-5xl font-semibold text-navy mb-2">
          <?php if (isset($s['type']) && $s['type']==='static'): ?>
            <span class="stat-num"><?=$s['num']?></span>
          <?php else: ?>
            <span class="stat-num" <?=$staticOrNum?>>0</span><span class="suffix"><?=$s['suffix']?></span>
          <?php endif; ?>
        </div>
        <div class="text-sm text-muted font-semibold leading-snug"><?=$s['label']?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===== SERVICES / PROCESS STEPS ===== -->
<section id="services" class="py-20 lg:py-28 bg-cream-2/50 relative">
  <!-- Pet decor: paws + bone -->
  <div class="pet-deco paw-drift paw-sm" style="top:8%;left:4%;">
    <svg viewBox="0 0 24 24" fill="rgba(255,107,74,0.35)"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco paw-drift-2 paw-sm" style="top:15%;right:5%;">
    <svg viewBox="0 0 24 24" fill="rgba(79,179,168,0.35)"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco bone-sm rotate-slow" style="bottom:10%;left:7%;opacity:0.3;">
    <svg viewBox="0 0 24 24" fill="#C9A962"><path d="M4.7 11.3c-.5-.5-.5-1.2 0-1.7L8 6.3C7.8 5.6 8.3 5 9 5c.3 0 .6.1.8.3l1.8 1.1 2.8-.2c.5 0 1 .2 1.4.5L17.7 8.3l.5-1c.2-.4.6-.6 1-.6 1 0 1.7.8 1.7 1.7 0 .4-.2.8-.6 1l-1 .5 1.7 2.7c.3.4.5.9.5 1.4 0 .5-.1 1-.4 1.4l-.2 2.8 1.1 1.8c.2.2.3.5.3.8 0 .7-.6 1.2-1.3 1.2-.4 0-.7-.1-1-.3l-1-.5-2.7 1.7c-.4.3-.9.5-1.4.5-.5 0-1-.1-1.4-.4l-2.8-.2-1.1 1.8c-.2.2-.5.3-.8.3-.7 0-1.2-.6-1.2-1.3 0-.3.1-.6.3-.8l1.1-1.8-.2-2.8c-.3-.4-.4-.9-.4-1.4 0-.5.1-1 .4-1.4l1.7-2.7-1-.5c-.4-.2-.6-.6-.6-1 0-.7.6-1.4 1.4-1.4z"/></svg>
  </div>

  <div class="max-w-7xl mx-auto px-6 lg:px-10">
    <div class="text-center max-w-3xl mx-auto mb-16 lg:mb-20">
      <div class="eyebrow mb-5">
        <span class="w-1.5 h-1.5 rounded-full bg-coral"></span>
        How BeethovenPets Works
      </div>
      <h2 class="font-display text-navy text-4xl sm:text-5xl lg:text-6xl font-semibold leading-tight mb-5">
        The 4-Step Luxury<br>
        <span class="italic text-gold">Pet Concierge Experience</span>
      </h2>
      <p class="text-muted text-lg leading-relaxed">Every relocation is handled end-to-end by our certified team of pet travel specialists — no hidden fees, no uncertain timelines.</p>
    </div>

    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-7">
      <?php
        $steps = [
          ['n'=>'01','title'=>'Personal Consultation','desc'=>'Share destination, pet details, and preferred dates. We assign a dedicated concierge within 2 hours.','icon'=>'💬','bg'=>'#FFEDE5','accent'=>'coral'],
          ['n'=>'02','title'=>'Vet & Docs Handled','desc'=>'Health certificate, import/export permit, rabies titre, microchip — we coordinate 100% with partner vets.','icon'=>'📋','bg'=>'#E6EEFA','accent'=>'navy'],
          ['n'=>'03','title'=>'Luxury Transit Journey','desc'=>'Climate-controlled rides, IATA crates, optional dedicated chaperone. Photos & GPS every step.','icon'=>'✈️','bg'=>'#E7F5F3','accent'=>'teal'],
          ['n'=>'04','title'=>'Doorstep Delivery','desc'=>'Your pet is hand-delivered to the destination address with a final health confirmation.','icon'=>'🏡','bg'=>'#FBF3DC','accent'=>'gold'],
        ];
        foreach($steps as $i => $st):
      ?>
      <div class="card-lux card-hover p-7 relative overflow-hidden">
        <div class="absolute top-4 right-5 font-display text-7xl opacity-[0.07] font-black text-navy"><?=$st['n']?></div>
        <div class="step-dot mb-6" style="background:<?=$st['bg']?>">
          <span class="text-2xl"><?=$st['icon']?></span>
        </div>
        <div class="font-bold uppercase text-xs tracking-[0.18em] mb-3 text-<?=$st['accent']?>" style="color:var(--<?=$st['accent']?>)">Step <?=$st['n']?></div>
        <h3 class="font-display text-navy text-2xl font-semibold mb-3 leading-tight"><?=$st['title']?></h3>
        <p class="text-muted leading-relaxed text-sm"><?=$st['desc']?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===== PET JOURNEY SHOWCASE (REPLACES QUOTE FORM) ===== -->
<section id="quote" class="py-20 lg:py-28 relative overflow-hidden">
  <!-- Pet decor: paws + hearts -->
  <div class="pet-deco paw-drift paw-md" style="top:10%;right:4%;">
    <svg viewBox="0 0 24 24" fill="rgba(201,169,98,0.4)"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco heart-pet sparkle" style="top:30%;left:5%;animation-delay:0.3s;">
    <svg viewBox="0 0 24 24" fill="rgba(255,107,74,0.45)"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
  </div>
  <div class="pet-deco sparkle-sm sparkle" style="bottom:18%;right:12%;animation-delay:0.8s;">
    <svg viewBox="0 0 24 24" fill="#C9A962"><polygon points="12,2 14,10 22,10 16,15 18,23 12,18 6,23 8,15 2,10 10,10"/></svg>
  </div>
  <div class="absolute top-16 left-10 hidden md:flex flex-col gap-2 opacity-40">
    <div class="flex gap-2"><div style="width:7px;height:7px;border-radius:50%;background:var(--coral)"></div><div style="width:7px;height:7px;border-radius:50%;background:var(--teal)"></div><div style="width:7px;height:7px;border-radius:50%;background:var(--navy)"></div></div>
    <div class="flex gap-2 pl-6"><div style="width:7px;height:7px;border-radius:50%;background:var(--teal)"></div><div style="width:7px;height:7px;border-radius:50%;background:var(--gold)"></div></div>
  </div>
  <div class="absolute -top-32 -right-32 w-[400px] h-[400px] rounded-full bg-coral/5 pointer-events-none"></div>
  <div class="absolute -bottom-40 -left-40 w-[500px] h-[500px] rounded-full bg-teal/5 pointer-events-none"></div>

  <div class="max-w-7xl mx-auto px-6 lg:px-10 relative">
    <div class="text-center max-w-3xl mx-auto mb-14">
      <div class="eyebrow mb-5"><span class="w-1.5 h-1.5 rounded-full bg-coral pin-pulse"></span>Consultation & Quote via WhatsApp</div>
      <h2 class="font-display text-navy text-4xl sm:text-5xl lg:text-6xl font-semibold leading-[1.08] mb-5">
        Speak Directly<br>
        <span class="italic text-coral">with Our Team</span>
      </h2>
      <p class="text-muted text-lg leading-relaxed">Need a custom quote for your pet's special journey? Chat with us on WhatsApp — we'll guide you from the first consultation all the way to safe delivery.</p>
    </div>

    <!-- CTA WhatsApp Banner Primary -->
    <div class="card-lux p-8 sm:p-10 lg:p-12 mb-14 relative overflow-hidden">
      <div class="absolute -top-20 -right-20 w-72 h-72 rounded-full bg-coral/10 pointer-events-none"></div>
      <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-teal/10 pointer-events-none"></div>
      <div class="relative flex flex-col lg:flex-row items-center gap-8 justify-between">
        <div class="flex items-center gap-5 lg:gap-6 flex-1">
          <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-3xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center flex-shrink-0 shadow-xl shadow-green-500/30">
            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
          </div>
          <div>
            <h3 class="font-display text-navy text-2xl sm:text-3xl lg:text-4xl font-semibold mb-2">We're here 24/7</h3>
            <p class="text-muted text-base sm:text-lg font-medium">Free consultation · Reply within minutes · No forms required</p>
            <div class="flex flex-wrap gap-2 mt-3">
              <span class="chip chip-teal">✓ Free Consultation</span>
              <span class="chip chip-coral">✓ No Hidden Fees</span>
              <span class="chip chip-gold">✓ IATA Certified</span>
            </div>
          </div>
        </div>
        <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%20would%20like%20to%20inquire%20about%20a%20custom%20pet%20relocation%20quote%20for%20my%20pet.%20Please%20advise%20on%20pricing%20and%20procedures.%20Thank%20you." target="_blank" class="btn btn-primary lg:px-10 min-h-[72px] text-base shadow-xl shadow-coral/30 whitespace-nowrap">
          <span class="flex flex-col items-start leading-tight">
            <span class="font-bold text-[17px]">Chat via WhatsApp Now</span>
            <span class="text-[12px] font-medium opacity-90 tracking-wide">Click → opens WhatsApp chat directly</span>
          </span>
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
      </div>
    </div>

    <!-- Showcase Pet Professional Gallery Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 lg:gap-6">
      <?php
        $showcases = [
          ['prompt'=>'ultra%20cute%20chubby%20pembroke%20welsh%20corgi%20puppy%20dog%20wearing%20navy%20blue%20IATA%20travel%20bandana%2C%20happy%20tongue%20out%20smile%2C%20sitting%20elegantly%20next%20to%20luxury%20beige%20IATA%20approved%20travel%20crate%2C%20soft%20warm%20studio%20lighting%2C%20cream%20beige%20background%2C%20premium%20pet%20photography','cap'=>'Premium Travel Kit · Corgi'],
          ['prompt'=>'super%20cute%20fluffy%20ragdoll%20kitten%20cat%20with%20stunning%20big%20round%20blue%20eyes%20and%20chubby%20cheeks%20cozily%20relaxing%20inside%20luxury%20premium%20soft%20beige%20pet%20carrier%20bag%2C%20airport%20departure%20lounge%20chair%20background%2C%20first%20class%20pet%20travel%20aesthetic','cap'=>'First Class Travel · Ragdoll'],
          ['prompt'=>'adorable%20happy%20smiling%20golden%20retriever%20puppy%20with%20wind%20in%20fluffy%20fur%20looking%20out%20luxury%20private%20van%20car%20window%2C%20premium%20door%20to%20door%20pet%20transfer%2C%20beautiful%20sunset%20golden%20hour%20warm%20lighting%2C%20joyful%20pet%20journey','cap'=>'Door-to-Door Transfer · Golden'],
          ['prompt'=>'ultra%20cute%20tiny%20chihuahua%20baby%20puppy%20with%20big%20sparkling%20eyes%20on%20bright%20clean%20white%20modern%20vet%20clinic%20examination%20table%2C%20gentle%20veterinarian%20hands%20with%20pink%20stethoscope%20doing%20health%20check%20before%20flight%2C%20premium%20vet%20care','cap'=>'Pre-Flight Vet Check · Chihuahua'],
          ['prompt'=>'heartwarming%20emotional%20happy%20family%20reunion%20welcoming%20their%20super%20cute%20fluffy%20pomeranian%20puppy%20dog%20at%20busy%20modern%20airport%20arrivals%20hall%2C%20luggage%20carts%20background%2C%20smiles%20all%20around%2C%20pet%20travel%20happy%20ending','cap'=>'Airport Reunion · Pomeranian'],
          ['prompt'=>'ultra%20adorable%20fluffy%20white%20bichon%20frise%20puppy%20dog%20freshly%20groomed%20wearing%20tiny%20pink%20bowtie%2C%20sitting%20proudly%20on%20luxury%20cream%20velvet%20tufted%20sofa%20in%20premium%20pet%20hotel%20suite%20bedroom%2C%20transit%20boarding%20facility%2C%20warm%20lamp%20lighting','cap'=>'Luxury Boarding · Bichon Frise'],
          ['prompt'=>'majestic%20cute%20german%20shepherd%20young%20dog%20wearing%20premium%20black%20harness%20standing%20proudly%20next%20to%20big%20passenger%20airplane%20on%20airport%20runway%20at%20beautiful%20orange%20cinematic%20sunset%2C%20luxury%20IATA%20pet%20cargo%20crate%20next%20to%20him','cap'=>'Cargo Ready · German Shepherd'],
          ['prompt'=>'super%20cute%20chubby%20fluffy%20british%20shorthair%20baby%20kitten%20with%20round%20big%20golden%20copper%20eyes%20and%20chubby%20cheeks%20sitting%20happily%20inside%20transparent%20premium%20pet%20backpack%20carrier%2C%20airport%20departure%20gate%20blurry%20background%2C%20cabin%20ready','cap'=>'Cabin Travel · British Shorthair'],
        ];
        $fallbackImg = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500"><rect width="400" height="500" fill="#EFE7D7"/><path d="M150 130c0-26-18-40-40-36-38 6-66 45-68 66-2 26 18 40 40 36 10-2 18-5 24-9 6 9 18 18 34 18 22 0 40-18 40-40 0-14-6-26-16-35zm100 0c0-26 18-40 40-36 38 6 66 45 68 66 2 26-18 40-40 36-10-2-18-5-24-9-6 9-18 18-34 18-22 0-40-18-40-40 0-14 6-26 16-35zm-50 220c0 40 8 56 40 56 20 0 40-7 40-27 0-60-40-68-80-74v45zm-100-44v-45c-40 6-80 14-80 74 0 20 20 27 40 27 32 0 40-16 40-56z" fill="#FF6B4A" opacity="0.35"/><text x="50%" y="90%" text-anchor="middle" font-family="Fraunces,serif" font-weight="600" font-size="22" fill="#0F2747" opacity="0.6">🐾 BeethovenPets</text></svg>');
        foreach($showcases as $i => $s):
      ?>
      <div class="group relative aspect-[4/5] rounded-[28px] overflow-hidden shadow-lg shadow-navy/5 bg-cream-3">
        <div class="grid-img-wrap">
          <img src="https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=<?=$s['prompt']?>&image_size=portrait_4_3" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="<?=$s['cap']?>" onerror="this.onerror=null;this.src='<?=$fallbackImg?>';this.classList.add('loaded');" onload="this.classList.add('loaded');">
        </div>
        <div class="absolute inset-0 bg-gradient-to-t from-navy/80 via-navy/10 to-transparent"></div>
        <div class="absolute bottom-0 left-0 right-0 p-4 sm:p-5">
          <div class="flex items-center gap-2 mb-2">
            <div class="w-2 h-2 rounded-full bg-coral pin-pulse"></div>
            <span class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-coral-soft/95">BeethovenPets</span>
          </div>
          <p class="text-white font-semibold text-sm sm:text-base leading-snug"><?=$s['cap']?></p>
        </div>
        <div class="absolute top-3 right-3 w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-white/90 backdrop-blur-sm flex items-center justify-center shadow-lg group-hover:bg-coral group-hover:text-white transition-all">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-navy group-hover:text-white"><path d="M20 8.25c0 6.5-8 12.75-8 12.75S4 14.75 4 8.25a8 8 0 0 1 16 0z"/><circle cx="12" cy="8.25" r="3"/></svg>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===== GALLERY (REPLACES TRACK JOURNEY) ===== -->
<section id="gallery" class="py-20 lg:py-28 bg-cream-2/50 relative overflow-hidden">
  <!-- Pet decor: yarn balls, fish bones, kneading paws, tiny treats -->
  <div class="pet-deco paw-md knead-paw" style="top:6%;left:3%;">
    <svg viewBox="0 0 24 24" fill="rgba(255,107,74,0.38)"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco paw-sm knead-paw" style="top:14%;right:5%;animation-delay:0.6s;">
    <svg viewBox="0 0 24 24" fill="rgba(79,179,168,0.38)"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco yarn-sm yarn-roll" style="bottom:12%;left:6%;opacity:0.45;">
    <svg viewBox="0 0 24 24" fill="#C9A962"><circle cx="12" cy="12" r="9" fill="none" stroke="#C9A962" stroke-width="2"/><path d="M4 12 Q12 4 20 12 Q12 20 4 12" fill="none" stroke="#C9A962" stroke-width="1.8" stroke-linecap="round"/><path d="M12 3 Q12 12 12 21" fill="none" stroke="#C9A962" stroke-width="1.6" stroke-linecap="round"/><path d="M6 7 Q12 12 18 17" fill="none" stroke="#C9A962" stroke-width="1.4" stroke-linecap="round"/><path d="M18 7 Q12 12 6 17" fill="none" stroke="#C9A962" stroke-width="1.4" stroke-linecap="round"/></svg>
  </div>
  <div class="pet-deco yarn-md yarn-roll" style="top:40%;right:3%;opacity:0.35;animation-direction:reverse;animation-duration:14s;">
    <svg viewBox="0 0 24 24" fill="#FF6B4A"><circle cx="12" cy="12" r="9" fill="none" stroke="#FF6B4A" stroke-width="2"/><path d="M4 12 Q12 4 20 12 Q12 20 4 12" fill="none" stroke="#FF6B4A" stroke-width="1.8" stroke-linecap="round"/><path d="M12 3 Q12 12 12 21" fill="none" stroke="#FF6B4A" stroke-width="1.6" stroke-linecap="round"/><path d="M6 7 Q12 12 18 17" fill="none" stroke="#FF6B4A" stroke-width="1.4" stroke-linecap="round"/><path d="M18 7 Q12 12 6 17" fill="none" stroke="#FF6B4A" stroke-width="1.4" stroke-linecap="round"/></svg>
  </div>
  <div class="pet-deco fish-md fish-swim" style="bottom:18%;right:10%;">
    <svg viewBox="0 0 24 14" fill="rgba(79,179,168,0.45)"><path d="M16 7 Q11 2 5 4 Q2 5 2 7 Q2 9 5 10 Q11 12 16 7Z"/><path d="M17 7 L22 2 L22 12 Z" fill="rgba(79,179,168,0.45)"/><circle cx="7.5" cy="6.8" r="1.1" fill="#0F2747" opacity="0.4"/></svg>
  </div>
  <div class="pet-deco fish-sm fish-swim" style="top:30%;left:8%;animation-delay:1.3s;animation-duration:11s;">
    <svg viewBox="0 0 24 14" fill="rgba(201,169,98,0.5)"><path d="M16 7 Q11 2 5 4 Q2 5 2 7 Q2 9 5 10 Q11 12 16 7Z"/><path d="M17 7 L22 2 L22 12 Z" fill="rgba(201,169,98,0.5)"/><circle cx="7.5" cy="6.8" r="1.1" fill="#0F2747" opacity="0.4"/></svg>
  </div>
  <div class="pet-deco treat-sm treat-drop" style="top:50%;left:2%;">
    <svg viewBox="0 0 24 24" fill="#FF6B4A"><path d="M12 3 L14 10 L21 12 L14 14 L12 21 L10 14 L3 12 L10 10 Z" opacity="0.55"/></svg>
  </div>
  <div class="pet-deco treat-sm treat-drop" style="bottom:30%;right:18%;animation-delay:2.1s;">
    <svg viewBox="0 0 24 24" fill="#4FB3A8"><circle cx="12" cy="12" r="4" opacity="0.5"/><path d="M12 2 Q14 6 12 10 Q10 14 12 18 Q14 22 12 22" fill="none" stroke="#4FB3A8" stroke-width="1.6" stroke-linecap="round" opacity="0.55"/></svg>
  </div>
  <div class="pet-deco sparkle-sm sparkle-burst" style="top:24%;left:14%;animation-delay:0.8s;">
    <svg viewBox="0 0 24 24" fill="#FF6B4A"><polygon points="12,2 14,10 22,10 16,15 18,23 12,18 6,23 8,15 2,10 10,10"/></svg>
  </div>
  <div class="pet-deco sparkle-sm sparkle-burst" style="bottom:8%;right:22%;animation-delay:1.6s;">
    <svg viewBox="0 0 24 24" fill="#C9A962"><polygon points="12,2 14,10 22,10 16,15 18,23 12,18 6,23 8,15 2,10 10,10"/></svg>
  </div>

  <div class="max-w-7xl mx-auto px-6 lg:px-10">
    <div class="text-center max-w-3xl mx-auto mb-14 lg:mb-16">
      <div class="eyebrow mb-5">
        <span class="w-1.5 h-1.5 rounded-full bg-teal pin-pulse"></span>
        Pet Journey Gallery
      </div>
      <h2 class="font-display text-navy text-4xl sm:text-5xl lg:text-6xl font-semibold leading-[1.08] mb-5">
        Gallery — Our Pets'<br>
        <span class="italic text-gold">Heartwarming Journeys</span>
      </h2>
      <p class="text-muted text-lg leading-relaxed">A curated collection of adorable moments from the pets we've safely relocated. 100% authentic photos by the BeethovenPets team.</p>
    </div>

    <!-- Gallery Grid · 7 REAL airport photos FIRST, then 1 studio photo last (Shih Tzu) · UNSPLASH direct URL agar PASTI foto HEWAN + OWNER -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 lg:gap-5">
      <?php
        $gallery = [
          ['src'=>'https://images.unsplash.com/photo-1450778869180-41d0601e046e?w=700&auto=format&fit=crop&q=75','cap'=>'Golden Retriever · Owner Hug at Airport Arrival','alt'=>'Happy dog owner hugging Golden Retriever at airport after pet relocation'],
          ['src'=>'https://images.unsplash.com/photo-1583511655826-05700d52f4d9?w=700&auto=format&fit=crop&q=75','cap'=>'Persian Cat · Baggage Claim Crate Pickup','alt'=>'Woman picking up Persian cat in travel crate at airport baggage claim'],
          ['src'=>'https://images.unsplash.com/photo-1583337130417-3346a1be7dee?w=700&auto=format&fit=crop&q=75','cap'=>'French Bulldog · Family Welcome at Home Gate','alt'=>'Happy family welcoming French Bulldog puppy at home entrance after flight'],
          ['src'=>'https://images.unsplash.com/photo-1560807707-8cc77767d783?w=700&auto=format&fit=crop&q=75','cap'=>'Maine Coon · Door-to-Door Chaperone Delivery','alt'=>'Pet chaperone delivering Maine Coon cat in carrier to owner at doorstep'],
          ['src'=>'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=700&auto=format&fit=crop&q=75','cap'=>'Scottish Fold · Owner Carry at Airport Terminal','alt'=>'Smiling woman carrying Scottish Fold cat in soft carrier bag at airport terminal'],
          ['src'=>'https://images.unsplash.com/photo-1544568100-847a948585b9?w=700&auto=format&fit=crop&q=75','cap'=>'Husky with Heterochromia · Reunion Hug Outside Home','alt'=>'Owner hugging Husky dog with heterochromia eyes in front yard after long trip'],
          ['src'=>'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=700&auto=format&fit=crop&q=75','cap'=>'Toy Poodle · Airport Lounge · Owner on Luggage Cart','alt'=>'Cute Toy Poodle on airport luggage cart beside smiling owner with passport'],
          ['src'=>'https://images.unsplash.com/photo-1518791841217-8f162f1e1131?w=700&auto=format&fit=crop&q=75','cap'=>'Shih Tzu Baby · Cute Ponytail (Studio Portrait)','alt'=>'Cute Shih Tzu puppy studio portrait with pink ribbon ponytail'],
        ];
        $fallbackUrls = [
          'https://images.unsplash.com/photo-1558788353-f76d92427f16?w=700&auto=format&fit=crop&q=75',
          'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=700&auto=format&fit=crop&q=75',
          'https://images.unsplash.com/photo-1583337130417-3346a1be7dee?w=700&auto=format&fit=crop&q=75',
          'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?w=700&auto=format&fit=crop&q=75',
          'https://images.unsplash.com/photo-1478098711619-5ab0b478d6e6?w=700&auto=format&fit=crop&q=75',
          'https://images.unsplash.com/photo-1605568427561-40dd23c2acea?w=700&auto=format&fit=crop&q=75',
          'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=700&auto=format&fit=crop&q=75',
          'https://images.unsplash.com/photo-1518791841217-8f162f1e1131?w=700&auto=format&fit=crop&q=75',
        ];
        foreach($gallery as $i => $g):
          $src = $g['src'];
          $fallback = $fallbackUrls[$i] ?? $fallbackUrls[0];
      ?>
      <div class="group relative aspect-square rounded-2xl overflow-hidden card-hover bg-cream-3 cursor-pointer">
        <img src="<?=$src?>" onerror="this.onerror=null;this.src='<?=$fallback?>';" alt="<?=e($g['alt'] ?? $g['cap'])?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
        <div class="absolute inset-0 bg-gradient-to-t from-navy/85 via-navy/20 to-transparent opacity-0 group-hover:opacity-100 transition-all duration-300"></div>
        <div class="absolute bottom-0 left-0 right-0 p-4 translate-y-4 group-hover:translate-y-0 opacity-0 group-hover:opacity-100 transition-all duration-300">
          <div class="text-[11px] font-bold uppercase tracking-widest text-coral mb-1">Journey #<?=str_pad($i+1,3,'0',STR_PAD_LEFT)?></div>
          <div class="text-cream font-semibold text-sm leading-snug"><?=$g['cap']?></div>
        </div>
        <div class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white/90 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 backdrop-blur-sm shadow-lg">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0F2747" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-12">
      <a href="#" class="btn btn-outline">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        View Full Gallery (120+)
      </a>
    </div>
  </div>
</section>

<!-- ===== OUR STORY · 20+ Years · 50+ Countries ===== -->
<section id="stories" class="py-20 lg:py-28 bg-navy relative overflow-hidden">
  <div class="absolute inset-0 opacity-[0.07]">
    <svg width="100%" height="100%">
      <pattern id="pawPatternStories" x="0" y="0" width="90" height="90" patternUnits="userSpaceOnUse">
        <path d="M12 10c0-2-1.2-3-3-3S6 8 6 10c0 2 1.2 3 3 3s3-1 3-3zM20 18c0-2-1.2-3-3-3s-3 1-3 3c0 2 1.2 3 3 3s3-1 3-3z" fill="#FBF7F0"/>
      </pattern>
      <rect width="100%" height="100%" fill="url(#pawPatternStories)"/>
    </svg>
  </div>
  <div class="absolute -top-40 -left-40 w-[600px] h-[600px] rounded-full bg-coral/10 blur-3xl"></div>
  <div class="absolute -bottom-40 -right-40 w-[600px] h-[600px] rounded-full bg-teal/10 blur-3xl"></div>

  <!-- Decorative floating elements -->
  <div class="pet-deco paw-sm tiny-bounce" style="top:8%;left:4%;opacity:0.35;animation-duration:3.2s;">
    <svg viewBox="0 0 24 24" fill="#FFB19E"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco paw-md tiny-bounce" style="top:18%;right:5%;opacity:0.28;animation-delay:0.9s;animation-duration:3.8s;">
    <svg viewBox="0 0 24 24" fill="#4FB3A8"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco heart-pet tiny-bounce" style="top:70%;left:3%;opacity:0.3;animation-delay:0.4s;">
    <svg viewBox="0 0 24 24" fill="#FF6B4A"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
  </div>
  <div class="pet-deco yarn-sm yarn-roll" style="top:45%;right:2%;opacity:0.22;animation-duration:16s;">
    <svg viewBox="0 0 24 24" fill="#FBF7F0"><circle cx="12" cy="12" r="9" fill="none" stroke="#FBF7F0" stroke-width="1.8"/><path d="M4 12 Q12 4 20 12 Q12 20 4 12" fill="none" stroke="#FBF7F0" stroke-width="1.5" stroke-linecap="round"/><path d="M12 3 Q12 12 12 21" fill="none" stroke="#FBF7F0" stroke-width="1.3" stroke-linecap="round"/></svg>
  </div>
  <div class="pet-deco sparkle-sm sparkle-burst" style="top:38%;left:7%;animation-delay:0.6s;opacity:0.7;">
    <svg viewBox="0 0 24 24" fill="#FFB19E"><polygon points="12,2 14,10 22,10 16,15 18,23 12,18 6,23 8,15 2,10 10,10"/></svg>
  </div>
  <div class="pet-deco sparkle-sm sparkle-burst" style="bottom:12%;right:12%;animation-delay:1.4s;opacity:0.7;">
    <svg viewBox="0 0 24 24" fill="#C9A962"><polygon points="12,2 14,10 22,10 16,15 18,23 12,18 6,23 8,15 2,10 10,10"/></svg>
  </div>
  <!-- Plane silhouette tracing route arc -->
  <div class="pet-deco" style="top:30%;left:50%;transform:translateX(-50%);opacity:0.12;width:70%;">
    <svg viewBox="0 0 800 120" fill="none">
      <path d="M20 90 Q400 10 780 90" stroke="#FBF7F0" stroke-width="2" stroke-dasharray="8 14" stroke-linecap="round"/>
      <path d="M770 78 L790 92 L772 98 Z" fill="#FF6B4A"/>
    </svg>
  </div>

  <div class="max-w-7xl mx-auto px-6 lg:px-10 relative">
    <!-- ===== HEADER ===== -->
    <div class="text-center max-w-4xl mx-auto mb-14 lg:mb-20">
      <div class="ribbon-lux ribbon-lux-on-dark mb-6">
        <span class="w-2.5 h-2.5 rounded-full bg-gold pin-pulse"></span>
        <span>✈️ Since 2005 · Two Decades Crafting Safe Pet Journeys</span>
      </div>
      <h2 class="font-display text-cream text-4xl sm:text-5xl lg:text-6xl font-semibold leading-[1.08] mb-7">
        The BeethovenPets<br>
        <span class="italic text-coral">Story</span>
      </h2>
      <p class="font-editorial italic text-xl text-cream/92 leading-relaxed max-w-3xl mx-auto" style="text-shadow:0 1px 2px rgba(0,0,0,0.35);">
        From a single office with one phone line and a promise — to a trusted global concierge
        trusted by pet families across six continents.
      </p>
    </div>

    <!-- ===== BIG STORY BLOCKS: 20 YEARS + 50 COUNTRIES ===== -->
    <div class="grid lg:grid-cols-2 gap-8 mb-16 lg:mb-20">
      <!-- CARD 1: 20+ YEARS HERITAGE -->
      <div class="rounded-[32px] p-8 lg:p-10 bg-gradient-to-br from-white/[0.08] to-white/[0.02] border border-white/10 backdrop-blur-md text-cream card-hover relative overflow-hidden">
        <div class="absolute -top-24 -right-24 w-64 h-64 rounded-full bg-coral/15 blur-3xl"></div>
        <div class="relative">
          <div class="flex items-start justify-between mb-7">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-coral to-[#FF8769] flex items-center justify-center shadow-lg shadow-coral/30">
              <svg viewBox="0 0 24 24" fill="#FBF7F0"><circle cx="12" cy="12" r="9" fill="none" stroke="#FBF7F0" stroke-width="2" stroke-linecap="round"/><path d="M12 7v5l3.5 2" fill="none" stroke="#FBF7F0" stroke-width="2" stroke-linecap="round"/></svg>
            </div>
            <div class="text-right">
              <div class="font-display text-6xl lg:text-7xl font-bold text-transparent bg-clip-text bg-gradient-to-br from-coral via-[#FFB19E] to-gold leading-none">
                <span class="stat-num" data-count="20">0</span><span class="text-coral">+</span>
              </div>
              <div class="text-xs font-bold tracking-[0.2em] uppercase text-cream/50 mt-2">Years of Experience</div>
            </div>
          </div>
          <h3 class="font-display text-2xl lg:text-3xl font-semibold mb-4 text-cream">Two Decades of Dedicated Pet Relocation</h3>
          <div class="space-y-4 text-cream/92 leading-relaxed text-[15px]" style="text-shadow:0 1px 2px rgba(0,0,0,0.25);">
            <p>
              <strong class="text-cream">Founded in 2005</strong> with a single vision: that no pet should ever travel like cargo. What began as a small family-run operation in Jakarta — answering late-night calls from anxious owners about permits, crates, and emotional goodbyes at the airport — has slowly and deliberately grown into the region's most trusted pet relocation concierge.
            </p>
            <p>
              In those early years, we learned what would become our signature standard: hand-delivered progress photos, vet-accompanied transfers, a 24/7 human on the other end of the line, and — above all — treating every animal as if it carried our own family name. We survived pandemics, border closures, airline policy shifts, and the heartbreaking last-minute reroutes because we simply refused to leave a pet behind.
            </p>
            <p class="pt-2 font-editorial italic text-cream/85 border-l-2 border-gold/60 pl-4" style="text-shadow:0 1px 2px rgba(0,0,0,0.3);">
              "Every puppy, every kitten, every senior dog — they all deserve the same care we would give our own. That promise hasn't changed in twenty years, and it never will."
            </p>
          </div>
        </div>
      </div>

      <!-- CARD 2: 50+ COUNTRIES GLOBAL REACH -->
      <div class="rounded-[32px] p-8 lg:p-10 bg-gradient-to-br from-teal/[0.10] via-white/[0.04] to-white/[0.02] border border-white/10 backdrop-blur-md text-cream card-hover relative overflow-hidden">
        <div class="absolute -top-24 -left-24 w-64 h-64 rounded-full bg-teal/15 blur-3xl"></div>
        <div class="relative">
          <div class="flex items-start justify-between mb-7">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-teal to-[#73CDC2] flex items-center justify-center shadow-lg shadow-teal/30">
              <svg viewBox="0 0 24 24" fill="#FBF7F0"><circle cx="12" cy="12" r="9" fill="none" stroke="#FBF7F0" stroke-width="2"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" fill="none" stroke="#FBF7F0" stroke-width="2" stroke-linecap="round"/></svg>
            </div>
            <div class="text-right">
              <div class="font-display text-6xl lg:text-7xl font-bold text-transparent bg-clip-text bg-gradient-to-br from-teal via-[#A7E2DC] to-gold leading-none">
                <span class="stat-num" data-count="50">0</span><span class="text-teal">+</span>
              </div>
              <div class="text-xs font-bold tracking-[0.2em] uppercase text-cream/50 mt-2">Countries & Territories</div>
            </div>
          </div>
          <h3 class="font-display text-2xl lg:text-3xl font-semibold mb-4 text-cream">From Jakarta to the World · A Truly Global Network</h3>
          <div class="space-y-4 text-cream/92 leading-relaxed text-[15px]" style="text-shadow:0 1px 2px rgba(0,0,0,0.25);">
            <p>
              Today, our paws touch <strong class="text-cream">six continents</strong> and a carefully curated partner network of IATA-certified agencies, USDA-accredited veterinarians, and pet-loving ground handlers in over <strong class="text-cream">50+ countries</strong>. We don't just ship pets — we design door-to-door, end-to-end journeys across the globe.
            </p>
            <p>
              From the neon streets of <span class="text-teal font-semibold">Tokyo</span> to the avenues of <span class="text-gold font-semibold">Paris</span>, from the beaches of <span class="text-coral font-semibold">Bali</span> to the skylines of <span class="text-[#FFB19E] font-semibold">Dubai</span>, from <span class="text-cream font-semibold">Sydney</span> harbor to <span class="text-teal font-semibold">Seoul</span>, from <span class="text-gold font-semibold">London</span> to <span class="text-coral font-semibold">Los Angeles</span> — we have personally overseen pet arrivals and built relationships that go far beyond paperwork. We know which vets in Amsterdam speak your language. We know which pet hotels in Frankfurt take extra walks. We know the quietest tarmac transfer teams in Singapore Changi. This is not logistics — it is a living, breathing network of people who love animals.
            </p>
          </div>

          <!-- Destination chips -->
          <div class="mt-7 flex flex-wrap gap-2">
            <?php
              $countries = ['🇯🇵 Tokyo','🇰🇷 Seoul','🇸🇬 Singapore','🇹🇭 Bangkok','🇭🇰 Hong Kong','🇨🇳 Shanghai','🇬🇧 London','🇫🇷 Paris','🇩🇪 Berlin','🇳🇱 Amsterdam','🇮🇹 Rome','🇪🇸 Madrid','🇺🇸 New York','🇺🇸 Los Angeles','🇨🇦 Toronto','🇦🇺 Sydney','🇳🇿 Auckland','🇦🇪 Dubai','🇶🇦 Doha','🇸🇦 Riyadh','🇹🇷 Istanbul','🇿🇦 Johannesburg','🇧🇷 São Paulo','🇲🇽 Mexico City','🇲🇾 Kuala Lumpur','🇻🇳 Hanoi','🇵🇭 Manila','🇮🇳 Mumbai','🇦🇪 Abu Dhabi','🇩🇰 Copenhagen','🇸🇪 Stockholm','🇨🇭 Zurich','🇦🇹 Vienna','🇵🇱 Warsaw','🇨🇿 Prague','🇭🇺 Budapest','🇷🇴 Bucharest','🇫🇮 Helsinki','🇳🇴 Oslo','🇮🇸 Reykjavik','🇵🇹 Lisbon','🇬🇷 Athens','🇪🇬 Cairo','🇲🇦 Casablanca','🇰🇪 Nairobi','🇱🇰 Colombo','🇲🇴 Macau','🇹🇼 Taipei','🇲🇳 Ulaanbaatar','🇫🇯 Suva'];
              shuffle($countries);
              $show = array_slice($countries, 0, 28);
              foreach($show as $c):
            ?>
            <span class="text-[11px] font-semibold px-3 py-1.5 rounded-full bg-white/[0.08] border border-white/12 text-cream/88 hover:bg-coral/25 hover:text-cream transition"><?=$c?></span>
            <?php endforeach; ?>
            <span class="text-[11px] font-bold px-3 py-1.5 rounded-full bg-gradient-to-r from-coral/30 to-gold/30 border border-gold/40 text-cream">+ 22 more destinations</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== MILESTONE / PILLARS ROW ===== -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-16 lg:mb-20">
      <div class="stat-card rounded-[22px] bg-gradient-to-b from-white/[0.10] to-white/[0.02] border border-white/15 py-8 px-5 text-center">
        <div class="font-display text-6xl lg:text-7xl font-bold mb-3 text-transparent bg-clip-text bg-gradient-to-br from-coral via-[#FF9B86] to-gold leading-none">
          <span class="stat-num" data-count="500">0</span><span class="text-coral text-4xl lg:text-5xl ml-1">+</span>
        </div>
        <div class="text-[12px] lg:text-[13px] font-extrabold text-cream uppercase tracking-[0.18em] mt-2">Happy Pets Relocated</div>
      </div>
      <div class="stat-card rounded-[22px] bg-gradient-to-b from-white/[0.10] to-white/[0.02] border border-white/15 py-8 px-5 text-center">
        <div class="font-display text-6xl lg:text-7xl font-bold mb-3 text-transparent bg-clip-text bg-gradient-to-br from-teal via-[#73CDC2] to-[#A7E2DC] leading-none">
          <span class="stat-num" data-count="100">0</span><span class="text-teal text-4xl lg:text-5xl ml-1">%</span>
        </div>
        <div class="text-[12px] lg:text-[13px] font-extrabold text-cream uppercase tracking-[0.18em] mt-2">IATA & Vet Certified</div>
      </div>
      <div class="stat-card rounded-[22px] bg-gradient-to-b from-white/[0.10] to-white/[0.02] border border-white/15 py-8 px-5 text-center">
        <div class="font-display text-6xl lg:text-7xl font-bold mb-3 text-transparent bg-clip-text bg-gradient-to-br from-gold via-[#E6D3A6] to-[#FFF1CC] leading-none">
          <span class="stat-num">24/7</span>
        </div>
        <div class="text-[12px] lg:text-[13px] font-extrabold text-cream uppercase tracking-[0.18em] mt-2">Live Journey Support</div>
      </div>
      <div class="stat-card rounded-[22px] bg-gradient-to-b from-white/[0.10] to-white/[0.02] border border-white/15 py-8 px-5 text-center">
        <div class="font-display text-6xl lg:text-7xl font-bold mb-3 text-transparent bg-clip-text bg-gradient-to-br from-[#FFB19E] via-[#FF9B86] to-coral leading-none">
          <span class="stat-num" data-count="100">0</span><span class="text-[#FFB19E] text-4xl lg:text-5xl ml-1">%</span>
        </div>
        <div class="text-[12px] lg:text-[13px] font-extrabold text-cream uppercase tracking-[0.18em] mt-2">Safety Delivery Record</div>
      </div>
    </div>

    <!-- ===== CUSTOMER STORIES / TESTIMONIALS ===== -->
    <div class="text-center mb-12">
      <div class="ribbon-lux ribbon-lux-on-dark mb-5">
        <span class="w-2.5 h-2.5 rounded-full bg-coral pin-pulse"></span>
        <span>💛 Real Stories · From Real Pet Families</span>
      </div>
      <h3 class="font-display text-cream text-3xl lg:text-4xl font-semibold mb-3">
        Stories That Keep <span class="italic text-coral">Waggin'</span>
      </h3>
    </div>

    <div class="grid md:grid-cols-3 gap-7">
      <?php
        $t = [
          ['name'=>'Amelia Sutanto','route'=>'Jakarta → Los Angeles','pet'=>'Samoyed · "Sky"','text'=>'I was terrified shipping our Samoyed to LA for my husband\'s job. BeethovenPets sent photos every 2 hours, handled the 6-month rabies wait, and Sky was bouncing at LAX arrival like it was just a spa day. Worth every penny.','rating'=>5],
          ['name'=>'Robert Chua','route'=>'Singapore → Bali','pet'=>'French Bulldog · "Milo"','text'=>'Frenchies are high-risk cargo and 3 airlines rejected us. BeethovenPets has a dedicated brachycephalic protocol and arranged a private pet-friendly jet segment. Milo arrived in Denpasar perfectly calm. This is concierge-level stuff.','rating'=>5],
          ['name'=>'Dewi Anggraini','route'=>'Surabaya → Seoul','pet'=>'Golden Retriever · "Luna"','text'=>'Our family moved to Korea for 2 years. The team handled the 5-hour van to Jakarta, overnight vet, import permit, KAL pet cargo, and a 2-week Seoul quarantine pickup. I never lifted a finger. Luna\'s tail has not stopped wagging.','rating'=>5],
        ];
        foreach($t as $tm):
      ?>
      <div class="rounded-[28px] p-8 lg:p-9 bg-gradient-to-br from-white/[0.06] to-white/[0.02] border border-white/10 backdrop-blur-md text-cream card-hover relative">
        <div class="quote-mark absolute top-4 right-6 text-cream/25 font-display text-6xl leading-none">"</div>
        <div class="flex mb-5">
          <?php for($i=0;$i<$tm['rating'];$i++): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="#FF6B4A" style="margin-right:2px"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <?php endfor; ?>
        </div>
        <p class="font-editorial italic text-xl leading-relaxed mb-7 text-cream/92" style="text-shadow:0 1px 3px rgba(0,0,0,0.25);"><?=$tm['text']?></p>
        <div class="divider-gold mb-5"></div>
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-coral to-gold flex items-center justify-center font-display text-xl font-bold text-cream">
            <?=strtoupper($tm['name'][0])?>
          </div>
          <div>
            <div class="font-bold text-cream" style="text-shadow:0 1px 2px rgba(0,0,0,0.3);"><?=$tm['name']?></div>
            <div class="text-xs text-cream/70 font-semibold" style="text-shadow:0 1px 2px rgba(0,0,0,0.25);"><?=$tm['route']?> · <?=$tm['pet']?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<!-- ===== CTA / FAQ ===== -->
<section id="faq" class="py-20 lg:py-28 relative overflow-hidden">
  <!-- Pet decor around FAQ section -->
  <div class="pet-deco paw-md knead-paw" style="top:10%;left:3%;">
    <svg viewBox="0 0 24 24" fill="rgba(201,169,98,0.35)"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco paw-sm knead-paw" style="top:18%;right:4%;animation-delay:0.8s;">
    <svg viewBox="0 0 24 24" fill="rgba(255,107,74,0.32)"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco bone-sm rotate-slow" style="bottom:8%;left:5%;opacity:0.28;">
    <svg viewBox="0 0 24 24" fill="#C9A962"><path d="M4.7 11.3c-.5-.5-.5-1.2 0-1.7L8 6.3C7.8 5.6 8.3 5 9 5c.3 0 .6.1.8.3l1.8 1.1 2.8-.2c.5 0 1 .2 1.4.5L17.7 8.3l.5-1c.2-.4.6-.6 1-.6 1 0 1.7.8 1.7 1.7 0 .4-.2.8-.6 1l-1 .5 1.7 2.7c.3.4.5.9.5 1.4 0 .5-.1 1-.4 1.4l-.2 2.8 1.1 1.8c.2.2.3.5.3.8 0 .7-.6 1.2-1.3 1.2-.4 0-.7-.1-1-.3l-1-.5-2.7 1.7c-.4.3-.9.5-1.4.5-.5 0-1-.1-1.4-.4l-2.8-.2-1.1 1.8c-.2.2-.5.3-.8.3-.7 0-1.2-.6-1.2-1.3 0-.3.1-.6.3-.8l1.1-1.8-.2-2.8c-.3-.4-.4-.9-.4-1.4 0-.5.1-1 .4-1.4l1.7-2.7-1-.5c-.4-.2-.6-.6-.6-1 0-.7.6-1.4 1.4-1.4z"/></svg>
  </div>
  <div class="pet-deco yarn-sm yarn-roll" style="bottom:14%;right:6%;opacity:0.35;animation-direction:reverse;">
    <svg viewBox="0 0 24 24" fill="#4FB3A8"><circle cx="12" cy="12" r="9" fill="none" stroke="#4FB3A8" stroke-width="2"/><path d="M4 12 Q12 4 20 12 Q12 20 4 12" fill="none" stroke="#4FB3A8" stroke-width="1.7" stroke-linecap="round"/><path d="M12 3 Q12 12 12 21" fill="none" stroke="#4FB3A8" stroke-width="1.4" stroke-linecap="round"/></svg>
  </div>
  <div class="pet-deco fish-sm fish-swim" style="top:40%;left:1.5%;animation-delay:1.1s;">
    <svg viewBox="0 0 24 14" fill="rgba(255,107,74,0.4)"><path d="M16 7 Q11 2 5 4 Q2 5 2 7 Q2 9 5 10 Q11 12 16 7Z"/><path d="M17 7 L22 2 L22 12 Z" fill="rgba(255,107,74,0.4)"/><circle cx="7.5" cy="6.8" r="1" fill="#0F2747" opacity="0.4"/></svg>
  </div>
  <div class="pet-deco sparkle-sm sparkle-burst" style="top:60%;right:2%;animation-delay:0.9s;">
    <svg viewBox="0 0 24 24" fill="#C9A962"><polygon points="12,2 14,10 22,10 16,15 18,23 12,18 6,23 8,15 2,10 10,10"/></svg>
  </div>
  <div class="pet-deco treat-sm treat-drop" style="top:4%;right:20%;animation-delay:1.3s;">
    <svg viewBox="0 0 24 24" fill="#4FB3A8"><circle cx="12" cy="12" r="4.5" opacity="0.5"/></svg>
  </div>
  <!-- Dog silhouette -->
  <div class="pet-deco dog-sil pet-silhouette" style="bottom:4%;right:14%;animation-delay:0.7s;">
    <svg viewBox="0 0 30 28" fill="#0F2747"><path d="M5 10 Q2 5 6 4 Q10 3 12 6 L18 6 Q20 3 24 4 Q28 5 25 10 L26 16 L24 22 L20 24 L15 22 L10 24 L6 22 L4 16 Z" opacity="0.08"/><circle cx="11" cy="13" r="1" fill="#0F2747" opacity="0.3"/><circle cx="19" cy="13" r="1" fill="#0F2747" opacity="0.3"/><ellipse cx="15" cy="17" rx="1.5" ry="1" fill="#0F2747" opacity="0.2"/></svg>
  </div>

  <div class="max-w-6xl mx-auto px-6 lg:px-10">
    <div class="card-lux p-10 lg:p-16 relative overflow-hidden">
      <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-coral/8 blur-3xl"></div>
      <div class="absolute -bottom-20 -left-20 w-80 h-80 rounded-full bg-teal/8 blur-3xl"></div>

      <div class="grid lg:grid-cols-2 gap-12 items-center relative">
        <div>
          <h2 class="font-display text-navy text-4xl lg:text-5xl font-semibold leading-[1.08] mb-5">
            Have Questions About<br>
            <span class="italic text-coral">Your Pet's Relocation?</span>
          </h2>
          <p class="text-muted text-lg leading-relaxed mb-8">
            Chat with our concierge team on WhatsApp. We typically reply in under 10 minutes. Available 24/7 for urgent pet travel needs.
          </p>
          <div class="flex flex-wrap gap-4">
            <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%27d%20like%20to%20ask%20about%20pet%20relocation%20services." target="_blank" class="btn btn-primary">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
              Chat on WhatsApp · <?=e(waNumber())?>
            </a>
            <?php $_ceBtn = getSetting('contact_email',''); if (!$_ceBtn) $_ceBtn = SITE_EMAIL; ?>
            <a href="mailto:<?=e($_ceBtn)?>" class="btn btn-outline">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              Email Us
            </a>
          </div>
        </div>

        <div class="space-y-4">
          <?php
            $faqs = [
              ['q'=>'Are pets allowed in-cabin or cargo?','a'=>'For most international flights, pets over 8kg travel in climate-controlled, pressurized IATA cargo holds — identical to passenger cabins. For small pets (<8kg) on select routes, in-cabin travel is available with our chaperone upgrade.'],
              ['q'=>'How long is the documentation process?','a'=>'Domestic relocations: 1-3 business days. International: 2-6 weeks depending on destination (rabies titre tests, import permits, quarantine rules). We start immediately upon booking.'],
              ['q'=>'What if my pet has special needs?','a'=>'We handle everything from brachycephalic protocols to senior pets, anxiety management, dietary requirements, and medication. Just inform us on the quote form and our team will custom-design the journey.'],
              ['q'=>'Do you offer live tracking?','a'=>'Yes — every booking gets a private tracking URL with GPS, photo updates, and optional live video check-ins. You also get a WhatsApp broadcast group with your dedicated concierge.'],
            ];
            foreach($faqs as $f):
          ?>
          <details class="group rounded-2xl border border-cream-3 bg-white/60 p-6 open:shadow-lg open:border-coral/40 transition-all">
            <summary class="flex items-start justify-between gap-6 cursor-pointer list-none">
              <span class="font-bold text-navy text-base leading-snug"><?=$f['q']?></span>
              <span class="w-9 h-9 rounded-full bg-coral/10 flex items-center justify-center flex-shrink-0 group-open:bg-coral group-open:text-white transition-all">
                <svg class="group-open:rotate-45 transition-transform" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FF6B4A" stroke-width="3" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              </span>
            </summary>
            <p class="pt-5 text-muted leading-relaxed"><?=$f['a']?></p>
          </details>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== FOLLOW US · SOCIAL MEDIA (with TIKTOK) ===== -->
<section class="py-[72px] lg:py-24 bg-gradient-to-br from-cream-2 via-cream to-[#FDF5E6] relative overflow-hidden">
  <div class="pet-deco paw-md knead-paw" style="top:9%;left:4%;opacity:0.28;">
    <svg viewBox="0 0 24 24" fill="#FF6B4A"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco fish-md fish-swim" style="bottom:10%;right:7%;opacity:0.4;">
    <svg viewBox="0 0 24 14" fill="#4FB3A8"><path d="M16 7 Q11 2 5 4 Q2 5 2 7 Q2 9 5 10 Q11 12 16 7Z"/><path d="M17 7 L22 2 L22 12 Z" fill="#4FB3A8"/></svg>
  </div>
  <div class="max-w-6xl mx-auto px-6 lg:px-10 relative">
    <div class="grid lg:grid-cols-2 gap-10 items-center">
      <div>
        <div class="eyebrow mb-5 inline-flex items-center gap-2 text-navy/70 bg-white/80 px-4 py-1.5 rounded-full border border-navy/10 shadow-sm"><span class="w-2 h-2 rounded-full bg-gold pin-pulse"></span> Follow Our Journey</div>
        <h2 class="font-display text-navy text-4xl sm:text-5xl font-semibold leading-tight mb-5">Follow Us & <span class="italic text-gold font-editorial">See the cutest pets live</span> our journeys</h2>
        <p class="text-muted text-lg leading-relaxed mb-8">Behind-the-scenes airport pickups, happy pet arrivals, global travel tips & IATA-certified team adventures. Follow us across all platforms, get cute daily updates!</p>
        <!-- Social Chips - baca dari settings admin via socialLink() -->
        <div class="flex flex-wrap gap-3">
          <?php
            $socials = [
              ['name'=>'instagram','label'=>'Instagram','bg'=>'linear-gradient(135deg,#833AB4 0%,#FD1D1D 50%,#F77737 75%,#FCAF45 100%)','icon'=>'<svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>'],
              ['name'=>'tiktok',   'label'=>'TikTok',   'bg'=>'linear-gradient(135deg,#000000 0%,#25F4EE 45%,#FE2C55 100%)','icon'=>'<svg viewBox="0 0 24 24" fill="white"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-.88-.07A6.33 6.33 0 005.4 15.14a6.35 6.35 0 0010.85 4.41 6.31 6.31 0 003.77-5.68v-2.26a8.15 8.15 0 004.78 1.52V9.45a4.84 4.84 0 01-5.21-5.32z"/></svg>'],
              ['name'=>'facebook', 'label'=>'Facebook', 'bg'=>'linear-gradient(135deg,#1877F2 0%,#0A5BD4 100%)','icon'=>'<svg viewBox="0 0 24 24" fill="white"><path d="M22 12.07C22 6.5 17.52 2 12 2S2 6.5 2 12.07c0 5.02 3.66 9.16 8.44 9.93v-7H7.03h-2.81v-2.9h2.81V9.41c0-2.78 1.66-4.3 4.18-4.3 1.21 0 2.47.22 2.47.22v2.71H20.6c-1.38 0-1.81.85-1.81 1.73v2.08h3.07l-.49 2.9h-2.58v7A10 10 0 0022 12.07z"/></svg>'],
              ['name'=>'youtube',  'label'=>'YouTube',  'bg'=>'linear-gradient(135deg,#FF0000 0%,#CC0000 100%)','icon'=>'<svg viewBox="0 0 24 24" fill="white"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>'],
              ['name'=>'whatsapp', 'label'=>'WhatsApp', 'bg'=>'linear-gradient(135deg,#25D366 0%,#128C7E 100%)','icon'=>'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.12 1.03 6.986 2.89a9.826 9.826 0 012.888 6.979c-.002 5.445-4.437 9.88-9.889 9.88z"/></svg>'],
              ['name'=>'linkedin', 'label'=>'LinkedIn', 'bg'=>'linear-gradient(135deg,#0A66C2 0%,#004182 100%)','icon'=>'<svg viewBox="0 0 24 24" fill="white"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.268 2.37 4.268 5.455v6.286zM5.337 7.433a2.062 2.062 0 110-4.124 2.063 2.063 0 010 4.124zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>'],
            ];
            foreach($socials as $sc):
              $lnk = socialLink($sc['name']);
              $nolink = ($lnk === '#');
              $chipStyle = 'background:' . $sc['bg'] . ';';
              if ($nolink) {
                  $chipStyle .= 'opacity:0.55;cursor:pointer;';
              } else {
                  $chipStyle .= 'filter:none;';
              }
          ?>
            <a href="<?=e($lnk)?>" target="_blank" rel="noopener" class="group inline-flex items-center gap-2.5 rounded-2xl px-5 py-3 text-white font-bold text-sm shadow-lg shadow-navy/20 hover:-translate-y-0.5 hover:shadow-xl transition-all duration-300" style="<?=e($chipStyle)?>">
              <span class="w-5 h-5 flex items-center justify-center"><?=$sc['icon']?></span>
              <span class="tracking-wide"><?=$sc['label']?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <div class="mt-7 text-[12px] text-muted/80 leading-snug">
          ℹ️  Update links di halaman Admin → <strong>Settings → 📱 Social Media URLs</strong>. Semua perubahan langsung tampil LIVE di website.
        </div>
      </div>
      <!-- Right: social preview collage with pet pics · UNSPLASH direct URL agar PASTI foto HEWAN -->
      <div class="relative grid grid-cols-2 gap-3 pl-4 md:pl-10">
        <div class="space-y-3">
          <div class="aspect-[4/5] rounded-[22px] overflow-hidden shadow-xl shadow-navy/15 bg-cream-3">
            <img src="https://images.unsplash.com/photo-1558788353-f76d92427f16?w=700&auto=format&fit=crop&q=75" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1450778869180-41d0601e046e?w=700&auto=format&fit=crop&q=75';" alt="Golden Retriever with owner at airport pickup" class="w-full h-full object-cover transition duration-500 hover:scale-105">
          </div>
          <div class="aspect-square rounded-2xl overflow-hidden shadow-lg shadow-navy/10 bg-cream-3">
            <img src="https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=600&auto=format&fit=crop&q=75" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1583511655826-05700d52f4d9?w=600&auto=format&fit=crop&q=75';" alt="Persian kitten in soft carrier bag with owner" class="w-full h-full object-cover">
          </div>
        </div>
        <div class="space-y-3 pt-10">
          <div class="aspect-square rounded-2xl overflow-hidden shadow-lg shadow-navy/10 bg-cream-3">
            <img src="https://images.unsplash.com/photo-1612774412771-005ed8e861d2?w=600&auto=format&fit=crop&q=75" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1583337130417-3346a1be7dee?w=600&auto=format&fit=crop&q=75';" alt="Happy corgi puppy being carried by owner at home arrival" class="w-full h-full object-cover">
          </div>
          <div class="aspect-[4/5] rounded-[22px] overflow-hidden shadow-xl shadow-navy/15 bg-cream-3">
            <img src="https://images.unsplash.com/photo-1605568427561-40dd23c2acea?w=700&auto=format&fit=crop&q=75" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1544568100-847a948585b9?w=700&auto=format&fit=crop&q=75';" alt="Husky dog reunited with owner hugging outside home" class="w-full h-full object-cover">
          </div>
        </div>
        <!-- Floating decorative badges -->
        <div class="absolute -left-2 top-1/3 card-lux py-2 px-4 flex items-center gap-2 shadow-2xl shadow-gold/20 float-slow">
          <span class="w-7 h-7 rounded-full bg-gradient-to-br from-[#FF0050] to-[#25F4EE] flex items-center justify-center text-white text-xs font-bold">♪</span>
          <span class="text-xs font-bold text-navy">Trending · 12.8K</span>
        </div>
        <div class="absolute -right-2 bottom-8 card-lux py-2 px-4 flex items-center gap-2 shadow-2xl shadow-navy/15 float">
          <span class="w-7 h-7 rounded-full bg-gradient-to-br from-[#833AB4] to-[#FCAF45] flex items-center justify-center text-white"><svg width="12" height="12" viewBox="0 0 24 24" fill="white" fill-opacity="0.9"><path d="M12 2.2c3.2 0 3.6 0 4.85.07 1.17.05 1.8.25 2.22.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.05.41 2.22.06 1.26.07 1.66.07 4.82s0 3.56-.07 4.82c-.05 1.17-.25 1.8-.41 2.22a3.7 3.7 0 01-.9 1.38 3.7 3.7 0 01-1.38.9c-.42.16-1.05.36-2.22.41-1.26.06-1.66.07-4.85.07s-3.6 0-4.85-.07c-1.17-.05-1.8-.25-2.22-.41a3.77 3.77 0 01-1.38-.9 3.7 3.7 0 01-.9-1.38c-.16-.42-.36-1.05-.41-2.22C2.2 15.56 2.2 15.16 2.2 12s0-3.56.07-4.82c.05-1.17.25-1.8.41-2.22.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.05-.36 2.22-.41C8.44 2.2 8.84 2.2 12 2.2zm0 1.8c-3.14 0-3.52.01-4.74.07-.99.04-1.53.21-1.89.35-.48.19-.82.41-1.18.77-.36.36-.58.7-.77 1.18-.14.36-.31.9-.35 1.89C3.01 8.48 3 8.86 3 12s.01 3.52.07 4.74c.04.99.21 1.53.35 1.89.19.48.41.82.77 1.18.36.36.7.58 1.18.77.36.14.9.31 1.89.35 1.22.06 1.6.07 4.74.07s3.52-.01 4.74-.07c.99-.04 1.53-.21 1.89-.35a3 3 0 001.18-.77 3 3 0 00.77-1.18c.14-.36.31-.9.35-1.89.06-1.22.07-1.6.07-4.74s-.01-3.52-.07-4.74c-.04-.99-.21-1.53-.35-1.89a3 3 0 00-.77-1.18 3 3 0 00-1.18-.77c-.36-.14-.9-.31-1.89-.35C15.52 4.01 15.14 4 12 4zm0 3.06a4.94 4.94 0 110 9.88 4.94 4.94 0 010-9.88zm0 1.8a3.14 3.14 0 100 6.28 3.14 3.14 0 000-6.28zm5.15-2.97a1.15 1.15 0 11-2.3 0 1.15 1.15 0 012.3 0z"/></svg></span>
          <span class="text-xs font-bold text-navy">52.4K Followers</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="bg-navy text-cream pt-20 pb-10 relative overflow-hidden">
  <div class="absolute -top-32 right-0 w-[600px] h-[600px] rounded-full bg-coral/5 blur-3xl"></div>

  <!-- Footer pet decorations: subtle paws, yarn, silhouettes -->
  <div class="pet-deco paw-sm knead-paw" style="top:8%;left:3%;opacity:0.22;animation-duration:4s;">
    <svg viewBox="0 0 24 24" fill="#FBF7F0"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco paw-md tiny-bounce" style="top:18%;right:5%;opacity:0.18;animation-delay:0.7s;">
    <svg viewBox="0 0 24 24" fill="#C9A962"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/><path d="M8 19.5v-3.3c-3 .5-6 1-6 5.5 0 1.5 1.5 2 3 2 2.5 0 3-1.2 3-4.2z"/></svg>
  </div>
  <div class="pet-deco yarn-sm yarn-roll" style="top:55%;left:2%;opacity:0.18;animation-duration:18s;animation-direction:reverse;">
    <svg viewBox="0 0 24 24" fill="#FBF7F0"><circle cx="12" cy="12" r="9" fill="none" stroke="#FBF7F0" stroke-width="1.8"/><path d="M4 12 Q12 4 20 12 Q12 20 4 12" fill="none" stroke="#FBF7F0" stroke-width="1.5" stroke-linecap="round"/><path d="M12 3 Q12 12 12 21" fill="none" stroke="#FBF7F0" stroke-width="1.3" stroke-linecap="round"/></svg>
  </div>
  <div class="pet-deco fish-sm fish-swim" style="top:40%;right:3%;opacity:0.2;animation-delay:1.6s;">
    <svg viewBox="0 0 24 14" fill="#FFB19E"><path d="M16 7 Q11 2 5 4 Q2 5 2 7 Q2 9 5 10 Q11 12 16 7Z"/><path d="M17 7 L22 2 L22 12 Z" fill="#FFB19E"/><circle cx="7.5" cy="6.8" r="1" fill="#0F2747" opacity="0.6"/></svg>
  </div>
  <div class="pet-deco sparkle-sm sparkle-burst" style="bottom:25%;left:8%;animation-delay:1.1s;opacity:0.55;">
    <svg viewBox="0 0 24 24" fill="#FF6B4A"><polygon points="12,2 14,10 22,10 16,15 18,23 12,18 6,23 8,15 2,10 10,10"/></svg>
  </div>
  <div class="pet-deco sparkle-sm sparkle-burst" style="top:55%;right:14%;animation-delay:0.3s;opacity:0.55;">
    <svg viewBox="0 0 24 24" fill="#C9A962"><polygon points="12,2 14,10 22,10 16,15 18,23 12,18 6,23 8,15 2,10 10,10"/></svg>
  </div>
  <!-- Cat silhouette bottom-left -->
  <div class="pet-deco cat-sil pet-silhouette" style="bottom:3%;left:1.5%;animation-delay:2s;">
    <svg viewBox="0 0 24 30" fill="#FBF7F0"><path d="M4 10 L6 2 L10 7 L14 7 L18 2 L20 10 L22 20 L20 26 L4 26 L2 20 Z" opacity="0.09"/><circle cx="9.5" cy="16" r="1.2" fill="#FBF7F0" opacity="0.3"/><circle cx="14.5" cy="16" r="1.2" fill="#FBF7F0" opacity="0.3"/></svg>
  </div>
  <!-- Dog silhouette top-right -->
  <div class="pet-deco dog-sil pet-silhouette" style="top:12%;right:22%;animation-delay:0.8s;">
    <svg viewBox="0 0 30 28" fill="#FBF7F0"><path d="M5 10 Q2 5 6 4 Q10 3 12 6 L18 6 Q20 3 24 4 Q28 5 25 10 L26 16 L24 22 L20 24 L15 22 L10 24 L6 22 L4 16 Z" opacity="0.08"/><circle cx="11" cy="13" r="1" fill="#FBF7F0" opacity="0.25"/><circle cx="19" cy="13" r="1" fill="#FBF7F0" opacity="0.25"/></svg>
  </div>

  <div class="max-w-7xl mx-auto px-6 lg:px-10 relative">
    <div class="grid md:grid-cols-2 lg:grid-cols-5 gap-10 lg:gap-8 mb-16">
      <div class="lg:col-span-2">
        <div class="flex items-center gap-5 mb-6">
          <!-- CUSTOM FOOTER LOGO (if uploaded) OR default inverted SVG -->
          <div id="footerLogoBox" class="w-[84px] h-[84px] sm:w-[92px] sm:h-[92px] rounded-[24px] bg-gradient-to-br from-[#172F52] via-[#0F2747] to-[#1E3A5F] flex items-center justify-center shadow-2xl border border-coral/30">
            <?php if (!empty($logoFooterUrl)): ?>
              <img id="footerLogoImg" src="<?=e($logoFooterUrl)?>"
                   data-fallback-url="<?=e($logoFooterUrlFallback ?? '')?>"
                   data-fallback-inline='<?= json_encode('<svg viewBox="0 0 220 200" width="82" height="76" xmlns="http://www.w3.org/2000/svg"><circle cx="110" cy="85" r="62" fill="#25A7C4"/><path d="M70 48 Q 62 38 76 32 Q 94 26 112 32 Q 136 28 150 42 Q 162 58 156 72 Z" fill="white"/><path d="M30 160 Q 8 108 40 52 Q 70 8 120 20" fill="none" stroke="#FBF7F0" stroke-width="16" stroke-linecap="round"/></svg>') ?>'
                   alt="BeethovenPets Footer Logo"
                   class="max-w-[94%] max-h-[94%] w-auto h-auto object-contain block"
              >
            <?php else: ?>
            <svg viewBox="0 0 220 200" width="82" height="76" xmlns="http://www.w3.org/2000/svg">
              <!-- ===== OUTER ORBIT SWOOSH (light cream, bottom left) ===== -->
              <path d="M45 155 Q 5 110 30 65 Q 50 28 95 20" fill="none" stroke="#FBF7F0" stroke-width="14" stroke-linecap="round" opacity="0.88"/>
              <!-- ===== OUTER ORBIT SWOOSH (coral, bottom right) ===== -->
              <path d="M60 168 Q 120 195 185 155 Q 215 118 205 70 Q 198 38 170 22" fill="none" stroke="#FF6B4A" stroke-width="14" stroke-linecap="round" opacity="0.95"/>
              <!-- ===== GLOBE (teal solid with white continents) ===== -->
              <circle cx="110" cy="85" r="62" fill="#2AB3D2"/>
              <!-- White continent: Greenland / Canada top -->
              <path d="M68 48 Q 62 38 72 32 Q 84 25 94 28 Q 104 24 112 30 Q 120 28 128 36 Q 136 30 145 38 Q 154 44 156 56 Q 162 52 165 62 Q 160 68 155 70 L 148 66 Q 145 78 138 80 Q 135 86 128 88 L 125 84 Q 118 94 110 96 Q 100 94 98 88 Q 88 92 82 86 Q 78 90 72 84 Q 68 76 68 68 Q 60 66 60 58 Z" fill="#FFFFFF"/>
              <!-- White continent: Africa / Middle East -->
              <path d="M118 96 Q 124 100 122 108 Q 126 116 120 122 Q 118 130 112 134 Q 108 140 102 136 L 98 130 Q 94 126 96 120 Q 92 114 96 108 Q 100 104 106 104 Q 110 100 118 96 Z" fill="#FFFFFF" opacity="0.92"/>
              <!-- White continent: South America bottom -->
              <path d="M76 130 Q 70 136 74 144 Q 80 148 84 146 Q 90 148 90 140 Z" fill="#FFFFFF" opacity="0.88"/>
              <!-- ===== AIRPLANE (coral top-right) ===== -->
              <g transform="translate(165 42) rotate(18)">
                <path d="M-2 6 L 28 -2 L 32 2 L 20 10 L 30 14 L 30 17 L 20 17 L 22 24 L 18 26 L 10 20 L -2 22 Z" fill="#FF6B4A"/>
              </g>
              <!-- ===== PET CRATE (coral, rounded) ===== -->
              <g transform="translate(48 88)">
                <!-- Outer crate shell -->
                <path d="M8 44 Q 4 44 4 38 L 2 18 Q 2 10 12 8 L 28 4 Q 40 2 52 6 Q 80 6 100 14 Q 114 20 118 32 Q 122 42 116 48 Q 110 54 98 56 L 20 56 Q 8 56 8 44 Z" fill="#FF6B4A"/>
                <!-- Inner darker crate lining -->
                <path d="M14 42 Q 10 42 10 36 L 8 18 Q 8 14 16 12 L 32 8 Q 42 7 52 10 Q 76 10 94 18 Q 108 24 110 32 Q 114 40 110 46 Q 104 50 96 52 L 22 52 Q 14 52 14 42 Z" fill="#E34D2E"/>
                <!-- Left 3 vertical ventilation slots -->
                <rect x="18" y="22" width="5" height="20" rx="1.5" fill="#FBF7F0" opacity="0.9"/>
                <rect x="26" y="22" width="5" height="20" rx="1.5" fill="#FBF7F0" opacity="0.9"/>
                <rect x="34" y="22" width="5" height="20" rx="1.5" fill="#FBF7F0" opacity="0.9"/>
              </g>
              <!-- ===== GOLDEN RETRIEVER (upper body, left side of crate opening) ===== -->
              <g transform="translate(72 102)">
                <!-- Chest / body -->
                <path d="M4 46 L 4 30 Q 2 20 10 14 L 14 10 Q 16 2 24 2 Q 30 0 36 4 L 44 8 Q 54 14 56 24 L 58 32 L 58 46 Z" fill="#FBB03B"/>
                <!-- Lighter chest highlight -->
                <path d="M14 46 L 16 32 Q 20 22 30 20 L 36 24 L 34 46 Z" fill="#F9C56B"/>
                <!-- Neck fur lines -->
                <path d="M34 24 Q 40 30 40 40" fill="none" stroke="#E39219" stroke-width="1.2" stroke-linecap="round" opacity="0.75"/>
                <!-- Head -->
                <circle cx="40" cy="18" r="12" fill="#FBB03B"/>
                <!-- Snout -->
                <ellipse cx="45.5" cy="21" rx="6" ry="5.2" fill="#F9C56B"/>
                <!-- Left floppy ear -->
                <ellipse cx="30" cy="12" rx="4.8" ry="8" fill="#E39219" transform="rotate(-18 30 12)"/>
                <!-- Right floppy ear -->
                <ellipse cx="49" cy="10" rx="4.5" ry="7.5" fill="#E39219" transform="rotate(14 49 10)"/>
                <!-- Eyes -->
                <circle cx="37" cy="16" r="1.4" fill="#0A2C52"/>
                <circle cx="44.5" cy="15" r="1.4" fill="#0A2C52"/>
                <circle cx="37.4" cy="15.6" r="0.45" fill="#FFFFFF"/>
                <circle cx="44.9" cy="14.6" r="0.45" fill="#FFFFFF"/>
                <!-- Nose -->
                <ellipse cx="49.2" cy="20" rx="2" ry="1.6" fill="#0A2C52"/>
                <!-- Smile mouth -->
                <path d="M45 23 Q 48 26.5 51.5 23.5" fill="none" stroke="#0A2C52" stroke-width="1.1" stroke-linecap="round"/>
                <!-- Pink tongue -->
                <path d="M47.2 24.5 Q 48.5 27.5 49.8 24.8 Q 49 25.8 47.2 24.5 Z" fill="#FF6B7F"/>
              </g>
              <!-- ===== CAT (white w/ navy patch, right of dog, inside crate) ===== -->
              <g transform="translate(126 112)">
                <!-- Body -->
                <path d="M4 40 L 4 22 Q 2 12 12 8 Q 18 4 28 4 Q 34 8 34 14 L 34 40 Z" fill="#FFFFFF"/>
                <!-- Head -->
                <circle cx="20" cy="12" r="11" fill="#FFFFFF"/>
                <!-- Navy patch: left side of head + ear + back -->
                <path d="M12 14 Q 10 8 14 4 L 16 0 L 14 8 Q 16 12 16 18 Q 14 22 12 20 Q 10 18 12 14 Z" fill="#0A2C52"/>
                <!-- Back navy patch -->
                <path d="M4 22 Q 2 18 6 16 Q 12 14 14 20 Q 12 28 6 28 Q 3 26 4 22 Z" fill="#0A2C52" opacity="0.92"/>
                <!-- Left ear -->
                <path d="M11 4 L 8 -2 L 14 1 Z" fill="#FFFFFF"/>
                <path d="M11 4 L 8 -2 L 14 1 Z" fill="none" stroke="#0A2C52" stroke-width="0.8"/>
                <!-- Right ear -->
                <path d="M29 4 L 32 -2 L 26 1 Z" fill="#FFFFFF"/>
                <path d="M29 4 L 32 -2 L 26 1 Z" fill="none" stroke="#0A2C52" stroke-width="0.8"/>
                <!-- Eyes -->
                <circle cx="15.5" cy="12.5" r="1.2" fill="#FFFFFF"/>
                <circle cx="24.5" cy="12" r="1.25" fill="#0A2C52"/>
                <circle cx="24.9" cy="11.6" r="0.4" fill="#FFFFFF"/>
                <!-- Nose -->
                <path d="M19 16 L 21.5 16 L 20.2 17.5 Z" fill="#FFB19E"/>
                <!-- Smile mouth -->
                <path d="M18.5 18 Q 20.2 20 22 18" fill="none" stroke="#0A2C52" stroke-width="0.9" stroke-linecap="round"/>
                <!-- Whiskers: 3 left + 3 right -->
                <line x1="6" y1="17" x2="13" y2="17.5" stroke="#0A2C52" stroke-width="0.55" stroke-linecap="round"/>
                <line x1="6.5" y1="19" x2="13" y2="18.8" stroke="#0A2C52" stroke-width="0.55" stroke-linecap="round"/>
                <line x1="7" y1="21" x2="13" y2="20" stroke="#0A2C52" stroke-width="0.55" stroke-linecap="round"/>
                <line x1="28" y1="17.5" x2="34.5" y2="17" stroke="#0A2C52" stroke-width="0.55" stroke-linecap="round"/>
                <line x1="28" y1="18.8" x2="34.5" y2="19" stroke="#0A2C52" stroke-width="0.55" stroke-linecap="round"/>
                <line x1="28" y1="20" x2="34.5" y2="21" stroke="#0A2C52" stroke-width="0.55" stroke-linecap="round"/>
              </g>
            </svg>
            <?php endif; ?>
          </div>
          <div>
            <div class="flex items-baseline gap-0">
              <span class="font-black text-[36px] sm:text-[40px] leading-none tracking-[-0.02em]" style="color:#FBF7F0;font-family:'Plus Jakarta Sans',system-ui,sans-serif;">beethoven</span><span class="font-black text-[36px] sm:text-[40px] leading-none tracking-[-0.02em]" style="color:#FF9B2A;font-family:'Plus Jakarta Sans',system-ui,sans-serif;">pets</span>
            </div>
          </div>
        </div>
        <p class="text-cream/75 leading-relaxed max-w-md mb-6">
          The most trusted premium pet relocation concierge in Southeast Asia. From Jakarta to Tokyo, Singapore to Sydney — we treat every pet like family.
        </p>
        <div class="mb-5">
          <div class="text-sm font-bold text-cream/80 mb-3 uppercase tracking-wider">Follow us on</div>
          <div class="flex items-center gap-2 flex-wrap">
            <a href="<?=e(socialLink('facebook'))?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-cream/20 text-sm font-semibold hover:bg-coral hover:border-coral transition-all">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12.07C22 6.5 17.52 2 12 2S2 6.5 2 12.07c0 5.02 3.66 9.16 8.44 9.93v-7H7.03h-2.81v-2.9h2.81V9.41c0-2.78 1.66-4.3 4.18-4.3 1.21 0 2.47.22 2.47.22v2.71H20.6c-1.38 0-1.81.85-1.81 1.73v2.08h3.07l-.49 2.9h-2.58v7A10 10 0 0022 12.07z"/></svg>
              Facebook
            </a>
            <a href="<?=e(socialLink('instagram'))?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-cream/20 text-sm font-semibold hover:bg-coral hover:border-coral transition-all">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
              Instagram
            </a>
            <a href="<?=e(socialLink('tiktok'))?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-cream/20 text-sm font-semibold hover:bg-coral hover:border-coral transition-all">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-.88-.07A6.33 6.33 0 005.4 15.14a6.35 6.35 0 0010.85 4.41 6.31 6.31 0 003.77-5.68v-2.26a8.15 8.15 0 004.78 1.52V9.45a4.84 4.84 0 01-5.21-5.32z"/></svg>
              TikTok
            </a>
            <a href="<?=e(socialLink('youtube'))?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-cream/20 text-sm font-semibold hover:bg-coral hover:border-coral transition-all">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
              YouTube
            </a>
          </div>
        </div>
        <div class="flex gap-3 flex-wrap">
          <?php
            $footerIconSocials = [
              ['name'=>'facebook','t'=>'Facebook','svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12.07C22 6.5 17.52 2 12 2S2 6.5 2 12.07c0 5.02 3.66 9.16 8.44 9.93v-7H7.03h-2.81v-2.9h2.81V9.41c0-2.78 1.66-4.3 4.18-4.3 1.21 0 2.47.22 2.47.22v2.71H20.6c-1.38 0-1.81.85-1.81 1.73v2.08h3.07l-.49 2.9h-2.58v7A10 10 0 0022 12.07z"/></svg>'],
              ['name'=>'instagram','t'=>'Instagram','svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>'],
              ['name'=>'tiktok','t'=>'TikTok','svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-.88-.07A6.33 6.33 0 005.4 15.14a6.35 6.35 0 0010.85 4.41 6.31 6.31 0 003.77-5.68v-2.26a8.15 8.15 0 004.78 1.52V9.45a4.84 4.84 0 01-5.21-5.32z"/></svg>'],
              ['name'=>'twitter','t'=>'Twitter / X','svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>'],
              ['name'=>'youtube','t'=>'YouTube','svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>'],
              ['name'=>'linkedin','t'=>'LinkedIn','svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.268 2.37 4.268 5.455v6.286zM5.337 7.433a2.062 2.062 0 110-4.124 2.063 2.063 0 010 4.124zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>'],
              ['name'=>'whatsapp','t'=>'WhatsApp','svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.12 1.03 6.986 2.89a9.826 9.826 0 012.888 6.979c-.002 5.445-4.437 9.88-9.889 9.88z"/></svg>'],
              ['name'=>'telegram_footer','t'=>'Telegram','svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M9.04 15.47 8.9 19c.4 0 .57-.17.78-.37l1.87-1.8 3.9 2.86c.72.4 1.23.19 1.42-.67l2.59-12.21c.26-1.12-.41-1.56-1.15-1.3L3.4 10.41c-1.1.42-1.08 1.06-.19 1.34l3.62 1.13 8.38-5.27c.39-.26.75-.12.46.16L9.04 15.47z"/></svg>'],
              ['name'=>'wechat_footer','t'=>'WeChat','svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.182 2.995 5.466a.59.59 0 01.227.665l-.298 1.113a.591.591 0 00.833.63l1.3-.739a.595.595 0 01.49-.056c.859.291 1.797.458 2.771.488a7.44 7.44 0 01-.175-1.574c0-3.536 3.228-6.402 7.205-6.402.139 0 .276.005.414.013C15.396 5.058 12.388 2.188 8.691 2.188zm-2.82 3.959a.992.992 0 110 1.984.992.992 0 010-1.984zm5.64 0a.992.992 0 110 1.984.992.992 0 010-1.984zm5.02 3.121c-3.528 0-6.393 2.542-6.393 5.678s2.865 5.678 6.393 5.678c.71 0 1.395-.093 2.042-.266a.496.496 0 01.41.046l1.088.619a.494.494 0 00.695-.526l-.249-.931a.495.495 0 01.19-.556C22.808 19.264 24 17.53 24 15.546c0-3.136-2.865-5.678-6.393-5.678h-1.276zm-2.54 2.842a.827.827 0 110 1.654.827.827 0 010-1.654zm4.65 0a.827.827 0 110 1.654.827.827 0 010-1.654z"/></svg>'],
            ];
            foreach($footerIconSocials as $s):
              $href = '#'; $onclick = ''; $target = '';
              if ($s['name'] === 'telegram_footer') { $href = e(telegramLink()); $target = ' target="_blank" rel="noopener"'; }
              elseif ($s['name'] === 'wechat_footer') { $href = '#'; $onclick = ' onclick="event.preventDefault();alert(\'WeChat ID: \'.WECHAT_ID.\'\\nPlease add us on WeChat for a consultation.\');"'; }
              else { $href = e(socialLink($s['name'])); if ($href !== '#') $target = ' target="_blank" rel="noopener"'; }
          ?>
          <a href="<?=$href?>" aria-label="<?=$s['t']?>" class="w-11 h-11 rounded-full border border-cream/20 flex items-center justify-center hover:bg-coral hover:border-coral transition-all"<?=$target?><?=$onclick?>>
            <?=$s['svg']?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <h4 class="font-display font-semibold text-xl mb-6">Services</h4>
        <ul class="space-y-3 text-sm text-cream/60">
          <li><a href="#services" class="hover:text-coral">Domestic Air Transport</a></li>
          <li><a href="#services" class="hover:text-coral">Domestic Land Concierge</a></li>
          <li><a href="#services" class="hover:text-coral">International Relocation</a></li>
          <li><a href="#services" class="hover:text-coral">Vet & Documentation</a></li>
          <li><a href="#services" class="hover:text-coral">Chaperone & Private Jet</a></li>
          <li><a href="#services" class="hover:text-coral">Transit Boarding Care</a></li>
        </ul>
      </div>

      <div>
        <h4 class="font-display font-semibold text-xl mb-6">Explore</h4>
        <ul class="space-y-3 text-sm text-cream/60">
          <li><a href="#" class="hover:text-coral">About BeethovenPets</a></li>
          <li><a href="#gallery" class="hover:text-coral">Pet Journey Gallery</a></li>
          <li><a href="pets-sale.php" class="hover:text-coral">Pets on Sale</a></li>
          <li><a href="#stories" class="hover:text-coral">Customer Stories</a></li>
          <li><a href="#faq" class="hover:text-coral">FAQ</a></li>
          <li><a href="admin/login.php" class="hover:text-coral">Staff Login</a></li>
        </ul>
      </div>

      <div>
        <h4 class="font-display font-semibold text-xl mb-6">Contact</h4>
        <ul class="space-y-4 text-sm text-cream/60">
          <li class="flex items-start gap-3">
            <span class="w-9 h-9 rounded-xl bg-coral/20 flex items-center justify-center flex-shrink-0 mt-0.5">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FF6B4A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
            </span>
            <a href="https://wa.me/<?=waDigits()?>" class="hover:text-coral font-medium"><?=e(waNumber())?><br><span class="text-xs opacity-70">WhatsApp · 24/7 response</span></a>
          </li>
          <li class="flex items-start gap-3">
            <span class="w-9 h-9 rounded-xl bg-teal/20 flex items-center justify-center flex-shrink-0 mt-0.5">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#4FB3A8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </span>
            <?php $_email = getSetting('contact_email',''); if (!$_email) $_email = SITE_EMAIL; ?>
            <a href="mailto:<?=e($_email)?>" class="hover:text-coral font-medium"><?=e($_email)?><br><span class="text-xs opacity-70">Reply within 2 hours</span></a>
          </li>
          <li class="flex items-start gap-3">
            <span class="w-9 h-9 rounded-xl bg-gold/25 flex items-center justify-center flex-shrink-0 mt-0.5">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C9A962" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
            </span>
            <?php $_addr = trim((string)getSetting('office_address','')); if (!$_addr) $_addr = 'Jl. TB Simatupang, Cilandak, Jakarta Selatan 12430'; ?>
            <div>Head Office · Jakarta<br><?=nl2br(e($_addr))?></div>
          </li>
        </ul>
      </div>
    </div>

    <div class="pt-8 border-t border-cream/10 flex flex-col md:flex-row justify-between items-center gap-4 text-sm text-cream/45">
      <div>© 2026 BeethovenPets Concierge Pte. Ltd. All rights reserved. · IATA Certified Partner</div>
      <div class="flex gap-6">
        <a href="#" class="hover:text-coral">Privacy Policy</a>
        <a href="#" class="hover:text-coral">Terms of Service</a>
      </div>
    </div>
  </div>
</footer>

<!-- ===== FLOATING TELEGRAM + WHATSAPP + WECHAT ===== -->
<div class="floating-social">
  <!-- TELEGRAM (di ATAS WhatsApp) -->
  <?php $_tl = telegramLink(); ?>
  <a href="<?=e($_tl)?>" target="_blank" class="float-btn" data-label="Telegram Admin" style="background:linear-gradient(135deg,#2AABEE 0%,#229ED9 50%,#0088CC 100%);color:white;">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9.04 15.47 8.9 19c.4 0 .57-.17.78-.37l1.87-1.8 3.9 2.86c.72.4 1.23.19 1.42-.67l2.59-12.21c.26-1.12-.41-1.56-1.15-1.3L3.4 10.41c-1.1.42-1.08 1.06-.19 1.34l3.62 1.13 8.38-5.27c.39-.26.75-.12.46.16L9.04 15.47z"/></svg>
  </a>
  <!-- WHATSAPP (tengah) -->
  <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%20want%20to%20ask%20about%20pet%20relocation%20services." target="_blank" class="float-btn" data-label="WhatsApp 24/7" style="background:linear-gradient(135deg,#25D366 0%,#128C7E 100%);color:white;">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.12 1.03 6.986 2.89a9.826 9.826 0 012.888 6.979c-.002 5.445-4.437 9.88-9.889 9.88zm8.421-16.471C20.441 2.69 16.478 0 12 0 5.373 0 0 5.373 0 12c0 2.12.555 4.2 1.606 6.01L0 24l6.164-1.604A11.954 11.954 0 0012 24c6.627 0 12-5.373 12-12 0-3.186-1.246-6.2-3.107-8.486z"/></svg>
  </a>
  <!-- WECHAT (bawah) -->
  <a href="#" id="wechatBtn" class="float-btn" data-label="WeChat Admin" style="background:linear-gradient(135deg,#07C160 0%,#06AD56 100%);color:white;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
      <path d="M8.5 4C4.4 4 1 6.9 1 10.5c0 2 1.1 3.8 2.8 5l-.3 1.5 1.9-1.1c.9.3 1.8.4 2.8.4h.5c3.6 0 6.6-2.5 6.6-5.8S12.6 4 8.5 4z"/>
      <circle cx="6.5" cy="9.5" r="1" fill="white" stroke="none"/>
      <circle cx="10" cy="9.5" r="1" fill="white" stroke="none"/>
      <path d="M23 14c0 2.5-2.2 4.5-5 4.5-.7 0-1.4-.1-2-.4l-1.7 1 .4-1.4C13.3 16.7 12.7 15.4 12.7 14c0-2.5 2.5-4.5 5.5-4.5s4.8 2 4.8 4.5z"/>
      <circle cx="15.5" cy="13.2" r=".9" fill="white" stroke="none"/>
      <circle cx="19" cy="13.2" r=".9" fill="white" stroke="none"/>
    </svg>
  </a>
</div>

<script>
  const WA_NUMBER = '<?=waDigits()?>';
  const WECHAT_ID = '<?=e(wechatId())?>';

  // Mobile menu
  const mb = document.getElementById('menuBtn');
  const mm = document.getElementById('mobileMenu');
  mb.addEventListener('click', () => mm.classList.toggle('hidden'));

  // WeChat button - show alert
  const wechatBtn = document.getElementById('wechatBtn');
  if (wechatBtn) {
    wechatBtn.addEventListener('click', (e) => {
      e.preventDefault();
      alert('WeChat ID: ' + WECHAT_ID + '\nPlease add us on WeChat for a consultation.');
    });
  }

  // ========================================
  // HERO CAROUSEL + 4 OVERLAY SYNC SLIDERS
  // ========================================
  (function () {
    const carousel = document.getElementById('heroCarousel');
    const track = document.getElementById('heroTrack');
    const prevBtn = document.getElementById('heroPrev');
    const nextBtn = document.getElementById('heroNext');
    const dotsWrap = document.getElementById('heroDots');
    // 4 overlay sliders (board-pass, testimonials, pet-cargo)
    const bpSlides = document.querySelectorAll('#bp-slides .bp-slide');
    const tSlides = document.querySelectorAll('#t-slides .t-slide');
    const tDots = document.querySelectorAll('#tCard .t-dot');
    const pcSlides = document.querySelectorAll('#pc-slides .pc-slide');
    const totalOv = bpSlides.length || 5;

    if (!carousel || !track) return;

    const slides = track.children;
    const total = slides.length || totalOv;
    let cur = 0;
    let timerId = null;
    let touchStartX = 0;
    let touchEndX = 0;
    const dots = dotsWrap ? [...dotsWrap.querySelectorAll('.hero-dot')] : [];

    function applyOverlays(i) {
      const idx = i % (bpSlides.length || 1);
      // 1. Boarding Pass slides
      bpSlides.forEach(function (el, k) {
        if (k === idx) {
          el.classList.remove('opacity-0','translate-x-4');
          el.classList.add('opacity-100','translate-x-0');
        } else {
          el.classList.add('opacity-0','translate-x-4');
          el.classList.remove('opacity-100','translate-x-0');
        }
      });
      // 2. Testimonial slides
      tSlides.forEach(function (el, k) {
        if (k === idx) {
          el.classList.remove('opacity-0','translate-y-3');
          el.classList.add('opacity-100','translate-y-0');
        } else {
          el.classList.add('opacity-0','translate-y-3');
          el.classList.remove('opacity-100','translate-y-0');
        }
      });
      // 3. Testimonial dots
      tDots.forEach(function (d, k) {
        if (k === idx) {
          d.classList.add('bg-coral','!w-4','rounded-full');
          d.classList.remove('bg-navy/25');
        } else {
          d.classList.remove('bg-coral','!w-4','rounded-full');
          d.classList.add('bg-navy/25');
        }
      });
      // 4. Pet Cargo tags
      pcSlides.forEach(function (el, k) {
        if (k === idx) {
          el.classList.remove('opacity-0','translate-y-2');
          el.classList.add('opacity-100','translate-y-0');
        } else {
          el.classList.add('opacity-0','translate-y-2');
          el.classList.remove('opacity-100','translate-y-0');
        }
      });
    }

    function goTo(i) {
      cur = ((i % total) + total) % total;
      track.style.transform = 'translateX(' + (-cur * 100) + '%)';
      dots.forEach(function (d, idx) {
        d.classList.toggle('active', idx === cur);
      });
      applyOverlays(cur);
    }

    function startAuto() {
      stopAuto();
      timerId = setInterval(function () { goTo(cur + 1); }, 5500);
    }
    function stopAuto() {
      if (timerId) { clearInterval(timerId); timerId = null; }
    }

    // Prev / Next
    if (prevBtn) prevBtn.addEventListener('click', function () { goTo(cur - 1); startAuto(); });
    if (nextBtn) nextBtn.addEventListener('click', function () { goTo(cur + 1); startAuto(); });

    // Dots
    dots.forEach(function (d) {
      d.addEventListener('click', function () {
        goTo(parseInt(d.dataset.i || '0', 10));
        startAuto();
      });
    });

    // Testimonial dots click
    tDots.forEach(function (d) {
      d.addEventListener('click', function () {
        goTo(parseInt(d.dataset.i || '0', 10));
        startAuto();
      });
    });

    // Pause on hover
    carousel.addEventListener('mouseenter', stopAuto);
    carousel.addEventListener('mouseleave', startAuto);
    // Also pause hovering the cards
    ['bpCard','tCard','pcCard'].forEach(function(id){
      const el = document.getElementById(id);
      if (el) { el.addEventListener('mouseenter', stopAuto); el.addEventListener('mouseleave', startAuto); }
    });

    // Touch swipe (mobile)
    carousel.addEventListener('touchstart', function (e) {
      touchStartX = e.changedTouches[0].screenX;
      stopAuto();
    }, { passive: true });
    carousel.addEventListener('touchend', function (e) {
      touchEndX = e.changedTouches[0].screenX;
      var diff = touchEndX - touchStartX;
      if (Math.abs(diff) > 50) {
        if (diff > 0) goTo(cur - 1);
        else goTo(cur + 1);
      }
      startAuto();
    }, { passive: true });

    // Kick off
    goTo(0);
    startAuto();
  })();

  // ========================================
  // ANIMATED NUMBER COUNTERS (scroll-triggered, page-wide)
  // ========================================
  (function () {
    const spans = [...document.querySelectorAll('.stat-num[data-count]')];
    if (!spans.length) return;

    function easeOutQuad(t) { return t * (2 - t); }

    function animateSpan(span) {
      if (span.dataset.animated === '1') return;
      span.dataset.animated = '1';
      const target = parseInt(span.dataset.count, 10);
      if (!target || isNaN(target)) return;
      const duration = 1600;
      const start = performance.now();

      function tick(now) {
        const elapsed = now - start;
        const t = Math.min(1, elapsed / duration);
        const eased = easeOutQuad(t);
        const val = Math.floor(eased * target);
        span.textContent = val.toLocaleString('en-US');
        if (t < 1) requestAnimationFrame(tick);
        else span.textContent = target.toLocaleString('en-US');
      }
      requestAnimationFrame(tick);
    }

    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animateSpan(entry.target);
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.3 });
      spans.forEach(function (s) { io.observe(s); });
    } else {
      spans.forEach(animateSpan);
    }
  })();

  // ========================================
  // FALLBACK LOGO IMAGE LOADERS (navbar + footer)
  // — tries: full URL → basePath → inline SVG
  // ========================================
  (function () {
    function setup(img, box) {
      if (!img) return;
      var tries = 0;
      function fallback() {
        tries++;
        if (tries === 1 && img.dataset.fallbackUrl) {
          img.src = img.dataset.fallbackUrl;
          return;
        }
        // last try: replace with inline SVG
        var inline = '';
        try { inline = JSON.parse(img.dataset.fallbackInline || '""'); } catch (e) {}
        if (inline && box) box.innerHTML = inline;
        else if (img.parentElement) img.remove();
      }
      if (img.complete && img.naturalWidth === 0) fallback();
      else img.addEventListener('error', fallback);
    }
    var navLogo = document.getElementById('navbarLogoImg');
    setup(navLogo, document.getElementById('navbarLogoBox'));
    var footLogo = document.getElementById('footerLogoImg');
    setup(footLogo, document.getElementById('footerLogoBox'));
  })();
</script>
</body>
</html>
