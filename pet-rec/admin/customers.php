<?php
require_once __DIR__ . '/includes/header.php';
$page_title = 'Customers & Pets';
requireAdmin();

$db = pawDB();

// Delete customer
if (!empty($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $cid = (int)$_GET['delete'];
    $db->execute("DELETE FROM customers WHERE id = ?", [$cid]);
    setFlash('customer','Customer and all associated pets & bookings deleted.','success');
    redirect('customers.php');
}

// Add / update customer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? 'Indonesia');
    $notes = trim($_POST['notes'] ?? '');

    if ($action === 'create' && $fullName && $email && $phone) {
        $existing = $db->fetch("SELECT id FROM customers WHERE email = ?", [$email]);
        if ($existing) {
            setFlash('customer','Customer with this email already exists!','error');
        } else {
            $id = $db->execute("INSERT INTO customers (full_name, email, phone, address, city, country, notes) VALUES (?,?,?,?,?,?,?)", [$fullName,$email,$phone,$address,$city,$country,$notes], true);
            setFlash('customer','Customer created successfully. ID #' . $id,'success');
        }
        redirect('customers.php');
    }
    if ($action === 'update' && !empty($_POST['id']) && ctype_digit($_POST['id'])) {
        $id = (int)$_POST['id'];
        $db->execute("UPDATE customers SET full_name=?, email=?, phone=?, address=?, city=?, country=?, notes=? WHERE id=?", [$fullName,$email,$phone,$address,$city,$country,$notes,$id]);
        setFlash('customer','Customer #' . $id . ' updated.','success');
        redirect('customers.php');
    }
}

$search = trim($_GET['q'] ?? '');
$where = ''; $params = [];
if ($search) {
    $where = "WHERE full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR city LIKE ?";
    $s = "%$search%";
    $params = [$s,$s,$s,$s];
}
$customers = $db->fetchAll("SELECT c.*,
  (SELECT COUNT(*) FROM pets p WHERE p.customer_id = c.id) AS pet_count,
  (SELECT COUNT(*) FROM bookings b WHERE b.customer_id = c.id) AS booking_count,
  (SELECT COALESCE(SUM(paid_amount_idr),0) FROM bookings b WHERE b.customer_id = c.id) AS total_spent
  FROM customers c $where ORDER BY c.created_at DESC LIMIT 500", $params);
?>

<div class="page-title-row">
  <div class="page-title">
    <h1>Customers & Pets</h1>
    <p>Complete CRM database with pet records, booking history, and lifetime value.</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('customerModal').classList.toggle('hidden')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add New Customer
    </button>
  </div>
</div>

<div class="card card-pad mb-6">
  <form method="GET" class="flex flex-wrap gap-3 items-end">
    <div class="field" style="margin:0;min-width:300px;flex:1;max-width:500px">
      <label>Search customers</label>
      <input class="input" name="q" value="<?=e($search)?>" placeholder="Name, email, phone, city...">
    </div>
    <button class="btn btn-navy btn-sm" style="height:47px">Search</button>
    <?php if ($search): ?><a href="customers.php" class="btn btn-outline btn-sm" style="height:47px">Clear</a><?php endif; ?>
    <div class="ml-auto text-sm font-bold text-navy"><?=count($customers)?> total customers</div>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Customer</th>
          <th>Contact</th>
          <th>City</th>
          <th>Pets</th>
          <th>Bookings</th>
          <th>Lifetime Value</th>
          <th>Joined</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($customers) === 0): ?>
        <tr><td colspan="8">
          <div class="empty" style="border:0;border-radius:0;margin:30px;background:transparent">
            <div class="empty-icon">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
            <p class="font-bold text-navy mb-1">No customers found.</p>
            <p class="text-muted text-sm">Try removing filters or add a new customer.</p>
          </div>
        </td></tr>
        <?php else: foreach($customers as $c): ?>
        <tr>
          <td>
            <div class="user-cell" style="min-width:0">
              <div class="avatar avatar-lg"><?=strtoupper(substr($c['full_name'],0,1))?></div>
              <div class="min-w-0">
                <div class="name truncate"><?=e($c['full_name'])?></div>
                <div class="sub truncate">ID #<?=(int)$c['id']?></div>
              </div>
            </div>
          </td>
          <td>
            <a href="mailto:<?=e($c['email'])?>" class="font-semibold text-navy text-sm block truncate"><?=e($c['email'])?></a>
            <a href="tel:<?=e($c['phone'])?>" class="text-xs text-muted block truncate mt-0.5"><?=e($c['phone'])?></a>
          </td>
          <td class="font-semibold text-navy text-sm"><?=e($c['city'] ?? '—')?><br><span class="text-xs text-muted"><?=e($c['country'] ?? '')?></span></td>
          <td>
            <span class="chip" style="background:#E7F5F3;color:var(--teal);border-color:#C5E7E2"><?=(int)$c['pet_count']?> pet<?=(int)$c['pet_count']===1?'':'s'?></span>
          </td>
          <td>
            <span class="chip" style="background:#E6EEFA;color:var(--navy);border-color:#C8D8F0"><?=(int)$c['booking_count']?> booking<?=(int)$c['booking_count']===1?'':'s'?></span>
          </td>
          <td class="font-bold text-navy"><?=formatRupiah($c['total_spent'] ?: 0)?></td>
          <td class="text-sm font-semibold text-muted"><?=formatDate($c['created_at'],'M j, Y')?></td>
          <td style="white-space:nowrap;text-align:right">
            <a href="customer-view.php?id=<?=(int)$c['id']?>" class="btn btn-primary btn-icon btn-sm">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </a>
            <a href="?delete=<?=(int)$c['id']?>" class="btn btn-outline btn-icon btn-sm confirm-delete" title="Delete customer & all data">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
            </a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: New Customer -->
