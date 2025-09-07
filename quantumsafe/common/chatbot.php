<?php
// common/chatbot.php — “Should I send?” Transaction Chatbot (PHP-only demo)
// Heuristics + deterministic hash bucket -> Trust Score + Verdict + Reasons.
// Optional DB insert into `common_analysis` if ../config.php exists.

ini_set('display_errors', 1);
error_reporting(E_ALL);

$dbEnabled = false;
if (file_exists(__DIR__ . "/../config.php")) {
  require_once __DIR__ . "/../config.php"; // $conn (mysqli)
  if (isset($conn) && $conn instanceof mysqli) $dbEnabled = true;
}

function clean($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function clamp01($v){ return max(0.0, min(1.0, $v)); }
function toInt($v){ return (int)round($v); }

function analyze_text($text, $upi, $amount, $relationship, $historyCount){
  $reasons = [];
  $risk = 0; // 0..100

  $t = strtolower($text ?? '');
  $u = strtolower($upi ?? '');
  $amt = is_numeric($amount) ? floatval($amount) : 0.0;

  // --- 1) Urgency / Threat / Pressure
  $urgency = ['immediately','urgent','right now','within 5 minutes','last warning','final notice','act now','instant','asap'];
  foreach($urgency as $w){ if (strpos($t,$w)!==false){ $risk+=14; $reasons[]="Urgency language: \"$w\""; break; } }

  $threats = ['penalty','fine','account blocked','order cancelled','legal action','police','blacklist','deactivate','chargeback'];
  foreach($threats as $w){ if (strpos($t,$w)!==false){ $risk+=16; $reasons[]="Threatening consequence: \"$w\""; break; } }

  // --- 2) Impersonation / Authority Claim
  $impersonation = ['bank officer','support team','customer care','electricity board','income tax','kbc','lottery','hr department','company ceo'];
  foreach($impersonation as $w){ if (strpos($t,$w)!==false){ $risk+=12; $reasons[]="Possible impersonation: \"$w\""; break; } }

  // --- 3) Off-platform / Secrecy / Giftcard / Crypto
  $off = ['telegram','whatsapp group','dm me','don\'t tell','keep secret','gift card','steam card','crypto','usdt','binance','wallet address'];
  foreach($off as $w){ if (strpos($t,$w)!==false){ $risk+=12; $reasons[]="Off-platform or secrecy cue: \"$w\""; break; } }

  // --- 4) Suspicious keywords (refund bait, prize)
  $bait = ['refund','bonus','cashback','offer','prize','winner','jackpot','reward','claim'];
  foreach($bait as $w){ if (strpos($t,$w)!==false){ $risk+=10; $reasons[]="Bait keyword: \"$w\""; break; } }

  // --- 5) UPI / Payee handle checks
  if ($u !== ''){
    if (!preg_match('/^[a-z0-9._-]{2,}@[a-z]{2,}$/', $u)) { $risk+=8; $reasons[]="UPI format looks unusual"; }
    if (preg_match('/\d{5,}/', $u)) { $risk+=10; $reasons[]="UPI contains long random digits"; }
    if (preg_match('/(lottery|winner|refund|urgent|help|bonus|gift|offer|crypto|claim|reward|loan|verify|test|demo)/', $u)) {
      $risk+=12; $reasons[]="UPI contains risky token";
    }
  }

  // --- 6) Amount heuristics
  if ($amt <= 0){ $risk+=8; $reasons[]="Invalid or missing amount"; }
  if ($amt >= 20000){ $risk+=16; $reasons[]="High amount (≥ ₹20k)"; }
  if (in_array((int)$amt, [499, 999, 1999, 2999, 1099, 1199])){ $risk+=8; $reasons[]="Psychological pricing pattern (₹$amt)"; }

  // --- 7) Relationship softening
  $rel = strtolower($relationship ?? '');
  if ($rel === 'family' || $rel === 'close friend') { $risk -= 6; $reasons[]="Known relationship reported"; }
  if ($rel === 'unknown' || $rel === 'stranger'){ $risk += 8; $reasons[]="Unknown sender"; }

  // --- 8) History/recurrence pattern
  $h = is_numeric($historyCount) ? intval($historyCount) : 0;
  if ($h >= 3 && $amt > 0 && $amt <= 100){ $risk += 12; $reasons[]="Repeated small requests (micro-fraud pattern)"; }

  // Normalize base risk
  $risk = max(0, min(100, $risk + rand(-2,3)));

  // Heuristic trust
  $heurTrust = 100 - $risk;

  // --- Deterministic bucket by content hash (stable variety)
  $hash = md5(($text ?? '').'|'.($upi ?? '').'|'.($amount ?? '').'|'.($relationship ?? '').'|'.($historyCount ?? ''));
  $bucket = hexdec(substr($hash,0,2)) % 3; // 0 safe,1 suspicious,2 fraud

  if ($bucket === 0)      $targetTrust = rand(85,95);
  elseif ($bucket === 1)  $targetTrust = rand(55,65);
  else                    $targetTrust = rand(15,30);

  // Overrides for very strong signals
  if (preg_match('/(kbc|lottery|winner)/', $t))    $targetTrust = min($targetTrust, rand(12,25));
  if (preg_match('/(legal action|police|blocked)/', $t)) $targetTrust = min($targetTrust, rand(18,35));
  if ($amt >= 50000)                               $targetTrust = min($targetTrust, rand(10,22));
  if ($rel === 'family' && $amt <= 2000 && $risk < 20) $targetTrust = max($targetTrust, rand(80,95));

  // Blend (keeps explanations real, makes outcome stable)
  $alpha = 0.6; // weight of heuristics (0..1)
  $trust = toInt($alpha * $heurTrust + (1-$alpha) * $targetTrust);
  $trust = max(0, min(100, $trust));

  // Verdict
  if     ($trust >= 75) $verdict = 'SAFE';
  elseif ($trust >= 50) $verdict = 'SUSPICIOUS';
  else                  $verdict = 'FRAUD';

  // Suggested action
  if ($verdict === 'FRAUD'){
    $action = "Do NOT pay • Call the person via saved contact • Report/Block";
  } elseif ($verdict === 'SUSPICIOUS'){
    $action = "Verify UPI name in your UPI app • Call back • Ask for invoice/GST";
  } else {
    $action = "Proceed if UPI name matches • Keep proof";
  }

  return [
    'trust'=>$trust,
    'verdict'=>$verdict,
    'reasons'=>$reasons,
    'action'=>$action,
    'hash'=>$hash
  ];
}

$result = null;
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $text = $_POST['message'] ?? '';
  $upi  = $_POST['upi'] ?? '';
  $amount = $_POST['amount'] ?? '';
  $relationship = $_POST['relationship'] ?? '';
  $historyCount = $_POST['history'] ?? '';

  $result = analyze_text($text, $upi, $amount, $relationship, $historyCount);

  // Optional DB save
  if ($dbEnabled){
    $feature = 'chatbot';
    $inputValue = "msg: ".substr($text,0,120)."... | upi: ".$upi." | amt: ".$amount;
    $score = $result['trust'];
    $verdict = $result['verdict'];
    $stmt = $conn->prepare("INSERT INTO common_analysis (feature, input_value, score, verdict) VALUES (?,?,?,?)");
    $stmt->bind_param("ssis", $feature, $inputValue, $score, $verdict);
    $stmt->execute();
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>QuantumSafe – Transaction Chatbot</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{
  --bg:#0b1022; --glass:rgba(255,255,255,.06); --border:rgba(255,255,255,.12);
  --txt:#e6edf3; --muted:#93a0b4; --safe:#22c55e; --warn:#f59e0b; --fraud:#ef4444;
}
*{box-sizing:border-box}
body{margin:0;background:
  radial-gradient(1200px 800px at 20% -10%, #1a2c64 0%, var(--bg) 55%),
  linear-gradient(180deg, #0a0f24, var(--bg));
  color:var(--txt);font-family:ui-sans-serif,system-ui,Segoe UI,Roboto,Arial}
.container{max-width:1000px;margin:32px auto;padding:0 16px}
.card{background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.02));
  -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px);
  border:1px solid var(--border); border-radius:18px; padding:18px; margin-bottom:16px}
h1{margin:0 0 6px;font-size:26px}
.sub{margin:0 0 16px;color:var(--muted)}
.grid{display:grid;gap:16px}
@media(min-width:900px){.grid{grid-template-columns:1.2fr .8fr}}
input,textarea,select,button{width:100%;padding:12px;border-radius:12px;border:1px solid var(--border);background:var(--glass);color:var(--txt)}
textarea{min-height:140px;resize:vertical}
button{cursor:pointer} button:hover{background:rgba(255,255,255,.12)}
.kv{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.badge{padding:4px 10px;border-radius:999px;border:1px solid var(--border);font-weight:700;font-size:12px}
.badge-safe{color:var(--safe);border-color:rgba(34,197,94,.35);background:rgba(34,197,94,.08)}
.badge-warn{color:var(--warn);border-color:rgba(245,158,11,.35);background:rgba(245,158,11,.08)}
.badge-fraud{color:var(--fraud);border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.10)}
.meta{font-size:12px;color:var(--muted)}
.reasons{font-size:13px;line-height:1.55;margin-top:8px}
.footer{display:flex;gap:12px;margin-top:10px}
.back{color:var(--txt);text-decoration:none;border:1px solid var(--border);padding:10px 14px;border-radius:12px;background:var(--glass)}
.back:hover{background:rgba(255,255,255,.12)}
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <h1>“Should I send?” – Transaction Chatbot</h1>
    <p class="sub">Paste the message you received and details below. We’ll return a Trust Score with a clear recommendation.</p>
  </div>

  <form method="post" class="grid">
    <div class="card">
      <h3 style="margin:0 0 8px">Message you received</h3>
      <textarea name="message" placeholder="Example: Urgent! Pay ₹999 to refund@upi or your order will be cancelled."><?php echo isset($_POST['message'])?clean($_POST['message']):''; ?></textarea>
      <div class="kv" style="margin-top:10px">
        <div>
          <label class="meta">Amount (₹)</label>
          <input type="number" name="amount" step="0.01" placeholder="e.g., 999" value="<?php echo isset($_POST['amount'])?clean($_POST['amount']):''; ?>">
        </div>
        <div>
          <label class="meta">UPI / Payee</label>
          <input type="text" name="upi" placeholder="name@bank (optional)" value="<?php echo isset($_POST['upi'])?clean($_POST['upi']):''; ?>">
        </div>
      </div>
      <div class="kv" style="margin-top:10px">
        <div>
          <label class="meta">Relationship</label>
          <select name="relationship">
            <?php
              $opts = ['unknown','vendor','colleague','close friend','family'];
              $cur = $_POST['relationship'] ?? 'unknown';
              foreach ($opts as $o){
                $sel = ($cur===$o)?'selected':'';
                echo "<option value=\"".clean($o)."\" $sel>".ucfirst($o)."</option>";
              }
            ?>
          </select>
        </div>
        <div>
          <label class="meta">Times they’ve asked recently</label>
          <input type="number" name="history" min="0" placeholder="e.g., 0" value="<?php echo isset($_POST['history'])?clean($_POST['history']):''; ?>">
        </div>
      </div>
      <div style="margin-top:12px">
        <button type="submit">Analyze</button>
      </div>
    </div>

    <div class="card">
      <h3 style="margin:0 0 8px">Tips</h3>
      <ul class="meta" style="margin:0 0 8px; padding-left:18px">
        <li>Never pay from a link sent on chat. Confirm UPI name inside your UPI app.</li>
        <li>Beware urgency, threats, refunds & prize claims.</li>
        <li>Call the person using your saved contact, not the number in the message.</li>
      </ul>
      <a class="back" href="index.php">← Back to Common Menu</a>
    </div>
  </form>

  <?php if ($result): ?>
    <div class="card">
      <h3 style="margin:0 0 8px">Result</h3>
      <?php
        $badge = $result['verdict']==='SAFE'?'badge-safe':($result['verdict']==='SUSPICIOUS'?'badge-warn':'badge-fraud');
      ?>
      <div class="meta">
        Trust Score: <strong><?php echo (int)$result['trust']; ?></strong>
        &nbsp;|&nbsp; Verdict:
        <span class="badge <?php echo $badge; ?>"><?php echo clean($result['verdict']); ?></span>
      </div>
      <?php if (!empty($_POST['message']) || !empty($_POST['upi']) || !empty($_POST['amount'])): ?>
        <div class="meta" style="margin-top:6px">
          <strong>You entered:</strong>
          <?php if (!empty($_POST['amount'])) echo "₹".clean($_POST['amount'])." • "; ?>
          <?php if (!empty($_POST['upi'])) echo clean($_POST['upi'])." • "; ?>
          “<?php echo clean(mb_strimwidth($_POST['message'],0,120,'…')); ?>”
        </div>
      <?php endif; ?>
      <?php if (!empty($result['reasons'])): ?>
        <div class="reasons"><strong>Why:</strong> <?php echo clean(implode(' • ', $result['reasons'])); ?></div>
      <?php endif; ?>
      <div class="reasons"><strong>Suggested Action:</strong> <?php echo clean($result['action']); ?></div>
      <div class="meta" style="margin-top:6px">Ref: <?php echo substr($result['hash'],0,8); ?></div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
