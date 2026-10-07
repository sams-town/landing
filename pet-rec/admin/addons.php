<?php
require_once __DIR__ . '/includes/header.php';
$page_title = 'Add-On Services';
requireAdmin();

$db = pawDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'save') {
    $fields = ['code','name','description','price_idr','icon','is_active'];
    $vals = [];
    foreach($fields as $f) {
      $v = $_POST[$f] ?? null;
      if ($f === 'is_active') $v = !empty($v) ? 1 : 0;
      if ($f === 'price_idr') $v = (float)$v;
      $vals[] = $v;
    }
    if (!empty($_POST['id']) && ctype_digit($_POST['id'])) {
      $id = (int)$_POST['id'];
      $set = implode(', ', array_map(fn($f) => "`$f` = ?", $fields));
      $vals[] = $id;
      $db->execute("UPDATE addons SET $set WHERE id = ?", $vals);
      setFlash('addon', "Add-on updated.", 'success');
    } else {
      $cols = implode(',', array_map(fn($f)=>"`$f`",$fields));
      $ph = implode(',', array_fill(0,count($fields),'?'));
      $db->execute("INSERT INTO addons ($cols) VALUES ($ph)", $vals);
      setFlash('addon', "New add-on added to the pricing engine.", 'success');
    }
    redirect('addons.php');
  }
  if ($action === 'delete' && !empty($_POST['id']) && ctype_digit($_POST['id'])) {
    $db->execute("DELETE FROM addons WHERE id = ?", [(int)$_POST['id']]);
    setFlash('addon','Add-on removed.','info');
    redirect('addons.php');
  }
}

$addons = $db->fetchAll("SELECT * FROM addons ORDER BY is_active DESC, id ASC");
$editA = null;
if (!empty($_GET['edit']) && ctype_digit($_GET['edit'])) {
  $editA = $db->fetch("SELECT * FROM addons WHERE id = ?", [(int)$_GET['edit']]);
}
?>

<div class="page-title-row">
  <div class="page-title">
    <h1>Add-On Services</h1>
    <p>Upsells and premium services customers can add to any booking.</p>
  </div>
  <?php if ($editA): ?>
    <a href="addons.php" class="btn btn-outline btn-sm page-actions">← Back</a>
  <?php endif; ?>
</div>

<div class="card card-pad mb-6">
  <h2 class="font-display text-2xl font-semibold text-navy mb-5"><?=$editA ? 'Edit Add-on · #' . (int)$editA['id'] : '➕ Create New Add-on'?></h2>
  <form method="POST">
    <input type="hidden" name="action" value="save">
    <?php if ($editA): ?><input type="hidden" name="id" value="<?=(int)$editA['id']?>"><?php endif; ?>
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-4">
      <div class="field md:col-span-2" style="margin:0"><label>Service Code<span class="req" style="color:var(--coral)">*</span></label>
        <input class="input" name="code" required placeholder="e.g. door_to_door" value="<?=e($editA['code'] ?? '')?>"></div>
      <div class="field md:col-span-2" style="margin:0"><label>Name<span class="req" style="color:var(--coral)">*</span></label>
        <input class="input" name="name" required value="<?=e($editA['name'] ?? '')?>"></div>
      <div class="field" style="margin:0"><label>Icon (emoji)</label>
        <input class="input" name="icon" value="<?=e($editA['icon'] ?? 'star')?>"></div>
      <div class="field" style="margin:0"><label>Price (IDR)</label>
        <input class="input" type="number" step="50000" name="price_idr" value="<?=e($editA['price_idr'] ?? 0)?>"></div>
    </div>
    <div class="field mb-4" style="margin:0">
      <label>Description</label>
      <textarea class="textarea" name="description"><?=e($editA['description'] ?? '')?></textarea>
    </div>
    <div class="flex items-center justify-between">
      <label class="flex items-center gap-2 font-semibold text-sm text-navy">
        <input type="checkbox" name="is_active" <?=($editA['is_active'] ?? 1)?'checked':''?> style="accent-color:#FF6B4A;width:18px;height:18px">
        Active & visible on the quote form
      </label>
      <div class="flex gap-3">
        <?php if ($editA): ?><a href="addons.php" class="btn btn-outline">Cancel</a><?php endif; ?>
        <button class="btn btn-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/></svg>
          <?=$editA ? 'Update Add-on' : 'Add Service'?>
        </button>
      </div>
    </div>
  </form>
</div>

<?php if (count($addons) === 0): ?>
<div class="card card-pad">
  <div class="empty" style="border:0;background:transparent">
    <div class="empty-icon">⭐</div>
    <p class="font-bold text-navy mb-1">No add-on services yet.</p>
    <p class="text-muted text-sm">Create your first upsell (Door-to-door, Vet docs, etc.) above.</p>
  </div>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
  <?php foreach($addons as $a): ?>
  <div class="card card-pad card-hover relative" style="<?=!$a['is_active']?'opacity:0.6':''?>">
    <div class="absolute top-5 right-5">
      <span class="chip" style="background:<?=$a['is_active']?'#D1FAE5;color:#065F46':'#FEE2E2;color:#991B1B'?>">
        <span class="chip-dot" style="background:<?=$a['is_active']?'#10B981':'#EF4444'?>"></span>
        <?=$a['is_active']?'Active':'Disabled'?>
      </span>
    </div>
    <div class="flex items-start gap-4 mb-4">
      <div class="w-14 h-14 rounded-2xl bg-coral/10 flex items-center justify-center text-3xl flex-shrink-0">
        <?=$a['icon'] && preg_match('/[\x{1F300}-\x{1F9FF}]/u', $a['icon']) ? $a['icon'] : '⭐'?>
      </div>
      <div class="min-w-0 pr-16">
        <div class="text-[11px] font-extrabold uppercase tracking-widest text-muted mb-1"><?=e($a['code'])?></div>
        <h3 class="font-bold text-navy text-lg leading-tight"><?=e($a['name'])?></h3>
      </div>
    </div>
    <p class="text-muted text-sm leading-relaxed mb-4"><?=e($a['description'])?></p>
    <div class="p-4 rounded-2xl bg-gradient-to-br from-cream-3 to-cream flex items-center justify-between">
      <div>
        <div class="text-[10px] font-extrabold uppercase tracking-widest text-muted">Price</div>
        <div class="font-display text-3xl font-bold text-coral leading-none mt-0.5"><?=formatRupiah($a['price_idr'])?></div>
      </div>
      <div class="flex gap-2">
        <a href="addons.php?edit=<?=(int)$a['id']?>" class="btn btn-outline btn-icon btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </a>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this add-on?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?=(int)$a['id']?>">
          <button class="btn btn-outline btn-icon btn-sm confirm-delete" style="border-color:#FCA5A5">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