<div id="customerModal" class="hidden fixed inset-0 z-[100] items-center justify-center p-4" style="background:rgba(15,39,71,0.55);backdrop-filter:blur(4px);display:none">
  <div class="card card-pad max-w-xl w-full relative">
    <button class="absolute top-4 right-4 btn btn-outline btn-icon btn-sm" onclick="document.getElementById('customerModal').classList.add('hidden');document.getElementById('customerModal').style.display='none'">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <h2 class="font-display text-3xl font-semibold text-navy mb-1">Add Customer</h2>
    <p class="text-muted text-sm mb-6">Create a new customer record with contact details.</p>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="grid-2">
        <div class="field" style="margin:0"><label>Full Name<span class="req" style="color:var(--coral)">*</span></label><input class="input" name="full_name" required></div>
        <div class="field" style="margin:0"><label>Email<span class="req" style="color:var(--coral)">*</span></label><input class="input" type="email" name="email" required></div>
        <div class="field" style="margin:0"><label>Phone / WhatsApp<span class="req" style="color:var(--coral)">*</span></label><input class="input" name="phone" required></div>
        <div class="field" style="margin:0"><label>Country</label><input class="input" name="country" value="Indonesia"></div>
        <div class="field" style="margin:0;grid-column:span 1"><label>City</label><input class="input" name="city"></div>
        <div class="field" style="margin:0;grid-column:span 1"><label>Address</label><input class="input" name="address"></div>
      </div>
      <div class="field mt-4"><label>Private Notes</label><textarea class="textarea" name="notes"></textarea></div>
      <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-cream-3">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('customerModal').classList.add('hidden');document.getElementById('customerModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Customer</button>
      </div>
    </form>
  </div>
</div>
<script>
  // Modal toggle with flex for centering
  document.getElementById('customerModal').addEventListener('click', function(e){ if (e.target === this) { this.classList.add('hidden'); this.style.display='none'; }});
  const origToggle = document.querySelector('button[onclick*="customerModal"]');
  if (origToggle) {
    origToggle.onclick = function() {
      const m = document.getElementById('customerModal');
      m.classList.toggle('hidden');
      m.style.display = m.classList.contains('hidden') ? 'none' : 'flex';
    };
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
