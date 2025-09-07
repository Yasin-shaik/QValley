<!-- php\bank\dashboard.php -->

<?php
// bank/dashboard.php
// Requires: ../config.php and DB schema from db.sql

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';

// Ensure uploads dir
$uploadDir = __DIR__ . '/../uploads';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }

// Helpers
function clean($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function toNumber($v){ return is_numeric($v) ? (float)$v : 0.0; }
function nowTs(){ return date('Y-m-d H:i:s'); }

// --- Mock ML Risk Engine (used only if CSV lacks score/verdict/reasons/action) ---
function analyze_row($row) {
    $account = trim($row['account'] ?? '');
    $payee   = strtolower(trim($row['payee'] ?? ''));
    $amount  = toNumber($row['amount'] ?? 0);
    $ts      = trim($row['ts'] ?? '') ?: nowTs();

    // Simulated probability-like score (0–100)
    $score = rand(5, 95);

    // Verdict thresholds
    if ($score >= 70) { $verdict = "FRAUD"; $badge = "badge-fraud"; }
    elseif ($score >= 40) { $verdict = "SUSPICIOUS"; $badge = "badge-warn"; }
    else { $verdict = "SAFE"; $badge = "badge-safe"; }

    // Reasons pool (mimic SHAP explanations)
    $reasonPool = [
        "High transaction amount",
        "Suspicious payee pattern",
        "Unusual time of transfer",
        "Repeated payments detected",
        "New/unknown payee",
        "Risky invoice-like pattern",
        "Potential phishing QR/invoice"
    ];
    shuffle($reasonPool);
    $reasons = array_slice($reasonPool, 0, rand(1,3));

    // Suggested action
    if ($verdict === "FRAUD") {
        $action = "HOLD & VERIFY KYC • Block payee • Call customer";
    } elseif ($verdict === "SUSPICIOUS") {
        $action = "Manual review • OTP confirm • Call-back verification";
    } else {
        $action = "Allow • Monitor";
    }

    return [
        'account' => $account,
        'payee'   => $payee,
        'amount'  => $amount,
        'ts'      => $ts,
        'score'   => (int)$score,
        'verdict' => $verdict,
        'badge'   => $badge,
        'reasons' => $reasons,
        'action'  => $action
    ];
}

// Process upload
$rows = [];
$summary = ['SAFE'=>0,'SUSPICIOUS'=>0,'FRAUD'=>0];
$filenameSaved = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    $file = $_FILES['csv'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $tmp  = $file['tmp_name'];
        $name = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/','_', $file['name']);
        $dest = $uploadDir . '/' . $name;
        if (move_uploaded_file($tmp, $dest)) {
            $filenameSaved = $name;
            // Read CSV
            $fh = fopen($dest, 'r');
            if ($fh !== false) {
                $headers = null;
                while (($data = fgetcsv($fh)) !== false) {
                    if (!$headers) { $headers = array_map('strtolower', $data); continue; }
                    $row = array_combine($headers, $data);

                    // Detect if CSV already has model outputs
                    $hasScore   = array_key_exists('score',   $row);
                    $hasVerdict = array_key_exists('verdict', $row);
                    $hasReasons = array_key_exists('reasons', $row);
                    $hasAction  = array_key_exists('action',  $row);

                    if ($hasScore && $hasVerdict && $hasReasons && $hasAction) {
                        // Use CSV values as-is
                        $verdict = strtoupper(trim($row['verdict'] ?? 'SAFE'));
                        $badge = ($verdict==='FRAUD') ? 'badge-fraud' : (($verdict==='SUSPICIOUS') ? 'badge-warn' : 'badge-safe');

                        // reasons: support either '•' or ',' separators
                        $reasonsRaw = trim($row['reasons'] ?? '');
                        if (strpos($reasonsRaw, '•') !== false) {
                            $reasonsArr = array_map('trim', explode('•', $reasonsRaw));
                        } else {
                            $reasonsArr = array_map('trim', explode(',', $reasonsRaw));
                        }

                        $res = [
                            'account' => trim($row['account'] ?? ''),
                            'payee'   => strtolower(trim($row['payee'] ?? '')),
                            'amount'  => toNumber($row['amount'] ?? 0),
                            'ts'      => trim($row['ts'] ?? '') ?: nowTs(),
                            'score'   => (int)($row['score'] ?? 0),
                            'verdict' => in_array($verdict, ['SAFE','SUSPICIOUS','FRAUD']) ? $verdict : 'SAFE',
                            'badge'   => $badge,
                            'reasons' => $reasonsArr,
                            'action'  => trim($row['action'] ?? 'Allow • Monitor'),
                        ];
                    } else {
                        // Fallback to mock analyzer
                        $res = analyze_row($row);
                    }

                    $rows[] = $res;
                    if (isset($summary[$res['verdict']])) { $summary[$res['verdict']]++; }

                    // Save to DB (store displayed values)
                    global $conn;
                    $stmt = $conn->prepare("INSERT INTO bank_transactions (account, payee, amount, score, verdict, created_at) VALUES (?,?,?,?,?,?)");
                    $created = $res['ts'] ?: nowTs();
                    $stmt->bind_param("ssdiss", $res['account'], $res['payee'], $res['amount'], $res['score'], $res['verdict'], $created);
                    $stmt->execute();
                }
                fclose($fh);
            }
        }
    }
}

