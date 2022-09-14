import time
from typing import Callable, Union

from requests import Response

from core.common.logger import LOGGER


def api_logger(func: Callable):
    def inner(*args, **kwargs):
        response = func(*args, **kwargs)
        LOGGER.info(f"REQUEST: {response.request.method}: {response.request.url}")
        if response.request.method in ["POST", "PUT", "PATCH"]:
            LOGGER.info(f"REQUEST_DATA: {response.request.body}")
        LOGGER.info(f'CONTENT: {response.status_code}":" {response.content}')

        return response

    return inner


def get_id_from_response(response: Response) -> str:
    return get_id_from_obj(response.json().get('entity'))


def get_id_from_obj(obj: dict) -> str:
    return obj.get("_id").get('$id')


def get_details(response: Response) -> list:
    """
    :param response: GET
    """
    return response.json().get('details')


def get_entity(response: Response) -> dict:
    """
    :rtype: object
    :param response: POST
    """
    return response.json().get('entity')


def find_item_by_id(response: Response, id_: str) -> dict:
    """
    response: GET
    """
    for item in get_details(response):
        if id_ == item['_id']['$id']:
            return item


def api_repeater(func: Callable, timeout: int = 1, polling: Union[int, float] = 0.1) -> Response:
    end_time = time.time() + timeout
    while True:
        result = func()
        if result.json().get('status') == 1:
            return result
        if time.time() > end_time:
            return result
        time.sleep(polling)
