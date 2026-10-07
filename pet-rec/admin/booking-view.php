<?php
require_once __DIR__ . '/includes/header.php';
$page_title = 'Booking Details';
requireAdmin();

$db = pawDB();
$id = (int)($_GET['id'] ?? 0);
$b = $db->fetch(
    "SELECT b.*, c.full_name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address,
     p.name AS pet_name, p.breed AS pet_breed, p.age AS pet_age, p.microchip AS pet_microchip, p.vaccine_status AS pet_vaccine, p.health_notes AS pet_notes
     FROM bookings b
     LEFT JOIN customers c ON c.id = b.customer_id
     LEFT JOIN pets p ON p.id = b.pet_id
     WHERE b.id = ?",
    [$id]
);
if (!$b) { setFlash('booking','Booking not found.','error'); redirect('bookings.php'); }

$logs = $db->fetchAll("SELECT * FROM tracking_logs WHERE booking_id = ? ORDER BY created_at ASC, id ASC", [$id]);

// ===== UPDATE BOOKING =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_id'] ?? '') === 'update_booking') {
    $fields = ['status','payment_status','origin_city','destination_city','travel_type','travel_date','return_date',
        'pet_type_booking','pet_weight_kg','price_estimated_idr','price_final_idr','paid_amount_idr',
        'addon_door','addon_docs','addon_stay','assigned_handler','flight_details','customer_notes','private_admin_notes','special_instructions'];
    $setClauses = []; $params = [];
    foreach($fields as $f) {
        $v = $_POST[$f] ?? null;
        if ($f === 'return_date' && empty($v)) { $v = null; }
        if (in_array($f, ['addon_door','addon_docs','addon_stay'])) $v = $v ? 1 : 0;
        if (in_array($f, ['price_estimated_idr','price_final_idr','paid_amount_idr','pet_weight_kg'])) $v = (float) $v;
        $setClauses[] = "`$f` = ?";
        $params[] = $v;
    }
    $params[] = $id;
    $db->execute("UPDATE bookings SET " . implode(', ', $setClauses) . " WHERE id = ?", $params);

    // Also update pet info
    if (!empty($b['pet_id'])) {
        $db->execute("UPDATE pets SET name=?, breed=?, age=?, microchip=?, vaccine_status=?, health_notes=?, weight_kg=?, size_category=? WHERE id = ?", [
            $_POST['pet_name'] ?? $b['pet_name'],
            $_POST['pet_breed'] ?? $b['pet_breed'],
            $_POST['pet_age'] ?? $b['pet_age'],
            $_POST['pet_microchip'] ?? $b['pet_microchip'],
            $_POST['pet_vaccine'] ?? $b['pet_vaccine'],
            $_POST['pet_notes'] ?? $b['pet_notes'],
            (float)($_POST['pet_weight_kg'] ?? $b['pet_weight_kg']),
            $_POST['pet_type_booking'] ?? $b['pet_type_booking'],
            (int)$b['pet_id'],
        ]);
    }
    setFlash('booking', 'Booking updated successfully.', 'success');
    redirect("booking-view.php?id=$id");
}

// ===== ADD TRACKING LOG =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_id'] ?? '') === 'add_tracking') {
    $status = $_POST['tl_status'] ?? 'in_transit';
    $title = trim($_POST['tl_title'] ?? '');
    $desc = trim($_POST['tl_description'] ?? '');
    $loc = trim($_POST['tl_location'] ?? '');
    if ($title) {
        $db->execute(
            "INSERT INTO tracking_logs (booking_id, status, title, description, location, created_by_admin_id) VALUES (?,?,?,?,?,?)",
            [$id, $status, $title, $desc, $loc, (int)$_SESSION['admin_id']]
        );
        // Auto-bump booking status if newer
        if (!in_array($b['status'], ['completed','delivered','cancelled'])) {
            $progress = ['inquiry','confirmed','preparing','picked_up','origin_airport','in_flight','in_transit','transit','destination_airport','out_for_delivery','arrived','delivered','completed'];
            $curPos = array_search($b['status'], $progress);
            $newPos = array_search($status, $progress);
            if ($newPos !== false && ($curPos === false || $newPos > $curPos)) {
                $db->execute("UPDATE bookings SET status = ? WHERE id = ?", [$status, $id]);
            }
        }
        setFlash('tracking', 'Tracking update added. Customers can now see this on the public tracker.', 'success');
        redirect("booking-view.php?id=$id");
    }
}

