from copy import deepcopy

from hamcrest import assert_that, has_entries

from core.common.entities import APIPath
from core.common.utils import (
    FAKE, convert_date_to_str,
    get_random_past_or_future_date, get_details,
    get_id_from_response, get_entity, convert_date_fields_to_expected,
    convert_date_to_date_obj, remove_keys_if_value_is_none
)
from core.resoursces.schemas import SUBSCRIBER_GET_SCHEMA, SUBSCRIBER_POST_SCHEMA
from core.testlib.API.base_api import BaseAPI
from steps.backend_assertion_steps.base_api_assertion_steps import APIAssertionSteps
from steps.backend_steps.customers_steps import Customers
from steps.backend_steps.plans_steps import Plans


class Subscribers(BaseAPI):

    def __init__(self):
        super().__init__(path=APIPath.SUBSCRIBERS)
        self.plan = None
        self.customer = None
        self.expected_response = None

    def compose_create_payload(
            self,
            aid: int = None,
            plan: str = None,
            plan_type: str = None,
            firstname: str = None,
            lastname: str = None,
            from_date: str = None,
            to: str = None,
            play: str = None,
            address: str = None,
            country: str = None,
            services: list = None
    ):
        self.create_payload = {
            "aid": aid or self.create_customer()['aid'],
            "plan": plan or self.create_plan(plan_type or 'postpaid')['name'],  # for now support only postpaid
            "firstname": firstname or FAKE.first_name(),
            "lastname": lastname or FAKE.last_name(),
            "from": from_date or convert_date_to_str(get_random_past_or_future_date()),
            "to": to or convert_date_to_str(get_random_past_or_future_date(past=False)),
            "play": play or 'Default',
            "address": address or FAKE.street_address(),
            "country": country or FAKE.country(),
            "services": services or []
        }

        return self

    def compose_update_payload(
            self,
            aid=None,
            plan=None,
            plan_type=None,
            firstname=None,
            lastname=None,
            address=None,
            country=None,
            services: list = None
    ):
        self.update_payload = {
            "aid": aid or self.create_customer()['aid'],
            "plan": plan or self.create_plan(plan_type or 'postpaid')['name'],  # for now support only postpaid
            "firstname": firstname or FAKE.first_name(),
            "lastname": lastname or FAKE.last_name(),
            "address": address or FAKE.street_address(),
            "country": country or FAKE.country(),
            "services": services
        }
        remove_keys_if_value_is_none(self.update_payload)

        return self

    def compose_close_payload(self, to=False, date_in_past=False):
        self.close_payload = {
            'to': convert_date_to_str(
                get_random_past_or_future_date(past=date_in_past)) if to else None
        }

        return self

    def compose_close_and_new_payload(
            self,
            from_date=None,
            aid=None,
            plan=None,
            plan_type=None,
            firstname=None,
            lastname=None,
            address=None,
            country=None,
            services: list = None
    ):
        self.close_and_new_payload = self.compose_update_payload(
            aid=aid,
            plan=plan,
            plan_type=plan_type,
            firstname=firstname,
            lastname=lastname,
            address=address,
            country=country,
            services=services
        ).update_payload

        self.close_and_new_payload['from'] = from_date or convert_date_to_str(
            get_random_past_or_future_date(range_nearest_days=5, past=False)
        )

        return self

    def create_customer(self):
        self.customer = get_entity(Customers().compose_create_payload().create())
        return self.customer

    def create_plan(self, type_):
        self.plan = get_entity(Plans().compose_create_payload(connection_type=type_).create())
        return self.plan

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
            "type": "subscriber",
            "aid": payload.get('aid'),
            "plan": payload.get('plan'),
            "firstname": payload.get('firstname'),
            "lastname": payload.get('lastname'),
            "from": payload.get('from'),
            "to": payload.get('to'),
            "play": payload.get('play'),
            "address": payload.get('address'),
            "country": payload.get('country'),
            "services": payload.get('services'),
            "plan_activation": convert_date_to_date_obj(payload.get('from')),
            "deactivation_date": convert_date_to_date_obj(payload.get('to')) if payload.get('to') else None,
            "activation_date": convert_date_to_date_obj(payload.get('from')),
            "plan_deactivation": convert_date_to_date_obj(payload.get('to')) if payload.get('to') else None
        }

        return self.expected_response


class SubscribersAssertionSteps(APIAssertionSteps):
    def __init__(self, instance: Subscribers):
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
            # response.pop('to') if not expected_response.get('to') else None

        convert_date_fields_to_expected(expected_response, ['from', 'to'], method)

        assert_that(
            actual, has_entries(expected_response), "Response is not corresponded to expected"
        )

    def validate_get_response_is_correct(self, schema=SUBSCRIBER_GET_SCHEMA, expected_response=None):
        super().validate_get_response_is_correct(schema, expected_response)

    def validate_post_response_is_correct(self, schema=SUBSCRIBER_POST_SCHEMA, expected_response=None):
        super().validate_post_response_is_correct(schema, expected_response)
