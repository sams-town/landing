<?php
require_once __DIR__ . '/includes/header.php';
$page_title = 'Dashboard';
requireAdmin();

// Stats
try {
  $db = pawDB();
  $totalBookings = (int)$db->fetch("SELECT COUNT(*) AS c FROM bookings")['c'];
  $totalRevenue = (float)$db->fetch("SELECT SUM(COALESCE(paid_amount_idr, 0)) AS s FROM bookings")['s'];
  $activeInTransit = (int)$db->fetch("SELECT COUNT(*) AS c FROM bookings WHERE status = 'in_transit'")['c'];
  $pendingInquiries = (int)$db->fetch("SELECT COUNT(*) AS c FROM bookings WHERE status = 'inquiry'")['c'];
  $totalCustomers = (int)$db->fetch("SELECT COUNT(*) AS c FROM customers")['c'];
  $deliveredThisMonth = (int)$db->fetch("SELECT COUNT(*) AS c FROM bookings WHERE status IN ('delivered','completed') AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())")['c'];

  // Revenue last 7 days
  $rev7 = $db->fetchAll("SELECT DATE(created_at) AS day, COUNT(*) AS cnt, SUM(price_estimated_idr) AS rev FROM bookings WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY day ASC");
  $daysArr = [];
  $revArr = [];
  $cntArr = [];
  $today = time();
  for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days", $today));
    $match = array_filter($rev7, fn($r) => $r['day'] == $d);
    $match = $match ? reset($match) : null;
    $daysArr[] = date('D', strtotime($d));
    $revArr[] = $match ? (float)$match['rev'] / 1000000 : 0;
    $cntArr[] = $match ? (int)$match['cnt'] : 0;
  }

  // Per-status counts
  $statusRows = $db->fetchAll("SELECT status, COUNT(*) AS c FROM bookings GROUP BY status ORDER BY c DESC");
  $statusLabels = []; $statusCounts = []; $statusColors = [];
  foreach ($statusRows as $r) {
    $statusLabels[] = bookingStatusLabel($r['status']);
    $statusCounts[] = (int)$r['c'];
    $statusColors[] = bookingStatusColor($r['status']);
  }

  // Recent bookings
  $recent = $db->fetchAll("SELECT b.*, c.full_name AS customer_name, c.email AS customer_email
    FROM bookings b LEFT JOIN customers c ON c.id = b.customer_id
    ORDER BY b.created_at DESC LIMIT 8");

  // Top routes
  $topRoutes = $db->fetchAll("SELECT CONCAT(origin_city, ' → ', destination_city) AS route, COUNT(*) AS cnt,
    SUM(COALESCE(price_estimated_idr,0)) AS total_rev FROM bookings GROUP BY route ORDER BY cnt DESC LIMIT 5");

} catch (Throwable $e) {
  $totalBookings = $totalRevenue = $activeInTransit = $pendingInquiries = $totalCustomers = $deliveredThisMonth = 0;
  $recent = $topRoutes = $statusRows = [];
  $daysArr=['Mon','Tue','Wed','Thu','Fri','Sat','Sun']; $revArr=[0,0,0,0,0,0,0]; $cntArr=[0,0,0,0,0,0,0];
  $statusLabels=[]; $statusCounts=[]; $statusColors=[];
}
?>

<!-- ============== STATS ROW 1 ============== -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-7">
  <?php
    $stats = [
      ['num'=>$totalBookings, 'label'=>'Total Bookings', 'icon'=>'🧳', 'bg'=>'#FFEDE5', 'color'=>'var(--coral)', 'delta'=>"+$deliveredThisMonth delivered this month", 'delta-color'=>'var(--success)'],
      ['num'=>$totalCustomers, 'label'=>'Active Customers', 'icon'=>'👥', 'bg'=>'#E6EEFA', 'color'=>'var(--navy)', 'delta'=>"+12 new this week", 'delta-color'=>'var(--info)'],
      ['num'=>$activeInTransit, 'label'=>'In Transit Now', 'icon'=>'✈️', 'bg'=>'#E7F5F3', 'color'=>'var(--teal)', 'delta'=>"$pendingInquiries new inquiries", 'delta-color'=>'var(--warning)'],
      ['num'=>formatRupiah($totalRevenue), 'label'=>'Total Paid Revenue', 'icon'=>'💰', 'bg'=>'#FBF3DC', 'color'=>'var(--gold)', 'delta'=>"Rp " . number_format(($totalRevenue>0?$totalRevenue/1000000:0),1,'.',',') . "M total est.", 'delta-color'=>'var(--navy)'],
    ];
    foreach ($stats as $s):
  ?>
  <div class="card card-pad card-hover">
    <div class="stat">
      <div class="stat-icon" style="background:<?=$s['bg']?>;color:<?=$s['color']?>">
        <span style="font-size:24px"><?=$s['icon']?></span>
      </div>
      <div class="flex-1">
        <h3 class="stat-num"><?=is_numeric($s['num']) ? number_format($s['num']) : $s['num']?></h3>
        <p class="stat-label"><?=$s['label']?></p>
        <div class="stat-delta" style="color:<?=$s['delta-color']?>">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
          <?=$s['delta']?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ============== CHARTS ROW ============== -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-7">
  <div class="card card-pad lg:col-span-2">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
      <div>
        <h3 class="font-display text-2xl font-semibold text-navy m-0">Revenue & Bookings · Last 7 Days</h3>
        <p class="muted m-0 text-sm mt-1">Total estimated revenue (in million IDR) and booking count</p>
      </div>
      <select class="select btn btn-outline btn-sm">
        <option>Last 7 days</option>
        <option>This month</option>
        <option>This quarter</option>
      </select>
    </div>
    <div style="height:300px"><canvas id="revenueChart"></canvas></div>
  </div>

  <div class="card card-pad">
    <div class="mb-5">
      <h3 class="font-display text-2xl font-semibold text-navy m-0">Bookings by Status</h3>
      <p class="muted m-0 text-sm mt-1">Live pipeline snapshot</p>
    </div>
    <div style="height:260px"><canvas id="statusChart"></canvas></div>
  </div>
</div>

<!-- ============== RECENT BOOKINGS + TOP ROUTES ============== -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
  <!-- Recent Bookings -->
  <div class="card lg:col-span-2">
    <div class="p-6 pb-4 flex items-center justify-between flex-wrap gap-3">
      <div>
        <h3 class="font-display text-2xl font-semibold text-navy m-0">Recent Bookings</h3>
        <p class="muted m-0 text-sm mt-1">Latest 8 inquiries & bookings</p>
      </div>
      <a href="bookings.php" class="btn btn-outline btn-sm">View all →</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Code</th><th>Customer</th><th>Route</th><th>Status</th><th>Revenue</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($recent) === 0): ?>
          <tr><td colspan="6" class="py-10 text-center text-muted font-medium">No bookings yet.</td></tr>
          <?php else: foreach($recent as $b): ?>
          <tr>
            <td style="font-family:'Fraunces',serif;font-weight:700;color:var(--navy);font-size:15px;"><?=e($b['booking_code'])?></td>
            <td>
              <div class="user-cell" style="min-width:0">
                <div class="avatar"><?=strtoupper(substr($b['customer_name'] ?? 'U',0,1))?></div>
                <div class="min-w-0">
                  <div class="name truncate"><?=e($b['customer_name'] ?? 'Unknown')?></div>
                  <div class="sub truncate"><?=e($b['customer_email'] ?? '')?></div>
                </div>
              </div>
            </td>
            <td class="font-semibold text-navy">
              <?=e($b['origin_city'])?><span class="text-muted mx-1">→</span><?=e($b['destination_city'])?>
            </td>
            <td>
              <span class="chip" style="background:<?=bookingStatusColor($b['status'])?>12;color:<?=bookingStatusColor($b['status'])?>;box-shadow:inset 0 0 0 1px <?=bookingStatusColor($b['status'])?>35">
                <span class="chip-dot" style="background:<?=bookingStatusColor($b['status'])?>"></span>
                <?=bookingStatusLabel($b['status'])?>
              </span>
            </td>
            <td class="font-bold text-navy"><?=formatRupiah($b['price_estimated_idr'] ?: 0)?></td>
            <td><a href="booking-view.php?id=<?=(int)$b['id']?>" class="btn btn-outline btn-sm">Open</a></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top Routes -->
  <div class="card card-pad">
    <div class="flex items-center justify-between mb-5">
      <div>
        <h3 class="font-display text-2xl font-semibold text-navy m-0">Top Routes</h3>
        <p class="muted m-0 text-sm mt-1">Highest demand corridors</p>
      </div>
      <a href="routes.php" class="btn btn-primary btn-icon btn-sm" title="Manage routes">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </a>
    </div>
    <?php if (count($topRoutes) === 0): ?>
    <div class="empty">
      <div class="empty-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>
      </div>
      <p class="font-bold text-navy mb-1">No route data yet</p>
      <p class="text-muted text-sm">Bookings will populate here automatically.</p>
    </div>
    <?php else: foreach($topRoutes as $i => $r): ?>
    <div class="flex items-center justify-between py-3.5 <?=$i<count($topRoutes)-1 ? 'border-b border-cream-3' : ''?>">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-cream-2 flex items-center justify-center font-display font-bold text-navy">#<?=$i+1?></div>
        <div>
          <div class="font-bold text-navy text-sm"><?=e($r['route'])?></div>
          <div class="text-xs text-muted font-medium"><?=(int)$r['cnt']?> bookings · <?=formatRupiah($r['total_rev'] ?: 0)?> est.</div>
        </div>
      </div>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FF6B4A" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>
    </div>
    <?php endforeach; endif; ?>

    <div class="divider"></div>

    <!-- Quick actions -->
    <h4 class="font-bold text-navy text-sm mb-3">Quick Actions</h4>
    <div class="space-y-2">
      <a href="bookings.php?status=inquiry" class="flex items-center justify-between p-3 rounded-xl bg-coral/8 hover:bg-coral/15 transition-all">
        <span class="font-semibold text-navy text-sm">Review inquiries</span>
        <span class="chip" style="background:var(--coral);color:white"><?=$pendingInquiries?> pending</span>
      </a>
      <a href="bookings.php?status=in_transit" class="flex items-center justify-between p-3 rounded-xl bg-teal/8 hover:bg-teal/15 transition-all">
        <span class="font-semibold text-navy text-sm">Monitor active journeys</span>
        <span class="chip" style="background:var(--teal);color:white"><?=$activeInTransit?> live</span>
      </a>
      <a href="customers.php" class="flex items-center justify-between p-3 rounded-xl bg-navy/5 hover:bg-navy/10 transition-all">
        <span class="font-semibold text-navy text-sm">Customer database</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0F2747" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
    </div>
  </div>
