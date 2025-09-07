from pydantic_settings import BaseSettings, SettingsConfigDict

class Settings(BaseSettings):
    """
    Application settings loaded from environment variables.
    """
    MONGO_DETAILS: str = "mongodb://localhost:27017"
    DATABASE_NAME: str = "quantumsafe"

    model_config = SettingsConfigDict(env_file=".env", env_file_encoding='utf-8')

settings = Settings()