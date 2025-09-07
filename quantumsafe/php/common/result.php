<!-- php\common\result.php -->

<?php
// common/results.php — Consolidated results viewer for common_analysis
// Requirements: ../config.php with $conn (mysqli)
// Table expected: common_analysis(id, feature, input_value, score, verdict, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../config.php"; // $conn

function clean($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- Filters ----
$feature  = isset($_GET['feature']) ? $_GET['feature'] : 'all'; // screenshot|invoice|qr|chatbot|microfraud|all
$fromDate = isset($_GET['from']) ? $_GET['from'] : '';
$toDate   = isset($_GET['to']) ? $_GET['to'] : '';
$order    = isset($_GET['order']) ? $_GET['order'] : 'new'; // new|old|hi|lo
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 50;
$offset   = ($page-1)*$perPage;

// ---- Build WHERE ----
$where = [];
$params = [];
$types = '';

if ($feature !== 'all') { $where[] = "feature = ?"; $params[] = $feature; $types .= 's'; }
if ($fromDate !== '')   { $where[] = "created_at >= ?"; $params[] = $fromDate." 00:00:00"; $types .= 's'; }
if ($toDate !== '')     { $where[] = "created_at <= ?"; $params[] = $toDate." 23:59:59"; $types .= 's'; }

$whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

// ---- Order ----
switch ($order) {
  case 'old': $orderSql = "ORDER BY created_at ASC"; break;
  case 'hi' : $orderSql = "ORDER BY score DESC, created_at DESC"; break;
  case 'lo' : $orderSql = "ORDER BY score ASC, created_at DESC"; break;
  default   : $orderSql = "ORDER BY created_at DESC";
}

// ---- Counts for header chips ----
$counts = ['SAFE'=>0,'SUSPICIOUS'=>0,'FRAUD'=>0,'TOTAL'=>0];
$countSql = "SELECT verdict, COUNT(*) c FROM common_analysis $whereSql GROUP BY verdict";
if ($stmt = $conn->prepare($countSql)) {
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $rs = $stmt->get_result();
  while ($row = $rs->fetch_assoc()) {
    $v = strtoupper($row['verdict']);
    if (isset($counts[$v])) $counts[$v] = (int)$row['c'];
    $counts['TOTAL'] += (int)$row['c'];
  }
  $stmt->close();
}

// ---- Export CSV ----
if (isset($_GET['export']) && $_GET['export'] == '1') {
  $expSql = "SELECT created_at, feature, verdict, score, input_value FROM common_analysis $whereSql $orderSql";
  if ($stmt = $conn->prepare($expSql)) {
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="quantumsafe_results.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['created_at','feature','verdict','score','input_value']);
    while ($row = $rs->fetch_assoc()) {
      fputcsv($out, $row);
    }
    fclose($out);
    exit;
  }
}

// ---- Total for pagination ----
$totalSql = "SELECT COUNT(*) c FROM common_analysis $whereSql";
$totalRows = 0;
if ($stmt = $conn->prepare($totalSql)) {
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $stmt->bind_result($totalRows);
  $stmt->fetch();
  $stmt->close();
}
$totalPages = max(1, ceil($totalRows / $perPage));

// ---- Fetch page ----
$listSql = "SELECT id, feature, input_value, score, verdict, created_at FROM common_analysis $whereSql $orderSql LIMIT ? OFFSET ?";
if ($types) { $typesPage = $types.'ii'; $paramsPage = array_merge($params, [$perPage, $offset]); }
else { $typesPage = 'ii'; $paramsPage = [$perPage, $offset]; }

$rows = [];
if ($stmt = $conn->prepare($listSql)) {
  $stmt->bind_param($typesPage, ...$paramsPage);
  $stmt->execute();
  $rs = $stmt->get_result();
  while ($row = $rs->fetch_assoc()) $rows[] = $row;
  $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>QuantumSafe – Results</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{
  --bg:#0b1022; --glass:rgba(255,255,255,.06); --border:rgba(255,255,255,.12);
  --txt:#e6edf3; --muted:#93a0b4; --safe:#22c55e; --warn:#f59e0b; --fraud:#ef4444; --accent:#3b82f6;
}
*{box-sizing:border-box}
body{margin:0;background:
  radial-gradient(1200px 800px at 20% -10%, #1a2c64 0%, var(--bg) 55%),
  linear-gradient(180deg, #0a0f24, var(--bg));
  color:var(--txt);font-family:ui-sans-serif,system-ui,Segoe UI,Roboto,Arial}
.container{max-width:1200px;margin:32px auto;padding:0 16px}
.card{background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.02));
  -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px);
  border:1px solid var(--border); border-radius:18px; padding:18px; margin-bottom:16px}
h1{margin:0 0 6px;font-size:26px}
.sub{margin:0;color:var(--muted)}
.grid{display:grid;gap:12px}
@media(min-width:900px){.grid-2{grid-template-columns: 1.2fr .8fr}}
label{font-size:12px;color:var(--muted)}
select,input,button{width:100%;padding:10px;border-radius:12px;border:1px solid var(--border);background:var(--glass);color:var(--txt)}
button{cursor:pointer}button:hover{background:rgba(255,255,255,.12)}
.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:10px}
.kpi{padding:12px;border-radius:12px;background:var(--glass);border:1px solid var(--border);text-align:center}
.kpi strong{font-size:18px;display:block}
.badge{padding:4px 10px;border-radius:999px;border:1px solid var(--border);font-weight:700;font-size:12px}
.badge-safe{color:var(--safe);border-color:rgba(34,197,94,.35);background:rgba(34,197,94,.08)}
.badge-warn{color:var(--warn);border-color:rgba(245,158,11,.35);background:rgba(245,158,11,.08)}
.badge-fraud{color:var(--fraud);border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.10)}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{padding:10px;border-bottom:1px solid var(--border);text-align:left;font-size:14px}
.meta{font-size:12px;color:var(--muted)}
.footer{display:flex;gap:10px;justify-content:space-between;align-items:center;margin-top:10px}
.pager a, .pager span{padding:8px 12px;border:1px solid var(--border);border-radius:10px;text-decoration:none;color:var(--txt);background:var(--glass);margin-right:6px}
.pager a:hover{background:rgba(255,255,255,.12)}
a.back{padding:10px 14px;border-radius:12px;border:1px solid var(--border);background:var(--glass);text-decoration:none;color:var(--txt)}
a.back:hover{background:rgba(255,255,255,.12)}
.actions{display:flex;gap:8px}
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <h1>Results</h1>
    <p class="sub">View, filter, and export past analyses from Chatbot, Screenshot/Invoice/QR, and Micro-Fraud modules.</p>
    <div class="kpis">
      <div class="kpi"><span class="meta">Total</span><strong><?php echo (int)$counts['TOTAL']; ?></strong></div>
      <div class="kpi"><span class="meta">Safe</span><strong style="color:var(--safe)"><?php echo (int)$counts['SAFE']; ?></strong></div>
      <div class="kpi"><span class="meta">Suspicious</span><strong style="color:var(--warn)"><?php echo (int)$counts['SUSPICIOUS']; ?></strong></div>
      <div class="kpi"><span class="meta">Fraud</span><strong style="color:var(--fraud)"><?php echo (int)$counts['FRAUD']; ?></strong></div>
    </div>
  </div>

  <form class="card">
    <div class="grid" style="grid-template-columns: repeat(5, 1fr); gap:10px">
      <div>
        <label>Feature</label>
        <select name="feature">
          <?php
            $opts = ['all'=>'All','screenshot'=>'Screenshot','invoice'=>'Invoice','qr'=>'QR','chatbot'=>'Chatbot','microfraud'=>'Micro-Fraud'];
            foreach ($opts as $val=>$label) {
              $sel = ($feature===$val)?'selected':'';
              echo "<option value=\"".clean($val)."\" $sel>".clean($label)."</option>";
            }
          ?>
        </select>
      </div>
      <div>
        <label>From</label>
        <input type="date" name="from" value="<?php echo clean($fromDate); ?>">
      </div>
      <div>
        <label>To</label>
        <input type="date" name="to" value="<?php echo clean($toDate); ?>">
      </div>
      <div>
        <label>Sort</label>
        <select name="order">
          <option value="new" <?php echo $order==='new'?'selected':''; ?>>Newest</option>
          <option value="old" <?php echo $order==='old'?'selected':''; ?>>Oldest</option>
          <option value="hi"  <?php echo $order==='hi'?'selected':''; ?>>Highest Score</option>
          <option value="lo"  <?php echo $order==='lo'?'selected':''; ?>>Lowest Score</option>
        </select>
      </div>
      <div style="display:flex;gap:8px;align-items:end">
        <button type="submit">Apply</button>
        <a class="back" href="?<?php
          // keep filters but add export=1
          $q = $_GET; $q['export']=1; echo clean(http_build_query($q));
        ?>">Export CSV</a>
      </div>
    </div>
  </form>

  <div class="card">
    <table>
      <tr>
        <th>When</th>
        <th>Feature</th>
        <th>Verdict</th>
        <th>Score</th>
        <th>Input (snippet)</th>
      </tr>
      <?php if (!$rows): ?>
        <tr><td colspan="5" class="meta">No records match your filters.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r):
          $badge = $r['verdict']==='SAFE'?'badge-safe':($r['verdict']==='SUSPICIOUS'?'badge-warn':'badge-fraud');
          $snippet = mb_strimwidth($r['input_value'] ?? '', 0, 120, '…');
        ?>
          <tr>
            <td class="meta"><?php echo clean($r['created_at']); ?></td>
            <td><?php echo ucfirst(clean($r['feature'])); ?></td>
            <td><span class="badge <?php echo $badge; ?>"><?php echo clean($r['verdict']); ?></span></td>
            <td><?php echo (int)$r['score']; ?></td>
            <td class="meta"><?php echo clean($snippet); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </table>

    <div class="footer">
      <a href="index.php" class="back">← Back to Common Menu</a>
      <div class="pager">
        <?php
          // Build base query without page
          $q = $_GET; unset($q['page']);
          $base = '?'.http_build_query($q);
          if ($page>1) echo '<a href="'.$base.'&page='.($page-1).'">Prev</a>';
          echo '<span>Page '.$page.' / '.$totalPages.'</span>';
          if ($page<$totalPages) echo '<a href="'.$base.'&page='.($page+1).'">Next</a>';
        ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
