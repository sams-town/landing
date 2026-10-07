<?php require_once __DIR__ . '/includes/init.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pets on Sale — BeethovenPets</title>
<meta name="description" content="Find quality pets: purebred dogs & cats with official certificates and guaranteed health.">
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
.btn-outline-white { border:1.5px solid rgba(251,247,240,0.35); color:var(--cream); padding:14px 28px; border-radius:999px; background:transparent; }
.btn-outline-white:hover { background:var(--cream); color:var(--navy); }

/* Cards */
.card-lux { background:white; border-radius:28px; box-shadow:0 10px 50px -20px rgba(15,39,71,0.18), 0 2px 8px rgba(15,39,71,0.04); border:1px solid rgba(239,231,215,0.6); }
.card-hover { transition:all 0.4s cubic-bezier(.2,.7,.3,1); }
.card-hover:hover { transform:translateY(-6px); box-shadow:0 30px 80px -25px rgba(15,39,71,0.25); }

/* Animations */
@keyframes float { 0%,100% { transform:translateY(0);} 50% { transform:translateY(-14px);} }
@keyframes floatSlow { 0%,100% { transform:translateY(0) rotate(-1deg);} 50% { transform:translateY(-10px) rotate(1.5deg);} }
@keyframes pulse { 0%,100% { opacity:1; transform:scale(1);} 50% { opacity:0.85; transform:scale(1.12);} }
.float { animation:float 5s ease-in-out infinite; }
.float-slow { animation:floatSlow 7s ease-in-out infinite; }
.pin-pulse { animation:pulse 2.4s ease-in-out infinite; }

.divider-gold { height:1px; background:linear-gradient(90deg,transparent,rgba(201,169,98,0.55),transparent); }

/* Nav link */
.nav-link { font-weight:500; color:var(--navy); position:relative; font-size:15px; }
.nav-link::after { content:''; position:absolute; left:0; bottom:-5px; width:0; height:2px; background:var(--coral); transition:width 0.3s; }
.nav-link:hover { color:var(--coral); }
.nav-link:hover::after { width:100%; }

/* Section eyebrow */
.eyebrow { display:inline-flex; align-items:center; gap:10px; padding:8px 16px; background:var(--teal-soft); border:1px solid rgba(79,179,168,0.25); border-radius:999px; font-size:12px; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--navy-3); }

/* Filter chip */
.filter-chip { display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border-radius:999px; border:1.5px solid var(--cream-3); background:white; font-size:13px; font-weight:600; color:var(--navy); cursor:pointer; transition:all 0.2s; }
.filter-chip:hover { border-color:rgba(255,107,74,0.4); }
.filter-chip.active { background:var(--coral); color:white; border-color:var(--coral); }

/* Pet tag */
.pet-tag { display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; }

html { scroll-behavior:smooth; }

/* Responsive tweaks */
@media (max-width: 768px) {
  .hero-img-wrap { border-radius:24px; }
  .ribbon-lux { padding:8px 14px; font-size:12px; }
}

