from abc import ABC, abstractmethod
from datetime import datetime

from hamcrest import assert_that, less_than_or_equal_to, equal_to

from core.common.matchers import check_that, has_not_existing_entity
from core.common.utils import (
    get_details, to_float_date_obj_form_get, get_entity, convert_datetime_str_to_timestamp,
    convert_date_str_to_datetime_str, get_id_from_response
)
from core.testlib.common_assertions import check_http_code_and_status, check_json_schema


class APIAssertionSteps(ABC):
    def __init__(self, instance):
        self.instance = instance

    @abstractmethod
    def check_response(self, actual_response, expected_response, schema, method):
        pass

    def validate_get_response_is_correct(self, schema, expected_response=None):
        self.check_response(
            actual_response=self.instance.get_response,
            expected_response=expected_response,
            schema=schema,
            method='GET'
        )

    def validate_post_response_is_correct(self, schema, expected_response=None):
        self.check_response(
            actual_response=self.instance.create_response,
            expected_response=expected_response,
            schema=schema,
            method='POST'
        )

    def check_post_response_is_successful(self):
        check_http_code_and_status(self.instance.create_response)

    def check_update_response_is_successful(self):
        check_http_code_and_status(self.instance.update_response)

    def check_close_response_is_successful(self):
        check_http_code_and_status(self.instance.close_response)

        to_from_payload = self.instance.close_payload.get('to') if self.instance.close_payload else None
        to_from_response = to_float_date_obj_form_get(
            get_entity(self.instance.close_response).get('to'), msec=False
        )
        if not to_from_payload:
            # If no to date supplied it will be now
            # we omit msec due to it can vary greatly
            time_now = datetime.now().replace(microsecond=0).timestamp()
            assert_that(
                to_from_response, less_than_or_equal_to(time_now),
                f"to param should be less or equal to {time_now}"
            )
        else:
            assert_that(
                to_from_response, equal_to(convert_datetime_str_to_timestamp(to_from_payload)),
                f"to param should be equal to {to_from_payload}"
            )

    def check_object_has_new_to_date_after_close_and_new(self):
        assert_that(
            get_details(self.instance.get_by_id())[0].get('to'),
            equal_to(
                convert_date_str_to_datetime_str(
                    self.instance.close_and_new_payload.get('from'))),
            '"to" param is not changed to "from" param from new revision'
        )

    def check_object_revision_status(self, status):
        check_that(
            lambda: get_details(self.instance.get_by_id())[0].get('revision_info').get('status'),
            equal_to(status),
            f"revision status should be {status}", timeout=3
        )

    def check_object_is_deleted_successfully(self):
        check_http_code_and_status(self.instance.delete_response)

        assert_that(
            self.instance.get_all(), has_not_existing_entity(
                get_id_from_response(self.instance.create_response))
        )

    @staticmethod
    def check_json_schema_and_http_code_and_status(schema, actual_response):
        check_http_code_and_status(actual_response)
        check_json_schema(actual_response, schema)

