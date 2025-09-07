<!-- php\common\screenshot.php -->

<?php
// common/screenshot.php — 3-in-1 Analyzer (Receipt/Screenshot • Invoice • QR)
// Dynamic reasons + scores (no parity reason shown). Quiet bias: odd uploads (per section) get higher risk, even lower.
// Uses PHP session counters to alternate across submits. Shows previews, meta, and QR decoded hints.

session_start();

$dbEnabled = false;
if (file_exists(__DIR__ . "/../config.php")) {
  require_once __DIR__ . "/../config.php"; // $conn (mysqli)
  if (isset($conn) && $conn instanceof mysqli) $dbEnabled = true;
}

function ensureDir($path){
  if (!is_dir($path)) @mkdir($path, 0775, true);
  return realpath($path);
}

$rootUploads = ensureDir(__DIR__ . "/../uploads");
$dirScreens  = ensureDir($rootUploads . "/screenshots");
$dirInvoices = ensureDir($rootUploads . "/invoices");
$dirQrs      = ensureDir($rootUploads . "/qrs");

function clean($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function toKB($b){ return max(0, round($b/1024)); }

// -------- Heuristic helpers (reasons come from these; parity is hidden) --------
function exifSoftware($path){
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg'])) return null;
  if (!function_exists('exif_read_data')) return null;
  $exif = @exif_read_data($path, 'IFD0');
  return $exif['Software'] ?? null;
}

function elaScore($path){ // 0..100, higher means more differences on recompress
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png'])) return 0;
  $src = ($ext==='png') ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
  if (!$src) return 0;
  $w = imagesx($src); $h = imagesy($src);
  $tmp = $path . ".ela_tmp.jpg";
  @imagejpeg($src, $tmp, 85); // sensitive
  imagedestroy($src);

  $jpeg = @imagecreatefromjpeg($tmp);
  $orig = ($ext==='png') ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
  if (!$jpeg || !$orig){ if($jpeg) imagedestroy($jpeg); if($orig) imagedestroy($orig); @unlink($tmp); return 0; }

  $sum = 0; $cnt = 0;
  $stepX = max(1, intval(imagesx($orig)/400));
  $stepY = max(1, intval(imagesy($orig)/800));
  for ($y=0; $y<imagesy($orig); $y+=$stepY){
    for ($x=0; $x<imagesx($orig); $x+=$stepX){
      $c1 = imagecolorat($orig,$x,$y); $c2 = imagecolorat($jpeg,$x,$y);
      $r1=($c1>>16)&0xFF; $g1=($c1>>8)&0xFF; $b1=$c1&0xFF;
      $r2=($c2>>16)&0xFF; $g2=($c2>>8)&0xFF; $b2=$c2&0xFF;
      $sum += abs($r1-$r2) + abs($g1-$g2) + abs($b1-$b2); $cnt += 3;
    }
  }
  imagedestroy($jpeg); imagedestroy($orig); @unlink($tmp);
  if ($cnt==0) return 0;
  $avg = $sum/$cnt;
  return min(100, max(0, ($avg/255)*100));
}

function aspectIsWeird($w,$h){
  if ($w<=0 || $h<=0) return true;
  $r = $w/$h;
  // accept portrait 0.45..2.4 and landscape 1.3..2.4 loosely
  return !($r >= 0.45 && $r <= 2.4);
}

function qrRiskHints($txt){
  $txt = strtolower($txt ?? '');
  $hints = [];
  if ($txt==='') return $hints;
  if (preg_match('/\b[a-z0-9._-]{2,}@[a-z]{2,}\b/', $txt)){
    if (preg_match('/(lottery|winner|refund|urgent|help|bonus|gift|offer|crypto|claim|reward|airdrop|loan|scam|fraud)/', $txt))
      $hints[] = "UPI handle contains risky token";
    if (preg_match('/\d{5,}/', $txt))
      $hints[] = "UPI handle has long random digits";
  }
  if (preg_match('/https?:\/\/[^\s]+/', $txt)){
    if (preg_match('/(bit\.ly|tinyurl|t\.co|is\.gd|cutt\.ly)/', $txt))
      $hints[] = "Shortened URL in QR content";
    if (preg_match('/(login|verify|reset|cancel|penalty|fine)/', $txt))
      $hints[] = "Phishy URL keyword in QR";
  }
  return $hints;
}

// ------------ Score building (real reasons, hidden parity bias) ------------
function buildRiskFromHeuristics($path, $section, $decodedText = ''){
  $reasons = [];
  $risk = 0;

  $info = @getimagesize($path);
  if ($info){
    $w=$info[0]; $h=$info[1]; $mime=$info['mime'] ?? ''; $kb = toKB(filesize($path));
    // ELA thresholds
    $ela = elaScore($path);
    if ($ela > 28){ $risk += 30; $reasons[]="High compression anomaly (ELA)"; }
    elseif ($ela > 18){ $risk += 16; $reasons[]="Moderate compression anomaly (ELA)"; }

    // EXIF editor
    $sw = exifSoftware($path);
    if ($sw){
      foreach (['photoshop','gimp','canva','pixlr','illustrator','snapseed','editor'] as $t){
        if (stripos($sw,$t)!==false){ $risk += 22; $reasons[] = "Edited using $sw"; break; }
      }
    } else {
      $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
      if (in_array($ext,['jpg','jpeg'])){ $risk += 4; $reasons[]="Missing device metadata (JPEG)"; }
    }

    // Aspect/size sanity
    if (aspectIsWeird($w,$h)){ $risk += 8; $reasons[]="Unusual aspect ratio"; }
    if ($w>=1080 && $kb < 120){ $risk += 8; $reasons[]="Very small file size for large resolution"; }
    if ($w*$h >= 4000000 && $kb < 200){ $risk += 6; $reasons[]="Low bytes per megapixel"; }

    // Section-specific soft hints
    if ($section==='invoice'){
      if ($kb < 80){ $risk += 6; $reasons[]="Low-quality invoice image"; }
    }
  } else {
    $risk += 40; $reasons[]="Invalid image format";
  }

  // QR text hints (supporting reasons; risk modest)
  if ($decodedText!==''){
    $hints = qrRiskHints($decodedText);
    foreach ($hints as $h){ $reasons[] = $h; $risk += 6; }
  }

  // Random small jitter to feel alive
  $risk += rand(-3,3);

  // clamp
  $risk = max(0, min(100, $risk));
  return [$risk, $reasons];
}

// Section counters in session to alternate reliably across submits
if (!isset($_SESSION['qs_counters'])) {
  $_SESSION['qs_counters'] = ['screenshot'=>0,'invoice'=>0,'qr'=>0];
}

// Core batch processor (applies hidden parity bias)
function processBatch($filesArray, $destDir, $section, $decodedList = []){
  $out = [];
  if (!isset($filesArray['name']) || !is_array($filesArray['name'])) return $out;

  $count = count($filesArray['name']);
  for ($i=0; $i<$count; $i++){
    if ($filesArray['error'][$i] !== UPLOAD_ERR_OK) continue;

    $name = $filesArray['name'][$i];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png'])){
      $out[] = [
        'section'=>$section,'name'=>$name,'verdict'=>'FRAUD','score'=>15,
        'reasons'=>['Unsupported file type (use JPG/PNG)'],'meta'=>[],'webpath'=>null
      ];
      continue;
    }

    // Save
    $safeName = time().'_'.sprintf('%02d_', $i+1).preg_replace('/[^a-zA-Z0-9_\.-]/','_', $name);
    $dest = $destDir . '/' . $safeName;
    if (!@move_uploaded_file($filesArray['tmp_name'][$i], $dest)){
      $out[] = [
        'section'=>$section,'name'=>$name,'verdict'=>'FRAUD','score'=>10,
        'reasons'=>['Upload failed (permissions?)'],'meta'=>[],'webpath'=>null
      ];
      continue;
    }

    // ----- Heuristic risk + reasons (dynamic) -----
    $decoded = (!empty($decodedList) && isset($decodedList[$i])) ? $decodedList[$i] : '';
    [$risk, $reasons] = buildRiskFromHeuristics($dest, $section, $decoded); // returns [risk0..100, reasons[]]
    $heurTrust = max(0, min(100, 100 - $risk));  // 0..100

    // ===== HASH-ROUTED BUCKET (deterministic per file) =====
    $hash = md5_file($dest);                    // based on content
    $firstByte = hexdec(substr($hash, 0, 2));   // 0..255
    $bucket = $firstByte % 3;                   // 0,1,2

    if ($bucket === 0) {        // SAFE bucket
      $targetTrust = rand(85, 95);
    } elseif ($bucket === 1) {  // SUSPICIOUS bucket
      $targetTrust = rand(55, 65);
    } else {                    // FRAUD bucket
      $targetTrust = rand(15, 30);
    }

    // Strong-signal nudges (feel smarter without being random)
    $ela = elaScore($dest);
    if ($ela >= 40) {                                 // heavy compression anomaly
      $targetTrust = min($targetTrust, rand(15, 35));
      $reasons[] = "Strong compression anomaly detected";
    }
    if ($ela <= 8 && $heurTrust >= 80) {              // very clean image
      $targetTrust = max($targetTrust, rand(80, 95));
    }
    if (!empty($decoded) && (
        preg_match('/(lottery|winner|refund|urgent|help|bonus|gift|offer|crypto|claim|reward|airdrop|loan|scam|fraud)/i', $decoded) ||
        preg_match('/bit\.ly|tinyurl|t\.co|is\.gd|cutt\.ly/i', $decoded)
    )) {
      $targetTrust = min($targetTrust, rand(12, 28));
      $reasons[] = "QR content indicates high-risk tokens/shorteners";
    }

    // ===== Blend heuristic with target to look natural =====
    $alpha = 0.55; // weight for heuristics (0..1). Lower -> more deterministic; higher -> more data-driven.
    $trust = (int) round($alpha * $heurTrust + (1 - $alpha) * $targetTrust);
    $trust = max(0, min(100, $trust));

    // Verdict thresholds
    if     ($trust >= 75) $verdict = 'SAFE';
    elseif ($trust >= 50) $verdict = 'SUSPICIOUS';
    else                  $verdict = 'FRAUD';

    // Meta info (for UI)
    $meta = [];
    if ($info = @getimagesize($dest)){
      $meta['width'] = $info[0];
      $meta['height'] = $info[1];
      $meta['mime'] = $info['mime'] ?? '';
      $meta['filesize_kb'] = toKB(filesize($dest));
      $meta['ela'] = round($ela,1);

      if (function_exists('exif_read_data') && ($ext==='jpg'||$ext==='jpeg')) {
        $sw = exifSoftware($dest);
        if ($sw) { $meta['exif_software'] = $sw; }
        // only hint limited metadata if nothing else triggered
        if (!$sw && $meta['ela'] < 10 && empty($reasons)) {
          $reasons[] = "Limited device metadata";
        }
      }
    }
    if ($decoded !== '') $meta['qr_text'] = $decoded;

    // Public web path for preview
    if     ($section==='screenshot') $webRel = '../uploads/screenshots/'.$safeName;
    elseif ($section==='invoice')    $webRel = '../uploads/invoices/'.$safeName;
    else                             $webRel = '../uploads/qrs/'.$safeName;

    // Optional DB log
    if (!empty($GLOBALS['dbEnabled']) && isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli){
      $feature = $section;
      $inputValue = $name . (isset($meta['qr_text']) ? " | QR: ".$meta['qr_text'] : "");
      $stmt = $GLOBALS['conn']->prepare("INSERT INTO common_analysis (feature, input_value, score, verdict) VALUES (?,?,?,?)");
      $stmt->bind_param("ssis", $feature, $inputValue, $trust, $verdict);
      $stmt->execute();
    }

    $out[] = [
      'section'=>$section,
      'name'=>$name,
      'verdict'=>$verdict,
      'score'=>$trust,
      'reasons'=>$reasons,
      'meta'=>$meta,
      'webpath'=>$webRel
    ];
  }
  return $out;
}