/* === Mobile-specific fixes (400px viewport) === */
@media (max-width: 520px) {
  section { padding-top: 3rem !important; padding-bottom: 3rem !important; }
  h1.font-display { font-size: 32px !important; line-height: 1.1 !important; }
  h1.font-display.text-4xl, h1.font-display.text-5xl, h1.font-display.text-6xl,
  h2.font-display, h3.font-display { font-size: 28px !important; line-height: 1.12 !important; }
  .font-display.text-3xl { font-size: 24px !important; }
  .font-display.text-2xl { font-size: 22px !important; }
  .btn { padding: 13px 22px !important; font-size: 14px !important; gap: 8px !important; }
  .btn-primary, .btn-navy { min-height: 52px !important; }
  .btn-outline { padding: 12px 20px !important; }
  .filter-chip { padding: 8px 14px !important; font-size: 12px !important; }
  #listings .grid { grid-template-columns: 1fr !important; }
  #listings .grid .pet-card { max-width: 400px; margin: 0 auto; width: 100%; }
  .card-lux { padding: 22px !important; border-radius: 22px !important; }
  footer .grid { grid-template-columns: 1fr !important; gap: 2.5rem !important; }
  footer .space-y-4 { gap: 0.75rem !important; }
  .eyebrow { font-size: 10px !important; padding: 6px 12px !important; letter-spacing: 0.1em !important; }
  .grid-cols-3 { grid-template-columns: 1fr 1fr 1fr !important; gap: 0.75rem !important; }
  .grid-cols-2 { gap: 0.75rem !important; }
  .hero-img-wrap { aspect-ratio: 1/1 !important; }
  .pet-tag { font-size: 10px !important; padding: 3px 8px !important; }
  .flex-wrap.gap-2 { gap: 0.5rem !important; }
  .flex-wrap.gap-4 { gap: 0.75rem !important; }
}

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
        <div class="w-[80px] h-[80px] sm:w-[88px] sm:h-[88px] rounded-[22px] bg-white flex items-center justify-center transition-all group-hover:scale-105 shadow-lg shadow-navy/10 relative overflow-hidden border border-cream-3">
          <?php
            $logoNavbarUrl = ''; $logoNavbarUrlFallback = '';
            $logosDir = __DIR__ . '/uploads/logos/';
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
            if (!empty($logoNavbarUrl)):
          ?>
            <img src="<?=e($logoNavbarUrl)?>"
                 data-fallback-url="<?=e($logoNavbarUrlFallback)?>"
                 alt="BeethovenPets Logo"
                 class="max-w-[94%] max-h-[94%] w-auto h-auto object-contain block"
                 onerror="var r=this.getAttribute('data-fallback-url');if(r && !this.getAttribute('data-fb')){this.setAttribute('data-fb','1');this.src=r;}else{this.style.display='none';}"
            >
          <?php else: ?>
          <svg viewBox="0 0 220 200" width="80" height="72" xmlns="http://www.w3.org/2000/svg">
            <path d="M45 155 Q 5 110 30 65 Q 50 28 95 20" fill="none" stroke="#25A7C4" stroke-width="14" stroke-linecap="round"/>
            <path d="M60 168 Q 120 195 185 155 Q 215 118 205 70 Q 198 38 170 22" fill="none" stroke="#0F3F73" stroke-width="14" stroke-linecap="round"/>
            <circle cx="110" cy="85" r="62" fill="#25A7C4"/>
            <path d="M68 48 Q 62 38 72 32 Q 84 25 94 28 Q 104 24 112 30 Q 120 28 128 36 Q 136 30 145 38 Q 154 44 156 56 Q 162 52 165 62 Q 160 68 155 70 L 148 66 Q 145 78 138 80 Q 135 86 128 88 L 125 84 Q 118 94 110 96 Q 100 94 98 88 Q 88 92 82 86 Q 78 90 72 84 Q 68 76 68 68 Q 60 66 60 58 Z" fill="#FFFFFF"/>
            <path d="M100 90 C 96 96 86 108 82 120 L 100 122 L 102 108 C 105 106 108 106 110 108 L 112 122 L 130 120 C 126 108 116 96 112 90 C 120 86 134 78 142 66 C 145 70 138 80 128 84 C 126 88 124 86 120 88 L 116 92 Q 114 96 110 98 Q 106 96 104 92 L 100 90 Z" fill="#FF9B2A"/>
            <path d="M84 114 Q 88 118 96 120 Q 108 122 112 118 Q 116 122 128 120 Q 136 118 140 114 L 140 122 Q 132 126 112 126 Q 92 126 84 122 Z" fill="#0F3F73"/>
            <circle cx="96" cy="100" r="3" fill="#0F2747"/>
            <circle cx="124" cy="100" r="3" fill="#0F2747"/>
            <path d="M170 28 L196 54 L182 60 Z" fill="#0F3F73"/>
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
        <a href="<?=SITE_URL?>/#services" class="nav-link">Services</a>
        <a href="<?=SITE_URL?>/#gallery" class="nav-link">Gallery</a>
        <a href="pets-sale.php" class="nav-link" style="color:var(--coral)">Pets on Sale</a>
        <a href="<?=SITE_URL?>/#stories" class="nav-link">Stories</a>
        <a href="<?=SITE_URL?>/#faq" class="nav-link">FAQ</a>
      </div>

      <div class="hidden lg:flex items-center gap-4">
        <a href="<?=SITE_URL?>/#gallery" class="btn-outline text-sm">Gallery</a>
        <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%20would%20like%20to%20inquire%20about%20pets%20for%20sale%20and%20relocation%20services." target="_blank" class="btn btn-primary text-sm">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
          Submit Quote
        </a>
      </div>

      <button id="menuBtn" class="lg:hidden w-11 h-11 rounded-xl border border-cream-3 flex items-center justify-center text-navy">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
    </div>

    <div id="mobileMenu" class="hidden lg:hidden border-t border-cream-3 py-5 flex flex-col gap-3">
      <a href="<?=SITE_URL?>/#services" class="nav-link py-2">Services</a>
      <a href="<?=SITE_URL?>/#gallery" class="nav-link py-2">Gallery</a>
      <a href="pets-sale.php" class="nav-link py-2">Pets on Sale</a>
      <a href="<?=SITE_URL?>/#stories" class="nav-link py-2">Stories</a>
      <a href="<?=SITE_URL?>/#faq" class="nav-link py-2">FAQ</a>
      <div class="flex flex-col gap-3 pt-3 border-t border-cream-3">
        <a href="<?=SITE_URL?>/#gallery" class="btn-outline text-sm justify-center">Gallery</a>
        <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%20would%20like%20to%20inquire%20about%20pets%20for%20sale%20and%20relocation%20services." target="_blank" class="btn btn-primary text-sm justify-center">Submit Quote</a>
      </div>
    </div>
  </div>
