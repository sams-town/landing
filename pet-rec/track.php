<?php require_once __DIR__ . '/includes/init.php';

$code = trim($_GET['code'] ?? $_POST['code'] ?? '');
$booking = null;
$customer = null;
$pet = null;
$trackingLogs = [];
$notFound = false;

if ($code) {
    try {
        $booking = pawDB()->fetch("SELECT * FROM bookings WHERE booking_code = ?", [$code]);
        if ($booking) {
            $customer = pawDB()->fetch("SELECT * FROM customers WHERE id = ?", [$booking['customer_id']]);
            $pet = pawDB()->fetch("SELECT * FROM pets WHERE id = ?", [$booking['pet_id']]);
            $trackingLogs = pawDB()->fetchAll("SELECT * FROM tracking_logs WHERE booking_id = ? ORDER BY created_at ASC, id ASC", [$booking['id']]);
        } else {
            $notFound = true;
        }
    } catch (Throwable $e) { $notFound = true; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track Pet Journey — BeethovenPets</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root { --cream:#FBF7F0;--navy:#0F2747;--coral:#FF6B4A;--teal:#4FB3A8;--gold:#C9A962;--muted:#4A5462; }
  body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--cream); }
  .font-display { font-family:'Cormorant Garamond',serif; font-weight:500; letter-spacing:-0.01em; }
  .bg-cream { background:var(--cream); }
  .bg-navy { background:var(--navy); }
  .bg-coral { background:var(--coral); }
  .bg-teal { background:var(--teal); }
  .text-navy { color:var(--navy); }
  .text-coral { color:var(--coral); }
  .text-teal { color:var(--teal); }
  .text-muted { color:var(--muted); }
  .btn { display:inline-flex;align-items:center;gap:10px;font-weight:600;transition:all 0.3s;border:0;cursor:pointer;border-radius:999px; }
  .btn-primary { background:var(--coral);color:white;padding:15px 26px;box-shadow:0 10px 30px -10px rgba(255,107,74,0.55); }
  .btn-primary:hover { background:#E55536; transform:translateY(-2px); }
  .btn-outline { border:2px solid var(--navy); color:var(--navy); padding:13px 24px; background:transparent; }
  .btn-outline:hover { background:var(--navy); color:var(--cream); transform:translateY(-2px); }
  .card { background:white; border-radius:28px; box-shadow:0 12px 50px -18px rgba(15,39,71,0.2); border:1px solid #EFE7D7; }
  .input { width:100%;padding:16px 20px;border-radius:18px;border:1.5px solid #E5DFD3;background:var(--cream);font-family:inherit;font-size:16px;transition:all 0.2s;letter-spacing:0.03em;text-transform:uppercase; }
  .input:focus { outline:none;border-color:var(--coral);background:white;box-shadow:0 0 0 5px rgba(255,107,74,0.1); }
  .label { font-size:13px;font-weight:700;color:var(--navy);display:flex;align-items:center;gap:6px;letter-spacing:0.02em; }

  /* Timeline */
  .timeline { position:relative;padding-left:44px; }
  .timeline::before { content:''; position:absolute; left:15px; top:8px; bottom:8px; width:3px; background:linear-gradient(180deg,#FF6B4A 0%,#C9A962 50%,#4FB3A8 100%); border-radius:999px; opacity:0.3; }
  .t-item { position:relative;padding:22px 0;border-bottom:1px dashed #EFE7D7; }
  .t-item:last-child { border-bottom:0; }
  .t-dot { position:absolute; left:-44px; top:26px; width:32px; height:32px; border-radius:50%; background:white; box-shadow:0 0 0 4px #FBF7F0, 0 0 0 6px rgba(255,107,74,0.35); display:flex; align-items:center; justify-content:center; z-index:2; }
  .t-item:last-child .t-dot { box-shadow:0 0 0 4px #FBF7F0, 0 0 0 6px rgba(79,179,168,0.45); background:var(--teal); }
  .t-dot-inner { width:12px;height:12px;border-radius:50%;background:var(--coral); }
  .t-item:last-child .t-dot-inner { background:white; }
  .t-dot svg { stroke:white; stroke-width:3; }

  .status-badge { display:inline-flex;align-items:center;gap:7px;padding:7px 15px;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:0.04em;text-transform:uppercase; }
  .eyebrow { display:inline-flex;align-items:center;gap:10px;padding:8px 16px;background:rgba(79,179,168,0.1);border:1px solid rgba(79,179,168,0.25);border-radius:999px;font-size:12px;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--navy); }

  .route-card { background:linear-gradient(135deg,#FFFDF9,#F7F1E5);border:1.5px dashed var(--navy);border-radius:22px;padding:22px 24px;position:relative;overflow:hidden; }
</style>
</head>
<body class="bg-cream min-h-screen pb-20">

<!-- NAV -->
<nav class="sticky top-0 z-50 border-b border-[#EFE7D7] bg-[rgba(251,247,240,0.85)] backdrop-blur-xl">
  <div class="max-w-6xl mx-auto px-6">
    <div class="flex items-center justify-between h-20">
      <a href="<?=SITE_URL?>/" class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-2xl bg-navy flex items-center justify-center">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="#FBF7F0"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/></svg>
        </div>
        <div>
          <div class="font-display font-semibold text-2xl text-navy leading-none">BeethovenPets</div>
          <div class="text-[10.5px] tracking-[0.22em] uppercase font-bold text-muted mt-1">Pet Journey Tracker</div>
        </div>
      </a>
      <div class="hidden sm:flex gap-3">
        <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%20would%20like%20to%20request%20a%20new%20quote%20for%20pet%20relocation%20services." target="_blank" class="btn-outline text-sm">New Quote</a>
        <a href="admin/login.php" class="btn-outline text-sm">Staff</a>
      </div>
    </div>
  </div>
</nav>

<main class="max-w-5xl mx-auto px-6 pt-14">

  <!-- HEADER -->
  <div class="text-center max-w-2xl mx-auto mb-12">
    <div class="eyebrow mb-5"><span class="w-1.5 h-1.5 rounded-full bg-coral pin-pulse" style="animation:pulse 2.4s ease-in-out infinite;@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.15)}}"></span>Live Journey Tracker</div>
    <h1 class="font-display text-navy text-4xl sm:text-5xl lg:text-6xl font-semibold leading-[1.06] mb-5">
      Track Your <span class="italic text-coral">Pet's Journey</span><br>in Real-Time
    </h1>
    <p class="text-muted text-lg leading-relaxed">Enter your 15-character booking reference code. You received this via email & WhatsApp when you submitted your quote.</p>
  </div>

  <!-- LOOKUP FORM -->
  <form method="GET" action="track.php" class="card p-7 sm:p-9 mb-12">
    <div class="flex flex-col sm:flex-row gap-4">
      <div class="flex-1">
        <label class="label mb-2">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0F2747" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9z"/><circle cx="12" cy="18" r="1.5" fill="#0F2747"/></svg>
          Booking Reference Code
        </label>
        <input name="code" value="<?=e($code)?>" required placeholder="e.g. PV-20261005-001" class="input" autofocus>
      </div>
      <div class="flex items-end">
        <button class="btn btn-primary w-full sm:w-auto justify-center h-[58px]">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Track Now
        </button>
      </div>
    </div>
    <?php if ($notFound): ?>
    <div class="mt-5 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 font-semibold flex items-start gap-3">
      <svg class="mt-0.5 flex-shrink-0" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div>No booking found for "<strong><?=e($code)?></strong>". Please double-check your code (case-insensitive) or <a href="https://wa.me/<?=waDigits()?>" class="underline underline-offset-2">chat our concierge</a>.</div>
    </div>
    <?php endif; ?>
  </form>

  <?php if ($booking): ?>
  <!-- BOOKING FOUND -->
  <div class="space-y-8">

    <!-- HEADER CARD: CUSTOMER + STATUS -->
    <div class="card p-7 sm:p-9 relative overflow-hidden">
      <div class="absolute -top-20 -right-20 w-64 h-64 rounded-full bg-coral/7 blur-3xl"></div>
      <div class="grid lg:grid-cols-3 gap-7 relative">
        <div class="lg:col-span-2">
          <div class="flex items-center gap-4 mb-5 flex-wrap">
            <div class="font-display text-navy text-2xl sm:text-3xl font-bold tracking-wider"><?=e($booking['booking_code'])?></div>
            <span class="status-badge" style="background:<?=bookingStatusColor($booking['status'])?>15;color:<?=bookingStatusColor($booking['status'])?>;box-shadow:inset 0 0 0 1.5px <?=bookingStatusColor($booking['status'])?>40">
              <span class="w-2 h-2 rounded-full" style="background:<?=bookingStatusColor($booking['status'])?>;animation:pulse 2s ease-in-out infinite"></span>
              <?=bookingStatusLabel($booking['status'])?>
            </span>
            <span class="status-badge" style="background:#E6EEFA;color:var(--navy);">
              <?=travelTypeLabel($booking['travel_type'])?>
            </span>
          </div>

          <div class="flex flex-wrap gap-x-8 gap-y-4 mb-6">
            <div>
              <div class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Owner</div>
              <div class="font-bold text-navy"><?=e($customer['full_name'] ?? 'Customer')?></div>
              <div class="text-xs text-muted"><?=e($customer['email'] ?? '')?></div>
            </div>
            <?php if ($pet): ?>
            <div>
              <div class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Traveling Pet</div>
              <div class="font-bold text-navy"><?=e($pet['name'])?> · <?=e($pet['breed'] ?? petSizeLabel($booking['pet_type_booking']))?></div>
              <div class="text-xs text-muted"><?=e($booking['pet_weight_kg'])?> kg · <?=petSizeLabel($booking['pet_type_booking'])?></div>
            </div>
            <?php endif; ?>
            <div>
              <div class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Departure</div>
              <div class="font-bold text-navy"><?=formatDate($booking['travel_date'])?></div>
              <div class="text-xs text-muted"><?=!empty($booking['payment_status']) ? paymentStatusLabel($booking['payment_status']) : ''?></div>
            </div>
          </div>
        </div>

        <!-- ROUTE MAP CARD -->
        <div class="route-card">
          <div class="text-[11px] font-extrabold uppercase tracking-[0.2em] text-muted mb-4">Journey Itinerary</div>
          <div class="space-y-2 relative">
            <div class="flex items-start gap-3">
              <div class="w-5 h-5 rounded-full bg-coral mt-1 flex-shrink-0 flex items-center justify-center"><span class="w-2 h-2 rounded-full bg-white"></span></div>
              <div>
                <div class="text-[11px] font-bold text-coral uppercase tracking-wider">Origin</div>
                <div class="font-bold text-navy text-lg leading-tight"><?=e($booking['origin_city'])?></div>
              </div>
            </div>
            <div class="pl-2.5 ml-[9px] w-0.5 h-10 bg-gradient-to-b from-coral via-gold to-teal rounded-full"></div>
            <div class="flex items-center justify-between pl-2.5">
              <div class="flex items-center gap-2 bg-white px-3 py-1.5 rounded-xl shadow-sm border border-cream">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C9A962" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>
                <span class="text-xs font-bold text-navy"><?=travelTypeLabel($booking['travel_type'])?></span>
              </div>
            </div>
            <div class="pl-2.5 ml-[9px] w-0.5 h-10 bg-gradient-to-b from-coral via-gold to-teal rounded-full"></div>
            <div class="flex items-start gap-3">
              <div class="w-5 h-5 rounded-full bg-teal mt-1 flex-shrink-0 flex items-center justify-center"><span class="w-2 h-2 rounded-full bg-white"></span></div>
              <div>
                <div class="text-[11px] font-bold text-teal uppercase tracking-wider">Destination</div>
                <div class="font-bold text-navy text-lg leading-tight"><?=e($booking['destination_city'])?></div>
              </div>
            </div>
          </div>
          <?php if (!empty($booking['price_estimated_idr'])): ?>
          <div class="mt-5 pt-5 border-t border-navy/10 flex items-center justify-between">
            <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Budget Est.</span>
            <span class="font-display text-2xl font-bold text-coral"><?=formatRupiah($booking['price_estimated_idr'])?></span>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- TIMELINE -->
    <div class="card p-7 sm:p-9">
      <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
        <div>
          <h2 class="font-display text-navy text-3xl sm:text-4xl font-semibold">Journey Timeline</h2>
          <p class="text-muted mt-1 text-sm">Live updates posted by your concierge team</p>
        </div>
        <div class="flex items-center gap-2 text-xs font-bold text-teal">
          <span class="w-2.5 h-2.5 rounded-full bg-teal" style="animation:pulse 1.8s infinite"></span>
          LIVE · <?=count($trackingLogs)?> updates
        </div>
      </div>

      <?php if (count($trackingLogs) > 0): ?>
      <div class="timeline">
        <?php foreach ($trackingLogs as $i => $log): ?>
        <div class="t-item">
          <div class="t-dot">
            <?php if ($i === count($trackingLogs)-1): ?>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <?php else: ?>
              <div class="t-dot-inner"></div>
            <?php endif; ?>
          </div>
          <div class="flex items-start justify-between gap-5 flex-wrap">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3 flex-wrap mb-2">
                <h3 class="font-bold text-navy text-lg leading-tight"><?=e($log['title'])?></h3>
                <span class="text-[10px] font-extrabold uppercase tracking-widest px-2.5 py-1 rounded-full" style="background:<?=bookingStatusColor($log['status'])?>12;color:<?=bookingStatusColor($log['status'])?>;border:1px solid <?=bookingStatusColor($log['status'])?>30">
                  <?=bookingStatusLabel($log['status'])?>
                </span>
              </div>
              <?php if (!empty($log['description'])): ?>
              <p class="text-muted leading-relaxed max-w-2xl"><?=e($log['description'])?></p>
              <?php endif; ?>
              <?php if (!empty($log['location'])): ?>
              <div class="mt-3 flex items-center gap-2 text-xs font-semibold text-muted">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
                <?=e($log['location'])?>
              </div>
              <?php endif; ?>
            </div>
            <div class="text-right flex-shrink-0">
              <div class="font-bold text-navy text-sm"><?=formatDate($log['created_at'], 'M j, Y')?></div>
              <div class="text-xs text-muted mt-0.5"><?=date('g:i A', strtotime($log['created_at']))?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="text-center py-16 rounded-3xl bg-cream/60 border-2 border-dashed border-[#E5DFD3]">
        <div class="mx-auto w-16 h-16 rounded-2xl bg-coral/10 flex items-center justify-center mb-4">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#FF6B4A" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <h3 class="font-display text-navy text-2xl font-semibold mb-2">Journey timeline pending</h3>
        <p class="text-muted max-w-md mx-auto">Your concierge team will publish the first update once the booking is confirmed and preparations begin. You'll receive a WhatsApp & email notification for every new post.</p>
      </div>
      <?php endif; ?>

      <div class="mt-10 pt-8 border-t border-[#EFE7D7] flex flex-col sm:flex-row gap-4 items-center justify-between">
        <p class="text-sm text-muted">Questions about this journey? We're one click away.</p>
        <div class="flex flex-wrap gap-3">
          <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%27d%20like%20to%20ask%20about%20booking%20<?=urlencode($booking['booking_code'])?>" target="_blank" class="btn btn-primary text-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
            Chat Concierge
          </a>
          <a href="<?=SITE_URL?>/" class="btn btn-outline text-sm">Back to Home</a>
        </div>
      </div>
    </div>

  </div>
  <?php elseif (empty($code)): ?>
    <!-- Nothing to show yet -->
    <div class="card p-12 text-center">
      <div class="mx-auto w-20 h-20 rounded-3xl bg-gradient-to-br from-coral/20 to-teal/15 flex items-center justify-center mb-6">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#0F2747" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
      </div>
      <h3 class="font-display text-navy text-3xl font-semibold mb-3">Find Your Booking</h3>
      <p class="text-muted max-w-xl mx-auto mb-6">Enter the reference code from your confirmation email above to see live updates about your pet's relocation journey.</p>
      <div class="text-xs text-muted">Sample test code you can try: <span class="font-bold text-navy select-all">PV-20261005-001</span></div>
    </div>
  <?php endif; ?>

</main>

<footer class="mt-24 pt-12 pb-8 border-t border-[#EFE7D7]">
  <div class="max-w-5xl mx-auto px-6 text-center text-sm text-muted">
    <div class="font-display text-navy text-2xl font-semibold mb-2">BeethovenPets</div>
    <p>IATA & Vet Certified · <?php $_tr = getSetting('contact_email',''); if (!$_tr) $_tr = SITE_EMAIL; echo e($_tr); ?></p>
    <div class="mt-6 flex justify-center gap-6">
      <a href="<?=SITE_URL?>/" class="font-bold text-navy hover:text-coral">Home</a>
      <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%20would%20like%20to%20request%20a%20new%20quote%20for%20pet%20relocation%20services." target="_blank" class="font-bold text-navy hover:text-coral">New Quote</a>
      <a href="admin/login.php" class="font-bold text-navy hover:text-coral">Staff Portal</a>
    </div>
  </div>
</footer>
</body>
</html>