// ===== DELETE TRACKING LOG =====
if (!empty($_GET['delete_log'])) {
    $lid = (int)$_GET['delete_log'];
    $db->execute("DELETE FROM tracking_logs WHERE id = ? AND booking_id = ?", [$lid, $id]);
    setFlash('tracking','Log entry deleted.','info');
    redirect("booking-view.php?id=$id");
}
?>

<div class="page-title-row">
  <div class="page-title">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
      <a href="bookings.php" class="btn btn-outline btn-icon btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </a>
      <h1 style="font-family:'Fraunces',serif"><?=e($b['booking_code'])?></h1>
      <span class="chip" style="background:<?=bookingStatusColor($b['status'])?>15;color:<?=bookingStatusColor($b['status'])?>;box-shadow:inset 0 0 0 1.5px <?=bookingStatusColor($b['status'])?>40">
        <span class="chip-dot" style="background:<?=bookingStatusColor($b['status'])?>"></span>
        <?=bookingStatusLabel($b['status'])?>
      </span>
    </div>
    <p><?=e($b['origin_city'])?> → <?=e($b['destination_city'])?> · <?=formatDate($b['travel_date'])?> · <?=travelTypeLabel($b['travel_type'])?></p>
  </div>
  <div class="page-actions">
    <a href="../track.php?code=<?=urlencode($b['booking_code'])?>" target="_blank" class="btn btn-outline btn-sm">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      View Public Tracker
    </a>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('saveBookingTop').click()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      Save Changes
    </button>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <!-- Left column: Details form -->
  <div class="lg:col-span-2 space-y-6">
    <!-- BOOKING EDIT -->
    <form method="POST">
      <input type="hidden" name="form_id" value="update_booking">
      <div class="card card-pad">
        <h2 class="font-display text-2xl font-semibold text-navy mb-5">Booking & Journey Details</h2>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
          <div class="field" style="margin:0">
            <label>Status</label>
            <select class="select" name="status">
              <?php foreach(['inquiry','confirmed','preparing','in_transit','arrived','delivered','completed','cancelled'] as $s): ?>
              <option value="<?=$s?>" <?=$b['status']==$s?'selected':''?>><?=bookingStatusLabel($s)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" style="margin:0">
            <label>Payment</label>
            <select class="select" name="payment_status">
              <?php foreach(['unpaid','deposit','paid','refunded'] as $p): ?>
              <option value="<?=$p?>" <?=$b['payment_status']==$p?'selected':''?>><?=paymentStatusLabel($p)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" style="margin:0">
            <label>Travel Type</label>
            <select class="select" name="travel_type">
              <?php foreach(['domestic_air','domestic_land','international','combined'] as $t): ?>
              <option value="<?=$t?>" <?=$b['travel_type']==$t?'selected':''?>><?=travelTypeLabel($t)?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="grid-2 mb-4">
          <div class="field" style="margin:0">
            <label>Origin City</label>
            <input class="input" name="origin_city" value="<?=e($b['origin_city'])?>">
          </div>
          <div class="field" style="margin:0">
            <label>Destination City</label>
            <input class="input" name="destination_city" value="<?=e($b['destination_city'])?>">
          </div>
          <div class="field" style="margin:0">
            <label>Departure Date</label>
            <input class="input" type="date" name="travel_date" value="<?=e($b['travel_date'])?>">
          </div>
          <div class="field" style="margin:0">
            <label>Return Date (optional)</label>
            <input class="input" type="date" name="return_date" value="<?=e($b['return_date'] ?? '')?>">
          </div>
        </div>

        <h3 class="font-bold text-navy text-sm uppercase tracking-wider text-muted mt-2 mb-3">Pet Info</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
          <div class="field" style="margin:0">
            <label>Name</label>
            <input class="input" name="pet_name" value="<?=e($b['pet_name'] ?? '')?>">
          </div>
          <div class="field" style="margin:0">
            <label>Breed</label>
            <input class="input" name="pet_breed" value="<?=e($b['pet_breed'] ?? '')?>">
          </div>
          <div class="field" style="margin:0">
            <label>Age</label>
            <input class="input" name="pet_age" value="<?=e($b['pet_age'] ?? '')?>">
          </div>
          <div class="field" style="margin:0">
            <label>Weight (kg)</label>
            <input class="input" type="number" step="0.1" name="pet_weight_kg" value="<?=e($b['pet_weight_kg'])?>">
          </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
          <div class="field" style="margin:0">
            <label>Size Category</label>
            <select class="select" name="pet_type_booking">
              <?php foreach(['small','medium','large','giant','brachycephalic'] as $sz): ?>
              <option value="<?=$sz?>" <?=$b['pet_type_booking']==$sz?'selected':''?>><?=petSizeLabel($sz)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" style="margin:0">
            <label>Microchip</label>
            <input class="input" name="pet_microchip" value="<?=e($b['pet_microchip'] ?? '')?>">
          </div>
          <div class="field" style="margin:0">
            <label>Vaccine Status</label>
            <select class="select" name="pet_vaccine">
              <?php foreach(['up_to_date','pending_expired','incomplete'] as $v): ?>
              <option value="<?=$v?>" <?=$b['pet_vaccine']==$v?'selected':''?>><?=ucwords(str_replace('_',' ',$v))?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" style="margin:0">
            <label>&nbsp;</label>
            <div class="flex gap-2 pt-1.5 flex-wrap">
              <label class="btn btn-outline btn-sm"><input type="checkbox" name="addon_door" <?=$b['addon_door']?'checked':''?> style="accent-color:#FF6B4A;margin-right:6px"> Door-to-door</label>
              <label class="btn btn-outline btn-sm"><input type="checkbox" name="addon_docs" <?=$b['addon_docs']?'checked':''?> style="accent-color:#FF6B4A;margin-right:6px"> Docs</label>
              <label class="btn btn-outline btn-sm"><input type="checkbox" name="addon_stay" <?=$b['addon_stay']?'checked':''?> style="accent-color:#FF6B4A;margin-right:6px"> Boarding</label>
            </div>
          </div>
        </div>
        <div class="field mb-4" style="margin:0">
          <label>Pet Health Notes</label>
          <textarea class="textarea" name="pet_notes"><?=e($b['pet_notes'] ?? '')?></textarea>
        </div>

        <h3 class="font-bold text-navy text-sm uppercase tracking-wider text-muted mt-2 mb-3">Operational Details</h3>
        <div class="grid-2 mb-4">
          <div class="field" style="margin:0">
            <label>Assigned Handler</label>
            <input class="input" name="assigned_handler" value="<?=e($b['assigned_handler'] ?? '')?>">
          </div>
          <div class="field" style="margin:0">
            <label>Flight / Cargo Details</label>
            <input class="input" name="flight_details" value="<?=e($b['flight_details'] ?? '')?>">
          </div>
        </div>

        <h3 class="font-bold text-navy text-sm uppercase tracking-wider text-muted mt-2 mb-3">Billing</h3>
        <div class="grid-3 mb-4">
          <div class="field" style="margin:0">
            <label>Estimated (IDR)</label>
            <input class="input" type="number" step="1000" name="price_estimated_idr" value="<?=e($b['price_estimated_idr'])?>">
          </div>
          <div class="field" style="margin:0">
            <label>Final Price (IDR)</label>
            <input class="input" type="number" step="1000" name="price_final_idr" value="<?=e($b['price_final_idr'] ?? '')?>">
          </div>
          <div class="field" style="margin:0">
            <label>Paid Amount (IDR)</label>
            <input class="input" type="number" step="1000" name="paid_amount_idr" value="<?=e($b['paid_amount_idr'])?>">
          </div>
        </div>

        <div class="grid-2 mb-5">
          <div class="field" style="margin:0">
            <label>Customer-visible Notes</label>
            <textarea class="textarea" name="customer_notes"><?=e($b['customer_notes'] ?? '')?></textarea>
          </div>
          <div class="field" style="margin:0">
            <label>Private Admin Notes (hidden)</label>
            <textarea class="textarea" name="private_admin_notes"><?=e($b['private_admin_notes'] ?? '')?></textarea>
          </div>
        </div>
        <div class="field" style="margin:0">
          <label>Special Instructions (shared with team)</label>
          <textarea class="textarea" name="special_instructions"><?=e($b['special_instructions'] ?? '')?></textarea>
        </div>

        <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-cream-3">
          <a href="bookings.php" class="btn btn-outline">Cancel</a>
          <button type="submit" id="saveBookingTop" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Booking
          </button>
        </div>
      </div>
    </form>

    <!-- CUSTOMER CONTACT CARD -->
    <div class="card card-pad">
      <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <h2 class="font-display text-2xl font-semibold text-navy">Customer</h2>
        <a href="customer-view.php?id=<?=(int)$b['customer_id']?>" class="btn btn-outline btn-sm">View full profile →</a>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="flex items-center gap-4">
          <div class="avatar avatar-lg"><?=strtoupper(substr($b['customer_name'] ?? 'U',0,1))?></div>
          <div>
            <div class="font-bold text-navy text-lg leading-tight"><?=e($b['customer_name'] ?? 'Unknown')?></div>
            <div class="text-xs text-muted font-semibold">ID #<?=(int)$b['customer_id']?></div>
          </div>
        </div>
        <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
          <a href="mailto:<?=e($b['customer_email'])?>" class="p-3 rounded-2xl bg-cream border border-cream-3 hover:border-coral/40 transition-all">
            <div class="text-[10px] font-extrabold uppercase tracking-widest text-muted mb-1">Email</div>
            <div class="font-semibold text-navy truncate"><?=e($b['customer_email'] ?? '')?></div>
          </a>
          <a href="tel:<?=e($b['customer_phone'])?>" class="p-3 rounded-2xl bg-cream border border-cream-3 hover:border-coral/40 transition-all">
            <div class="text-[10px] font-extrabold uppercase tracking-widest text-muted mb-1">Phone</div>
            <div class="font-semibold text-navy truncate"><?=e($b['customer_phone'] ?? '')?></div>
          </a>
          <a href="https://wa.me/<?=preg_replace('/\D+/','',$b['customer_phone'] ?? '')?>" target="_blank" class="p-3 rounded-2xl bg-cream border border-cream-3 hover:border-coral/40 transition-all">
            <div class="text-[10px] font-extrabold uppercase tracking-widest text-muted mb-1">WhatsApp</div>
            <div class="font-semibold text-navy truncate">Open chat →</div>
          </a>
        </div>
      </div>
      <?php if (!empty($b['customer_address'])): ?>
      <div class="mt-4 p-4 rounded-2xl bg-cream border border-cream-3 text-sm">
        <div class="text-[10px] font-extrabold uppercase tracking-widest text-muted mb-1">Address on file</div>
        <div class="font-semibold text-navy"><?=e($b['customer_address'])?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- JOURNEY TIMELINE + ADD LOG -->
    <div class="card card-pad">
      <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
          <h2 class="font-display text-2xl font-semibold text-navy">Journey Timeline</h2>
          <p class="text-muted text-sm mt-1 m-0"><?=count($logs)?> status updates · visible to customer</p>
        </div>
        <a href="tracking.php?booking_id=<?=$id?>" class="btn btn-outline btn-sm">Manage all logs</a>
      </div>

      <!-- ADD NEW LOG -->
      <form method="POST" class="p-5 rounded-2xl border-2 border-dashed border-coral/30 bg-coral/5 mb-6">
        <input type="hidden" name="form_id" value="add_tracking">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
          <h3 class="font-bold text-navy m-0">➕ Publish New Update</h3>
          <span class="text-xs font-bold text-muted">Posted instantly to public tracker</span>
        </div>
        <div class="grid-2 mb-4">
          <div class="field" style="margin:0">
            <label>Milestone Status</label>
            <select class="select" name="tl_status">
              <?php foreach(['inquiry','confirmed','preparing','picked_up','origin_airport','in_flight','in_transit','transit','destination_airport','out_for_delivery','arrived','delivered','completed','cancelled'] as $s): ?>
              <option value="<?=$s?>"><?=bookingStatusLabel($s)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" style="margin:0">
            <label>Location</label>
            <input class="input" name="tl_location" placeholder="e.g. Soekarno-Hatta Intl Airport, T3 Cargo">
          </div>
        </div>
        <div class="field mb-4" style="margin:0">
          <label>Update Title</label>
          <input class="input" name="tl_title" required placeholder="e.g. Pet picked up from home safely">
        </div>
        <div class="field mb-4" style="margin:0">
          <label>Description (optional)</label>
          <textarea class="textarea" name="tl_description" placeholder="Any specific details for the customer — e.g. given water, walked, resting calmly"></textarea>
        </div>
        <button class="btn btn-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          Publish Update Now
        </button>
      </form>

      <!-- LOGS -->
      <?php if (count($logs) === 0): ?>
      <div class="empty">
        <div class="empty-icon">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <p class="font-bold text-navy mb-1">No tracking updates yet</p>
        <p class="text-muted text-sm">Publish your first update above — it'll appear instantly for the customer.</p>
      </div>
      <?php else: ?>
      <div style="padding-left:44px;position:relative">
        <div style="content:'';position:absolute;left:15px;top:8px;bottom:8px;width:3px;background:linear-gradient(180deg,#FF6B4A 0%,#C9A962 50%,#4FB3A8 100%);border-radius:999px;opacity:0.3"></div>
        <?php foreach(array_reverse($logs) as $i => $l): ?>
        <div class="tracker-step" style="position:relative">
          <div class="dot" style="position:absolute;left:-44px;top:17px">
            <div style="width:12px;height:12px;border-radius:50%;background:<?=$i===0 ? 'white' : 'var(--coral)'?>;<?=$i===0?'background:var(--teal)':''?>"></div>
          </div>
          <div class="flex-1">
            <div class="flex items-start justify-between gap-4 flex-wrap">
              <div>
                <div class="flex items-center gap-2 flex-wrap mb-2">
                  <h4 class="font-bold text-navy text-lg leading-tight m-0"><?=e($l['title'])?></h4>
                  <span class="chip" style="background:<?=bookingStatusColor($l['status'])?>15;color:<?=bookingStatusColor($l['status'])?>;font-size:10.5px">
                    <span class="chip-dot" style="background:<?=bookingStatusColor($l['status'])?>"></span>
                    <?=bookingStatusLabel($l['status'])?>
                  </span>
                </div>
                <?php if (!empty($l['description'])): ?>
                <p class="text-muted leading-relaxed mb-2 m-0"><?=e($l['description'])?></p>
                <?php endif; ?>
                <?php if (!empty($l['location'])): ?>
                <div class="text-xs font-semibold text-muted flex items-center gap-1.5">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
                  <?=e($l['location'])?>
                </div>
                <?php endif; ?>
              </div>
              <div class="text-right flex-shrink-0">
                <div class="font-bold text-navy text-sm"><?=formatDate($l['created_at'],'M j, Y')?></div>
                <div class="text-xs text-muted mt-1"><?=date('g:i A', strtotime($l['created_at']))?></div>
                <a href="?id=<?=$id?>&delete_log=<?=(int)$l['id']?>" class="btn btn-outline btn-icon btn-xs confirm-delete mt-2" title="Delete" style="width:30px;height:30px;padding:0">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
                </a>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- RIGHT COLUMN: SUMMARY SIDEBAR -->
  <div class="space-y-6">
    <!-- PRICE / FINANCE CARD -->
    <div class="card card-pad">
      <h3 class="font-display text-2xl font-semibold text-navy mb-5">Finance</h3>
      <div class="space-y-4">
        <div class="flex justify-between items-center pb-4 border-b border-cream-3">
          <span class="text-muted font-semibold text-sm">Estimated</span>
          <span class="font-display text-2xl font-semibold text-navy"><?=formatRupiah($b['price_estimated_idr'] ?: 0)?></span>
        </div>
        <div class="flex justify-between items-center pb-4 border-b border-cream-3">
          <span class="text-muted font-semibold text-sm">Final Price</span>
          <span class="font-display text-2xl font-semibold text-navy"><?=formatRupiah($b['price_final_idr'] ?: 0)?></span>
        </div>
        <div class="flex justify-between items-center pb-4 border-b border-cream-3">
          <span class="text-muted font-semibold text-sm">Paid</span>
          <span class="font-display text-2xl font-semibold" style="color:var(--success)"><?=formatRupiah($b['paid_amount_idr'] ?: 0)?></span>
        </div>
        <?php $outstanding = (float)($b['price_final_idr'] ?: $b['price_estimated_idr']) - (float)$b['paid_amount_idr']; ?>
        <div class="flex justify-between items-center p-4 rounded-2xl" style="background:<?=$outstanding > 0 ? 'rgba(255,107,74,0.08)' : 'rgba(16,185,129,0.08)'?>">
          <span class="font-bold" style="color:<?=$outstanding > 0 ? 'var(--coral)' : 'var(--success)'?>">
            <?= $outstanding > 0 ? 'Outstanding' : '✅ Fully Paid' ?>
          </span>
          <span class="font-display text-2xl font-bold" style="color:<?=$outstanding > 0 ? 'var(--coral)' : 'var(--success)'?>">
            <?= $outstanding > 0 ? formatRupiah($outstanding) : 'Rp 0' ?>
          </span>
        </div>
      </div>

      <div class="divider"></div>

      <h4 class="font-bold text-navy text-sm mb-3">Service Add-ons</h4>
      <div class="space-y-2">
        <?php
          $addonsList = [
            ['k'=>'addon_door','label'=>'Door-to-Door Concierge','price'=>1200000,'v'=>(bool)$b['addon_door']],
            ['k'=>'addon_docs','label'=>'Vet Documentation','price'=>1800000,'v'=>(bool)$b['addon_docs']],
            ['k'=>'addon_stay','label'=>'Transit Boarding','price'=>950000,'v'=>(bool)$b['addon_stay']],
          ];
          foreach($addonsList as $a):
        ?>
        <div class="flex items-center justify-between p-3 rounded-xl <?= $a['v'] ? 'bg-coral/8 border border-coral/25' : 'bg-cream-2 border border-cream-3 opacity-60'?>">
          <div class="flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:<?=$a['v'] ? 'var(--coral)' : 'var(--muted)'?>;color:white">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><?=$a['v']?'<polyline points="20 6 9 17 4 12"/>':'<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'?></svg>
            </div>
            <span class="font-semibold text-navy text-sm"><?=$a['label']?></span>
          </div>
          <span class="font-bold text-navy text-sm"><?= formatRupiah($a['price']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- PROGRESS CARD -->
    <div class="card card-pad">
      <h3 class="font-display text-2xl font-semibold text-navy mb-5">Journey Progress</h3>
      <?php
        $pipeline = ['inquiry','confirmed','preparing','in_transit','arrived','delivered','completed'];
        $curPos = array_search($b['status'], $pipeline);
        $progressPct = $curPos !== false ? round(($curPos + 1) / count($pipeline) * 100) : 5;
      ?>
      <div class="mb-2 flex items-center justify-between">
        <span class="text-xs font-extrabold uppercase tracking-wider text-muted">Journey progress</span>
        <span class="font-display text-2xl font-bold text-coral"><?=$progressPct?>%</span>
      </div>
      <div style="height:12px;border-radius:999px;background:var(--cream-3);overflow:hidden;margin-bottom:20px">
        <div style="height:100%;width:<?=$progressPct?>%;background:linear-gradient(90deg,#FF6B4A,#FF8769);border-radius:999px;transition:width 0.6s"></div>
      </div>
      <div class="space-y-3">
        <?php foreach($pipeline as $pi => $step): ?>
          <?php $done = ($curPos !== false && $pi <= $curPos); ?>
          <?php
            $pipelineBg = $done ? ($pi === $curPos ? 'var(--coral)' : 'var(--teal)') : 'var(--cream-3)';
            $pipelineColor = $done ? 'white' : 'var(--muted)';
            $stepColor = $done ? 'var(--navy)' : 'var(--muted)';
          ?>
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background:<?=$pipelineBg?>;color:<?=$pipelineColor?>">
              <?php if ($done): ?>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              <?php else: ?>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              <?php endif; ?>
            </div>
            <span class="font-semibold text-sm" style="color:<?=$stepColor?>"><?=bookingStatusLabel($step)?></span>
            <?php if ($pi === $curPos): ?>
            <span class="chip ml-auto" style="background:var(--coral);color:white;font-size:10px">CURRENT</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="card card-pad">
      <h3 class="font-bold text-navy mb-4">Quick Actions</h3>
      <div class="space-y-2.5">
        <a href="https://wa.me/<?=preg_replace('/\D+/','',$b['customer_phone'] ?? '')?>?text=Hello%20<?=urlencode($b['customer_name'] ?? '')?>%2C%20regarding%20your%20BeethovenPets%20booking%20<?=urlencode($b['booking_code'])?>..." target="_blank" class="flex items-center justify-between p-3 rounded-xl hover:bg-coral/8 transition-all border border-transparent hover:border-coral/30">
          <span class="font-semibold text-navy text-sm">💬 WhatsApp Customer</span>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#FF6B4A" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
        </a>
        <a href="mailto:<?=e($b['customer_email'])?>?subject=BeethovenPets%20Booking%20<?=urlencode($b['booking_code'])?>" class="flex items-center justify-between p-3 rounded-xl hover:bg-navy/5 transition-all border border-transparent hover:border-navy/20">
          <span class="font-semibold text-navy text-sm">📧 Email Booking Summary</span>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0F2747" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
        </a>
        <a href="bookings.php" class="flex items-center justify-between p-3 rounded-xl hover:bg-teal/8 transition-all border border-transparent hover:border-teal/30">
          <span class="font-semibold text-navy text-sm">📋 Print Waybill / Labels</span>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#4FB3A8" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        </a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
