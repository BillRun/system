import random
from copy import deepcopy

from hamcrest import assert_that, has_entries

from core.common.entities import APIPath
from core.common.utils import (
    get_details,
    get_id_from_response, get_entity, convert_date_fields_to_expected,
    remove_keys_if_value_is_none, get_random_str, get_random_int,
    get_true_or_false, get_random_past_or_future_date_str
)
from core.resoursces.price_obj import create_price_obj
from core.resoursces.schemas import SERVICES_POST_SCHEMA, SERVICES_GET_SHEMA
from core.testlib.API.base_api import BaseAPI
from steps.backend_assertion_steps.base_api_assertion_steps import APIAssertionSteps


class Services(BaseAPI):

    def __init__(self):
        super().__init__(path=APIPath.SERVICES)
        self.expected_response = None

    def compose_create_payload(
            self,
            description=None,
            name=None,  # KEY on UI
            price=None,
            tax=None,
            from_date=None,
            to=None,
            prorated=None,
            quantitative=None,
            include=None,  # ?
    ):
        self.create_payload = {
            'description': description or get_random_str(),
            'name': name or get_random_str().upper(),
            'price': price or create_price_obj(price=get_random_int(start=10, stop=10000)),
            'tax': tax,
            'from_date': from_date or get_random_past_or_future_date_str(),
            'to': to or get_random_past_or_future_date_str(past=False),
            'prorated': prorated or get_true_or_false(),
            'quantitative': quantitative or get_true_or_false(),
        }

        return self

    def compose_update_payload(
            self,
            description=None,
            price=None,
            tax=None,
            prorated=None,
            quantitative=None,
    ):
        self.update_payload = {
            'description': description or get_random_str(),
            'price': price or create_price_obj(price=get_random_int(start=10, stop=10000)),
            'tax': tax,
            'prorated': prorated or random.choice([False, True]),
            'quantitative': quantitative,
        }
        remove_keys_if_value_is_none(self.update_payload)

        return self

    def compose_close_payload(self, to=False, date_in_past=False):
        self.close_payload = {
            'to': get_random_past_or_future_date_str(past=date_in_past) if to else None
        }

        return self

    def compose_close_and_new_payload(
            self,
            from_date=None,
            description=None,
            price=None,
            tax=None,
            prorated=None,
            quantitative=None,
    ):
        self.close_and_new_payload = self.compose_update_payload(
            description=description,
            price=price,
            tax=tax,
            prorated=prorated,
            quantitative=quantitative
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
        #  after close and new product we receive product with new id in response
        return self.generate_expected_response(
            payload=payload, id_=get_id_from_response(self.close_and_new_response))

    def generate_expected_response(self, payload: dict = None, id_: str = None):
        payload = payload or self.create_payload
        self.expected_response = {
            "_id": {
                "$id": id_ or get_id_from_response(self.create_response)
            },
            'description': payload.get('description'),
            'name': payload.get('name'),
            'price': payload.get('price'),
            'tax': payload.get('tax'),
            'from': payload.get('from'),
            'to': payload.get('to'),
            'prorated': payload.get('prorated'),
            'quantitative': payload.get('quantitative'),
        }

        return remove_keys_if_value_is_none(self.expected_response)


class ServicesAssertionSteps(APIAssertionSteps):
    def __init__(self, instance: Services):
        super().__init__(instance=instance)

    def check_response(self, actual_response, expected_response, schema, method):
        super().check_response(actual_response, expected_response, schema, method)

        # response GET details  has type array, so we should get 0 elem from array
        actual = (
            deepcopy(get_details(actual_response))[0]
            if method == 'GET'
            else deepcopy(get_entity(actual_response))
        )
        expected_response = expected_response or self.instance.generate_expected_response()

        for response in (actual, expected_response):
            response.pop('revision_info', None)  # can't be predicted for now

        convert_date_fields_to_expected(expected_response, ['to', 'from'], method)

        assert_that(
            actual, has_entries(expected_response), "Response is not corresponded to expected"
        )

    def validate_post_response_is_correct(self, schema=SERVICES_POST_SCHEMA, expected_response=None):
        super().validate_post_response_is_correct(
            schema=schema, expected_response=expected_response)

    def validate_get_response_is_correct(self, schema=SERVICES_GET_SHEMA, expected_response=None):
        super().validate_get_response_is_correct(
            schema=schema, expected_response=expected_response)
