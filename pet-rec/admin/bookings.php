<?php
require_once __DIR__ . '/includes/header.php';
$page_title = 'Bookings & Estimates';
requireAdmin();

$db = pawDB();

// Handle bulk status update / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];

    if ($action === 'delete' && !empty($ids)) {
        foreach ($ids as $id) {
            $db->execute("DELETE FROM bookings WHERE id = ?", [$id]);
        }
        setFlash('booking', count($ids) . ' booking(s) deleted successfully.', 'success');
        redirect('bookings.php');
    }
    if ($action === 'set_status' && !empty($ids) && !empty($_POST['new_status'])) {
        $st = $_POST['new_status'];
        foreach ($ids as $id) {
            $db->execute("UPDATE bookings SET status = ? WHERE id = ?", [$st, $id]);
        }
        setFlash('booking', 'Status updated for ' . count($ids) . ' booking(s) to: ' . bookingStatusLabel($st), 'success');
        redirect('bookings.php');
    }
}

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$travelFilter = $_GET['travel_type'] ?? '';

$where = [];
$params = [];
if ($statusFilter) { $where[] = "b.status = ?"; $params[] = $statusFilter; }
if ($travelFilter) { $where[] = "b.travel_type = ?"; $params[] = $travelFilter; }
if ($search) {
    $where[] = "(b.booking_code LIKE ? OR c.full_name LIKE ? OR c.email LIKE ? OR b.origin_city LIKE ? OR b.destination_city LIKE ? OR p.name LIKE ?)";
    $s = "%$search%";
    for ($i=0;$i<6;$i++) $params[] = $s;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$bookings = $db->fetchAll(
    "SELECT b.*, c.full_name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, p.name AS pet_name
     FROM bookings b
     LEFT JOIN customers c ON c.id = b.customer_id
     LEFT JOIN pets p ON p.id = b.pet_id
     $whereSQL
     ORDER BY b.created_at DESC LIMIT 200",
    $params
);
?>

<div class="page-title-row">
  <div class="page-title">
    <h1>Bookings & Estimates</h1>
    <p>Manage the full pet relocation pipeline from inquiry to doorstep delivery.</p>
  </div>
  <div class="page-actions">
    <a href="https://wa.me/<?=waDigits()?>?text=Hello%20BeethovenPets%2C%20I%20would%20like%20to%20create%20a%20new%20booking%20for%20pet%20relocation%20services." target="_blank" class="btn btn-outline btn-sm">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
      New Booking
    </a>
  </div>
</div>

<!-- FILTERS + BULK ACTIONS -->
<div class="card card-pad mb-6">
  <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-5">
    <div class="field" style="margin:0">
      <label>Search</label>
      <input class="input" name="q" value="<?=e($search)?>" placeholder="Code, customer, route, pet...">
    </div>
    <div class="field" style="margin:0">
      <label>Status</label>
      <select class="select" name="status">
        <option value="">All statuses</option>
        <?php foreach(['inquiry','confirmed','preparing','in_transit','arrived','delivered','completed','cancelled'] as $s): ?>
        <option value="<?=$s?>" <?=$statusFilter===$s?'selected':''?>><?=bookingStatusLabel($s)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="margin:0">
      <label>Travel Type</label>
      <select class="select" name="travel_type">
        <option value="">All types</option>
        <?php foreach(['domestic_air','domestic_land','international','combined'] as $t): ?>
        <option value="<?=$t?>" <?=$travelFilter===$t?'selected':''?>><?=travelTypeLabel($t)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex items-end gap-2">
      <button type="submit" class="btn btn-navy btn-sm" style="height:45px">Apply Filters</button>
      <a href="bookings.php" class="btn btn-outline btn-sm" style="height:45px">Reset</a>
    </div>
  </form>

  <form method="POST" id="bulkForm" onsubmit="return checkBulkAction(event)">
    <input type="hidden" name="action" id="bulkAction" value="">
    <input type="hidden" name="new_status" id="bulkStatus" value="">

    <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-cream-3">
      <div class="text-xs text-muted font-semibold">With <span id="selectedCount">0</span> selected:</div>
      <select class="select" id="statusSelector" style="max-width:220px;padding:9px 40px 9px 14px;min-height:auto">
        <option value="">Change status to...</option>
        <?php foreach(['inquiry','confirmed','preparing','in_transit','arrived','delivered','completed','cancelled'] as $s): ?>
        <option value="<?=$s?>"><?=bookingStatusLabel($s)?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="btn btn-outline btn-sm" onclick="submitBulk('set_status')">Apply Status</button>
      <button type="button" class="btn btn-danger btn-sm confirm-delete" onclick="submitBulk('delete')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        Delete
      </button>
      <div class="ml-auto text-sm font-bold text-navy"><?=count($bookings)?> total <?=count($bookings)==1?'booking':'bookings'?></div>
    </div>
</div>

<!-- TABLE -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:44px"><input type="checkbox" id="checkAll" style="accent-color:#FF6B4A;width:17px;height:17px;cursor:pointer"></th>
          <th>Code</th>
          <th>Customer</th>
          <th>Pet</th>
          <th>Route</th>
          <th>Travel</th>
          <th>Departure</th>
          <th>Status</th>
          <th>Value</th>
          <th>Payment</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($bookings) === 0): ?>
        <tr><td colspan="11">
          <div class="empty" style="border-radius:0;border:0;background:transparent;margin:30px">
            <div class="empty-icon">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/></svg>
            </div>
            <p class="font-bold text-navy mb-1">No bookings match these filters.</p>
            <p class="text-muted text-sm">Remove filters or refresh the page.</p>
          </div>
        </td></tr>
        <?php else: foreach($bookings as $b): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?=(int)$b['id']?>" class="rowCheck" style="accent-color:#FF6B4A;width:17px;height:17px;cursor:pointer"></td>
          <td style="font-family:'Fraunces',serif;font-weight:700;font-size:15px;color:var(--navy)"><?=e($b['booking_code'])?></td>
          <td>
            <div class="user-cell" style="min-width:0">
              <div class="avatar"><?=strtoupper(substr($b['customer_name'] ?? 'U', 0, 1))?></div>
              <div class="min-w-0">
                <div class="name truncate"><?=e($b['customer_name'] ?? '—')?></div>
                <div class="sub truncate"><?=e($b['customer_email'] ?? '')?></div>
              </div>
            </div>
          </td>
          <td>
            <span class="font-bold text-navy text-sm"><?=e($b['pet_name'] ?? 'Unnamed')?></span>
            <span class="text-xs text-muted block mt-0.5"><?=e($b['pet_weight_kg'])?>kg · <?=petSizeLabel($b['pet_type_booking'])?></span>
          </td>
          <td class="font-semibold text-navy">
            <div><?=e($b['origin_city'])?></div>
            <div class="text-muted text-xs mt-1">→ <?=e($b['destination_city'])?></div>
          </td>
          <td>
            <span class="chip" style="background:#E6EEFA;color:var(--navy);border-color:#C8D8F0"><?=travelTypeLabel($b['travel_type'])?></span>
          </td>
          <td class="font-semibold text-sm text-navy"><?=formatDate($b['travel_date'], 'M j, Y')?></td>
          <td>
            <span class="chip" style="background:<?=bookingStatusColor($b['status'])?>12;color:<?=bookingStatusColor($b['status'])?>;box-shadow:inset 0 0 0 1px <?=bookingStatusColor($b['status'])?>35">
              <span class="chip-dot" style="background:<?=bookingStatusColor($b['status'])?>"></span>
              <?=bookingStatusLabel($b['status'])?>
            </span>
          </td>
          <td class="font-bold text-navy"><?=formatRupiah($b['price_estimated_idr'] ?: 0)?></td>
          <td>
            <?php
              $pc = $b['payment_status'];
              $pmap = ['unpaid'=>'#F59E0B','deposit'=>'#8B5CF6','paid'=>'#10B981','refunded'=>'#6B7280'];
            ?>
            <span class="chip" style="background:<?=$pmap[$pc]?>15;color:<?=$pmap[$pc]?>;box-shadow:inset 0 0 0 1px <?=$pmap[$pc]?>40">
              <span class="chip-dot" style="background:<?=$pmap[$pc]?>"></span>
              <?=paymentStatusLabel($pc)?>
            </span>
          </td>
          <td style="white-space:nowrap;text-align:right">
            <a href="booking-view.php?id=<?=(int)$b['id']?>" class="btn btn-primary btn-icon btn-sm" title="View">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </a>
            <a href="../track.php?code=<?=urlencode($b['booking_code'])?>" target="_blank" class="btn btn-outline btn-icon btn-sm" title="Public tracker">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            </a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</form>

<script>
function updateCount(){
  document.getElementById('selectedCount').textContent = document.querySelectorAll('.rowCheck:checked').length;
}
document.getElementById('checkAll')?.addEventListener('change', function(e){
  document.querySelectorAll('.rowCheck').forEach(c => c.checked = e.target.checked);
  updateCount();
});
document.querySelectorAll('.rowCheck').forEach(c => c.addEventListener('change', updateCount));

function submitBulk(action){
  const n = document.querySelectorAll('.rowCheck:checked').length;
  if (n === 0) { alert('Please select at least 1 booking.'); return false; }
  if (action === 'delete') {
    if (!confirm('Permanently delete ' + n + ' selected booking(s)?')) return false;
  }
  if (action === 'set_status') {
    const ns = document.getElementById('statusSelector').value;
    if (!ns) { alert('Please choose a new status.'); return false; }
    document.getElementById('bulkStatus').value = ns;
  }
  document.getElementById('bulkAction').value = action;
  document.getElementById('bulkForm').submit();
}
function checkBulkAction(e){ return true; }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
