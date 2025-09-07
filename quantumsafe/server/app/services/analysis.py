import random
import re
import hashlib
from datetime import datetime
from typing import Dict, Any, List, Tuple
from PIL import Image, ExifTags
import io


def analyze_bank_transaction_row(row: Dict[str, Any]) -> Dict[str, Any]:
    """
    Simulated ML Risk Engine for a single bank transaction row.
    This is a direct Python translation of the PHP analyze_row function.
    """
    account = str(row.get('account', '')).strip()
    payee = str(row.get('payee', '')).strip().lower()
    
    try:
        amount = float(row.get('amount', 0))
    except (ValueError, TypeError):
        amount = 0.0
        
    ts_str = str(row.get('ts', '')).strip()
    try:
        # Attempt to parse the timestamp, otherwise use now
        ts = datetime.fromisoformat(ts_str.replace(" ", "T")).strftime('%Y-%m-%d %H:%M:%S')
    except ValueError:
        ts = datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')

    score = random.randint(5, 95)

    if score >= 70:
        verdict = "FRAUD"
    elif score >= 40:
        verdict = "SUSPICIOUS"
    else:
        verdict = "SAFE"

    reason_pool = [
        "High transaction amount", "Suspicious payee pattern", "Unusual time of transfer",
        "Repeated payments detected", "New/unknown payee", "Risky invoice-like pattern",
        "Potential phishing QR/invoice"
    ]
    random.shuffle(reason_pool)
    reasons = random.sample(reason_pool, k=random.randint(1, 3))

    if verdict == "FRAUD":
        action = "HOLD & VERIFY KYC • Block payee • Call customer"
    elif verdict == "SUSPICIOUS":
        action = "Manual review • OTP confirm • Call-back verification"
    else:
        action = "Allow • Monitor"

    return {
        'account': account, 'payee': payee, 'amount': amount, 'ts': ts,
        'score': score, 'verdict': verdict, 'reasons': reasons, 'action': action
    }

# --- Logic from common/chatbot.php ---
def analyze_chatbot_request(
    text: str, upi: str, amount: float, relationship: str, history_count: int
) -> Dict[str, Any]:
    """Analyzes a user's payment request message for fraud risk."""
    reasons, risk = [], 0
    t, u = text.lower(), upi.lower()

    if any(w in t for w in ['immediately', 'urgent', 'right now', 'final notice', 'asap']):
        risk += 14; reasons.append("Urgency language detected")
    if any(w in t for w in ['penalty', 'fine', 'blocked', 'legal action', 'police']):
        risk += 16; reasons.append("Threatening consequence detected")
    if u and not re.match(r'^[a-z0-9._-]{2,}@[a-z]{2,}$', u):
        risk += 8; reasons.append("UPI format looks unusual")
    if amount >= 20000:
        risk += 16; reasons.append("High amount (≥ ₹20k)")
    if relationship in ['unknown', 'stranger']:
        risk += 8; reasons.append("Unknown sender")

    risk = max(0, min(100, risk + random.randint(-2, 3)))
    heur_trust = 100 - risk

    hash_input = f"{text}|{upi}|{amount}|{relationship}|{history_count}"
    hash_val = hashlib.md5(hash_input.encode()).hexdigest()
    bucket = int(hash_val[:2], 16) % 3
    target_trust = {0: random.randint(85, 95), 1: random.randint(55, 65), 2: random.randint(15, 30)}[bucket]

    trust = int(0.6 * heur_trust + 0.4 * target_trust)
    trust = max(0, min(100, trust))
    verdict = 'SAFE' if trust >= 75 else 'SUSPICIOUS' if trust >= 50 else 'FRAUD'
    
    action_map = {
        'FRAUD': "Do NOT pay • Call the person via saved contact • Report/Block",
        'SUSPICIOUS': "Verify UPI name in your UPI app • Call back • Ask for invoice/GST",
        'SAFE': "Proceed if UPI name matches • Keep proof"
    }
    return {'trust': trust, 'verdict': verdict, 'reasons': reasons, 'action': action_map[verdict], 'hash': hash_val}

