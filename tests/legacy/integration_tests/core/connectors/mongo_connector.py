from pymongo import MongoClient

from config.credentials import MONGO_CONNECTION, MONGO_BILLING_DB_NAME


class MongoConnector:

    def __init__(self):
        self.connection = MongoClient(MONGO_CONNECTION)

    def __enter__(self):
        return self.connection

    def __exit__(self, exc_type, exc_val, exc_tb):
        self.connection.close()


def insert_into_mongo(
        coll_name: str, data: list[dict], db_name: str = MONGO_BILLING_DB_NAME
) -> list[str]:
    with MongoConnector() as client:
        db = client[db_name]
        coll = db[coll_name]

        result = coll.insert_many(data)

    return [str(id_) for id_ in result.inserted_ids]


def find_in_mongo(
        coll_name: str, search: dict, db_name: str = MONGO_BILLING_DB_NAME
):
    with MongoConnector() as client:
        db = client[db_name]
        coll = db[coll_name]

        result = coll.find(search)

    return result


if __name__ == "__main__":
    print(insert_into_mongo(coll_name="bills", data=[{"test": 22}]))
