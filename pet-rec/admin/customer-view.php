<?php
require_once __DIR__ . '/includes/header.php';
$page_title = 'Customer Profile';
requireAdmin();

$db = pawDB();
$id = (int)($_GET['id'] ?? 0);
$c = $db->fetch("SELECT * FROM customers WHERE id = ?", [$id]);
if (!$c) { setFlash('customer','Customer not found.','error'); redirect('customers.php'); }

// Update customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_id'] ?? '') === 'update_customer') {
  $db->execute("UPDATE customers SET full_name=?,email=?,phone=?,address=?,city=?,country=?,notes=? WHERE id=?", [
    $_POST['full_name'],$_POST['email'],$_POST['phone'],$_POST['address'],$_POST['city'],$_POST['country'],$_POST['notes'],$id
  ]);
  setFlash('customer','Customer details updated.','success');
  redirect("customer-view.php?id=$id");
}

// Add pet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_id'] ?? '') === 'add_pet') {
  $db->execute("INSERT INTO pets (customer_id,name,species,breed,age,weight_kg,size_category,microchip,vaccine_status,health_notes) VALUES (?,?,?,?,?,?,?,?,?,?)", [
    $id, trim($_POST['name'] ?? 'Pet'), $_POST['species'] ?? 'dog', trim($_POST['breed'] ?? ''), trim($_POST['age'] ?? ''),
    (float)($_POST['weight_kg'] ?? 10), $_POST['size_category'] ?? 'medium', trim($_POST['microchip'] ?? ''), $_POST['vaccine_status'] ?? 'up_to_date', trim($_POST['health_notes'] ?? '')
  ]);
  setFlash('pet','New pet added to profile.','success');
  redirect("customer-view.php?id=$id");
}

// Delete pet
if (!empty($_GET['delete_pet']) && ctype_digit($_GET['delete_pet'])) {
  $db->execute("DELETE FROM pets WHERE id = ? AND customer_id = ?", [(int)$_GET['delete_pet'], $id]);
  setFlash('pet','Pet record deleted.','info');
  redirect("customer-view.php?id=$id");
}

$pets = $db->fetchAll("SELECT * FROM pets WHERE customer_id = ? ORDER BY id DESC", [$id]);
$bookings = $db->fetchAll("SELECT * FROM bookings WHERE customer_id = ? ORDER BY created_at DESC", [$id]);
$totalBookings = count($bookings);
$totalSpent = array_sum(array_map(fn($b) => (float)($b['paid_amount_idr'] ?? 0), $bookings));
$totalEstimated = array_sum(array_map(fn($b) => (float)($b['price_estimated_idr'] ?? 0), $bookings));
?>

