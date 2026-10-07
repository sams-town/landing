<?php require_once __DIR__ . '/includes/init.php';
$code = $_GET['code'] ?? '';
$booking = null;
$customer = null;
if ($code) {
    try {
        $booking = pawDB()->fetch("SELECT * FROM bookings WHERE booking_code = ?", [$code]);
        if ($booking) {
            $customer = pawDB()->fetch("SELECT * FROM customers WHERE id = ?", [$booking['customer_id']]);
        }
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quote Submitted — BeethovenPets</title>
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
  .btn-primary { background:var(--coral);color:white;padding:16px 28px;box-shadow:0 10px 30px -10px rgba(255,107,74,0.55); }
  .btn-primary:hover { transform:translateY(-2px); background:#E55536; }
  .btn-outline { border:2px solid var(--navy); color:var(--navy); padding:14px 26px; background:transparent; }
  .btn-outline:hover { background:var(--navy); color:var(--cream); transform:translateY(-2px); }
  .card { background:white; border-radius:28px; box-shadow:0 20px 60px -20px rgba(15,39,71,0.2); border:1px solid #EFE7D7; }
  @keyframes pop { 0%{transform:scale(0);opacity:0} 60%{transform:scale(1.15)} 100%{transform:scale(1);opacity:1} }
  .pop-anim { animation:pop 0.7s cubic-bezier(.2,.7,.3,1); }
  @keyframes confetti { 0%{transform:translateY(-10px) rotate(0deg);opacity:1} 100%{transform:translateY(600px) rotate(720deg);opacity:0} }
  .confetti { position:absolute;width:10px;height:10px;animation:confetti 3s ease-in forwards; }
</style>
</head>
<body class="bg-cream min-h-screen flex items-center justify-center py-16 px-5 relative overflow-hidden">
  <!-- Decorative confetti -->
  <div id="confettiWrap" class="absolute inset-0 pointer-events-none"></div>

  <div class="max-w-2xl w-full relative">
    <div class="card p-10 lg:p-14 text-center relative overflow-hidden">
      <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-coral/8 blur-3xl"></div>
      <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-teal/8 blur-3xl"></div>

      <div class="relative">
        <!-- Success Icon -->
        <div class="mx-auto w-24 h-24 rounded-full bg-gradient-to-br from-[#10B981] to-[#059669] flex items-center justify-center shadow-2xl mb-8 pop-anim">
          <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>

        <h1 class="font-display text-navy text-4xl sm:text-5xl lg:text-6xl font-semibold leading-[1.05] mb-5">
          Quote Received<br>
          <span class="italic text-coral">Successfully!</span>
        </h1>
        <p class="text-muted text-lg leading-relaxed mb-8 max-w-lg mx-auto">
          Thank you for choosing BeethovenPets. Our concierge team has received your pet relocation request and will contact you within <strong class="text-navy">24 hours</strong> with a personalized proposal.
        </p>

        <?php if ($code): ?>
        <!-- Booking Code Card -->
        <div class="rounded-2xl border-2 border-dashed border-coral/40 bg-coral/5 px-6 py-6 mb-8 max-w-md mx-auto">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-muted mb-2">Your Booking Reference Code</div>
          <div class="font-display text-navy text-4xl font-bold tracking-wider mb-3 select-all"><?=e($code)?></div>
          <div class="text-xs font-semibold text-muted">Save this code — you'll need it to track your pet's journey anytime.</div>
        </div>
        <?php endif; ?>

        <?php if ($booking && $customer): ?>
        <!-- Summary -->
        <div class="grid grid-cols-2 gap-4 text-left mb-10 max-w-md mx-auto">
          <div class="p-4 rounded-2xl bg-[#EFE7D7]/40">
            <div class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Route</div>
            <div class="font-bold text-navy"><?=e($booking['origin_city'])?> → <?=e($booking['destination_city'])?></div>
          </div>
          <div class="p-4 rounded-2xl bg-[#EFE7D7]/40">
            <div class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Departure</div>
            <div class="font-bold text-navy"><?=formatDate($booking['travel_date'])?></div>
          </div>
          <div class="p-4 rounded-2xl bg-[#EFE7D7]/40">
            <div class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Pet Weight</div>
            <div class="font-bold text-navy"><?=e($booking['pet_weight_kg'])?> kg · <?=petSizeLabel($booking['pet_type_booking'])?></div>
          </div>
          <div class="p-4 rounded-2xl bg-[#EFE7D7]/40">
            <div class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Estimated Budget</div>
            <div class="font-bold text-coral"><?=formatRupiah($booking['price_estimated_idr'])?></div>
          </div>
        </div>

        <div class="rounded-2xl bg-teal/8 border border-teal/20 p-5 text-left mb-10 max-w-md mx-auto">
          <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-xl bg-teal/15 flex items-center justify-center flex-shrink-0">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4FB3A8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div>
              <div class="font-bold text-navy mb-1">Confirmation sent to your inbox</div>
              <div class="text-sm text-muted">We emailed a copy to <strong><?=e($customer['email'])?></strong>. Check spam if not found in inbox.</div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <div class="flex flex-wrap gap-4 justify-center">
          <a href="track.php?code=<?=urlencode($code)?>" class="btn btn-primary">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Track This Journey
          </a>
          <a href="<?=SITE_URL?>/" class="btn btn-outline">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Back to Homepage
          </a>
        </div>

        <div class="mt-10 pt-6 border-t text-sm text-muted" style="border-color:var(--cream-3)">
          Need urgent help? <a href="https://wa.me/<?=waDigits()?>" class="font-bold text-navy underline underline-offset-4 hover:text-coral">Chat with us on WhatsApp →</a>
        </div>
      </div>
    </div>
  </div>

<script>
(function(){
  const wrap = document.getElementById('confettiWrap');
  const colors = ['#FF6B4A','#4FB3A8','#C9A962','#0F2747','#FFD166'];
  for (let i=0;i<60;i++) {
    const c = document.createElement('div');
    c.className = 'confetti';
    c.style.background = colors[i%colors.length];
    c.style.left = Math.random()*100 + '%';
    c.style.top = -20 + 'px';
    c.style.animationDelay = (Math.random()*2) + 's';
    c.style.animationDuration = (2.5 + Math.random()*1.5) + 's';
    if (Math.random() > 0.5) c.style.borderRadius = '2px';
    else c.style.borderRadius = '50%';
    wrap.appendChild(c);
  }
})();
</script>
</body>
</html>
