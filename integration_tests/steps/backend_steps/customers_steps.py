from copy import deepcopy

import allure
from hamcrest import assert_that, has_entries

from core.common.entities import APIPath
from core.common.helpers.utils import (
    get_random_str, FAKE, convert_date_fields_to_expected, get_random_past_or_future_date_str
)
from core.common.helpers.api_helpers import get_id_from_response, get_details, get_entity
from core.resoursces.schemas import CUSTOMER_GET_SCHEMA, CUSTOMER_POST_SCHEMA
from core.testlib.API.base_api import BaseAPI
from steps.assertion_steps.backend_assertion_steps.base_api_assertion_steps import APIAssertionSteps


class Customers(BaseAPI):
    def __init__(self):
        super().__init__(path=APIPath.CUSTOMERS)
        self.expected_response = None

    def compose_create_payload(
            self,
            lastname: str = None,
            firstname: str = None,
            from_date: str = None,
            to_date: str = None,
            zip_code: int = None,
            address: str = None,
            country: str = None,
            salutation: str = None,
            email: str = None,
            invoice_detailed: bool = False,  # https://billrun.atlassian.net/browse/BRCD-3826
            invoice_shipping_method: str = None,

    ):
        lastname = lastname or FAKE.last_name()
        firstname = firstname or FAKE.first_name()
        email = email or f'{lastname.lower()}_{firstname.lower()}@{FAKE.domain_name()}'

        self.create_payload = {
            "lastname": lastname,
            "firstname": firstname,
            "from": from_date or get_random_past_or_future_date_str(),
            "to": to_date or get_random_past_or_future_date_str(past=False),
            "zip_code": zip_code or FAKE.zipcode(),
            "address": address or FAKE.street_address(),
            "country": country or FAKE.country(),
            "salutation": salutation or get_random_str(),
            "email": email,
            "invoice_detailed": invoice_detailed,
            "invoice_shipping_method": invoice_shipping_method,  # email value

        }
        return self

    def generate_expected_response(self, payload=None):
        payload = payload or self.create_payload
        self.expected_response = {
            "_id": {
                "$id": get_id_from_response(self.create_response)
            },
            "aid": self.create_response.json().get('entity').get('aid'),
            "lastname": payload.get('lastname'),
            "firstname": payload.get('firstname'),
            "from": payload.get('from'),
            "to": payload.get('to'),
            "zip_code": payload.get('zip_code'),
            "address": payload.get('address'),
            "country": payload.get('country'),
            "salutation": payload.get('salutation'),
            "email": payload.get('email'),
            "type": "account",
        }

        return self.expected_response


class CustomerAssertionSteps(APIAssertionSteps):
    def __init__(self, instance: Customers):
        super().__init__(instance=instance)

    @allure.step('Check response is correct')
    def check_response(self, actual_response, expected_response, schema, method):
        self.check_json_schema_and_http_code_and_status(schema, actual_response)
        # response GET details  has type array, so we should get 0 elem from array
        actual = (
            deepcopy(get_details(actual_response))[0]
            if method == 'GET'
            else deepcopy(get_entity(actual_response))
        )
        expected_response = expected_response or self.instance.generate_expected_response()
        convert_date_fields_to_expected(expected_response, ['from', 'to'], method)

        assert_that(
            actual, has_entries(expected_response), "Response is not corresponded to expected"
        )

    def validate_get_response_is_correct(self, schema=CUSTOMER_GET_SCHEMA, expected_response=None):
        super().validate_get_response_is_correct(schema, expected_response)

    def validate_create_response_is_correct(self, schema=CUSTOMER_POST_SCHEMA, expected_response=None):
        super().validate_create_response_is_correct(schema, expected_response)

