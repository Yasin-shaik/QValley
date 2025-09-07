from pydantic import BaseModel, Field
from typing import Optional, List
from datetime import datetime

class CommonAnalysis(BaseModel):
    feature: str # 'chatbot', 'microfraud', 'screenshot', etc.
    inputValue: Optional[str] = None
    score: int
    verdict: str # ENUM('SAFE','SUSPICIOUS','FRAUD')
    reasons: Optional[List[str]] = None
    action: Optional[str] = None
    createdAt: datetime = Field(default_factory=datetime.utcnow)

class CommonAnalysisInDB(CommonAnalysis):
    id: str = Field(alias="_id")