<?php
require_once __DIR__ . '/includes/header.php';
$page_title = 'Routes & Pricing';
requireAdmin();

$db = pawDB();

// Save route (create / update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_route') {
        $fields = ['origin_city','origin_code','destination_city','destination_code','type',
            'base_price_idr','est_duration_days','crate_required','is_active','notes'];
        $vals = [];
        foreach($fields as $f) {
            $v = $_POST[$f] ?? null;
            if (in_array($f, ['crate_required','is_active'])) $v = !empty($v) ? 1 : 0;
            if (in_array($f, ['base_price_idr','est_duration_days'])) $v = (float) $v;
            $vals[] = $v;
        }
        if (!empty($_POST['id']) && ctype_digit($_POST['id'])) {
            $id = (int)$_POST['id'];
            $set = implode(', ', array_map(fn($f) => "`$f` = ?", $fields));
            $vals[] = $id;
            $db->execute("UPDATE routes SET $set WHERE id = ?", $vals);
            setFlash('route', "Route #$id updated successfully.", 'success');
        } else {
            $cols = implode(',', array_map(fn($f)=>"`$f`",$fields));
            $ph = implode(',', array_fill(0,count($fields),'?'));
            $db->execute("INSERT INTO routes ($cols) VALUES ($ph)", $vals);
            setFlash('route', 'New route added to pricing engine.', 'success');
        }
        redirect('routes.php');
    }
    if ($action === 'delete' && !empty($_POST['id']) && ctype_digit($_POST['id'])) {
        $db->execute("DELETE FROM routes WHERE id = ?", [(int)$_POST['id']]);
        setFlash('route','Route deleted.','info');
        redirect('routes.php');
    }
}

