from http.client import OK

from hamcrest import assert_that, all_of
from json_schema_matchers.common_matcher import matches_json_schema
from requests import Response

from core.common.entities import APIStatus
from core.common.matchers import has_status_code, has_status


def check_json_schema(response: Response, schema: dict) -> None:
    assert_that(response.json(), matches_json_schema(schema))


def check_http_code_and_status(
        response: Response,
        http_code: int = OK,
        status: int = APIStatus.SUCCESSFUL,
) -> None:
    assert_that(
        response, all_of(
            has_status(status), has_status_code(http_code)
        )
    )
