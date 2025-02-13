import os
from dotenv import load_dotenv

load_dotenv()

USERNAME = os.environ.get("USERNAME", "admin")
PASSWORD = os.environ.get("PASSWORD", "12345678")
MONGO_CONNECTION = "mongodb://localhost:27017/"
MONGO_BILLING_DB_NAME = "billing_container"
LOCAL_HOST = 'http://localhost:8074'
