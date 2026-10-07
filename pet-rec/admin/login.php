<?php
/**
 * Admin Login Page — bcrypt password verify + session
 */
require_once __DIR__ . '/../includes/init.php';

$page_title = 'Sign In';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $admin = pawDB()->fetch("SELECT * FROM admins WHERE email = ?", [$email]);
            if (!$admin) {
                $error = 'Invalid email or password.';
            } else {
                // bcrypt verify OR fallback for plain dev password
                $valid = false;
                if (function_exists('password_verify') && str_starts_with($admin['password_hash'], '$2')) {
                    $valid = password_verify($password, $admin['password_hash']);
                }
                // Fallback: check legacy md5 or plain (in case user changed seed)
                if (!$valid && $admin['password_hash'] === md5($password)) $valid = true;
                if (!$valid && $admin['password_hash'] === $password) $valid = true;

                if ($valid) {
                    $_SESSION['admin_id'] = (int)$admin['id'];
                    pawDB()->execute("UPDATE admins SET last_login = NOW() WHERE id = ?", [$admin['id']]);
                    setFlash('admin', 'Welcome back, ' . e($admin['full_name']) . '! You are now signed in.', 'success');
                    $redirect = $_GET['redirect'] ?? 'index.php';
                    redirect($redirect);
                } else {
                    $error = 'Invalid email or password.';
                }
            }
        } catch (Throwable $e) {
            $error = 'Database error. Make sure SQL schema is imported into MySQL and config.php has correct credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In · BeethovenPets Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root { --cream:#FBF7F0;--navy:#0F2747;--coral:#FF6B4A;--teal:#4FB3A8;--muted:#6B7280; }
  body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--navy); color:var(--navy); min-height:100vh; margin:0; }
  .bg-wrap { min-height:100vh; display:grid; grid-template-columns:1.1fr 1fr; }
  @media (max-width: 900px) { .bg-wrap { grid-template-columns:1fr; } }
  .hero-side { background:var(--navy); color:var(--cream); padding:48px; display:flex; flex-direction:column; position:relative; overflow:hidden; }
  @media (max-width: 900px) { .hero-side { display:none; } }
  .hero-side::before { content:''; position:absolute; inset:0; background:
    radial-gradient(ellipse 700px 400px at 90% 0%, rgba(255,107,74,0.18), transparent 60%),
    radial-gradient(ellipse 600px 400px at 0% 100%, rgba(79,179,168,0.20), transparent 60%);
  }
  .form-side { background:var(--cream); padding:48px; display:flex; align-items:center; justify-content:center; }
  .form-wrap { max-width:420px; width:100%; position:relative; z-index:2; }
  .font-display { font-family:'Cormorant Garamond',serif; font-weight:500; letter-spacing:-0.01em; }
  .btn-primary { background:var(--coral); color:white; padding:14px 24px; border-radius:14px; font-weight:700; font-size:15px; border:0; cursor:pointer; width:100%; display:flex; align-items:center; justify-content:center; gap:10px; box-shadow:0 12px 24px -12px rgba(255,107,74,0.7); transition:all 0.2s; }
  .btn-primary:hover { background:#E55536; transform:translateY(-2px); }
  .input { width:100%; padding:14px 16px; border-radius:14px; border:1.5px solid #E5DFD3; background:var(--cream); font-family:inherit; font-size:15px; transition:all 0.2s; }
  .input:focus { outline:none; border-color:var(--coral); background:white; box-shadow:0 0 0 5px rgba(255,107,74,0.1); }
  .field { display:flex; flex-direction:column; gap:8px; margin-bottom:18px; }
  .field label { font-size:13px; font-weight:700; color:var(--navy); }
  .card { background:white; border-radius:28px; box-shadow:0 20px 70px -30px rgba(15,39,71,0.25); border:1px solid #EFE7D7; }
  .login-logo { width:58px; height:58px; border-radius:18px; background:var(--coral); display:flex; align-items:center; justify-content:center; box-shadow:0 10px 24px -10px rgba(255,107,74,0.6); }
  .chip { display:inline-flex; align-items:center; gap:7px; padding:7px 14px; border-radius:999px; font-size:11.5px; font-weight:800; background:rgba(251,247,240,0.1); border:1px solid rgba(251,247,240,0.15); color:var(--cream); letter-spacing:0.03em; }
  .feature-row { display:flex; align-items:flex-start; gap:14px; margin-bottom:18px; }
  .feature-icon { width:40px; height:40px; border-radius:12px; background:rgba(255,107,74,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
</style>
</head>
<body>
<div class="bg-wrap">
  <!-- LEFT: PREMIUM HERO SIDE -->
  <div class="hero-side">
    <div class="relative z-10 mb-auto">
      <div class="flex items-center gap-3 mb-14">
        <div class="w-12 h-12 rounded-2xl bg-coral flex items-center justify-center">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="#FBF7F0"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/></svg>
        </div>
        <div>
          <div class="font-display text-3xl font-semibold leading-none">BeethovenPets</div>
          <div class="text-[11px] tracking-[0.24em] uppercase font-bold text-cream/45 mt-1.5">Admin Control Center</div>
        </div>
      </div>

      <div class="mb-5"><span class="chip"><span class="w-2 h-2 rounded-full bg-coral pin-pulse" style="animation:pulse 2.4s ease-in-out infinite"></span>Trusted operations hub · v1.0</span></div>

      <h1 class="font-display text-5xl lg:text-6xl font-semibold leading-[1.03] mb-6 tracking-[-0.02em]">
        Manage every pet<br>
        <span class="italic text-coral">journey</span> from<br>
        one elegant place.
      </h1>

      <div class="mt-12 max-w-md space-y-5 text-sm text-cream/75 leading-relaxed">
        <div class="feature-row">
          <div class="feature-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FFB19E" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/></svg>
          </div>
          <div><strong class="text-cream font-semibold">Booking pipeline.</strong> Inquiries, confirmed, in-transit, delivered — full status workflow with audit trail.</div>
        </div>
        <div class="feature-row">
          <div class="feature-icon" style="background:rgba(79,179,168,0.18)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#A7E2DC" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
          </div>
          <div><strong class="text-cream font-semibold">Real-time tracking.</strong> Post photo/video check-ins. Customers see updates instantly on the public tracker.</div>
        </div>
        <div class="feature-row">
          <div class="feature-icon" style="background:rgba(201,169,98,0.22)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#E6D3A6" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
          <div><strong class="text-cream font-semibold">Pricing engine.</strong> Configure routes, size multipliers, add-on services — everything customer-facing quotes pull from.</div>
        </div>
      </div>
    </div>

    <div class="relative z-10 pt-10 border-t border-cream/10">
      <div class="flex items-center gap-5 flex-wrap text-xs text-cream/45">
        <span>🛡️ Bcrypt auth</span>
        <span>·</span>
        <span>🛩️ IATA ready</span>
        <span>·</span>
        <span>💎 Zero Composer required</span>
        <span>·</span>
        <span>📦 cPanel & aaPanel ready</span>
      </div>
    </div>
  </div>

  <!-- RIGHT: LOGIN FORM -->
  <div class="form-side">
    <div class="form-wrap">
      <div class="card p-8 sm:p-10">
        <div class="mb-8">
          <div class="login-logo mb-5">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="white"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/></svg>
          </div>
          <h2 class="font-display text-navy text-4xl font-semibold leading-[1.05] mb-3">Welcome back.</h2>
          <p class="text-muted leading-relaxed">Sign in to manage pet relocations, update journeys, and delight your customers.</p>
        </div>

        <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 font-semibold text-sm leading-relaxed flex items-start gap-3">
          <svg class="mt-0.5 flex-shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div><?=e($error)?></div>
        </div>
        <?php endif; ?>

        <form method="POST">
          <div class="field">
            <label for="email">Email Address</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="email" placeholder="Enter your admin email" class="input">
          </div>
          <div class="field">
            <div class="flex justify-between">
              <label for="password">Password</label>
              <a href="#" class="text-xs font-bold text-coral hover:underline" onclick="alert('Contact site owner to reset password.'); return false;">Forgot password?</a>
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••" class="input">
          </div>
          <button type="submit" class="btn-primary mt-2">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            Sign in to Admin
          </button>
        </form>


      </div>
      <div class="mt-6 text-center text-xs text-navy/40">
        © 2026 BeethovenPets Concierge · All systems operational
      </div>
    </div>
  </div>
</div>
</body>
</html>