<div class="page-title-row">
  <div class="page-title">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
      <a href="customers.php" class="btn btn-outline btn-icon btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </a>
      <h1 style="font-family:'Fraunces',serif"><?=e($c['full_name'])?></h1>
      <span class="chip" style="background:#E7F5F3;color:var(--teal);border-color:#C5E7E2"><?=count($pets)?> Pet<?=count($pets)===1?'':'s'?></span>
      <span class="chip" style="background:#E6EEFA;color:var(--navy);border-color:#C8D8F0"><?=$totalBookings?> Booking<?=$totalBookings===1?'':'s'?></span>
    </div>
    <p><?=e($c['email'])?> · <?=e($c['phone'])?></p>
  </div>
  <div class="page-actions">
    <a href="https://wa.me/<?=preg_replace('/\D+/','',$c['phone'] ?? '')?>" target="_blank" class="btn btn-outline btn-sm">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
      Chat WhatsApp
    </a>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('saveCustBtn').click()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      Save Profile
    </button>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <!-- EDIT FORM -->
  <div class="lg:col-span-2 space-y-6">
    <form method="POST">
      <input type="hidden" name="form_id" value="update_customer">
      <div class="card card-pad">
        <h2 class="font-display text-2xl font-semibold text-navy mb-5">Customer Information</h2>
        <div class="grid-2">
          <div class="field" style="margin:0"><label>Full Name</label><input class="input" name="full_name" value="<?=e($c['full_name'])?>"></div>
          <div class="field" style="margin:0"><label>Email</label><input class="input" type="email" name="email" value="<?=e($c['email'])?>"></div>
          <div class="field" style="margin:0"><label>Phone / WhatsApp</label><input class="input" name="phone" value="<?=e($c['phone'])?>"></div>
          <div class="field" style="margin:0"><label>City</label><input class="input" name="city" value="<?=e($c['city'] ?? '')?>"></div>
          <div class="field" style="margin:0"><label>Country</label><input class="input" name="country" value="<?=e($c['country'] ?? 'Indonesia')?>"></div>
          <div class="field" style="margin:0"><label>Joined On</label><input class="input" value="<?=formatDate($c['created_at'])?>" disabled></div>
        </div>
        <div class="field mt-4" style="margin:0"><label>Street Address</label><input class="input" name="address" value="<?=e($c['address'] ?? '')?>"></div>
        <div class="field mt-4" style="margin:0"><label>Private Admin Notes</label><textarea class="textarea" name="notes"><?=e($c['notes'] ?? '')?></textarea></div>
        <div class="flex justify-end mt-6 pt-6 border-t border-cream-3">
          <button type="submit" id="saveCustBtn" class="btn btn-primary">Save Changes</button>
        </div>
      </div>
    </form>

    <!-- PETS -->
    <div class="card card-pad">
      <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <h2 class="font-display text-2xl font-semibold text-navy">Pets in Household</h2>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('petModal').classList.toggle('hidden');document.getElementById('petModal').style.display=document.getElementById('petModal').classList.contains('hidden')?'none':'flex'">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add New Pet
        </button>
      </div>
      <?php if (count($pets) === 0): ?>
      <div class="empty">
        <div class="empty-icon">🐾</div>
        <p class="font-bold text-navy mb-1">No pets registered yet.</p>
        <p class="text-muted text-sm">Add this customer's first pet above.</p>
      </div>
      <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach($pets as $p): ?>
        <div class="p-5 rounded-2xl border border-cream-3 bg-cream/50 hover:bg-white hover:border-coral/30 transition-all">
          <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl" style="background:<?=petSizeColor($p['size_category'])?>15">
                <?=$p['species']==='cat' ? '🐱' : ($p['species']==='other' ? '🐾' : '🐶')?>
              </div>
              <div>
                <div class="font-display text-xl font-bold text-navy leading-tight"><?=e($p['name'])?></div>
                <div class="text-xs text-muted font-semibold"><?=e($p['breed'] ?? ucfirst($p['species']))?></div>
              </div>
            </div>
            <span class="chip" style="background:<?=petSizeColor($p['size_category'])?>18;color:<?=petSizeColor($p['size_category'])?>;border-color:<?=petSizeColor($p['size_category'])?>40">
              <?=petSizeLabel($p['size_category'])?>
            </span>
          </div>
          <div class="grid grid-cols-3 gap-3 text-center mb-4">
            <div class="p-2.5 rounded-xl bg-white/80 border border-cream-3">
              <div class="text-[10px] font-extrabold uppercase tracking-wider text-muted">Weight</div>
              <div class="font-bold text-navy text-sm mt-0.5"><?=e($p['weight_kg'])?> kg</div>
            </div>
            <div class="p-2.5 rounded-xl bg-white/80 border border-cream-3">
              <div class="text-[10px] font-extrabold uppercase tracking-wider text-muted">Age</div>
              <div class="font-bold text-navy text-sm mt-0.5"><?=e($p['age'] ?? '—')?></div>
            </div>
            <div class="p-2.5 rounded-xl bg-white/80 border border-cream-3">
              <div class="text-[10px] font-extrabold uppercase tracking-wider text-muted">Vaccine</div>
              <div class="font-bold text-sm mt-0.5" style="color:<?= $p['vaccine_status']==='up_to_date' ? 'var(--success)' : ($p['vaccine_status']==='incomplete' ? 'var(--danger)' : 'var(--warning)') ?>">
                <?=ucwords(str_replace('_',' ',$p['vaccine_status']))?>
              </div>
            </div>
          </div>
          <?php if (!empty($p['microchip'])): ?>
          <div class="text-xs font-bold text-muted mb-2">Microchip #: <span class="text-navy font-mono"><?=e($p['microchip'])?></span></div>
          <?php endif; ?>
          <?php if (!empty($p['health_notes'])): ?>
          <div class="p-3 rounded-xl bg-white border border-cream-3 text-xs text-muted leading-relaxed">
            <strong class="text-navy">Health Notes:</strong> <?=e($p['health_notes'])?>
          </div>
          <?php endif; ?>
          <div class="flex justify-end mt-4 pt-4 border-t border-cream-3">
            <a href="?id=<?=$id?>&delete_pet=<?=(int)$p['id']?>" class="btn btn-outline btn-sm confirm-delete" style="color:var(--danger);border-color:#FCA5A5">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
              Remove
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- BOOKING HISTORY -->
    <div class="card">
      <div class="p-6 pb-4">
        <h2 class="font-display text-2xl font-semibold text-navy">Booking History</h2>
        <p class="text-muted text-sm m-0 mt-1">All past and upcoming relocations for this customer.</p>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Code</th><th>Route</th><th>Date</th><th>Status</th><th>Value</th><th>Paid</th><th></th></tr>
          </thead>
          <tbody>
            <?php if (count($bookings) === 0): ?>
            <tr><td colspan="7"><div class="empty" style="border:0;margin:20px;background:transparent"><p class="font-bold text-navy mb-1">No bookings yet.</p><p class="text-muted text-sm">This customer hasn't made any relocations yet.</p></div></td></tr>
            <?php else: foreach($bookings as $b): ?>
            <tr>
              <td style="font-family:'Fraunces',serif;font-weight:700;color:var(--navy)"><?=e($b['booking_code'])?></td>
              <td class="font-semibold text-navy text-sm"><?=e($b['origin_city'])?> → <?=e($b['destination_city'])?></td>
              <td class="text-sm font-semibold text-muted"><?=formatDate($b['travel_date'])?></td>
              <td>
                <span class="chip" style="background:<?=bookingStatusColor($b['status'])?>15;color:<?=bookingStatusColor($b['status'])?>;font-size:10.5px;box-shadow:inset 0 0 0 1px <?=bookingStatusColor($b['status'])?>30">
                  <span class="chip-dot" style="background:<?=bookingStatusColor($b['status'])?>"></span>
                  <?=bookingStatusLabel($b['status'])?>
                </span>
              </td>
              <td class="font-bold text-navy"><?=formatRupiah($b['price_estimated_idr'] ?? 0)?></td>
              <td class="font-bold" style="color:<?=(float)($b['paid_amount_idr'] ?? 0) > 0 ? 'var(--success)' : 'var(--warning)'?>"><?=formatRupiah($b['paid_amount_idr'] ?? 0)?></td>
              <td><a href="booking-view.php?id=<?=(int)$b['id']?>" class="btn btn-outline btn-sm">Open</a></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- SIDEBAR: STATS -->
  <div class="space-y-6">
    <div class="card card-pad">
      <h3 class="font-display text-2xl font-semibold text-navy mb-5">Lifetime Stats</h3>
      <div class="space-y-5">
        <div class="p-4 rounded-2xl bg-coral/8 border border-coral/20">
          <div class="text-[10.5px] font-extrabold uppercase tracking-widest text-coral mb-1">Total Estimated Revenue</div>
          <div class="font-display text-3xl font-bold text-navy"><?=formatRupiah($totalEstimated)?></div>
        </div>
        <div class="p-4 rounded-2xl bg-teal/8 border border-teal/25">
          <div class="text-[10.5px] font-extrabold uppercase tracking-widest text-teal mb-1">Total Amount Paid</div>
          <div class="font-display text-3xl font-bold text-navy"><?=formatRupiah($totalSpent)?></div>
        </div>
        <div class="p-4 rounded-2xl bg-navy/5 border border-navy/10">
          <div class="text-[10.5px] font-extrabold uppercase tracking-widest text-navy mb-1">Outstanding Balance</div>
          <div class="font-display text-3xl font-bold" style="color:var(--coral)"><?=formatRupiah(max(0,$totalEstimated - $totalSpent))?></div>
        </div>
      </div>
    </div>

    <div class="card card-pad">
      <h3 class="font-bold text-navy mb-4">Contact Shortcuts</h3>
      <div class="space-y-2.5">
        <a href="https://wa.me/<?=preg_replace('/\D+/','',$c['phone'] ?? '')?>" target="_blank" class="flex items-center justify-between p-3 rounded-xl hover:bg-coral/8 transition-all border border-transparent hover:border-coral/30">
          <span class="font-semibold text-navy text-sm">💬 WhatsApp</span>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#FF6B4A" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
        </a>
        <a href="mailto:<?=e($c['email'])?>" class="flex items-center justify-between p-3 rounded-xl hover:bg-navy/5 transition-all border border-transparent hover:border-navy/20">
          <span class="font-semibold text-navy text-sm">📧 Send Email</span>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0F2747" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
        </a>
        <a href="tel:<?=e($c['phone'])?>" class="flex items-center justify-between p-3 rounded-xl hover:bg-teal/8 transition-all border border-transparent hover:border-teal/30">
          <span class="font-semibold text-navy text-sm">📞 Call Customer</span>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#4FB3A8" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Add Pet Modal -->
