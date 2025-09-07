from motor.motor_asyncio import AsyncIOMotorClient
from .config import settings

class MongoDB:
    client: AsyncIOMotorClient = None
    db = None

db_manager = MongoDB()

async def connect_to_mongo():
    """
    Connects to the MongoDB database on application startup.
    """
    print("Connecting to MongoDB...")
    db_manager.client = AsyncIOMotorClient(settings.MONGO_DETAILS)
    db_manager.db = db_manager.client[settings.DATABASE_NAME]
    print("Successfully connected to MongoDB!")

async def close_mongo_connection():
    """
    Closes the MongoDB connection on application shutdown.
    """
    print("Closing MongoDB connection...")
    db_manager.client.close()
    print("MongoDB connection closed.")

def get_database():
    """
    Returns the database instance.
    """
    return db_manager.db