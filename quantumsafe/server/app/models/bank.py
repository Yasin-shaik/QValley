from pydantic import BaseModel, Field
from typing import List, Optional
from datetime import datetime

class BankTransaction(BaseModel):
    account: str
    payee: str
    amount: float
    score: int
    verdict: str # ENUM('SAFE','SUSPICIOUS','FRAUD')
    reasons: Optional[List[str]] = None
    action: Optional[str] = None
    createdAt: datetime = Field(default_factory=datetime.utcnow)

class BankTransactionInDB(BankTransaction):
    id: str = Field(alias="_id")