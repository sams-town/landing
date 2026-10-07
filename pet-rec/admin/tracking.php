<?php
require_once __DIR__ . '/includes/header.php';
$page_title = 'Journey Tracking Console';
requireAdmin();

$db = pawDB();

// Delete log
if (!empty($_GET['delete']) && ctype_digit($_GET['delete'])) {
  $db->execute("DELETE FROM tracking_logs WHERE id = ?", [(int)$_GET['delete']]);
  setFlash('tracking','Log entry deleted.','info');
  redirect('tracking.php');
}

// Post a new log entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_id'] ?? '') === 'new_log') {
  $bookingId = (int)($_POST['booking_id'] ?? 0);
  $status = $_POST['status'] ?? 'in_transit';
  $title = trim($_POST['title'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  $loc = trim($_POST['location'] ?? '');
  if ($bookingId && $title) {
    $b = $db->fetch("SELECT id, status AS old_status FROM bookings WHERE id = ?", [$bookingId]);
    if ($b) {
      $db->execute("INSERT INTO tracking_logs (booking_id, status, title, description, location, created_by_admin_id) VALUES (?,?,?,?,?,?)",
        [$bookingId, $status, $title, $desc, $loc, (int)$_SESSION['admin_id']]);
      $progress = ['inquiry','confirmed','preparing','picked_up','origin_airport','in_flight','transit','in_transit','destination_airport','out_for_delivery','arrived','delivered','completed'];
      $cur = array_search($b['old_status'], $progress);
      $new = array_search($status, $progress);
      if ($new !== false && ($cur === false || $new > $cur) && !in_array($b['old_status'], ['delivered','completed','cancelled'])) {
        $db->execute("UPDATE bookings SET status = ? WHERE id = ?", [$status, $bookingId]);
      }
      setFlash('tracking','Tracking update posted successfully. Visible on public tracker instantly.','success');
      redirect('tracking.php' . (!empty($_GET['booking_id']) ? '?booking_id=' . $bookingId : ''));
    }
  }
}

$bookingFilter = (int)($_GET['booking_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

$where = []; $params = [];
if ($bookingFilter) { $where[] = "tl.booking_id = ?"; $params[] = $bookingFilter; }
if ($statusFilter) { $where[] = "tl.status = ?"; $params[] = $statusFilter; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$logs = $db->fetchAll("SELECT tl.*, b.booking_code, b.origin_city, b.destination_city, c.full_name AS customer_name
  FROM tracking_logs tl
  JOIN bookings b ON b.id = tl.booking_id
  LEFT JOIN customers c ON c.id = b.customer_id
  $whereSQL
  ORDER BY tl.created_at DESC LIMIT 200", $params);

$bookingsForSelect = $db->fetchAll("SELECT id, booking_code, origin_city, destination_city FROM bookings ORDER BY created_at DESC LIMIT 50");
?>

<div class="page-title-row">
  <div class="page-title">
    <h1>Journey Tracking Console</h1>
    <p>Post customer-visible updates to every relocation in real-time.</p>
  </div>
  <?php if ($bookingFilter): ?>
    <a href="tracking.php" class="btn btn-outline btn-sm page-actions">← Show all logs</a>
  <?php endif; ?>
</div>

<!-- POST NEW LOG -->
<div class="card card-pad mb-6" style="border:2px solid var(--coral);box-shadow:0 20px 50px -25px rgba(255,107,74,0.35)">
  <div class="flex items-center gap-3 mb-5 flex-wrap">
    <div class="w-11 h-11 rounded-2xl bg-coral/15 flex items-center justify-center">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FF6B4A" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
    </div>
    <div>
      <h2 class="font-display text-2xl font-semibold text-navy m-0">📣 Post Journey Update</h2>
      <p class="text-muted m-0 text-sm mt-1">Customers receive this via the tracker page, email & WhatsApp.</p>
    </div>
  </div>

  <form method="POST">
    <input type="hidden" name="form_id" value="new_log">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
      <div class="field" style="margin:0">
        <label>Booking<span class="req" style="color:var(--coral)">*</span></label>
        <select class="select" name="booking_id" required>
          <option value="" disabled selected>Select booking...</option>
          <?php foreach($bookingsForSelect as $b): ?>
            <option value="<?=(int)$b['id']?>" <?=$bookingFilter===$b['id']?'selected':''?>>
              <?=e($b['booking_code'])?> · <?=e($b['origin_city'])?> → <?=e($b['destination_city'])?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin:0"><label>Milestone Status</label>
        <select class="select" name="status">
          <?php foreach(['inquiry','confirmed','preparing','picked_up','origin_airport','in_flight','transit','in_transit','destination_airport','out_for_delivery','arrived','delivered','completed','cancelled'] as $s): ?>
            <option value="<?=$s?>"><?=bookingStatusLabel($s)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin:0"><label>Location</label>
        <input class="input" name="location" placeholder="e.g. CGK T3 Cargo, Jakarta">
      </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
      <div class="field md:col-span-2" style="margin:0">
        <label>Update Title<span class="req" style="color:var(--coral)">*</span></label>
        <input class="input" name="title" required placeholder="e.g. Pet picked up from home">
      </div>
      <div class="field md:col-span-3" style="margin:0">
        <label>Description</label>
        <input class="input" name="description" placeholder="Customer-visible detail (optional)">
      </div>
    </div>
    <div class="flex justify-end">
      <button class="btn btn-primary px-8">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Publish Update → Customers See Live
      </button>
    </div>
  </form>
</div>

<!-- FILTERS -->
<div class="card card-pad mb-6">
  <form method="GET" class="flex flex-wrap gap-3 items-end">
    <div class="field" style="margin:0;min-width:220px">
      <label>Milestone filter</label>
      <select class="select" name="status">
        <option value="">All statuses</option>
        <?php foreach(['inquiry','confirmed','preparing','picked_up','origin_airport','in_flight','transit','in_transit','destination_airport','out_for_delivery','arrived','delivered','completed','cancelled'] as $s): ?>
          <option value="<?=$s?>" <?=$statusFilter===$s?'selected':''?>><?=bookingStatusLabel($s)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-navy btn-sm" style="height:45px">Apply Filter</button>
    <?php if ($bookingFilter || $statusFilter): ?>
      <a href="tracking.php" class="btn btn-outline btn-sm" style="height:45px">Reset</a>
    <?php endif; ?>
    <div class="ml-auto text-sm font-bold text-navy"><?=count($logs)?> total logs</div>
  </form>
</div>

<!-- LOGS TABLE -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Time</th><th>Booking</th><th>Customer</th><th>Update</th><th>Status</th><th>Location</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($logs) === 0): ?>
        <tr><td colspan="7"><div class="empty" style="margin:30px;border:0;background:transparent">
          <div class="empty-icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <p class="font-bold text-navy mb-1">No journey logs yet.</p>
          <p class="text-muted text-sm">Post the first update in the form above.</p>
        </div></td></tr>
        <?php else: foreach($logs as $l): ?>
        <tr>
          <td>
            <div class="font-bold text-navy text-sm"><?=formatDate($l['created_at'], 'M j, Y')?></div>
            <div class="text-xs text-muted"><?=date('g:i A', strtotime($l['created_at']))?></div>
          </td>
          <td>
            <a href="booking-view.php?id=<?=(int)$l['booking_id']?>" style="font-family:'Fraunces',serif;font-weight:700;color:var(--navy);font-size:15px">
              <?=e($l['booking_code'])?>
            </a>
            <div class="text-xs text-muted mt-0.5"><?=e($l['origin_city'])?> → <?=e($l['destination_city'])?></div>
          </td>
          <td class="font-semibold text-navy text-sm"><?=e($l['customer_name'] ?? '—')?></td>
          <td class="min-w-[300px]">
            <div class="font-bold text-navy text-sm mb-0.5"><?=e($l['title'])?></div>
            <?php if (!empty($l['description'])): ?>
              <div class="text-xs text-muted leading-relaxed"><?=e($l['description'])?></div>
            <?php endif; ?>
          </td>
          <td>
            <span class="chip" style="background:<?=bookingStatusColor($l['status'])?>12;color:<?=bookingStatusColor($l['status'])?>;box-shadow:inset 0 0 0 1px <?=bookingStatusColor($l['status'])?>35;white-space:nowrap;font-size:10.5px">
              <span class="chip-dot" style="background:<?=bookingStatusColor($l['status'])?>"></span>
              <?=bookingStatusLabel($l['status'])?>
            </span>
          </td>
          <td class="font-semibold text-navy text-xs">📍 <?=e($l['location'] ?? '—')?></td>
          <td style="text-align:right;white-space:nowrap">
            <a href="../track.php?code=<?=urlencode($l['booking_code'])?>" target="_blank" class="btn btn-outline btn-icon btn-sm">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </a>
            <a href="?delete=<?=(int)$l['id']?><?=$bookingFilter?'&booking_id='.$bookingFilter:''?>" class="btn btn-outline btn-icon btn-sm confirm-delete" style="border-color:#FCA5A5">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
            </a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