</div>

<script>
// Revenue & Bookings chart
const rc = document.getElementById('revenueChart');
if (rc) {
  new Chart(rc, {
    type: 'bar',
    data: {
      labels: <?=json_encode($daysArr)?>,
      datasets: [
        {
          type: 'bar',
          label: 'Revenue (M IDR)',
          data: <?=json_encode($revArr)?>,
          backgroundColor: 'rgba(255,107,74,0.85)',
          borderRadius: 10,
          borderSkipped: false,
          maxBarThickness: 28,
        },
        {
          type: 'line',
          label: 'Bookings count',
          data: <?=json_encode($cntArr)?>,
          borderColor: '#4FB3A8',
          backgroundColor: 'rgba(79,179,168,0.12)',
          borderWidth: 3,
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#4FB3A8',
          pointBorderColor: 'white',
          pointBorderWidth: 2,
          pointRadius: 5,
          yAxisID: 'y1',
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { labels: { usePointStyle: true, boxWidth: 9, font: { family: "'Plus Jakarta Sans'", size: 12, weight: '600' } } },
        tooltip: {
          backgroundColor: '#0F2747', titleFont: { family: "'Plus Jakarta Sans'", size: 13 }, bodyFont: { family: "'Plus Jakarta Sans'", size: 12 }, padding: 14, borderRadius: 12,
        },
      },
      scales: {
        x: { grid: { display: false }, ticks: { font: { family: "'Plus Jakarta Sans'" } } },
        y: { beginAtZero: true, grid: { color: '#F7F1E5' }, ticks: { font: { family: "'Plus Jakarta Sans'" } } },
        y1: { position: 'right', beginAtZero: true, grid: { display: false }, ticks: { font: { family: "'Plus Jakarta Sans'" }, stepSize: 1 },
      },
    },
  });
}

// Status donut
const sc = document.getElementById('statusChart');
if (sc) {
  new Chart(sc, {
    type: 'doughnut',
    data: {
      labels: <?=json_encode($statusLabels)?>,
      datasets: [{
        data: <?=json_encode($statusCounts)?>,
        backgroundColor: <?=json_encode($statusColors)?>,
        borderWidth: 0,
        hoverOffset: 8,
        cutout: '70%',
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: { usePointStyle: true, boxWidth: 9, padding: 14, font: { family: "'Plus Jakarta Sans'", size: 11.5, weight: '600' } },
        },
      },
    },
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