// Export CSV (analyzed)
if (isset($_GET['export']) && $_GET['export'] === '1') {
    $res = $conn->query("SELECT account, payee, amount, score, verdict, created_at FROM bank_transactions ORDER BY id DESC LIMIT 1000");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="quantumsafe_analysis_export.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['account','payee','amount','score','verdict','created_at']);
    while($r = $res->fetch_assoc()){
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>QuantumSafe – Bank/FinTech Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
:root{
  --bg:#0b1022; --bg2:#0f1630; --glass:rgba(255,255,255,0.06);
  --border:rgba(255,255,255,0.12); --txt:#e6edf3; --muted:#9aa4b2;
  --safe:#22c55e; --warn:#f59e0b; --fraud:#ef4444; --accent:#3b82f6;
}
*{box-sizing:border-box}
body{margin:0;background:radial-gradient(1200px 800px at 20% -10%, #15224a 0%, var(--bg) 55%), linear-gradient(180deg, #0a0f24, var(--bg));
     font-family:ui-sans-serif,system-ui,Segoe UI,Roboto,Arial;color:var(--txt)}
.container{max-width:1200px;margin:32px auto;padding:0 16px}
h1{margin:0 0 6px;font-size:28px}
.sub{margin:0 0 18px;color:var(--muted)}
.card{background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.02));
      -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px);
      border:1px solid var(--border); border-radius:18px; padding:18px}
.grid{display:grid;gap:16px}
@media(min-width:900px){.grid{grid-template-columns:1.2fr .8fr}}
input[type=file],button,.btn{width:100%;padding:12px 14px;border-radius:12px;border:1px solid var(--border);
     background:var(--glass);color:var(--txt)}
button,.btn{cursor:pointer}
button:hover,.btn:hover{background:rgba(255,255,255,0.12)}
.row{display:flex;gap:10px;flex-wrap:wrap}
.kpi{display:flex;align-items:center;gap:10px;background:var(--glass);border:1px solid var(--border);
     border-radius:12px;padding:12px 14px}