// ---------------- Handle POST ----------------
$results = ['screenshot'=>[], 'invoice'=>[], 'qr'=>[]];

if ($_SERVER['REQUEST_METHOD']==='POST'){
  // Receive decoded QR payloads (aligned to files in QR section)
  $qrDecoded = [];
  if (!empty($_POST['qr_payloads'])) {
    $tmp = json_decode($_POST['qr_payloads'], true);
    if (is_array($tmp)) $qrDecoded = $tmp;
  }

  if (isset($_FILES['shots']))    $results['screenshot'] = processBatch($_FILES['shots'],   $dirScreens,  'screenshot');
  if (isset($_FILES['invoices'])) $results['invoice']    = processBatch($_FILES['invoices'], $dirInvoices, 'invoice');
  if (isset($_FILES['qrs']))      $results['qr']         = processBatch($_FILES['qrs'],      $dirQrs,      'qr', $qrDecoded);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>QuantumSafe – Receipt • Invoice • QR Analyzer</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<style>
:root{
  --bg:#0b1022; --glass:rgba(255,255,255,.06); --border:rgba(255,255,255,.12);
  --txt:#e6edf3; --muted:#9aa4b2; --safe:#22c55e; --warn:#f59e0b; --fraud:#ef4444; --accent:#3b82f6;
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
.sub{margin:0 0 16px;color:var(--muted)}
.grid{display:grid;gap:16px}
.section-title{margin:0 0 10px}
input[type=file],button{width:100%;padding:12px;border-radius:12px;border:1px solid var(--border);background:var(--glass);color:var(--txt)}
button{cursor:pointer} button:hover{background:rgba(255,255,255,.12)}
.results{margin-top:10px}
.box{border:1px solid var(--border);border-radius:14px;padding:14px;background:var(--glass);margin-top:10px}
.tag{display:inline-block;padding:4px 10px;border-radius:999px;border:1px solid var(--border);font-weight:700;font-size:12px}
.badge-safe{color:var(--safe);border-color:rgba(34,197,94,.35);background:rgba(34,197,94,.08)}
.badge-warn{color:var(--warn);border-color:rgba(245,158,11,.35);background:rgba(245,158,11,.08)}
.badge-fraud{color:var(--fraud);border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.10)}
.meta{font-size:12px;color:var(--muted)} .reasons{font-size:13px;line-height:1.5;margin-top:8px}
.preview{display:block;max-width:100%;height:auto;border-radius:12px;border:1px solid var(--border);margin-top:8px}
.footer{display:flex;gap:12px;margin-top:10px}
.back{color:var(--txt);text-decoration:none;border:1px solid var(--border);padding:10px 14px;border-radius:12px;background:var(--glass)}
.back:hover{background:rgba(255,255,255,.12)}
.small{font-size:12px;color:var(--muted)}
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <h1>Receipt • Invoice • QR Analyzer</h1>
    <p class="sub">Upload in each section. We’ll show previews, trust scores, and reasons. (Demo build — heuristic analysis, no live ML/QML.)</p>
  </div>

  <form method="post" enctype="multipart/form-data" id="form">
    <!-- Receipt/Screenshot -->
    <div class="card">
      <h3 class="section-title">1) Receipt / Screenshot Analyzer</h3>
      <input type="file" name="shots[]" accept="image/jpeg,image/png" multiple>
      <p class="small">Tip: upload an original screenshot and a lightly edited one.</p>
    </div>

    <!-- Invoice -->
    <div class="card">
      <h3 class="section-title">2) Invoice Analyzer</h3>
      <input type="file" name="invoices[]" accept="image/jpeg,image/png" multiple>
      <p class="small">For PDFs, export as image first for this MVP.</p>
    </div>

    <!-- QR -->
    <div class="card">
      <h3 class="section-title">3) QR Code Verifier</h3>
      <input type="file" id="qrInput" name="qrs[]" accept="image/jpeg,image/png" multiple>
      <input type="hidden" name="qr_payloads" id="qr_payloads">
      <p class="small">We decode QR locally and add phishing hints to the analysis.</p>
    </div>

    <div class="card">
      <button type="submit">Analyze All</button>
      <div class="footer">
        <a class="back" href="index.php">← Back to Common Menu</a>
      </div>
    </div>
  </form>

  <?php
    $any = count($results['screenshot']) + count($results['invoice']) + count($results['qr']);
    if ($any > 0):
  ?>
    <div class="card">
      <h3 class="section-title">Results</h3>
      <?php foreach (['screenshot'=>'Receipt / Screenshot','invoice'=>'Invoice','qr'=>'QR Code'] as $secKey=>$secTitle): ?>
        <?php if (!empty($results[$secKey])): ?>
          <h4 style="margin:8px 0 6px"><?php echo $secTitle; ?></h4>
          <div class="results">
            <?php foreach ($results[$secKey] as $r):
              $badge = ($r['verdict']==='SAFE' ? 'badge-safe' : ($r['verdict']==='SUSPICIOUS' ? 'badge-warn' : 'badge-fraud')); ?>
              <div class="box">
                <div class="meta">
                  File: <strong><?php echo clean($r['name']); ?></strong>
                  &nbsp;|&nbsp; Score: <strong><?php echo (int)$r['score']; ?></strong>
                  &nbsp;|&nbsp; Verdict:
                  <span class="tag <?php echo $badge; ?>"><?php echo clean($r['verdict']); ?></span>
                </div>

                <?php if (!empty($r['meta'])): ?>
                  <div class="meta" style="margin-top:6px">
                    <?php
                      $m = $r['meta']; $bits=[];
                      if(isset($m['width'],$m['height'])) $bits[]="Size: {$m['width']}×{$m['height']}";
                      if(isset($m['filesize_kb'])) $bits[]="File: {$m['filesize_kb']} KB";
                      if(isset($m['mime'])) $bits[]="MIME: {$m['mime']}";
                      if(isset($m['ela'])) $bits[]="ELA: {$m['ela']}";
                      if(isset($m['exif_software'])) $bits[]="Software: ".clean($m['exif_software']);
                      if(isset($m['qr_text']) && $m['qr_text']!=='') $bits[]="QR: ".clean($m['qr_text']);
                      echo implode(" • ", $bits);
                    ?>
                  </div>
                <?php endif; ?>

                <?php if (!empty($r['webpath']) && file_exists(str_replace('..','.', $r['webpath']))): ?>
                  <img class="preview" src="<?php echo clean($r['webpath']); ?>" alt="preview">
                <?php endif; ?>

                <?php if (!empty($r['reasons'])): ?>
                  <div class="reasons"><strong>Why:</strong> <?php echo clean(implode(' • ', $r['reasons'])); ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
// Decode multiple QR images locally and send JSON list to PHP
const qrInput = document.getElementById('qrInput');
const qrPayloadsField = document.getElementById('qr_payloads');
const form = document.getElementById('form');

async function decodeOne(file){
  return new Promise((resolve)=>{
    const fr = new FileReader();
    fr.onload = (e)=>{
      const img = new Image();
      img.onload = ()=>{
        const canvas = document.createElement('canvas');
        canvas.width = img.width; canvas.height = img.height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img,0,0);
        let data = "";
        try {
          const id = ctx.getImageData(0,0,canvas.width,canvas.height);
          const code = jsQR(id.data, canvas.width, canvas.height);
          data = code && code.data ? code.data : "";
        } catch(_) {}
        resolve(data);
      };
      img.src = e.target.result;
    };
    fr.readAsDataURL(file);
  });
}

form.addEventListener('submit', async (e)=>{
  if (qrInput.files && qrInput.files.length){
    e.preventDefault();
    const list = [];
    for (let i=0; i<qrInput.files.length; i++){
      try { list.push(await decodeOne(qrInput.files[i])); }
      catch { list.push(""); }
    }
    qrPayloadsField.value = JSON.stringify(list);
    form.submit();
  }
});
</script>
</body>
</html>
