<!-- php\common\index.php -->

<?php /* common/index.php — QuantumSafe (Common Version) */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>QuantumSafe – Common Version</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{
  --bg:#0b1022; --bg2:#0f1630; --glass:rgba(255,255,255,.06);
  --border:rgba(255,255,255,.12); --txt:#e6edf3; --muted:#93a0b4; --accent:#3b82f6;
}
*{box-sizing:border-box}
body{margin:0;background:
  radial-gradient(1200px 800px at 20% -10%, #1a2c64 0%, var(--bg) 55%),
  linear-gradient(180deg, #0a0f24, var(--bg));
  color:var(--txt);font-family:ui-sans-serif,system-ui,Segoe UI,Roboto,Arial}
.container{max-width:1100px;margin:40px auto;padding:0 16px}
.header{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.logo{width:38px;height:38px;border-radius:10px;
  background:linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.04));
  border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-weight:800}
h1{margin:0;font-size:26px}
.sub{margin:4px 0 0;color:var(--muted);font-size:14px}
.card{
  background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.02));
  -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px);
  border:1px solid var(--border); border-radius:18px; padding:18px;
}
.grid{display:grid;gap:16px;margin-top:10px}
@media(min-width:880px){.grid{grid-template-columns:repeat(3, 1fr)}}
.tile{padding:18px;border-radius:16px;border:1px solid var(--border);background:var(--glass);position:relative;overflow:hidden}
.tile h3{margin:0 0 8px;font-size:18px}
.tile p{margin:0 0 12px;color:var(--muted);font-size:14px;min-height:40px}
.btn{display:inline-block;padding:10px 14px;border-radius:12px;
  background:var(--glass);border:1px solid var(--border);color:var(--txt);text-decoration:none}
.btn:hover{background:rgba(255,255,255,.12)}
.footer{margin-top:14px;color:var(--muted);font-size:12px}
.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:14px}
.kpi{padding:12px;border-radius:12px;background:var(--glass);border:1px solid var(--border)}
.kpi .num{font-weight:800}
.badge{padding:2px 8px;border-radius:999px;border:1px solid var(--border);font-size:12px;margin-left:8px}
.badge-beta{color:#22c55e;border-color:rgba(34,197,94,.35);background:rgba(34,197,94,.08)}
.note{font-size:12px;color:var(--muted);margin-top:8px}
</style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">QS</div>
      <div>
        <h1>QuantumSafe – Common Version <span class="badge badge-beta">MVP</span></h1>
        <p class="sub">Verify before you pay: Screenshot • QR • Chatbot • Micro-fraud</p>
      </div>
    </div>

    <div class="card">
      <div class="kpis">
        <div class="kpi"><div>Protection</div><div class="num">Pre-transaction</div></div>
        <div class="kpi"><div>Privacy</div><div class="num">No PII stored</div></div>
        <div class="kpi"><div>Status</div><div class="num">Demo Mode</div></div>
      </div>
      <p class="note">This MVP uses heuristic checks (no live bank/QML integration). Perfect for hackathon demos.</p>
    </div>

    <div class="grid">
      <div class="tile">
        <h3>Screenshot / Invoice / QR Analyzer</h3>
        <p>Upload a payment screenshot or QR. We scan for tampering & risky patterns.</p>
        <a class="btn" href="screenshot.php">Open Analyzer →</a>
      </div>

      <div class="tile">
        <h3>“Should I send?” Chatbot</h3>
        <p>Type the request you received. Get a quick Safe / Suspicious / Fraud verdict.</p>
        <a class="btn" href="chatbot.php">Ask the Bot →</a>
      </div>

      <div class="tile">
        <h3>Micro-Fraud Detector</h3>
        <p>Paste recent payments. We flag small, repeated or unusual patterns.</p>
        <a class="btn" href="microfraud.php">Scan History →</a>
      </div>
    </div>

    <div class="footer">© <?php echo date('Y'); ?> QuantumSafe – Demo build for hackathon</div>
  </div>
</body>
</html>
