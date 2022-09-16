import random
from copy import deepcopy

from hamcrest import assert_that, has_entries

from core.common.entities import APIPath
from core.common.helpers.api_helpers import get_id_from_response, get_details, get_entity
from core.common.helpers.utils import (
    convert_date_fields_to_expected,
    remove_keys_if_value_is_none,
    get_random_past_or_future_date_str,
    get_random_str
)
from core.resoursces.discount_conditions import create_all_types_conditions, create_random_customer_conditions
from core.resoursces.schemas import DISCOUNT_POST_SCHEMAS, DISCOUNT_GET_SCHEMAS
from core.testlib.API.base_api import BaseAPI
from steps.backend_assertion_steps.base_api_assertion_steps import APIAssertionSteps
from steps.backend_steps.plans_steps import Plans
from steps.backend_steps.services_steps import Services


class Discounts(BaseAPI):

    def __init__(self):
        super().__init__(path=APIPath.DISCOUNTS)
        self.service = None
        self.plan = None
        self.expected_response = None

    def compose_create_payload(
            self,
            description=None,
            key=None,
            proration=None,
            priority=None,
            min_subscribers=None,
            max_subscribers=None,
            from_date=None,
            to=None,
            discount_type=None,
            cycles=None,
            plan=None,
            plan_value=None,
            service=None,
            service_value=None
    ):
        params = create_all_types_conditions(
            min_subscribers=min_subscribers or random.choice(["", random.randint(1, 50)]),
            max_subscribers=max_subscribers or random.choice(["", random.randint(50, 200)]),
            cycles=cycles or random.choice([None, random.randint(1, 50)])
        )

        self.create_payload = {
            "description": description or get_random_str(),
            "key": key or get_random_str().upper(),
            "proration": proration or random.choice(['inherited', 'no']),
            "priority": str(priority or random.choice([None, random.randint(1, 10)])),
            "from": from_date or get_random_past_or_future_date_str(),
            "to": to or get_random_past_or_future_date_str(past=False, range_nearest_days=50),
            "type": discount_type or random.choice(["monetary", "percentage"]),
            "params": params,
            "subject": self.create_subject(plan, plan_value, service, service_value)
        }

        return self

    def compose_update_payload(
            self,
            description=None,
            proration=None,
            priority=None,
            min_subscribers=None,
            max_subscribers=None,
            discount_type=None,
            plan=None,
            plan_value=None,
            service=None,
            service_value=None
    ):
        params = create_random_customer_conditions(
            min_subscribers=min_subscribers or random.choice(["", random.randint(1, 50)]),
            max_subscribers=max_subscribers or random.choice(["", random.randint(50, 200)]),
            count=random.randint(1, 3)
        )

        self.update_payload = {
            "description": description or get_random_str(),
            "proration": proration or random.choice(['inherited', 'no']),
            "priority": str(priority or random.choice([None, random.randint(1, 10)])),
            "type": discount_type or random.choice(["monetary", "percentage"]),
            "params": params,
            "subject": self.create_subject(plan, plan_value, service, service_value)
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
            proration=None,
            priority=None,
            min_subscribers=None,
            max_subscribers=None,
            discount_type=None,
            plan=None,
            plan_value=None,
            service=None,
            service_value=None
    ):
        self.close_and_new_payload = self.compose_update_payload(
            description=description,
            proration=proration,
            priority=priority,
            min_subscribers=min_subscribers,
            max_subscribers=max_subscribers,
            discount_type=discount_type,
            plan=plan,
            plan_value=plan_value,
            service=service,
            service_value=service_value
        ).update_payload

        self.close_and_new_payload['from'] = from_date or get_random_past_or_future_date_str(
            range_nearest_days=5, past=False
        )
        return self

    def _create_plan(self, type_: str):
        self.plan = Plans().compose_create_payload(connection_type=type_).create()
        return get_entity(self.plan)

    def _create_service(self):
        self.service = Services().compose_create_payload().create()
        return get_entity(self.service)

    def create_subject(
            self, plan=None, plan_value=None, service=None, service_value=None
    ):
        return {
            "plan": {
                plan or self._create_plan('postpaid')['name']: {
                    "value": plan_value or random.randint(1, 100)}
            },
            "service": {
                service or self._create_service()['name']: {
                    "value": service_value or random.randint(1, 100)}
            }
        }

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
        #  after close and new product we receive new discount's revision
        return self.generate_expected_response(
            payload=payload, id_=get_id_from_response(self.close_and_new_response))

    def generate_expected_response(self, payload: dict = None, id_: str = None):
        payload = payload or self.create_payload
        self.expected_response = {
            "_id": {
                "$id": id_ or get_id_from_response(self.create_response)
            },
            "description": payload.get("description"),
            "key": payload.get("key"),
            "proration": payload.get("proration"),
            "priority": payload.get("priority"),
            "from": payload.get("from"),
            "to": payload.get("to"),
            "type": payload.get("type"),
            "params": payload.get("params"),
            "subject": payload.get("subject")
        }

        return self.expected_response


class DiscountAssertionSteps(APIAssertionSteps):

    def __init__(self, instance=Discounts):
        super().__init__(instance)

    def check_response(self, actual_response, expected_response, schema, method):
        self.check_json_schema_and_http_code_and_status(schema, actual_response)

        actual = (
            deepcopy(get_details(actual_response))[0]
            if method == 'GET'
            else deepcopy(get_entity(actual_response))
        )
        expected_response = expected_response or self.instance.generate_expected_response()

        # we can not predict to param if it is not presented in payload
        if not expected_response.get('to'):
            for response in (actual, expected_response):
                response.pop('to', None)  # for close method w/o to param

        convert_date_fields_to_expected(expected_response, ['to', 'from'], method)

        assert_that(
            actual, has_entries(expected_response), "Response is not corresponded to expected"
        )

    def validate_post_response_is_correct(self, schema=DISCOUNT_POST_SCHEMAS, expected_response=None):
        super().validate_post_response_is_correct(
            schema=schema, expected_response=expected_response)

    def validate_get_response_is_correct(self, schema=DISCOUNT_GET_SCHEMAS, expected_response=None):
        super().validate_get_response_is_correct(
            schema=schema, expected_response=expected_response)
