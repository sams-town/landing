<?php
require_once __DIR__ . '/includes/header.php';
$page_title = 'System Settings';
requireAdmin();

$db = pawDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // ====== LOGO UPLOAD HANDLER ======
  if ($action === 'upload_logo' || $action === 'delete_logo') {
    $key = $_POST['logo_key'] ?? ''; // 'logo_navbar' or 'logo_footer'
    if (!in_array($key, ['logo_navbar','logo_footer'])) {
      setFlash('setting','Invalid logo key.','error');
      redirect('settings.php');
    }
    $logosDir = __DIR__ . '/../uploads/logos/';
    if ($action === 'upload_logo' && !empty($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
      $file = $_FILES['logo_file'];
      $maxSize = 2 * 1024 * 1024; // 2 MB
      if ($file['size'] > $maxSize) { setFlash('setting','Logo too large. Max 2MB.','error'); redirect('settings.php'); }
      $allowedExts = ['png','jpg','jpeg','svg','webp','gif'];
      $fileName = strtolower(basename($file['name']));
      $ext = pathinfo($fileName, PATHINFO_EXTENSION);
      if (!in_array($ext, $allowedExts)) { setFlash('setting','Invalid logo type. Allowed: PNG, JPG, SVG, WebP, GIF.','error'); redirect('settings.php'); }
      // Sanitize & unique filename
      $safeName = $key . '_' . uniqid() . '.' . $ext;
      $target = $logosDir . $safeName;
      if (!is_dir($logosDir)) @mkdir($logosDir,0777,true);
      if (move_uploaded_file($file['tmp_name'], $target)) {
        // Delete previous logo if exists (and not empty value)
        $old = getSetting($key, '');
        if (!empty($old) && $old !== $safeName) {
          $oldPath = $logosDir . $old;
          if (is_file($oldPath)) @unlink($oldPath);
        }
        $db->execute("INSERT INTO site_settings (setting_key, setting_value, setting_group) VALUES (?,?,?)
                      ON DUPLICATE KEY UPDATE setting_value = ?", [$key, $safeName, 'general', $safeName]);
        getSetting(null); // invalidate cache
        setFlash('setting',ucwords(str_replace('_',' ',$key)).' uploaded successfully!','success');
      } else {
        setFlash('setting','Upload failed. Check uploads/logos folder is writable.','error');
      }
      redirect('settings.php');
    }
    if ($action === 'delete_logo') {
      $cur = getSetting($key, '');
      if ($cur) {
        $path = $logosDir . $cur;
        if (is_file($path)) @unlink($path);
      }
      $db->execute("UPDATE site_settings SET setting_value = '' WHERE setting_key = ?", [$key]);
      getSetting(null);
      setFlash('setting',ucwords(str_replace('_',' ',$key)).' reset to default SVG logo.','success');
      redirect('settings.php');
    }
  }

  if ($action === 'save_settings') {
    $settings = $_POST['s'] ?? [];
    foreach ($settings as $k => $v) {
      $k = trim($k);
      if (!$k) continue;
      $group = $_POST['g'][$k] ?? 'general';
      $exists = $db->fetch("SELECT id FROM site_settings WHERE setting_key = ?", [$k]);
      if ($exists) {
        $db->execute("UPDATE site_settings SET setting_value = ?, setting_group = ? WHERE setting_key = ?", [$v, $group, $k]);
      } else {
        $db->execute("INSERT INTO site_settings (setting_key, setting_value, setting_group) VALUES (?,?,?)", [$k, $v, $group]);
      }
    }
    getSetting(null);
    setFlash('setting','System settings updated. Pricing engine refreshed!','success');
    redirect('settings.php');
  }

  if ($action === 'add_setting') {
    $k = trim($_POST['new_key'] ?? '');
    $v = $_POST['new_value'] ?? '';
    $g = $_POST['new_group'] ?? 'general';
    if ($k) {
      $db->execute("INSERT INTO site_settings (setting_key, setting_value, setting_group) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value = ?", [$k, $v, $g, $v]);
      setFlash('setting','New setting added.','success');
    }
    redirect('settings.php');
  }

  if ($action === 'create_admin' && !empty($_POST['email'])) {
    $email = trim($_POST['email']);
    $name = trim($_POST['full_name']);
    $pass = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'manager';
    if (!$name || !$email || strlen($pass) < 4) {
      setFlash('setting','Admin creation failed. All fields required, password min 4 chars.','error');
    } else {
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      try {
        $db->execute("INSERT INTO admins (full_name, email, password_hash, role) VALUES (?,?,?,?)", [$name, $email, $hash, $role]);
        setFlash('setting','Staff user created successfully.','success');
      } catch (Throwable $e) {
        setFlash('setting','Could not create admin (email may already exist).','error');
      }
    }
    redirect('settings.php');
  }

  if ($action === 'delete_admin' && !empty($_POST['id']) && ctype_digit($_POST['id'])) {
    if ((int)$_POST['id'] === (int)($_SESSION['admin_id'] ?? 0)) {
      setFlash('setting','You cannot delete your own account!','error');
    } else {
      $db->execute("DELETE FROM admins WHERE id = ?", [(int)$_POST['id']]);
      setFlash('setting','Staff account removed.','info');
    }
    redirect('settings.php');
  }
}

$settings = $db->fetchAll("SELECT * FROM site_settings ORDER BY setting_group, setting_key");
$grouped = [];
foreach($settings as $s) $grouped[$s['setting_group']][] = $s;
$admins = $db->fetchAll("SELECT * FROM admins ORDER BY created_at DESC");
?>

<div class="page-title-row">
  <div class="page-title">
    <h1>System Settings</h1>
    <p>Pricing multipliers, contact info, and staff user management.</p>
  </div>
</div>

<!-- GROUPS TABS -->
<?php
$tabs = [
  'pricing' => ['label' => '💰 Pricing Engine', 'fields' => ['price_weight_multiplier_small','price_weight_multiplier_medium','price_weight_multiplier_large','price_weight_multiplier_giant','price_weight_multiplier_brachy']],
  'contact' => ['label' => '📞 Contact Info', 'fields' => ['whatsapp_number','wechat_id','telegram_username','contact_email','office_address','site_name']],
  'social'  => ['label' => '📱 Social Media URLs', 'fields' => ['social_instagram','social_tiktok','social_facebook','social_youtube','social_linkedin','social_twitter']],
  'general' => ['label' => '⚙️ Other Settings', 'fields' => []],
];
?>

<form method="POST" class="mb-6">
  <input type="hidden" name="action" value="save_settings">
  <?php foreach($tabs as $groupKey => $tab): ?>
    <div class="card card-pad mb-5">
      <h2 class="font-display text-2xl font-semibold text-navy mb-5"><?=$tab['label']?></h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <?php
          // Display existing settings for this group + any required in the tab fields list
          $displayed = [];
          $items = $grouped[$groupKey] ?? [];
          foreach($items as $s) { $displayed[] = $s['setting_key']; renderSettingField($s); }
          // Any missing tab fields
          foreach($tab['fields'] as $f) {
            if (!in_array($f, $displayed)) {
              $fake = ['setting_key'=>$f,'setting_value'=>'','setting_group'=>$groupKey];
              renderSettingField($fake);
            }
          }
          // If group is 'general' and has extra fields from DB, they're already shown above
        ?>
      </div>
      <div class="flex justify-end"><button class="btn btn-primary">💾 Save <?=ucfirst($groupKey)?> Settings</button></div>
    </div>
  <?php endforeach; ?>
</form>

<?php
function renderSettingField($s) {
  $k = $s['setting_key'];
  $labelRaw = str_replace('_',' ',$k);
  $label = ucwords($labelRaw);
  $v = $s['setting_value'] ?? '';
  $g = $s['setting_group'] ?? 'general';

  // Tentukan type input berdasarkan key
  $type = 'text';
  $isTextarea = false;
  $placeholder = '';
  $hint = '';
  $step = 'any';

  if (stripos($k, 'email') !== false) {
    $type = 'email';
    $placeholder = 'hello@example.com';
  } elseif (str_starts_with($k, 'social_')) {
    $type = 'url';
    $socialMap = [
      'social_instagram' => ['https://instagram.com/username_anda', 'Isi link lengkap profile Instagram. Contoh: https://instagram.com/beethovenpets'],
      'social_tiktok'    => ['https://tiktok.com/@username_anda', 'Isi link lengkap profile TikTok. Contoh: https://tiktok.com/@beethovenpets'],
      'social_facebook'  => ['https://facebook.com/username_anda', 'Isi link lengkap halaman Facebook. Contoh: https://facebook.com/beethovenpets'],
      'social_youtube'   => ['https://youtube.com/@username_anda', 'Isi link channel YouTube. Contoh: https://youtube.com/@beethovenpets'],
      'social_linkedin'  => ['https://linkedin.com/company/company_anda', 'Isi link LinkedIn Company Page. Contoh: https://linkedin.com/company/beethovenpets'],
      'social_twitter'   => ['https://x.com/username_anda', 'Isi link profile X/Twitter. Contoh: https://x.com/beethovenpets'],
    ];
    if (isset($socialMap[$k])) {
      $placeholder = $socialMap[$k][0];
      $hint = $socialMap[$k][1];
    } else {
      $placeholder = 'https://...';
      $hint = 'Isi link lengkap mulai dengan https://';
    }
  } elseif (stripos($k, 'whatsapp') !== false || stripos($k, 'phone') !== false) {
    $type = 'tel';
    $placeholder = '+62 8xx xxxx xxxx';
    $hint = 'Gunakan format internasional. Contoh: +62 877 7206 6006';
  } elseif (stripos($k, 'telegram') !== false) {
    $placeholder = 'username_anda (tanpa @)';
    $hint = 'Isi username Telegram tanpa @. Contoh: beethovenpets (bukan @beethovenpets)';
  } elseif (stripos($k, 'wechat') !== false) {
    $placeholder = 'WeChat ID';
    $hint = 'Isi WeChat ID murni, tanpa link weixin://. Contoh: Jullycheung';
  } elseif (stripos($k, 'address') !== false) {
    $isTextarea = true;
    $placeholder = "Contoh: Tokopedia Tower Lt. 16 Unit 38\nJl. Prof. Dr. Satrio Kav. 11\nCengkareng, Jakarta Barat 11740";
    $hint = 'Tuliskan alamat lengkap. Boleh ENTER untuk baris baru (menampilkan <br> di website).';
  } elseif (str_contains($k, 'price') || str_contains($k, 'multiplier') || str_contains($k, 'limit') || str_contains($k, 'days')) {
    $type = 'number';
    $step = str_contains($k, 'multiplier') ? '0.1' : '1000';
  }
  ?>
  <input type="hidden" name="g[<?=e($k)?>]" value="<?=e($g)?>">
  <div class="field" style="margin:0">
    <label for="s_<?=e($k)?>"><?=$label?></label>
    <?php if ($isTextarea): ?>
      <textarea
        id="s_<?=e($k)?>"
        class="input"
        name="s[<?=e($k)?>]"
        rows="4"
        placeholder="<?=e($placeholder)?>"
        style="resize:vertical; min-height:96px; line-height:1.55;"><?=e($v)?></textarea>
    <?php else: ?>
      <input
        id="s_<?=e($k)?>"
        class="input"
        type="<?=e($type)?>"
        step="<?=e($step)?>"
        name="s[<?=e($k)?>]"
        placeholder="<?=e($placeholder)?>"
        value="<?=e($v)?>">
    <?php endif; ?>
    <?php if ($hint !== ''): ?>
      <div class="text-[11.5px] text-muted/80 mt-1 leading-snug">💡 <?=$hint?></div>
    <?php endif; ?>
  </div>
  <?php
}
?>

<!-- BRANDING: LOGO UPLOAD -->
<div class="card card-pad mb-5">
  <h2 class="font-display text-2xl font-semibold text-navy mb-5">🎨 Branding · Site Logos</h2>
  <p class="text-muted text-sm mb-6 leading-relaxed">
    Upload your own logo image files. <strong>Accepted formats:</strong> PNG, JPG, SVG, WebP or GIF.
    <strong>Max size:</strong> 2 MB. For best results, upload a <em>transparent-square PNG/SVG</em> around 500&times;500 px.
    If empty, the default inline SVG BeethovenPets logo will be used automatically.
  </p>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <?php
      $logoKeys = [
        'logo_navbar' => [
          'title' => 'Navbar Logo (Light Mode)',
          'desc'  => 'Appears on top navigation bar with cream/white background.',
        ],
        'logo_footer' => [
          'title' => 'Footer Logo (Dark Mode)',
          'desc'  => 'Appears on footer with navy/dark background. Use a white/light version for best contrast.',
        ],
      ];
      $logosDir = __DIR__ . '/../uploads/logos/';
      $_admBase = '';
      if (isset($_SERVER['SCRIPT_NAME']) && is_string($_SERVER['SCRIPT_NAME'])) {
        $_ad = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        // script is at /pet-rec/admin/settings.php → base is /pet-rec
        $_admBase = rtrim(dirname($_ad), '/');
        if ($_admBase === '.' || $_admBase === '\\') $_admBase = '';
      }
      foreach ($logoKeys as $key => $meta):
        $curFile = getSetting($key, '');
        $curUrl = '';
        $curUrlRel = '';
        if (!empty($curFile) && is_file($logosDir . $curFile)) {
          $curUrl = rtrim(SITE_URL,'/') . '/uploads/logos/' . rawurlencode($curFile);
          $curUrlRel = $_admBase . '/uploads/logos/' . rawurlencode($curFile);
        }
    ?>
    <div class="rounded-2xl border border-cream-2 bg-cream/40 p-5">
      <h3 class="font-bold text-navy mb-2"><?=e($meta['title'])?></h3>
      <p class="text-xs text-muted mb-4 leading-relaxed"><?=e($meta['desc'])?></p>

      <!-- Preview -->
      <div class="mb-4 flex items-center gap-4 min-h-[92px] p-4 rounded-xl border-2 border-dashed border-navy/15 bg-white">
        <?php if ($curUrl): ?>
          <div class="flex-shrink-0 w-20 h-20 flex items-center justify-center rounded-xl border border-cream-2 bg-white overflow-hidden">
            <img src="<?=e($curUrl)?>"
                 data-rel="<?=e($curUrlRel)?>"
                 alt="<?=e($meta['title'])?> Preview"
                 class="max-w-full max-h-full object-contain block"
                 onerror="var r=this.getAttribute('data-rel');if(r && !this.getAttribute('data-fb')){this.setAttribute('data-fb','1');this.src=r;}else{this.style.display='none';this.parentElement.innerHTML='<div class=\'text-xs text-red-500 font-semibold px-2 text-center\'>Image broken · check uploads/logos folder</div>';}"
            >
          </div>
          <div class="min-w-0 flex-1">
            <div class="text-[11px] font-mono text-muted truncate mb-1">📄 <?=e($curFile)?></div>
            <div class="text-[11px] text-muted mb-2">
              <?php if (is_file($logosDir.$curFile)): ?>
                Size: <?=number_format(filesize($logosDir.$curFile))?> bytes
              <?php endif; ?>
            </div>
            <form method="POST" onsubmit="return confirm('Reset this logo back to the default inline SVG?');">
              <input type="hidden" name="action" value="delete_logo">
              <input type="hidden" name="logo_key" value="<?=e($key)?>">
              <button type="submit" class="btn btn-outline btn-sm" style="border-color:#FCA5A5;color:#EF4444;">
                🗑 Remove · Use Default SVG
              </button>
            </form>
          </div>
        <?php else: ?>
          <div class="flex-shrink-0 w-20 h-20 flex items-center justify-center rounded-xl border border-dashed border-navy/15 bg-navy/[0.03] text-xs text-muted text-center px-2 leading-tight">
            Default<br>SVG
          </div>
          <div class="text-xs text-muted italic">No custom logo uploaded yet — currently using the built-in BeethovenPets SVG.</div>
        <?php endif; ?>
      </div>

      <!-- Upload form -->
      <form method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-2 items-end">
        <input type="hidden" name="action" value="upload_logo">
        <input type="hidden" name="logo_key" value="<?=e($key)?>">
        <div class="flex-1 min-w-0 w-full">
          <label class="block text-xs font-bold text-navy mb-1.5">Choose image file</label>
          <input type="file" name="logo_file"
                 accept="image/png,image/jpeg,image/svg+xml,image/webp,image/gif"
                 class="input"
                 style="padding:9px 12px;"
                 required>
        </div>
        <button type="submit" class="btn btn-primary w-full sm:w-auto whitespace-nowrap">⬆ Upload</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- CUSTOM SETTING -->
<div class="card card-pad mb-5">
  <h2 class="font-display text-2xl font-semibold text-navy mb-5">➕ Add Custom Setting</h2>
  <form method="POST">
    <input type="hidden" name="action" value="add_setting">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="field" style="margin:0"><label>Key</label><input class="input" name="new_key" placeholder="e.g. tax_percent"></div>
      <div class="field" style="margin:0"><label>Value</label><input class="input" name="new_value"></div>
      <div class="field" style="margin:0"><label>Group</label>
        <select class="select" name="new_group">
          <?php foreach(['general','pricing','contact','payment'] as $g): ?>
            <option value="<?=$g?>"><?=ucfirst($g)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin:0"><label>&nbsp;</label>
        <button class="btn btn-outline" style="height:47px">Create Setting</button>
      </div>
    </div>
  </form>
</div>

<!-- ADMIN USERS -->
<div class="card card-pad mb-5">
  <h2 class="font-display text-2xl font-semibold text-navy mb-5">👤 Staff / Admin Users</h2>

  <div class="mb-8 p-6 rounded-2xl border-2 border-dashed border-navy/15 bg-navy/[0.03]">
    <h3 class="font-bold text-navy mb-4">Create New Staff Account</h3>
    <form method="POST">
      <input type="hidden" name="action" value="create_admin">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="field" style="margin:0"><label>Full Name</label><input class="input" name="full_name" required></div>
        <div class="field" style="margin:0"><label>Email<span class="req" style="color:var(--coral)">*</span></label><input class="input" type="email" name="email" required></div>
        <div class="field" style="margin:0"><label>Temporary Password</label><input class="input" name="password" required></div>
        <div class="field" style="margin:0"><label>Role</label>
          <select class="select" name="role">
            <option value="manager">Manager</option>
            <option value="operator">Operator</option>
            <option value="super_admin">Super Admin</option>
          </select>
        </div>
      </div>
      <div class="flex justify-end mt-2">
        <button class="btn btn-navy">➕ Create Staff Account</button>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>User</th><th>Email</th><th>Role</th><th>Last Login</th><th>Created</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach($admins as $a): ?>
        <tr>
          <td>
            <div class="user-cell" style="min-width:0">
              <div class="avatar avatar-lg" style="background:<?= $a['role']==='super_admin' ? 'var(--coral)' : ($a['role']==='manager' ? 'var(--navy)' : 'var(--teal)') ?>;color:white;">
                <?=strtoupper(substr($a['full_name'],0,1))?>
              </div>
              <div class="min-w-0">
                <div class="name truncate"><?=e($a['full_name'])?></div>
                <div class="sub truncate">ID #<?=(int)$a['id']?></div>
              </div>
            </div>
          </td>
          <td class="font-semibold text-navy text-sm"><?=e($a['email'])?></td>
          <td>
            <span class="chip" style="background:<?= $a['role']==='super_admin' ? '#FF6B4A18;color:#FF6B4A' : ($a['role']==='manager' ? '#E6EEFA;color:var(--navy)' : '#E7F5F3;color:#4FB3A8') ?>">
              <?=ucwords(str_replace('_',' ',$a['role']))?>
            </span>
          </td>
          <td class="text-sm font-semibold text-muted"><?=!empty($a['last_login']) ? formatDateTime($a['last_login']) : 'Never'?></td>
          <td class="text-sm font-semibold text-muted"><?=formatDate($a['created_at'])?></td>
          <td style="text-align:right">
            <?php if ((int)$a['id'] !== (int)($_SESSION['admin_id'] ?? -1)): ?>
            <form method="POST" onsubmit="return confirm('Permanently delete this staff user?')" style="display:inline">
              <input type="hidden" name="action" value="delete_admin">
              <input type="hidden" name="id" value="<?=(int)$a['id']?>">
              <button class="btn btn-outline btn-icon btn-sm confirm-delete" style="border-color:#FCA5A5">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
              </button>
            </form>
            <?php else: ?>
              <span class="chip" style="background:var(--teal);color:white">YOU</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