.kpi .num{font-weight:800}
.table{width:100%;border-collapse:separate;border-spacing:0 8px;margin-top:12px}
.table th{font-size:13px;color:var(--muted);text-align:left;padding:6px 10px}
.table td{background:var(--glass);border:1px solid var(--border);padding:10px;border-left:none;border-right:none}
.table tr{border-radius:12px}
.badge{padding:4px 10px;border-radius:999px;font-weight:700;font-size:12px;border:1px solid var(--border)}
.badge-safe{color:var(--safe);border-color:rgba(34,197,94,0.35);background:rgba(34,197,94,0.08)}
.badge-warn{color:var(--warn);border-color:rgba(245,158,11,0.35);background:rgba(245,158,11,0.08)}
.badge-fraud{color:var(--fraud);border-color:rgba(239,68,68,0.35);background:rgba(239,68,68,0.10)}
.action{font-size:12px;color:var(--muted)}
.footer{display:flex;gap:12px;margin-top:12px}
.small{font-size:12px;color:var(--muted)}
hr{border:none;border-top:1px solid var(--border);opacity:.7;margin:14px 0}
.note{font-size:12px;color:var(--muted);margin-top:6px}
.btn{display:inline-block;text-align:center;text-decoration:none}
.right{margin-left:auto}
</style>
</head>
<body>
<div class="container">
  <div class="card" style="margin-bottom:16px">
    <h1>QuantumSafe – Bank / FinTech Dashboard</h1>
    <p class="sub">Upload CSV → get instant risk analysis, alerts & actions</p>
    <form method="post" enctype="multipart/form-data" class="grid">
      <div class="card">
        <h3 style="margin:0 0 10px">Upload Transactions (CSV)</h3>
        <input type="file" name="csv" accept=".csv" required>
        <p class="note">Accepted formats:<br>
          <code>account,payee,amount,ts</code> (engine will simulate score) <br>
          or<br>
          <code>account,payee,amount,score,verdict,reasons,action,ts</code> (uses provided outputs)
        </p>
        <div class="footer">
          <button type="submit">Analyze CSV</button>
          <a class="btn right" href="?export=1">Export Latest Results</a>
        </div>
      </div>

      <div class="card">
        <h3 style="margin:0 0 10px">Summary</h3>
        <div class="row">
          <div class="kpi"><span>SAFE:</span><span class="num"><?php echo (int)$summary['SAFE']; ?></span></div>
          <div class="kpi"><span>SUSPICIOUS:</span><span class="num"><?php echo (int)$summary['SUSPICIOUS']; ?></span></div>
          <div class="kpi"><span>FRAUD:</span><span class="num"><?php echo (int)$summary['FRAUD']; ?></span></div>
        </div>
        <?php if ($filenameSaved): ?>
          <hr>
          <div class="small">File stored: <code><?php echo clean($filenameSaved); ?></code></div>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h3 style="margin:0 0 10px">Analysis Dashboard</h3>
    <table class="table">
      <thead>
        <tr>
          <th>Account</th>
          <th>Payee</th>
          <th>Amount (₹)</th>
          <th>Score</th>
          <th>Status</th>
          <th>Reasons</th>
          <th>Suggested Action</th>
          <th>Time</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($rows)): ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo clean($r['account']); ?></td>
              <td><?php echo clean($r['payee']); ?></td>
              <td><?php echo number_format($r['amount'], 2); ?></td>
              <td><?php echo (int)$r['score']; ?></td>
              <td><span class="badge <?php echo clean($r['badge']); ?>"><?php echo clean($r['verdict']); ?></span></td>
              <td><?php echo clean(implode(' • ', $r['reasons'])); ?></td>
              <td class="action"><?php echo clean($r['action']); ?></td>
              <td><?php echo clean($r['ts']); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <?php
          // Show last 25 from DB if no fresh upload
          $res = $conn->query("SELECT account,payee,amount,score,verdict,created_at FROM bank_transactions ORDER BY id DESC LIMIT 25");
          while($row = $res->fetch_assoc()):
            $badge = $row['verdict']==='FRAUD'?'badge-fraud':($row['verdict']==='SUSPICIOUS'?'badge-warn':'badge-safe');
          ?>
            <tr>
              <td><?php echo clean($row['account']); ?></td>
              <td><?php echo clean($row['payee']); ?></td>
              <td><?php echo number_format($row['amount'], 2); ?></td>
              <td><?php echo (int)$row['score']; ?></td>
              <td><span class="badge <?php echo $badge; ?>"><?php echo clean($row['verdict']); ?></span></td>
              <td class="small">—</td>
              <td class="action">—</td>
              <td><?php echo clean($row['created_at']); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
    <p class="note">* If your CSV includes <code>score, verdict, reasons, action</code>, the dashboard uses them directly. Otherwise, a mocked ML engine simulates outputs for demo.</p>
  </div>
</div>
</body>
</html>
