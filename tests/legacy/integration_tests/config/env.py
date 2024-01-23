import os
from dotenv import load_dotenv

load_dotenv()

ENV = os.environ.get("ENV", "http://localhost:8074")

PROJECT_DIRECTORY = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
