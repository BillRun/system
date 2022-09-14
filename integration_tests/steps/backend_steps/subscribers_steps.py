from copy import deepcopy

from hamcrest import assert_that, has_entries
from addict import Dict

from core.common.entities import APIPath
from core.common.utils import (
    FAKE, convert_date_to_str,
    get_details,
    get_id_from_response, get_entity, convert_date_fields_to_expected,
    convert_date_to_date_obj, remove_keys_if_value_is_none,
    get_random_date_between_dates, get_random_past_or_future_date_str,
    dumps_values, api_repeater, get_id_from_obj, remove_keys_in_nested_dict
)
from core.resoursces.schemas import SUBSCRIBER_GET_SCHEMA, SUBSCRIBER_POST_SCHEMA
from core.testlib.API.base_api import BaseAPI
from core.testlib.common_assertions import check_http_code_and_status
from steps.backend_assertion_steps.base_api_assertion_steps import APIAssertionSteps
from steps.backend_steps.customers_steps import Customers
from steps.backend_steps.plans_steps import Plans
from steps.backend_steps.services_steps import Services


class Subscribers(BaseAPI):

    def __init__(self):
        super().__init__(path=APIPath.SUBSCRIBERS)
        self.service = None
        self.permanent_change_payload = None
        self.permanent_change_response = None
        self.permanent_change_query = None
        self.permanent_change_options = None
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
            "from": from_date or get_random_past_or_future_date_str(),
            "to": to or get_random_past_or_future_date_str(past=False, start_range_from=20),
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
            'to': get_random_past_or_future_date_str(past=date_in_past) if to else None
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

        self.close_and_new_payload['from'] = from_date or get_random_past_or_future_date_str(
            range_nearest_days=5, past=False
        )

        return self

    def compose_permanent_change_payload(self, from_date=None, to=None):
        # need to use date value between from and to
        if not from_date:
            from_date = get_random_date_between_dates(
                start_date=self.create_payload.get('from'),
                end_date=self.create_payload.get('to')
            )
        self.permanent_change_payload = {
            'from': convert_date_to_str(from_date),
            'to': to or None
        }
        remove_keys_if_value_is_none(self.permanent_change_payload)

        return self

    def _compose_permanent_change_query(self, effective_date):
        self.permanent_change_query = {
            "type": "subscriber",
            "sid": get_entity(self.create_response).get('sid'),
            "effective_date": effective_date,
        }
        return self.permanent_change_query

    def _compose_permanent_change_options(self):
        self.create_service()
        self.permanent_change_options = {
            "push_fields": [{
                "field_name": "services", "field_values": [{
                    "name": self.service.get('name'),
                    "from": self.service.get('from'),
                    "to": self.service.get('to')
                }]
            }]
        }
        return self.permanent_change_options

    def do_permanent_change(self, payload=None):
        payload = payload or self.permanent_change_payload

        payload = self._add_update_key(payload)
        payload['query'] = self._compose_permanent_change_query(payload['update']['from'])
        payload['options'] = self._compose_permanent_change_options()

        # occasionally we get error "Service was not found", so we use 1 sec waiter
        self.permanent_change_response = api_repeater(lambda: self.post(
            f'{self.path}/permanentchange', data=dumps_values(payload)
        ))

        return self.permanent_change_response

    def create_customer(self):
        self.customer = get_entity(Customers().compose_create_payload().create())
        return self.customer

    def create_plan(self, type_: str):
        self.plan = get_entity(Plans().compose_create_payload(connection_type=type_).create())
        return self.plan

    def create_service(self):
        self.service = get_entity(Services().compose_create_payload().create())
        return self.service

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
        #  after close and new we receive subscriber revision in response
        return self.generate_expected_response(
            payload=payload, id_=get_id_from_response(self.close_and_new_response))

    def generate_expected_objects_after_permanent_change(self, payload=None) -> Dict:
        payload = payload or self.create_payload

        def generate_init_revision_obj():
            payload_copy = deepcopy(payload)
            # "to" is changed to "from" from permanent_change_payload for init revision
            payload_copy['to'] = self.permanent_change_payload['from']

            return self.generate_expected_response(payload_copy)

        def generate_new_revision_obj():
            updated_params = {}
            payload_copy = deepcopy(payload)
            # "from" is changed to "from" from permanent_change_payload for new revision
            payload_copy.update(self.permanent_change_payload)

            for param in self.permanent_change_options['push_fields']:
                field_name = param.get('field_name')
                field_values = param.get('field_values')
                for value in field_values:
                    updated_params[field_name] = [value]
            # also update new revision with permanent_change_options
            payload_copy.update(updated_params)

            return self.generate_expected_response(payload_copy)

        return Dict(
            init_revision=generate_init_revision_obj(), new_revision=generate_new_revision_obj()
        )

    def generate_expected_response(self, payload: dict = None, id_: str = None):
        payload = payload or self.create_payload
        # From - To > the subscriber configuration revision (subscriber can have many revisions)
        # Activation date - deactivation date > this is the time the subscriber is active
        # Plan activation date - plan deactivation date > the time range that the plan was/is set for the subscriber
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
            "plan_activation": convert_date_to_date_obj(
                self.create_payload.get('from')),
            "deactivation_date": convert_date_to_date_obj(
                self.create_payload.get('to')) if payload.get('to') else None,
            "activation_date": convert_date_to_date_obj(
                self.create_payload.get('from')),
            "plan_deactivation": convert_date_to_date_obj(
                self.create_payload.get('to')) if payload.get('to') else None
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
            # can't be predicted for now
            remove_keys_in_nested_dict(response, ['service_id', 'creation_time'])
            # for close method w/o to param
            if not expected_response.get('to'):
                response.pop('to')
                response.pop('deactivation_date')
                response.pop('plan_deactivation')

        convert_date_fields_to_expected(expected_response, ['from', 'to'], method)

        assert_that(
            actual, has_entries(expected_response), "Response is not corresponded to expected"
        )

    def validate_get_response_is_correct(self, schema=SUBSCRIBER_GET_SCHEMA, expected_response=None):
        super().validate_get_response_is_correct(schema, expected_response)

    def validate_post_response_is_correct(self, schema=SUBSCRIBER_POST_SCHEMA, expected_response=None):
        super().validate_post_response_is_correct(schema, expected_response)

    def check_permanent_change_is_successful(self, expected_objects):
        check_http_code_and_status(self.instance.permanent_change_response)
        query = {
            'aid': get_entity(self.instance.create_response)['aid'],
            'sid': get_entity(self.instance.create_response)['sid']
        }
        self.instance.get_by_query(**query)

        # need to determine new revision's id
        for subscriber in get_details(self.instance.get_response):
            if get_id_from_obj(subscriber) != get_id_from_obj(expected_objects.init_revision):
                expected_objects.new_revision['_id']["$id"] = get_id_from_obj(subscriber)

        self.instance.get_by_id()
        self.validate_get_response_is_correct(expected_response=expected_objects.init_revision)

        self.instance.get_by_id(get_id_from_obj(expected_objects.new_revision))
        self.validate_get_response_is_correct(expected_response=expected_objects.new_revision)