</nav>

<!-- ===== HERO PETS ON SALE ===== -->
<section class="relative overflow-hidden py-14 lg:py-20">
  <div class="max-w-7xl mx-auto px-6 lg:px-10 relative">
    <div class="grid lg:grid-cols-2 gap-14 items-center">
      <div>
        <div class="ribbon-lux mb-8 float" style="display:inline-flex;align-items:center;gap:10px;padding:12px 20px;background:white;border-radius:999px;box-shadow:0 6px 24px -8px rgba(15,39,71,0.18);border:1px solid rgba(15,39,71,0.05);font-weight:600;font-size:14px;color:var(--navy)">
          <span class="w-2.5 h-2.5 rounded-full bg-coral pin-pulse"></span>
          <span class="tracking-wide">🐾 48 Pets Available · Official Certificates</span>
        </div>

        <h1 class="font-display text-navy leading-[1.03] tracking-[-0.02em] text-[46px] sm:text-[58px] mb-7">
          Find Your Perfect<br>
          <span class="relative inline-block italic text-coral">
            Pet Companion
            <svg class="absolute -bottom-3 left-0 w-full" height="18" viewBox="0 0 400 18" preserveAspectRatio="none">
              <path d="M4 11 C80 1 200 1 396 10" stroke="#FF6B4A" stroke-width="4.5" fill="none" stroke-linecap="round"/>
            </svg>
          </span><br>
          Right Here.
        </h1>

        <p class="text-lg text-muted leading-relaxed max-w-xl mb-8">
          A premium collection of pets from <strong class="text-navy">trusted breeders</strong>. Complete with <strong class="text-navy">pedigree certificates, vaccinations, microchip,</strong> and a 30-day health guarantee.
        </p>

        <div class="flex flex-wrap gap-4 mb-10">
          <a href="#listings" class="btn btn-primary text-base">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Browse All Pets
          </a>
          <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%20would%20like%20to%20ask%20about%20available%20pets%20for%20sale." target="_blank" class="btn btn-navy text-base">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
            WhatsApp Consultation
          </a>
        </div>

        <div class="grid grid-cols-3 gap-4 max-w-md">
          <div class="card-lux p-5 text-center">
            <div class="font-display text-3xl font-semibold text-navy mb-1">100%</div>
            <div class="text-xs font-bold text-muted uppercase tracking-wider">Healthy & Vaccinated</div>
          </div>
          <div class="card-lux p-5 text-center">
            <div class="font-display text-3xl font-semibold text-coral mb-1">30h</div>
            <div class="text-xs font-bold text-muted uppercase tracking-wider">Health Guarantee</div>
          </div>
          <div class="card-lux p-5 text-center">
            <div class="font-display text-3xl font-semibold text-teal mb-1">5★</div>
            <div class="text-xs font-bold text-muted uppercase tracking-wider">Top Breeders</div>
          </div>
        </div>
      </div>

      <div class="relative">
        <div class="relative aspect-[4/5] rounded-[36px] overflow-hidden shadow-2xl shadow-coral/20">
          <img src="https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=super%20adorable%20chubby%20fluffy%20golden%20retriever%20baby%20puppy%2C%20big%20round%20sparkling%20eyes%2C%20tiny%20pink%20tongue%20out%2C%20sitting%20on%20cloud%20fluffy%20cream%20pillow%2C%20soft%20pastel%20pink%20bokeh%20background%2C%20cute%20baby%20puppy%20studio%20portrait%2C%20ultra%20detailed%20fur&image_size=portrait_4_3" class="w-full h-full object-cover" alt="Golden Retriever Puppy">
        </div>
        <!-- Featured tag -->
        <div class="absolute -top-4 -left-4 card-lux py-3 px-5 flex items-center gap-3 float-slow">
          <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-coral to-gold flex items-center justify-center text-white shadow-lg shadow-coral/30 pin-pulse">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          </div>
          <div>
            <div class="text-[10px] font-bold text-muted uppercase tracking-wider">Featured</div>
            <div class="text-sm font-bold text-navy">Champion Bloodline</div>
          </div>
        </div>
        <!-- Stock badge -->
        <div class="absolute bottom-6 right-6 card-lux p-4 max-w-[220px] float">
          <div class="flex items-center gap-2 mb-2">
            <span class="w-2.5 h-2.5 rounded-full bg-teal pin-pulse"></span>
            <span class="text-[10px] font-bold text-teal uppercase tracking-wider">Available Now</span>
          </div>
          <div class="font-display text-2xl font-semibold text-navy leading-none">12 Puppies</div>
          <div class="text-xs font-bold text-muted mt-1">Ready for adoption</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== FILTER + LISTINGS ===== -->
