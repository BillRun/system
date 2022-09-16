import random
from copy import deepcopy

from hamcrest import assert_that, has_entries

from core.common.entities import APIPath, Recurrence
from core.common.helpers.utils import (
    get_random_str, get_true_or_false, convert_date_fields_to_expected,
    remove_keys_if_value_is_none, get_random_past_or_future_date_str
)
from core.common.helpers.api_helpers import get_id_from_response, get_details, get_entity
from core.resoursces.price_obj import create_price_obj
from core.resoursces.schemas import PLANS_GET_SCHEMA, PLANS_POST_SCHEMA
from core.testlib.API.base_api import BaseAPI
from steps.backend_assertion_steps.base_api_assertion_steps import APIAssertionSteps


class Plans(BaseAPI):

    def __init__(self):
        super().__init__(path=APIPath.PLANS)
        self.expected_response = None

    def compose_create_payload(
            self,
            connection_type=None,
            from_date=None,
            to=None,
            name=None,
            price=None,
            form_date_price=None,
            to_date_price=None,
            description=None,
            include: list = None,  # services i.e. SMS, CALL
            upfront=None,
            recurrence_frequency=None,
            recurrence_start=None,
            rates: list = None,
            charging_type=None,
            prorated_start=None,
            prorated_end=None,
            prorated_termination=None
    ):
        self.create_payload = {
            "connection_type": connection_type,  # "prepaid", "postpaid",
            "charging_type": charging_type or connection_type,  # "prepaid", "postpaid"
            "to": to or get_random_past_or_future_date_str(past=False),
            "price": create_price_obj(price, form_date_price, to_date_price),
            "from": from_date or get_random_past_or_future_date_str(),
            "name": name or get_random_str().upper(),  # KEY on UI
            "upfront": upfront or get_true_or_false(),
            "recurrence": {
                "frequency": recurrence_frequency or random.choice(Recurrence.as_list()),
                "start": recurrence_start or 1
            },
            "rates": rates or [],
            "description": description or get_random_str(),
            "include": {"services": include} if include else None,
            "prorated_start": prorated_start or get_true_or_false(),
            "prorated_end": prorated_end or get_true_or_false(),
            "prorated_termination": prorated_termination or get_true_or_false()

        }
        return self

    def compose_update_payload(
            self,
            price=None,
            form_date_price=None,
            to_date_price=None,
            description=None,
            upfront=None,
            recurrence_frequency=None,
            recurrence_start=None,
            rates=None,
            prorated_start=None,
            prorated_end=None,
            prorated_termination=None
    ):
        self.update_payload = {
            # "to": to or convert_date_to_str(get_random_past_or_future_date(past=False)), ToDo is updatable ?
            "price": create_price_obj(price, form_date_price, to_date_price),
            "upfront": upfront or get_true_or_false(),
            "recurrence": {
                "frequency": recurrence_frequency or random.choice(Recurrence.as_list()),
                "start": recurrence_start or 1
            },
            "rates": rates or [],
            "description": description or get_random_str(),
            "include": {"services": random.choice(['SMS', 'CALL'])},
            "prorated_start": prorated_start or get_true_or_false(),
            "prorated_end": prorated_end or get_true_or_false(),
            "prorated_termination": prorated_termination or get_true_or_false(),
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
            price=None,
            form_date_price=None,
            to_date_price=None,
            description=None,
            upfront=None,
            recurrence_frequency=None,
            recurrence_start=None,
            rates=None,
            prorated_start=None,
            prorated_end=None,
            prorated_termination=None
    ):
        self.close_and_new_payload = self.compose_update_payload(
            price=price,
            form_date_price=form_date_price,
            to_date_price=to_date_price,
            description=description,
            upfront=upfront,
            recurrence_frequency=recurrence_frequency,
            recurrence_start=recurrence_start,
            rates=rates,
            prorated_start=prorated_start,
            prorated_end=prorated_end,
            prorated_termination=prorated_termination
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
        #  after close and new product we receive plan with new id in response
        return self.generate_expected_response(
            payload=payload, id_=get_id_from_response(self.close_and_new_response))

    def generate_expected_response(self, payload: dict = None, id_: str = None):
        payload = payload or self.create_payload
        self.expected_response = {
            "_id": {
                "$id": id_ or get_id_from_response(self.create_response)
            },
            "charging_type": payload.get('charging_type'),
            "connection_type": payload.get('connection_type'),
            "description": payload.get('description'),
            "from": payload.get('from'),
            "name": payload.get('name'),
            "price": payload.get('price'),
            "rates": payload.get('rates'),
            "recurrence": payload.get('recurrence'),
            "include": payload.get("include"),
            # "revision_info": {},
            "to": payload.get('to'),
            "upfront": payload.get('upfront'),
            "prorated_start": payload.get('prorated_start'),
            "prorated_end": payload.get("prorated_end"),
            "prorated_termination": payload.get("prorated_termination")
        }

        return remove_keys_if_value_is_none(self.expected_response)


class PlansAssertionSteps(APIAssertionSteps):
    def __init__(self, instance: Plans):
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

        convert_date_fields_to_expected(expected_response, ['to', 'from'], method)

        assert_that(
            actual, has_entries(expected_response), "Response is not corresponded to expected"
        )

    def validate_post_response_is_correct(self, schema=PLANS_POST_SCHEMA, expected_response=None):
        super().validate_post_response_is_correct(
            schema=schema, expected_response=expected_response)

    def validate_get_response_is_correct(self, schema=PLANS_GET_SCHEMA, expected_response=None):
        super().validate_get_response_is_correct(
            schema=schema, expected_response=expected_response)
