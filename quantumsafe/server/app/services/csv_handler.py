# File: server/app/services/csv_handler.py

import csv
import io
from fastapi import UploadFile, HTTPException
from typing import List, Dict
from datetime import datetime

from .analysis import analyze_bank_transaction_row

async def process_bank_csv(file: UploadFile) -> List[Dict]:
    """
    Reads a CSV file, processes each row, and returns a list of dictionaries
    containing the analysis results.
    """
    if not file.filename.endswith('.csv'):
        raise HTTPException(status_code=400, detail="Invalid file type. Please upload a CSV.")

    contents = await file.read()
    file_stream = io.StringIO(contents.decode('utf-8'))
    reader = csv.reader(file_stream)

    try:
        headers = [h.lower().strip() for h in next(reader)]
    except StopIteration:
        raise HTTPException(status_code=400, detail="CSV file is empty.")

    processed_rows = []
    
    for row_data in reader:
        row_dict = dict(zip(headers, row_data))

        has_analysis = all(k in row_dict for k in ['score', 'verdict', 'reasons', 'action'])

        if has_analysis:
            verdict = str(row_dict.get('verdict', 'SAFE')).strip().upper()
            reasons_raw = str(row_dict.get('reasons', '')).strip()
            
            if '•' in reasons_raw:
                reasons = [r.strip() for r in reasons_raw.split('•')]
            else:
                reasons = [r.strip() for r in reasons_raw.split(',')]
            
            analysis_result = {
                'account': str(row_dict.get('account', '')).strip(),
                'payee': str(row_dict.get('payee', '')).strip().lower(),
                'amount': float(row_dict.get('amount', 0)),
                'ts': str(row_dict.get('ts', '')).strip() or datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S'),
                'score': int(row_dict.get('score', 0)),
                'verdict': verdict,
                'reasons': reasons,
                'action': str(row_dict.get('action', 'Allow • Monitor')).strip()
            }
        else:
            analysis_result = analyze_bank_transaction_row(row_dict)
        
        processed_rows.append(analysis_result)

    return processed_rows