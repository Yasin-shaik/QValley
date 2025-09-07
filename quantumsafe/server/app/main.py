from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from contextlib import asynccontextmanager
from .core.db import connect_to_mongo, close_mongo_connection
# Import routers
from .routers import bank, common

@asynccontextmanager
async def lifespan(app: FastAPI):
    # On startup
    await connect_to_mongo()
    yield
    # On shutdown
    await close_mongo_connection()

app = FastAPI(
    title="QuantumSafe AI API",
    description="API for the QuantumSafe project, migrating from PHP to FastAPI.",
    version="1.0.0",
    lifespan=lifespan
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/", tags=["Root"])
async def read_root():
    return {"message": "Welcome to the QuantumSafe API!"}

# Include routers
app.include_router(bank.router, tags=["Bank"], prefix="/bank")
app.include_router(common.router, tags=["Common"], prefix="/common")