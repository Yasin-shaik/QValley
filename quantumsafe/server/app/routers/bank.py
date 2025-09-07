import csv
import io
from fastapi import APIRouter, UploadFile, File, HTTPException, Depends
from fastapi.responses import StreamingResponse
from typing import List
from ..core.db import get_database
from ..models.bank import BankTransaction
from ..services.analysis import analyze_bank_transaction_row
import datetime
from ..services.csv_handler import process_bank_csv # Import the new service

router = APIRouter()


@router.post("/upload", response_model=List[BankTransaction])
async def upload_and_analyze_csv(file: UploadFile = File(...), db=Depends(get_database)):
    """
    Receives a CSV file, analyzes each transaction, saves it to the database,
    and returns the analysis results.
    """
    if not file.filename.endswith('.csv'):
        raise HTTPException(status_code=400, detail="Invalid file type. Please upload a CSV.")

    contents = await file.read()
    file_stream = io.StringIO(contents.decode('utf-8'))
    reader = csv.reader(file_stream)

    try:
        headers = [h.lower() for h in next(reader)]
    except StopIteration:
        raise HTTPException(status_code=400, detail="CSV file is empty.")

    processed_rows = []
    
    for row_data in reader:
        row_dict = dict(zip(headers, row_data))

        # Check if CSV already has model outputs
        has_analysis = all(k in row_dict for k in ['score', 'verdict', 'reasons', 'action'])

        if has_analysis:
            # Use CSV values as-is
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
            # Fallback to mock analyzer
            analysis_result = analyze_bank_transaction_row(row_dict)
        
        # Create a Pydantic model instance for validation and DB insertion
        transaction_to_save = BankTransaction(**analysis_result)
        
        # Insert into MongoDB
        await db["bank_transactions"].insert_one(transaction_to_save.model_dump(by_alias=True))
        
        processed_rows.append(transaction_to_save)

    return processed_rows



@router.get("/transactions", response_model=List[BankTransaction])
async def get_latest_transactions(limit: int = 25, db=Depends(get_database)):
    """
    Fetches the most recent transactions from the database, similar to the
    initial page load in the PHP version.
    """
    transactions_cursor = db["bank_transactions"].find().sort("createdAt", -1).limit(limit)
    transactions = await transactions_cursor.to_list(length=limit)
    return transactions


@router.get("/export")
async def export_results_to_csv(limit: int = 1000, db=Depends(get_database)):
    """
    Exports the latest transaction analysis results to a CSV file.
    """
    transactions_cursor = db["bank_transactions"].find().sort("createdAt", -1).limit(limit)
    transactions = await transactions_cursor.to_list(length=limit)

    output = io.StringIO()
    writer = csv.writer(output)
    
    # Write header
    headers = ['account', 'payee', 'amount', 'score', 'verdict', 'reasons', 'action', 'createdAt']
    writer.writerow(headers)

    # Write rows
    for tx in transactions:
        writer.writerow([
            tx.get('account'),
            tx.get('payee'),
            tx.get('amount'),
            tx.get('score'),
            tx.get('verdict'),
            ' • '.join(tx.get('reasons', [])),
            tx.get('action'),
            tx.get('createdAt').strftime('%Y-%m-%d %H:%M:%S') if tx.get('createdAt') else ''
        ])

    output.seek(0)
    
    return StreamingResponse(
        output,
        media_type="text/csv",
        headers={"Content-Disposition": "attachment; filename=quantumsafe_analysis_export.csv"}
    )