$routes = $db->fetchAll("SELECT * FROM routes ORDER BY is_active DESC, origin_city ASC, destination_city ASC");
$editRoute = null;
if (!empty($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $editRoute = $db->fetch("SELECT * FROM routes WHERE id = ?", [(int)$_GET['edit']]);
}
?>

<div class="page-title-row">
  <div class="page-title">
    <h1>Routes & Base Pricing</h1>
    <p>All origin → destination corridors that the quote engine pulls base fares from.</p>
  </div>
  <div class="page-actions">
    <?php if ($editRoute): ?>
      <a href="routes.php" class="btn btn-outline btn-sm">← Back to all routes</a>
    <?php endif; ?>
  </div>
</div>

<!-- FORM (edit or create) -->
<div class="card card-pad mb-6">
  <h2 class="font-display text-2xl font-semibold text-navy mb-5">
    <?=$editRoute ? 'Edit Route · #' . (int)$editRoute['id'] : '➕ Add New Route'?>
  </h2>
  <form method="POST">
    <input type="hidden" name="action" value="save_route">
    <?php if ($editRoute): ?>
      <input type="hidden" name="id" value="<?=(int)$editRoute['id']?>">
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
      <div class="field" style="margin:0"><label>Origin City<span class="req" style="color:var(--coral)">*</span></label>
        <input class="input" name="origin_city" required value="<?=e($editRoute['origin_city'] ?? '')?>"></div>
      <div class="field" style="margin:0"><label>Origin Code</label>
        <input class="input" name="origin_code" placeholder="CGK" value="<?=e($editRoute['origin_code'] ?? '')?>"></div>
      <div class="field" style="margin:0"><label>Destination City<span class="req" style="color:var(--coral)">*</span></label>
        <input class="input" name="destination_city" required value="<?=e($editRoute['destination_city'] ?? '')?>"></div>
      <div class="field" style="margin:0"><label>Destination Code</label>
        <input class="input" name="destination_code" placeholder="SIN" value="<?=e($editRoute['destination_code'] ?? '')?>"></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
      <div class="field" style="margin:0"><label>Travel Type</label>
        <select class="select" name="type">
          <?php foreach(['domestic_air'=>'Domestic (Air)','domestic_land'=>'Domestic (Land)','international'=>'International','combined'=>'Combined Multi-Modal'] as $k=>$v): ?>
            <option value="<?=$k?>" <?=(($editRoute['type'] ?? 'domestic_air') === $k) ? 'selected' : ''?>><?=$v?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin:0"><label>Base Price (IDR)</label>
        <input class="input" type="number" step="100000" name="base_price_idr" required value="<?=e($editRoute['base_price_idr'] ?? 3500000)?>">
      </div>
      <div class="field" style="margin:0"><label>Est. Duration (days)</label>
        <input class="input" type="number" min="1" name="est_duration_days" value="<?=e($editRoute['est_duration_days'] ?? 1)?>">
      </div>
      <div class="field" style="margin:0"><label>&nbsp;</label>
        <div class="flex items-center gap-6 pt-2">
          <label class="flex items-center gap-2 font-semibold text-sm text-navy">
            <input type="checkbox" name="crate_required" <?=($editRoute['crate_required'] ?? 1)?'checked':''?> style="accent-color:#FF6B4A;width:17px;height:17px">
            Crate Required
          </label>
          <label class="flex items-center gap-2 font-semibold text-sm text-navy">
            <input type="checkbox" name="is_active" <?=($editRoute['is_active'] ?? 1)?'checked':''?> style="accent-color:#FF6B4A;width:17px;height:17px">
            Active
          </label>
        </div>
      </div>
    </div>

    <div class="field mb-5" style="margin:0"><label>Notes (internal)</label>
      <textarea class="textarea" name="notes" placeholder="Flight partners, restrictions, special handling..."><?=e($editRoute['notes'] ?? '')?></textarea>
    </div>

    <div class="flex justify-end gap-3">
      <?php if ($editRoute): ?>
        <a href="routes.php" class="btn btn-outline">Cancel edit</a>
      <?php endif; ?>
      <button class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        <?=$editRoute ? 'Update Route' : 'Add Route to Database'?>
      </button>
    </div>
  </form>
</div>

<!-- TABLE -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Route</th><th>Type</th><th>Base Fare</th><th>Duration</th><th>Active</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (count($routes) === 0): ?>
        <tr><td colspan="6"><div class="empty" style="border:0;margin:30px;background:transparent">
          <div class="empty-icon">🛫</div>
          <p class="font-bold text-navy mb-1">No routes in the database.</p>
          <p class="text-muted text-sm">Add your first route using the form above.</p>
        </div></td></tr>
        <?php else: foreach($routes as $r): ?>
        <tr style="<?=!$r['is_active']?'opacity:0.55':''?>">
          <td>
            <div class="flex items-center gap-3">
              <div class="w-11 h-11 rounded-2xl bg-cream-3 flex items-center justify-center text-xl">✈️</div>
              <div>
                <div class="font-bold text-navy text-sm">
                  <?=e($r['origin_city'])?>
                  <?php if (!empty($r['origin_code'])): ?><span class="text-xs text-muted ml-1">(<?=e($r['origin_code'])?>)</span><?php endif; ?>
                  <span class="text-coral font-bold mx-2">→</span>
                  <?=e($r['destination_city'])?>
                  <?php if (!empty($r['destination_code'])): ?><span class="text-xs text-muted ml-1">(<?=e($r['destination_code'])?>)</span><?php endif; ?>
                </div>
                <?php if (!empty($r['notes'])): ?>
                  <div class="text-xs text-muted mt-0.5">📝 <?=e($r['notes'])?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td>
            <span class="chip" style="background:<?= $r['type']==='international' ? '#FF6B4A18;color:#FF6B4A' : '#E6EEFA;color:var(--navy)' ?>;<?= $r['type']==='international' ? 'border-color:#FF6B4A40' : '' ?>"><?=travelTypeLabel($r['type'])?></span>
          </td>
          <td class="font-display text-2xl font-bold text-navy"><?=formatRupiah($r['base_price_idr'])?></td>
          <td class="font-semibold text-navy text-sm"><?=(int)$r['est_duration_days']?> day<?=(int)$r['est_duration_days']===1?'':'s'?><?=$r['crate_required']?' · 📦 crate':''?></td>
          <td>
            <span class="chip" style="background:<?=$r['is_active']?'#D1FAE5;color:#065F46':'#FEE2E2;color:#991B1B'?>">
              <span class="chip-dot" style="background:<?=$r['is_active']?'#10B981':'#EF4444'?>"></span>
              <?=$r['is_active']?'Active':'Disabled'?>
            </span>
          </td>
          <td style="white-space:nowrap;text-align:right">
            <a href="routes.php?edit=<?=(int)$r['id']?>" class="btn btn-outline btn-icon btn-sm" title="Edit">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            </a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this route?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?=(int)$r['id']?>">
              <button class="btn btn-outline btn-icon btn-sm confirm-delete" title="Delete" style="border-color:#FCA5A5">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
