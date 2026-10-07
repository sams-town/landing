<?php
/**
 * Admin Panel — Shared Header / Sidebar Layout
 * Automatically checks auth + renders sidebar + topbar + flash messages
 */
if (strpos($_SERVER['PHP_SELF'], '/admin/login.php') === false &&
    strpos($_SERVER['PHP_SELF'], '/admin/logout.php') === false) {
    require_once __DIR__ . '/../../includes/init.php';
    requireAdmin();
    $admin = currentAdmin();
}
if (!isset($page_title)) $page_title = 'Admin';
$page_base = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=e($page_title)?> · BeethovenPets Admin</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root { --cream:#FBF7F0;--cream-2:#F7F1E5;--cream-3:#EFE7D7;--navy:#0F2747;--navy-2:#172F52;--navy-3:#1E3A5F;
          --coral:#FF6B4A;--coral-2:#E55536;--teal:#4FB3A8;--teal-soft:#E7F5F3;--ink:#14142B;--muted:#6B7280;--gold:#C9A962;
          --danger:#EF4444;--success:#10B981;--warning:#F59E0B;--info:#3B82F6; }
  * { box-sizing:border-box; }
  body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--cream); color:var(--ink); margin:0; }
  .font-display { font-family:'Fraunces',serif; }
  a { color:inherit; text-decoration:none; }

  /* Layout */
  .layout { display:grid; grid-template-columns:260px 1fr; min-height:100vh; }
  @media (max-width: 1024px) { .layout { grid-template-columns:1fr; } }

  /* Sidebar */
  .sidebar { background:var(--navy); color:var(--cream); padding:28px 20px; position:sticky; top:0; height:100vh; overflow-y:auto; border-right:1px solid rgba(255,255,255,0.05); }
  @media (max-width: 1024px) { .sidebar { display:none; position:fixed; z-index:999; height:100vh; width:280px; box-shadow:20px 0 50px rgba(15,39,71,0.3);} .sidebar.open { display:block; } }
  .side-brand { display:flex; align-items:center; gap:10px; padding:6px 4px 26px; border-bottom:1px solid rgba(255,255,255,0.08); margin-bottom:22px; }
  .side-brand .logo { width:40px; height:40px; border-radius:12px; background:var(--coral); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .side-brand .name { font-family:'Fraunces',serif; font-weight:600; font-size:20px; line-height:1; }
  .side-brand .sub { font-size:10px; font-weight:700; letter-spacing:0.15em; color:rgba(251,247,240,0.45); text-transform:uppercase; margin-top:3px; }

  .nav-section { margin:18px 0 6px; padding:0 4px; font-size:10.5px; font-weight:800; letter-spacing:0.18em; text-transform:uppercase; color:rgba(251,247,240,0.4); }
  .nav-link { display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:14px; color:rgba(251,247,240,0.75); font-weight:500; font-size:14px; margin-bottom:3px; transition:all 0.15s; }
  .nav-link:hover { background:rgba(251,247,240,0.08); color:var(--cream); transform:translateX(3px); }
  .nav-link.active { background:linear-gradient(90deg, var(--coral) 0%, #FF8769 100%); color:white; box-shadow:0 8px 20px -8px rgba(255,107,74,0.6); }
  .nav-link svg { width:18px; height:18px; flex-shrink:0; }
  .nav-link .badge { margin-left:auto; padding:3px 8px; font-size:10.5px; font-weight:800; background:rgba(255,107,74,0.2); color:#FFB19E; border-radius:999px; }
  .nav-link.active .badge { background:rgba(255,255,255,0.25); color:white; }

  .side-footer { margin-top:30px; padding:16px; background:rgba(251,247,240,0.05); border-radius:16px; border:1px solid rgba(255,255,255,0.07); }
  .side-footer .avatar { width:36px; height:36px; border-radius:11px; background:linear-gradient(135deg,var(--gold),var(--coral)); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; }

  /* Main area */
  .main { display:flex; flex-direction:column; min-width:0; }
  .topbar { background:rgba(251,247,240,0.85); backdrop-filter:blur(14px); border-bottom:1px solid var(--cream-3); padding:18px 32px; display:flex; align-items:center; justify-content:space-between; gap:20px; position:sticky; top:0; z-index:50; }
  @media (max-width: 1024px) { .topbar { padding:14px 18px; } }
  .mobile-menu-btn { display:none; width:42px; height:42px; border-radius:12px; border:1px solid var(--cream-3); align-items:center; justify-content:center; color:var(--navy); background:white; cursor:pointer; }
  @media (max-width:1024px) { .mobile-menu-btn { display:flex; } }

  .topbar-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .topbar-search { display:flex; align-items:center; gap:8px; background:white; border:1px solid var(--cream-3); border-radius:14px; padding:10px 14px; min-width:260px; }
  .topbar-search input { border:0; outline:0; background:transparent; font-family:inherit; font-size:14px; flex:1; }
  .pill-btn { width:42px; height:42px; border-radius:12px; border:1px solid var(--cream-3); background:white; display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--navy); position:relative; transition:all 0.15s; }
  .pill-btn:hover { border-color:var(--coral); color:var(--coral); }
  .pill-btn .red-dot { position:absolute; top:10px; right:10px; width:8px; height:8px; border-radius:50%; background:var(--coral); border:2px solid white; }

  .content { padding:28px 32px 60px; flex:1; }
  @media (max-width: 1024px) { .content { padding:20px 18px 60px; } }

  .page-title-row { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; margin-bottom:26px; flex-wrap:wrap; }
  .page-title h1 { margin:0; font-family:'Fraunces',serif; font-size:32px; font-weight:600; color:var(--navy); letter-spacing:-0.01em; }
  .page-title p { margin:6px 0 0; color:var(--muted); font-size:14.5px; }
  .page-actions { display:flex; gap:10px; flex-wrap:wrap; }

  /* Cards & Buttons */
  .card { background:white; border-radius:22px; box-shadow:0 8px 30px -15px rgba(15,39,71,0.15); border:1px solid var(--cream-3); }
  .card-pad { padding:24px; }

  .btn { display:inline-flex; align-items:center; gap:8px; font-weight:600; padding:10px 18px; border-radius:12px; border:0; font-family:inherit; font-size:14px; cursor:pointer; transition:all 0.15s; text-decoration:none; white-space:nowrap; }
  .btn-primary { background:var(--coral); color:white; box-shadow:0 8px 18px -10px rgba(255,107,74,0.7); }
  .btn-primary:hover { background:var(--coral-2); transform:translateY(-1px); box-shadow:0 12px 24px -12px rgba(255,107,74,0.85); }
  .btn-navy { background:var(--navy); color:var(--cream); }
  .btn-navy:hover { background:var(--navy-3); }
  .btn-teal { background:var(--teal); color:white; }
  .btn-success { background:var(--success); color:white; }
  .btn-danger { background:var(--danger); color:white; }
  .btn-outline { background:transparent; border:1.5px solid var(--cream-3); color:var(--navy); }
  .btn-outline:hover { border-color:var(--navy); }

  .btn-sm { padding:7px 13px; font-size:12.5px; border-radius:10px; }
  .btn-icon { width:34px; height:34px; padding:0; border-radius:10px; justify-content:center; }

  /* Stat cards */
  .stat { display:flex; align-items:flex-start; gap:14px; }
  .stat-icon { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .stat-icon svg { width:22px; height:22px; }
  .stat-num { font-family:'Fraunces',serif; font-size:28px; font-weight:700; color:var(--navy); line-height:1; margin:0; }
  .stat-label { font-size:13px; color:var(--muted); margin-top:6px; font-weight:500; }
  .stat-delta { font-size:12px; margin-top:8px; font-weight:700; display:flex; align-items:center; gap:4px; }

  /* Forms */
  .field { display:flex; flex-direction:column; gap:6px; margin-bottom:16px; }
  .field label { font-size:13px; font-weight:700; color:var(--navy); }
  .field label .req { color:var(--coral); margin-left:2px; }
  .input, .select, .textarea {
    padding:12px 15px; border-radius:13px; border:1.5px solid #E5DFD3; background:var(--cream);
    font-family:inherit; font-size:14px; color:var(--ink); transition:all 0.15s; width:100%;
  }
  .textarea { resize:vertical; min-height:90px; }
  .input:focus, .select:focus, .textarea:focus {
    outline:none; border-color:var(--coral); background:white; box-shadow:0 0 0 4px rgba(255,107,74,0.10);
  }
  select.select { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%230F2747' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 15px center; padding-right:44px; }
  .grid-2 { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; }
  .grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
  .grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
  @media (max-width: 900px) { .grid-2, .grid-3, .grid-4 { grid-template-columns:1fr; } }

  /* Tables */
  table { width:100%; border-collapse:collapse; }
  .table-wrap { overflow-x:auto; border-radius:18px; }
  th, td { padding:14px 18px; text-align:left; font-size:14px; vertical-align:middle; }
  thead th { background:var(--cream-2); font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; color:var(--navy); border-bottom:1px solid var(--cream-3); }
  tbody tr { border-bottom:1px solid var(--cream-3); transition:background 0.1s; }
  tbody tr:hover { background:var(--cream); }
  tbody tr:last-child { border-bottom:0; }
  .avatar { width:36px; height:36px; border-radius:50%; background:var(--cream-3); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; color:var(--navy); flex-shrink:0; }
  .avatar-lg { width:44px; height:44px; border-radius:14px; font-size:15px; }
  .user-cell { display:flex; align-items:center; gap:12px; min-width:220px; }
  .user-cell .name { font-weight:700; color:var(--navy); font-size:14.5px; }
  .user-cell .sub { font-size:12px; color:var(--muted); }

  .chip { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:999px; font-size:11.5px; font-weight:800; letter-spacing:0.02em; text-transform:capitalize; border:1.5px solid transparent; }
  .chip-dot { width:7px; height:7px; border-radius:50%; }

  /* Flash messages */
  .flash { padding:14px 18px; border-radius:14px; margin-bottom:18px; font-weight:500; font-size:14px; line-height:1.45; display:flex; align-items:flex-start; gap:12px; }
  .flash-success { background:#D1FAE5; color:#065F46; border:1px solid #6EE7B7; }
  .flash-error { background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5; }
  .flash-warning { background:#FEF3C7; color:#92400E; border:1px solid #FCD34D; }
  .flash-info { background:#DBEAFE; color:#1E40AF; border:1px solid #93C5FD; }

  /* Empty state */
  .empty { padding:44px 20px; text-align:center; border-radius:20px; background:var(--cream); border:2px dashed #E5DFD3; }
  .empty-icon { width:66px; height:66px; border-radius:20px; background:var(--cream-3); display:flex; align-items:center; justify-content:center; margin:0 auto 16px; color:var(--muted); }

  /* Journey status tracker */
  .tracker-step { display:flex; gap:16px; padding:16px; border-radius:16px; border:1px solid var(--cream-3); background:var(--cream); margin-bottom:10px; transition:all 0.2s; }
  .tracker-step:hover { background:white; border-color:var(--coral); }
  .tracker-step .dot { width:30px; height:30px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; background:white; box-shadow:0 0 0 4px var(--cream-3); }
  .tracker-step.current .dot { background:var(--coral); box-shadow:0 0 0 4px rgba(255,107,74,0.2); color:white; }

  /* Misc */
  .divider { height:1px; background:var(--cream-3); margin:22px 0; }
  .link { color:var(--coral); font-weight:600; cursor:pointer; }
  .link:hover { text-decoration:underline; }
  .muted { color:var(--muted); }
  .hidden { display:none !important; }
  details.faq summary { list-style:none; cursor:pointer; font-weight:700; padding:14px 18px; background:var(--cream); border-radius:14px; }
  details.faq summary::-webkit-details-marker { display:none; }

  /* Scrollbar */
  ::-webkit-scrollbar { width:10px; height:10px; }
  ::-webkit-scrollbar-track { background:var(--cream-2); }
  ::-webkit-scrollbar-thumb { background:#D6CAB2; border-radius:999px; }
  ::-webkit-scrollbar-thumb:hover { background:var(--coral); }
</style>
</head>
<body>

<?php if (strpos($_SERVER['PHP_SELF'], '/admin/login.php') === false &&
          strpos($_SERVER['PHP_SELF'], '/admin/logout.php') === false): ?>

<div class="layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="side-brand">
      <div class="logo">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="#FBF7F0"><path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.879 3.308-5 4.828C1.5 9.218 3.077 10.321 5 10c.717-.118 1.326-.346 1.816-.64C7.42 10.337 8.627 11 10 11c1.657 0 3-1.343 3-3 0-.935-.435-1.768-1.115-2.328C11.584 5.405 10.806 5.172 10 5.172z"/><path d="M14 5.172C14 3.782 15.577 2.679 17.5 3c2.823.47 4.879 3.308 5 4.828C22.5 9.218 20.923 10.321 19 10c-.717-.118-1.326-.346-1.816-.64C16.58 10.337 15.373 11 14 11c-1.657 0-3-1.343-3-3 0-.935.435-1.768 1.115-2.328C12.416 5.405 13.194 5.172 14 5.172z"/><path d="M12 19.5c0 3 .5 4.2 3 4.2 1.5 0 3-.5 3-2 0-4.5-3-5-6-5.5v3.3z"/></svg>
      </div>
      <div>
        <div class="name">BeethovenPets</div>
        <div class="sub">Admin Control</div>
      </div>
    </div>

    <?php
      try {
        $newBookingsCount = pawDB()->fetch("SELECT COUNT(*) AS c FROM bookings WHERE status = 'inquiry'")['c'] ?? 0;
      } catch (Throwable $e) { $newBookingsCount = 0; }
    ?>

    <div class="nav-section">Overview</div>
    <a href="index.php" class="nav-link <?=$page_base=='index.php'?'active':''?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>

    <div class="nav-section">Operations</div>
    <a href="bookings.php" class="nav-link <?=$page_base=='bookings.php'||$page_base=='booking-view.php'?'active':''?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      Bookings & Estimates
      <?php if ($newBookingsCount > 0): ?><span class="badge"><?=$newBookingsCount?> NEW</span><?php endif; ?>
    </a>
    <a href="customers.php" class="nav-link <?=$page_base=='customers.php'||$page_base=='customer-view.php'?'active':''?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Customers & Pets
    </a>
    <a href="tracking.php" class="nav-link <?=$page_base=='tracking.php'?'active':''?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
      Journey Tracking
    </a>

    <div class="nav-section">Pricing</div>
    <a href="routes.php" class="nav-link <?=$page_base=='routes.php'?'active':''?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>
      Routes & Pricing
    </a>
    <a href="addons.php" class="nav-link <?=$page_base=='addons.php'?'active':''?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      Add-On Services
    </a>
    <a href="settings.php" class="nav-link <?=$page_base=='settings.php'?'active':''?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      System Settings
    </a>

    <div class="nav-section">Public</div>
    <a href="../index.php" target="_blank" class="nav-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      View Live Site
    </a>
    <a href="logout.php" class="nav-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Sign Out
    </a>

    <!-- Side Footer: Current admin -->
    <div class="side-footer">
      <div class="flex items-center gap-3">
        <div class="avatar"><?=strtoupper(substr($admin['full_name'] ?? 'A', 0, 1))?></div>
        <div class="min-w-0 flex-1">
          <div class="font-bold text-sm truncate"><?=e($admin['full_name'] ?? 'Admin')?></div>
          <div class="text-[11px] opacity-60 font-semibold"><?=e($admin['role'] ?? 'manager')?></div>
        </div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main">
    <div class="topbar">
      <div class="flex items-center gap-4">
        <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="page-title">
          <h1 style="font-size:20px;"><?=e($page_title)?></h1>
        </div>
      </div>

      <div class="topbar-actions">
        <div class="topbar-search hidden md:flex">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" placeholder="Search booking code, customer, pet...">
        </div>
        <a href="bookings.php?status=inquiry" class="pill-btn" title="New inquiries">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <?php if ($newBookingsCount > 0): ?><span class="red-dot"></span><?php endif; ?>
        </a>
        <a href="../track.php" target="_blank" class="pill-btn" title="Open public tracker">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </a>
      </div>
    </div>

    <div class="content">
<?php endif; ?>

<?php
// Render ALL flash messages
foreach (['admin', 'booking', 'customer', 'pet', 'route', 'addon', 'setting', 'tracking'] as $k) {
    echo renderFlash($k);
}
?>

<script>
// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
  const btn = document.getElementById('mobileMenuBtn');
  const side = document.getElementById('sidebar');
  if (btn && side) {
    btn.addEventListener('click', () => side.classList.toggle('open'));
  }
  // Submit confirmation for delete buttons
  document.querySelectorAll('.confirm-delete').forEach(b => {
    b.addEventListener('click', function(e) {
      if (!confirm('Delete this item? This action cannot be undone.')) { e.preventDefault(); return false; }
    });
  });
});
</script>