# --- Logic from common/microfraud.php ---
def analyze_microfraud_transactions(transactions: List[Dict]) -> List[Dict]:
    """Analyzes a list of transactions for micro-fraud patterns."""
    grouped = {}
    for t in transactions:
        p = str(t.get('payee', '')).lower()
        if not p: continue
        if p not in grouped:
            grouped[p] = {'payee': t['payee'], 'total': 0.0, 'count': 0}
        grouped[p]['total'] += float(t.get('amount', 0))
        grouped[p]['count'] += 1
    
    results = []
    for p, g in grouped.items():
        reasons, risk = [], 0
        avg = g['total'] / max(1, g['count'])
        if avg <= 300 and g['count'] >= 3:
            risk += 18; reasons.append(f"Repeated small payments ({g['count']})")
        if g['count'] >= 5 and g['total'] >= 2000:
            risk += 16; reasons.append(f"High total (₹{g['total']:.2f}) across small payments")
        
        heur_trust = max(0, min(100, 100 - risk))
        
        hash_input = f"{p}|{g['total']}|{g['count']}"
        hash_val = hashlib.md5(hash_input.encode()).hexdigest()
        bucket = int(hash_val[:2], 16) % 3
        target_trust = {0: random.randint(85, 95), 1: random.randint(55, 65), 2: random.randint(15, 30)}[bucket]
        trust = int(0.6 * heur_trust + 0.4 * target_trust)
        verdict = 'SAFE' if trust >= 75 else 'SUSPICIOUS' if trust >= 50 else 'FRAUD'
        
        results.append({
            'payee': g['payee'], 'count': g['count'], 'total': g['total'],
            'trust': trust, 'verdict': verdict, 'reasons': reasons
        })
    return results

# --- Logic from common/screenshot.php ---
def get_ela_score(image_bytes: bytes) -> float:
    try:
        original = Image.open(io.BytesIO(image_bytes)).convert('RGB')
        resaved_buffer = io.BytesIO()
        original.save(resaved_buffer, 'JPEG', quality=85)
        resaved = Image.open(resaved_buffer)
        diff_sum = sum(abs(p1 - p2) for p1, p2 in zip(original.tobytes(), resaved.tobytes()))
        pixels = original.width * original.height * 3
        return min(100.0, (diff_sum / pixels) * 10) if pixels > 0 else 0.0
    except Exception: return 0.0

def get_exif_software(image_bytes: bytes) -> str:
    try:
        img = Image.open(io.BytesIO(image_bytes))
        exif = {ExifTags.TAGS[k]: v for k, v in img._getexif().items() if k in ExifTags.TAGS}
        return str(exif.get('Software', ''))
    except Exception: return ''

def analyze_image_heuristics(image_bytes: bytes, qr_text: str = "") -> Dict[str, Any]:
    reasons, risk = [], 0
    if get_ela_score(image_bytes) > 2.0:
        risk += 30; reasons.append("High compression anomaly (ELA)")
    software = get_exif_software(image_bytes).lower()
    if any(e in software for e in ['photoshop', 'gimp', 'canva']):
        risk += 22; reasons.append(f"Edited using {software}")
    if qr_text and "bit.ly" in qr_text.lower():
        risk += 15; reasons.append("Shortened URL in QR content")
    
    risk = max(0, min(100, risk + random.randint(-3, 3)))
    heur_trust = 100 - risk
    hash_val = hashlib.md5(image_bytes).hexdigest()
    bucket = int(hash_val[:2], 16) % 3
    target_trust = {0: random.randint(85, 95), 1: random.randint(55, 65), 2: random.randint(15, 30)}[bucket]
    trust = int(0.55 * heur_trust + 0.45 * target_trust)
    verdict = 'SAFE' if trust >= 75 else 'SUSPICIOUS' if trust >= 50 else 'FRAUD'
    
    return {'trust': trust, 'verdict': verdict, 'reasons': list(set(reasons))}