<div id="petModal" class="hidden fixed inset-0 z-[100] items-center justify-center p-4" style="background:rgba(15,39,71,0.55);backdrop-filter:blur(4px);display:none">
  <div class="card card-pad max-w-xl w-full relative">
    <button class="absolute top-4 right-4 btn btn-outline btn-icon btn-sm" onclick="document.getElementById('petModal').classList.add('hidden');document.getElementById('petModal').style.display='none'">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <h2 class="font-display text-3xl font-semibold text-navy mb-1">Register New Pet</h2>
    <p class="text-muted text-sm mb-6">Create a pet record under <?=e($c['full_name'])?>.</p>
    <form method="POST">
      <input type="hidden" name="form_id" value="add_pet">
      <div class="grid grid-cols-2 gap-4">
        <div class="field" style="margin:0"><label>Name<span class="req" style="color:var(--coral)">*</span></label><input class="input" name="name" required></div>
        <div class="field" style="margin:0"><label>Species</label>
          <select class="select" name="species">
            <option value="dog">Dog</option>
            <option value="cat">Cat</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="field" style="margin:0"><label>Breed</label><input class="input" name="breed"></div>
        <div class="field" style="margin:0"><label>Age</label><input class="input" name="age" placeholder="e.g. 3 tahun"></div>
        <div class="field" style="margin:0"><label>Weight (kg)</label><input class="input" type="number" step="0.1" name="weight_kg" value="10"></div>
        <div class="field" style="margin:0"><label>Size Category</label>
          <select class="select" name="size_category">
            <?php foreach(['small','medium','large','giant','brachycephalic'] as $sz): ?>
              <option value="<?=$sz?>"><?=petSizeLabel($sz)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="margin:0"><label>Microchip #</label><input class="input" name="microchip"></div>
        <div class="field" style="margin:0"><label>Vaccine Status</label>
          <select class="select" name="vaccine_status">
            <option value="up_to_date">Up to date</option>
            <option value="pending_expired">Pending / expiring</option>
            <option value="incomplete">Incomplete</option>
          </select>
        </div>
      </div>
      <div class="field mt-4" style="margin:0"><label>Health Notes</label><textarea class="textarea" name="health_notes"></textarea></div>
      <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-cream-3">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('petModal').classList.add('hidden');document.getElementById('petModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Pet to Profile</button>
      </div>
    </form>
  </div>
</div>
<script>
  document.getElementById('petModal').addEventListener('click', function(e){ if (e.target === this) { this.classList.add('hidden'); this.style.display='none'; }});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