<section id="listings" class="py-16 lg:py-24 bg-cream-2/50 relative">
  <div class="max-w-7xl mx-auto px-6 lg:px-10">
    <!-- Filters -->
    <div class="flex flex-wrap items-center justify-between gap-6 mb-12">
      <div>
        <h2 class="font-display text-navy text-3xl lg:text-4xl font-semibold mb-2">All Available Pets</h2>
        <p class="text-muted font-medium">Filter by your favorite pet category.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button class="filter-chip active" data-filter="all">All</button>
        <button class="filter-chip" data-filter="dog">🐕 Dogs</button>
        <button class="filter-chip" data-filter="cat">🐈 Cats</button>
        <button class="filter-chip" data-filter="puppy">🐶 Puppies</button>
        <button class="filter-chip" data-filter="kitten">🐱 Kittens</button>
      </div>
    </div>

    <!-- Pet Grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 lg:gap-7">
      <?php
        $pets = [
          ['cat'=>'dog','type'=>'puppy','name'=>'Max','breed'=>'Golden Retriever','age'=>'2 Months','gender'=>'Jantan','price'=>'12.500.000','prompt'=>'ultra%20cute%20chubby%20baby%20golden%20retriever%20puppy%2C%20big%20sparkling%20brown%20eyes%2C%20tiny%20pink%20tongue%20out%2C%20sitting%20on%20pastel%20yellow%20soft%20blanket%2C%20fluffy%20cream%20fur%2C%20cute%20studio%20portrait%2C%20heart%20bokeh','tags'=>[['l'=>'STGB','c'=>'#C9A962','b'=>'#FBF3DC'],['l'=>'Ready','c'=>'#4FB3A8','b'=>'#E7F5F3']]],
          ['cat'=>'cat','type'=>'kitten','name'=>'Mochi','breed'=>'Persia Peaknose','age'=>'3 Months','gender'=>'Betina','price'=>'8.750.000','prompt'=>'super%20cute%20fluffy%20white%20persian%20peaknose%20kitten%2C%20huge%20round%20shiny%20blue%20eyes%2C%20tiny%20pink%20nose%2C%20sitting%20inside%20pastel%20pink%20flower%20pot%2C%20soft%20cream%20studio%20lighting%2C%20chubby%20cheeks%20baby%20kitten','tags'=>[['l'=>'Vaccinated','c'=>'#FF6B4A','b'=>'#FFEDE5'],['l'=>'Ready','c'=>'#4FB3A8','b'=>'#E7F5F3']]],
          ['cat'=>'dog','type'=>'puppy','name'=>'Rocky','breed'=>'Siberian Husky','age'=>'3 Months','gender'=>'Jantan','price'=>'15.000.000','prompt'=>'adorable%20baby%20siberian%20husky%20puppy%20heterochromia%2C%20one%20eye%20bright%20blue%20one%20brown%2C%20tiny%20smile%2C%20fluffy%20thick%20fur%2C%20sitting%20on%20soft%20white%20snow%20rug%2C%20cute%20chubby%20baby%20dog%20studio','tags'=>[['l'=>'Blue Eyes','c'=>'#6366F1','b'=>'#EEF2FF'],['l'=>'STGB','c'=>'#C9A962','b'=>'#FBF3DC']]],
          ['cat'=>'cat','type'=>'kitten','name'=>'Luna','breed'=>'Maine Coon','age'=>'4 Months','gender'=>'Betina','price'=>'18.500.000','prompt'=>'tiny%20fluffy%20maine%20coon%20baby%20kitten%2C%20huge%20paws%20oversized%2C%20big%20shiny%20green%20eyes%2C%20chubby%20cheeks%2C%20playing%20with%20small%20butterfly%20stuffed%20toy%2C%20soft%20pink%20studio%20background%2C%20ultra%20cute','tags'=>[['l'=>'Rare','c'=>'#EC4899','b'=>'#FCE7F3'],['l'=>'Booking','c'=>'#FF6B4A','b'=>'#FFEDE5']]],
          ['cat'=>'dog','type'=>'puppy','name'=>'Bruno','breed'=>'French Bulldog','age'=>'2.5 Months','gender'=>'Jantan','price'=>'22.000.000','prompt'=>'chubby%20french%20bulldog%20baby%20puppy%2C%20big%20bat%20ears%2C%20sparkly%20eyes%2C%20tiny%20tongue%20always%20hanging%2C%20wearing%20tiny%20yellow%20raincoat%2C%20sitting%20on%20pastel%20blue%20blanket%2C%20ultra%20adorable%20studio%20photo','tags'=>[['l'=>'Brachy','c'=>'#D97706','b'=>'#FEF3C7'],['l'=>'Ready','c'=>'#4FB3A8','b'=>'#E7F5F3']]],
          ['cat'=>'cat','type'=>'kitten','name'=>'Coco','breed'=>'Scottish Fold','age'=>'2 Months','gender'=>'Betina','price'=>'9.800.000','prompt'=>'chubby%20scottish%20fold%20baby%20kitten%20folded%20ears%2C%20huge%20round%20golden%20eyes%2C%20sitting%20like%20a%20loaf%20bread%20on%20cozy%20knitted%20blanket%2C%20tiny%20pink%20paws%2C%20soft%20cream%20studio%2C%20ultra%20cute%20aesthetic','tags'=>[['l'=>'Folded','c'=>'#0F2747','b'=>'#E6EEFA'],['l'=>'Ready','c'=>'#4FB3A8','b'=>'#E7F5F3']]],
          ['cat'=>'dog','type'=>'dog','name'=>'Bella','breed'=>'Toy Poodle','age'=>'5 Months','gender'=>'Betina','price'=>'11.200.000','prompt'=>'tiny%20curly%20apricot%20toy%20poodle%20puppy%2C%20big%20sparkling%20eyes%2C%20wearing%20tiny%20pink%20bowtie%2C%20sitting%20on%20fluffy%20cloud%20pillow%2C%20tongue%20out%20smile%2C%20ultra%20cute%20studio%20photography','tags'=>[['l'=>'Groomed','c'=>'#FF6B4A','b'=>'#FFEDE5'],['l'=>'STGB','c'=>'#C9A962','b'=>'#FBF3DC']]],
          ['cat'=>'cat','type'=>'cat','name'=>'Simba','breed'=>'British Shorthair','age'=>'6 Months','gender'=>'Jantan','price'=>'13.500.000','prompt'=>'chubby%20round%20british%20shorthair%20blue%20gray%20kitten%2C%20massive%20chubby%20cheeks%2C%20big%20round%20copper%20eyes%2C%20sitting%20like%20a%20ball%20on%20cream%20rug%2C%20tiny%20smile%2C%20soft%20pastel%20background%20cute%20portrait','tags'=>[['l'=>'Chubby','c'=>'#0F2747','b'=>'#E6EEFA'],['l'=>'Ready','c'=>'#4FB3A8','b'=>'#E7F5F3']]],
          ['cat'=>'dog','type'=>'puppy','name'=>'Zeus','breed'=>'Alaskan Malamute','age'=>'4 Months','gender'=>'Jantan','price'=>'17.800.000','prompt'=>'giant%20fluffy%20alaskan%20malamute%20baby%20puppy%2C%20thick%20fluffy%20coat%2C%20big%20sparkly%20brown%20eyes%2C%20tiny%20happy%20smile%2C%20sitting%20on%20snow%20white%20fluffy%20rug%2C%20chubby%20paws%2C%20cute%20baby%20portrait','tags'=>[['l'=>'Giant','c'=>'#7C3AED','b'=>'#F5F3FF'],['l'=>'Booking','c'=>'#FF6B4A','b'=>'#FFEDE5']]],
          ['cat'=>'cat','type'=>'kitten','name'=>'Snowbell','breed'=>'Ragdoll','age'=>'3.5 Months','gender'=>'Betina','price'=>'16.000.000','prompt'=>'fluffy%20ragdoll%20baby%20kitten%2C%20stunning%20bright%20blue%20big%20eyes%2C%20cream%20point%20soft%20fur%2C%20sleepy%20relaxed%20pose%20on%20cashmere%20soft%20blanket%2C%20tiny%20pink%20nose%2C%20chubby%20cheeks%20ultra%20cute','tags'=>[['l'=>'Blue Eyes','c'=>'#6366F1','b'=>'#EEF2FF'],['l'=>'Ready','c'=>'#4FB3A8','b'=>'#E7F5F3']]],
          ['cat'=>'dog','type'=>'puppy','name'=>'Oreo','breed'=>'Shih Tzu','age'=>'2 Months','gender'=>'Jantan','price'=>'7.500.000','prompt'=>'tiny%20teacup%20size%20shih%20tzu%20puppy%2C%20black%20white%20fluffy%20fur%2C%20top%20ponytail%20with%20pink%20ribbon%2C%20big%20shiny%20eyes%2C%20tongue%20out%20happy%20smile%2C%20sitting%20inside%20tiny%20teacup%2C%20lavender%20pillow%20cute%20studio','tags'=>[['l'=>'Teacup','c'=>'#EC4899','b'=>'#FCE7F3'],['l'=>'Ready','c'=>'#4FB3A8','b'=>'#E7F5F3']]],
          ['cat'=>'cat','type'=>'kitten','name'=>'Milo','breed'=>'Exotic Shorthair','age'=>'3 Months','gender'=>'Jantan','price'=>'10.200.000','prompt'=>'flat%20faced%20chubby%20exotic%20shorthair%20baby%20kitten%2C%20huge%20round%20eyes%20wide%20open%2C%20fluffy%20gray%20coat%2C%20tiny%20pink%20nose%2C%20squishy%20chubby%20cheeks%2C%20soft%20white%20background%20cute%20portrait','tags'=>[['l'=>'Flat Face','c'=>'#D97706','b'=>'#FEF3C7'],['l'=>'Vaccinated','c'=>'#FF6B4A','b'=>'#FFEDE5']]],
        ];
        foreach($pets as $p):
      ?>
      <div class="pet-card card-lux card-hover overflow-hidden" data-type="<?=$p['cat']?>" data-stage="<?=$p['type']?>">
        <div class="relative aspect-[4/5] bg-cream-3 overflow-hidden group">
          <img src="https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=<?=$p['prompt']?>&image_size=portrait_4_3" alt="<?=$p['name']?> - <?=$p['breed']?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
          <!-- Gender badge -->
          <div class="absolute top-4 left-4">
            <?php $genderBg = $p['gender']==='Jantan' ? 'rgba(15,39,71,0.9)' : 'rgba(236,72,153,0.9)'; ?>
            <div class="rounded-full px-3 py-1.5 backdrop-blur-md text-white text-[11px] font-bold uppercase tracking-wider flex items-center gap-1.5" style="background:<?=$genderBg?>">
              <?= $p['gender']==='Jantan'
                ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="10" cy="14" r="5"/><line x1="19" y1="5" x2="13.6" y2="10.4"/><line x1="15" y1="5" x2="19" y2="5"/><line x1="19" y1="5" x2="19" y2="9"/></svg>'
                : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="9" r="5"/><line x1="12" y1="14" x2="12" y2="22"/><line x1="9" y1="19" x2="15" y2="19"/></svg>'
              ?>
              <?= $p['gender']==='Jantan' ? 'Male' : 'Female' ?>
            </div>
          </div>
          <!-- Favorite button -->
          <button class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/90 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all backdrop-blur-md shadow-lg hover:scale-110">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FF6B4A" stroke-width="2.2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          </button>
          <!-- Tags -->
          <div class="absolute bottom-4 left-4 right-4 flex flex-wrap gap-1.5">
            <?php foreach($p['tags'] as $t): ?>
              <span class="pet-tag" style="background:<?=$t['b']?>;color:<?=$t['c']?>"><?=$t['l']?></span>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="p-5 sm:p-6">
          <div class="flex items-start justify-between mb-2">
            <div>
              <h3 class="font-display text-2xl font-semibold text-navy leading-tight"><?=$p['name']?></h3>
              <p class="text-sm font-semibold text-coral"><?=$p['breed']?></p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 mb-4 py-4 border-y border-cream-3">
            <div class="flex items-center gap-2">
              <div class="w-7 h-7 rounded-lg bg-cream-3 flex items-center justify-center">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#0F2747" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              </div>
              <div>
                <div class="text-[10px] uppercase font-bold text-muted tracking-wider">Age</div>
                <div class="text-xs font-bold text-navy"><?=$p['age']?></div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-7 h-7 rounded-lg bg-coral/10 flex items-center justify-center">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#FF6B4A" stroke-width="2.2"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z"/><path d="M12 6v6l4 2"/></svg>
              </div>
              <div>
                <div class="text-[10px] uppercase font-bold text-muted tracking-wider">Vaccine</div>
                <div class="text-xs font-bold text-teal">Complete ✓</div>
              </div>
            </div>
          </div>

          <div class="flex items-end justify-between gap-3 mb-4">
            <div>
              <div class="text-[10px] font-bold text-muted uppercase tracking-wider mb-1">Starting from</div>
              <div class="font-display text-2xl lg:text-3xl font-semibold text-navy leading-none">Rp <?= number_format((int)str_replace('.','',$p['price']),0,',','.') ?></div>
            </div>
          </div>

          <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%27m%20interested%20in%20<?=urlencode($p['name'])?>%20(<?=urlencode($p['breed'])?>)%20priced%20at%20Rp%20<?=urlencode($p['price'])?>%2C%20could%20I%20get%20more%20details%3F" target="_blank" class="btn btn-primary w-full justify-center py-3 text-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
            Ask via WhatsApp
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- CTA Bottom -->
    <div class="mt-16 card-lux p-8 lg:p-12 relative overflow-hidden">
      <div class="absolute -top-20 -right-20 w-72 h-72 rounded-full bg-coral/8 blur-3xl"></div>
      <div class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full bg-teal/8 blur-3xl"></div>
      <div class="grid lg:grid-cols-2 gap-10 items-center relative">
        <div>
          <h3 class="font-display text-navy text-3xl lg:text-4xl font-semibold leading-[1.1] mb-4">
            Can't find the right match?<br>
            <span class="italic text-coral">We'll help you find one!</span>
          </h3>
          <p class="text-muted text-base leading-relaxed mb-6">Share your dream pet preferences (breed, color, age, budget) with our team. We have a network of 50+ trusted partner breeders across Indonesia.</p>
          <div class="flex flex-wrap gap-3">
            <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%20would%20like%20to%20request%20a%20pet%20with%20the%20following%20specifications%20..." target="_blank" class="btn btn-primary">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
              Request Pet via WhatsApp
            </a>
            <a href="<?=SITE_URL?>/" class="btn btn-outline">Back to Home</a>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <?php foreach([
            ['i'=>'📋','t'=>'Pedigree & Certificates','d'=>'Complete official documents'],
            ['i'=>'💉','t'=>'Full Vaccination','d'=>'Medical records provided'],
            ['i'=>'🔖','t'=>'Microchip ID','d'=>'Per-parent integrated'],
            ['i'=>'🛡️','t'=>'30-Day Guarantee','d'=>'100% health assured'],
          ] as $b): ?>
          <div class="rounded-2xl border border-cream-3 bg-white/60 p-5 card-hover">
            <div class="text-3xl mb-2"><?=$b['i']?></div>
            <div class="font-bold text-navy text-sm mb-0.5"><?=$b['t']?></div>
            <div class="text-xs text-muted font-medium"><?=$b['d']?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="bg-navy text-cream pt-20 pb-10 relative overflow-hidden">
  <div class="absolute -top-32 right-0 w-[600px] h-[600px] rounded-full bg-coral/5 blur-3xl"></div>
  <div class="max-w-7xl mx-auto px-6 lg:px-10 relative">
    <div class="grid md:grid-cols-2 lg:grid-cols-5 gap-10 lg:gap-8 mb-16">
      <div class="lg:col-span-2">
        <div class="flex items-center gap-5 mb-6">
          <div class="w-[84px] h-[84px] sm:w-[92px] sm:h-[92px] rounded-[24px] bg-gradient-to-br from-[#172F52] via-[#0F2747] to-[#1E3A5F] flex items-center justify-center shadow-2xl border border-coral/30">
            <?php
              $footerLogoBox_ps = ''; $footerLogoBoxFb_ps = '';
              $fL = getSetting('logo_footer','');
              if ($fL && is_file(__DIR__ . '/uploads/logos/' . $fL)) {
                $footerLogoBox_ps = rtrim(SITE_URL,'/') . '/uploads/logos/' . rawurlencode($fL);
                $footerLogoBoxFb_ps = ($_basePath ?? '') . '/uploads/logos/' . rawurlencode($fL);
              }
              if (!empty($footerLogoBox_ps)):
            ?>
              <img src="<?=e($footerLogoBox_ps)?>"
                   data-fallback-url="<?=e($footerLogoBoxFb_ps)?>"
                   alt="BeethovenPets Footer Logo"
                   class="max-w-[94%] max-h-[94%] w-auto h-auto object-contain block"
                   onerror="var r=this.getAttribute('data-fallback-url');if(r && !this.getAttribute('data-fb')){this.setAttribute('data-fb','1');this.src=r;}else{this.style.display='none';}"
              >
            <?php else: ?>
            <svg viewBox="0 0 220 200" width="82" height="76" xmlns="http://www.w3.org/2000/svg">
              <path d="M45 155 Q 5 110 30 65 Q 50 28 95 20" fill="none" stroke="#FBF7F0" stroke-width="14" stroke-linecap="round" opacity="0.88"/>
              <path d="M60 168 Q 120 195 185 155 Q 215 118 205 70 Q 198 38 170 22" fill="none" stroke="#FF6B4A" stroke-width="14" stroke-linecap="round" opacity="0.95"/>
              <circle cx="110" cy="85" r="62" fill="#2AB3D2"/>
              <path d="M68 48 Q 62 38 72 32 Q 84 25 94 28 Q 104 24 112 30 Q 120 28 128 36 Q 136 30 145 38 Q 154 44 156 56 Q 162 52 165 62 Q 160 68 155 70 L 148 66 Q 145 78 138 80 Q 135 86 128 88 L 125 84 Q 118 94 110 96 Q 100 94 98 88 Q 88 92 82 86 Q 78 90 72 84 Q 68 76 68 68 Q 60 66 60 58 Z" fill="#FFFFFF"/>
              <path d="M170 28 L196 54 L182 60 Z" fill="#FBF7F0"/>
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
          Indonesia's most complete premium pet relocation + pet shop. Quality pets, trusted breeders, official health guarantee.
        </p>
        <div class="mb-5">
          <div class="text-sm font-bold text-cream/80 mb-3 uppercase tracking-wider">Follow us on</div>
          <div class="flex items-center gap-2 flex-wrap">
            <a href="#" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-cream/20 text-sm font-semibold hover:bg-coral hover:border-coral transition-all">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
              Facebook
            </a>
            <a href="#" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-cream/20 text-sm font-semibold hover:bg-coral hover:border-coral transition-all">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
              Instagram
            </a>
          </div>
        </div>
        <div class="flex gap-3">
          <?php $socialsFooter = [
            ['t'=>'Facebook','i'=>'fb'],
            ['t'=>'Instagram','i'=>'ig'],
            ['t'=>'Twitter','i'=>'tw'],
            ['t'=>'LinkedIn','i'=>'li'],
          ];
          foreach($socialsFooter as $s):
          ?>
          <a href="#" aria-label="<?=$s['t']?>" class="w-11 h-11 rounded-full border border-cream/20 flex items-center justify-center hover:bg-coral hover:border-coral transition-all">
            <?php if($s['i']==='fb'): ?>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 2h-17A1.5 1.5 0 0 0 2 3.5v17A1.5 1.5 0 0 0 3.5 22h17a1.5 1.5 0 0 0 1.5-1.5v-17A1.5 1.5 0 0 0 20.5 2zM8 19H5v-9h3zM6.5 8.25A1.75 1.75 0 1 1 8.3 6.5a1.78 1.78 0 0 1-1.8 1.75zM19 19h-3v-4.74c0-1.42-.6-1.93-1.38-1.93A1.74 1.74 0 0 0 13 14.19a.66.66 0 0 0 0 .14V19h-3v-9h2.9v1.3a3.11 3.11 0 0 1 2.7-1.4c1.55 0 3.36.86 3.36 3.66z"/></svg>
            <?php elseif($s['i']==='ig'): ?>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
            <?php elseif($s['i']==='tw'): ?>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg>
            <?php else: ?>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <h4 class="font-display font-semibold text-xl mb-6">Services</h4>
        <ul class="space-y-3 text-sm text-cream/60">
          <li><a href="<?=SITE_URL?>/#services" class="hover:text-coral">Domestic Air Transport</a></li>
          <li><a href="<?=SITE_URL?>/#services" class="hover:text-coral">Domestic Land Concierge</a></li>
          <li><a href="<?=SITE_URL?>/#services" class="hover:text-coral">International Relocation</a></li>
          <li><a href="<?=SITE_URL?>/#services" class="hover:text-coral">Vet & Documentation</a></li>
          <li><a href="<?=SITE_URL?>/#services" class="hover:text-coral">Transit Boarding Care</a></li>
        </ul>
      </div>

      <div>
        <h4 class="font-display font-semibold text-xl mb-6">Explore</h4>
        <ul class="space-y-3 text-sm text-cream/60">
          <li><a href="#" class="hover:text-coral">About BeethovenPets</a></li>
          <li><a href="<?=SITE_URL?>/#gallery" class="hover:text-coral">Pet Journey Gallery</a></li>
          <li><a href="pets-sale.php" class="hover:text-coral" style="color:var(--coral)">Pets on Sale</a></li>
          <li><a href="<?=SITE_URL?>/#stories" class="hover:text-coral">Customer Stories</a></li>
          <li><a href="<?=SITE_URL?>/#faq" class="hover:text-coral">FAQ</a></li>
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
  const WECHAT_ID = '<?=e(wechatId())?>';

  // Mobile menu
  const mb = document.getElementById('menuBtn');
  const mm = document.getElementById('mobileMenu');
  mb.addEventListener('click', () => mm.classList.toggle('hidden'));

  // WeChat button
  const wechatBtn = document.getElementById('wechatBtn');
  if (wechatBtn) {
    wechatBtn.addEventListener('click', (e) => {
      e.preventDefault();
      alert('WeChat ID: ' + WECHAT_ID + '\nPlease add us on WeChat for a consultation.');
    });
  }

  // Filter chips
  document.querySelectorAll('.filter-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      const f = chip.dataset.filter;
      document.querySelectorAll('.pet-card').forEach(card => {
        let show = false;
        if (f === 'all') show = true;
        else if (f === 'dog' || f === 'cat') show = card.dataset.type === f;
        else if (f === 'puppy' || f === 'kitten') show = card.dataset.stage === f;
        card.style.display = show ? '' : 'none';
      });
    });
  });
</script>
</body>
</html>
