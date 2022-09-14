import random
from copy import deepcopy

from hamcrest import assert_that, has_entries

from core.common.entities import APIPath
from core.common.helpers.utils import (
    convert_date_fields_to_expected,
    get_random_past_or_future_date_str, get_true_or_false, get_random_str
)
from core.common.helpers.api_helpers import get_id_from_response, get_details, get_entity
from core.resoursces.schemas import TAX_RATES_GET_SCHEMA, TAX_RATES_POST_SCHEMA
from core.testlib.API.base_api import BaseAPI
from steps.backend_assertion_steps.base_api_assertion_steps import APIAssertionSteps


class TaxRates(BaseAPI):

    def __init__(self):
        super().__init__(path=APIPath.TAX_RATES)
        self.expected_response = None

    def compose_create_payload(
            self,
            from_date=None,
            to=None,
            rate=None,  # %
            embed_tax=None,
            description=None,
            key=None,
    ):
        self.create_payload = {
            "from": from_date or get_random_past_or_future_date_str(),
            "rate": rate or round(random.uniform(0.1, 0.6), 2),
            "embed_tax": embed_tax or get_true_or_false(),
            "description": description or get_random_str(),
            "key": key or get_random_str().upper(),
            "to": to or get_random_past_or_future_date_str(past=False),
        }

        return self

    def compose_update_payload(
            self,
            rate=None,  # %
            embed_tax=None,
            description=None,
    ):
        self.update_payload = {
            "rate": rate or round(random.uniform(0.1, 0.6), 2),
            "embed_tax": embed_tax or get_true_or_false(),
            "description": description or get_random_str()
        }

        return self

    def compose_close_payload(self, to=False, date_in_past=False):
        self.close_payload = {
            'to': get_random_past_or_future_date_str(past=date_in_past) if to else None
        }

        return self

    def compose_close_and_new_payload(
            self,
            from_date=None,
            rate=None,  # %
            embed_tax=None,
            description=None,
    ):
        self.close_and_new_payload = self.compose_update_payload(
            rate=rate,
            embed_tax=embed_tax,
            description=description
        ).update_payload

        self.close_and_new_payload['from'] = from_date or get_random_past_or_future_date_str(
            range_nearest_days=5, past=False
        )

        return self

    def generate_expected_response_after_updating(self, payload=None):
        payload = payload or self.create_payload
        payload.update(self.update_payload)

        return self.generate_expected_response(payload=payload)

    def generate_expected_response_after_close(self, payload=None):
        payload = payload or self.create_payload
        payload.update(self.close_payload) if self.close_payload else None

        return self.generate_expected_response(payload=payload)

    def generate_expected_response_after_close_and_new(self, payload=None):
        payload = payload or self.create_payload
        payload.update(self.close_and_new_payload)
        #  after close and new product we receive tax rate with new id in response
        return self.generate_expected_response(
            payload=payload, id_=get_id_from_response(self.close_and_new_response))

    def generate_expected_response(self, payload: dict = None, id_: str = None):
        payload = payload or self.create_payload

        self.expected_response = {
            "_id": {
                "$id": id_ or get_id_from_response(self.create_response)
            },
            "from": payload.get('from'),
            "to": payload.get('to'),
            "rate": payload.get('rate'),
            "embed_tax": payload.get('embed_tax'),
            "description": payload.get('description'),
            "key": payload.get('key')
        }

        return self.expected_response


class TaxRatesAssertionSteps(APIAssertionSteps):
    def __init__(self, instance: TaxRates):
        super().__init__(instance=instance)

    def check_response(self, actual_response, expected_response, schema, method):
        self.check_json_schema_and_http_code_and_status(schema, actual_response)
        # response GET details  has type array, so we should get 0 elem from array
        actual = (
            deepcopy(get_details(actual_response))[0]
            if method == 'GET'
            else deepcopy(get_entity(actual_response))
        )
        expected_response = expected_response or self.instance.generate_expected_response()

        for response in (actual, expected_response):
            response.pop('revision_info', None)  # can't be predicted for now
            # for close method w/o to param
            if not expected_response.get('to'):
                response.pop('to')

        convert_date_fields_to_expected(expected_response, ['from', 'to'], method)

        assert_that(
            actual, has_entries(expected_response), "Response is not corresponded to expected"
        )

    def validate_get_response_is_correct(self, schema=TAX_RATES_GET_SCHEMA, expected_response=None):
        super().validate_get_response_is_correct(schema, expected_response)

    def validate_post_response_is_correct(self, schema=TAX_RATES_POST_SCHEMA, expected_response=None):
        super().validate_post_response_is_correct(schema, expected_response)
