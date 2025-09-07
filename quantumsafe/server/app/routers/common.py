from fastapi import APIRouter, Depends, HTTPException, UploadFile, File, Form, Body
from typing import List, Optional
from datetime import datetime
import csv
import io
from pydantic import BaseModel

from ..core.db import get_database
from ..models.common import CommonAnalysis
from ..services.analysis import (
    analyze_chatbot_request,
    analyze_microfraud_transactions,
    analyze_image_heuristics
)

router = APIRouter()

# --- Chatbot Endpoint ---
class ChatbotRequest(BaseModel):
    message: str
    upi: Optional[str] = ""
    amount: Optional[float] = 0.0
    relationship: Optional[str] = "unknown"
    history: Optional[int] = 0

@router.post("/chatbot", response_model=CommonAnalysis)
async def chatbot_analyzer(request: ChatbotRequest, db=Depends(get_database)):
    result = analyze_chatbot_request(
        request.message, request.upi, request.amount, request.relationship, request.history
    )
    analysis_to_save = CommonAnalysis(
        feature='chatbot',
        inputValue=f"msg: {request.message[:120]}... | upi: {request.upi}",
        score=result['trust'],
        verdict=result['verdict'],
        reasons=result['reasons'],
        action=result['action']
    )
    await db["common_analyses"].insert_one(analysis_to_save.model_dump())
    return analysis_to_save

# --- Micro-fraud Endpoint ---
class MicrofraudRequest(BaseModel):
    transactions_text: str

@router.post("/microfraud")
async def microfraud_analyzer(request: MicrofraudRequest, db=Depends(get_database)):
    lines = request.transactions_text.strip().split('\n')
    transactions = []
    for line in lines:
        try:
            parts = [p.strip() for p in line.split(',')]
            if len(parts) >= 3:
                transactions.append({'date': parts[0], 'payee': parts[1], 'amount': float(parts[2])})
        except (ValueError, IndexError):
            continue
    
    results = analyze_microfraud_transactions(transactions)
    # Save each result to DB
    for res in results:
        analysis_to_save = CommonAnalysis(
            feature='microfraud',
            inputValue=f"{res['payee']} ({res['count']} txns, ₹{res['total']})",
            score=res['trust'],
            verdict=res['verdict'],
            reasons=res['reasons']
        )
        await db["common_analyses"].insert_one(analysis_to_save.model_dump())
        
    return results

# --- Image Analyzer Endpoint ---
@router.post("/analyze-image", response_model=CommonAnalysis)
async def analyze_image(
    file: UploadFile = File(...),
    qr_text: Optional[str] = Form(None),
    section: str = Form("screenshot"),
    db=Depends(get_database)
):
    contents = await file.read()
    if not contents:
        raise HTTPException(status_code=400, detail="Empty file uploaded.")
    
    result = analyze_image_heuristics(contents, qr_text)
    
    analysis_to_save = CommonAnalysis(
        feature=section,
        inputValue=f"{file.filename} | QR: {qr_text or 'N/A'}",
        score=result['trust'],
        verdict=result['verdict'],
        reasons=result['reasons']
    )
    await db["common_analyses"].insert_one(analysis_to_save.model_dump())
    return analysis_to_save

# --- Results Viewer Endpoint ---
@router.get("/results", response_model=List[CommonAnalysis])
async def get_analysis_results(
    feature: Optional[str] = None,
    from_date: Optional[str] = None,
    to_date: Optional[str] = None,
    order: str = 'new',
    page: int = 1,
    limit: int = 50,
    db=Depends(get_database)
):
    query = {}
    if feature and feature != 'all':
        query['feature'] = feature
    if from_date:
        query['createdAt'] = {'$gte': datetime.fromisoformat(from_date)}
    if to_date:
        query.setdefault('createdAt', {})['$lte'] = datetime.fromisoformat(to_date + "T23:59:59")

    sort_order = [("createdAt", -1)]
    if order == 'old':
        sort_order = [("createdAt", 1)]
    elif order == 'hi':
        sort_order = [("score", -1), ("createdAt", -1)]
    elif order == 'lo':
        sort_order = [("score", 1), ("createdAt", -1)]

    skip = (page - 1) * limit
    cursor = db["common_analyses"].find(query).sort(sort_order).skip(skip).limit(limit)
    results = await cursor.to_list(length=limit)
    return results