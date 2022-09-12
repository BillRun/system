import random
from copy import deepcopy
from json import loads

from hamcrest import has_entries, assert_that

from core.common.entities import APIPath
from core.common.utils import (
    get_random_str, get_id_from_response, get_details,
    dumps_values, get_random_past_or_future_date, convert_date_to_str,
    remove_keys_for_missing_values, convert_date_fields_to_expected
)
from core.resoursces.schemas import PRODUCT_GET_SCHEMA
from core.testlib.API.base_api import BaseAPI
from steps.backend_assertion_steps.base_api_assertion_steps import APIAssertionSteps


class Products(BaseAPI):

    def __init__(self):
        super().__init__(path=APIPath.PRODUCTS)
        self.expected_response = None
        self.activity_type_label = None

    def compose_create_payload(
            self,
            invoice_label: str = None,
            add_to_retail: bool = None,
            from_date: str = None,
            tax_type: str = None,
            tax_taxation: str = None,
            tariff_category: str = None,
            pricing_method: str = None,
            description: str = None,
            key: str = None,
            to: str = None
    ):
        self.create_payload = {
            "invoice_label": invoice_label or get_random_str(),
            "add_to_retail": add_to_retail or True,
            "from": from_date or convert_date_to_str(get_random_past_or_future_date()),
            "tariff_category": tariff_category or random.choice(["retail", 'tariff']),
            "pricing_method": pricing_method or "tiered",
            "description": description or get_random_str(),
            "key": key or get_random_str().upper(),
            "to": to or convert_date_to_str(
                get_random_past_or_future_date(range_nearest_days=100, past=False, start_range_from=6)
            )
        }
        tax = {
            "tax": [
                {
                    "type": tax_type or "vat",
                    "taxation": tax_taxation or "global"
                }
            ]
        }
        activity_type = self.create_activity_type()
        rates = {
            "rates": {
                activity_type['label']: {
                    "BASE": {
                        "rate": [
                            {
                                "from": 0,
                                "to": "UNLIMITED",
                                "interval": 0, "price": "",
                                "uom_display": {
                                    "range": "minutes",
                                    "interval": "minutes"
                                }
                            }
                        ]
                    }
                }
            }
        }
        self.create_payload.update(tax)
        self.create_payload.update(rates)

        return self

    def compose_update_payload(
            self,
            invoice_label=None,
            tax_type=None,
            tax_taxation=None,
            description=None,
            pricing_method=None
    ):
        self.update_payload = {
            "invoice_label": invoice_label or get_random_str(),
            "tax": [
                {
                    "type": tax_type or None,
                    "taxation": tax_taxation or None
                }
            ],
            "description": description or get_random_str(),
            "pricing_method": pricing_method or None
        }
        remove_keys_for_missing_values(self.update_payload)

        return self

    def compose_close_payload(self, to=False, date_in_past=False):
        self.close_payload = {
            'to': convert_date_to_str(
                get_random_past_or_future_date(past=date_in_past)) if to else None
        }

        return self

    def compose_close_and_new_payload(
            self,
            invoice_label=None,
            from_date=None,
            tax_type=None,
            tax_taxation=None,
            description=None,
            pricing_method=None
    ):
        self.close_and_new_payload = self.compose_update_payload(
            invoice_label=invoice_label,
            tax_type=tax_type,
            tax_taxation=tax_taxation,
            description=description,
            pricing_method=pricing_method
        ).update_payload

        self.close_and_new_payload['from'] = from_date or convert_date_to_str(
            get_random_past_or_future_date(range_nearest_days=5, past=False)
        )

        return self

    def create_activity_type(self):
        self.activity_type_label = get_random_str()
        payload = {
            "category": "usage_types",
            "action": "set",
            "data": [
                {
                    "usage_type": self.activity_type_label,
                    "label": self.activity_type_label,
                    "property_type": "time",
                    "invoice_uom": "minutes",
                    "input_uom": "minutes"
                }
            ]
        }
        response = self.post('api/settings', data=dumps_values(payload))
        try:
            result = loads(response.json().get('input').get('data'))[0]
        except IndexError:
            raise Exception('Activity type is not created')

        return result

    def generate_expected_response_after_close_and_new(self, payload=None):
        payload = payload or self.create_payload
        payload.update(self.close_and_new_payload)
        #  after close and new product we receive product with new id in response
        return self.generate_expected_response(
            payload=payload, id_=get_id_from_response(self.close_and_new_response))

    def generate_expected_response_after_close(self, payload=None):
        payload = payload or self.create_payload
        payload.update(self.close_payload) if self.close_payload else None

        return self.generate_expected_response(payload=payload)

    def generate_expected_response_after_updating(self, payload=None):
        payload = payload or self.create_payload
        payload.update(self.update_payload)

        return self.generate_expected_response(payload=payload)

    def generate_expected_response(self, payload: dict = None, id_: str = None):
        payload = payload or self.create_payload
        self.expected_response = {
            "_id": {
                "$id": id_ or get_id_from_response(self.create_response)
            },
            "add_to_retail": payload.get('add_to_retail'),
            "description": payload.get('description'),
            "from": payload.get('from'),
            "invoice_label": payload.get('invoice_label'),
            "key": payload.get('key'),
            "pricing_method": payload.get('pricing_method'),
            "rates": payload.get('rates'),
            # "revision_info": REVISION_INFO[revision_info]  # can't predict for now
            "tariff_category": payload.get('tariff_category'),
            "tax": payload.get('tax'),
            "to": payload.get('to')
        }

        return self.expected_response


class ProductAssertionSteps(APIAssertionSteps):
    def __init__(self, instance: Products):
        super().__init__(instance=instance)

    def check_response(self, actual_response, expected_response, schema, method):
        self.check_json_schema_and_http_code_and_status(schema, actual_response)
        # response details has type array, so we should get 0 elem from array
        actual = deepcopy(get_details(actual_response))[0]  # fix
        expected_response = expected_response or self.instance.generate_expected_response()

        for response in (actual, expected_response):
            response.pop('revision_info', None)  # can't be predicted for now
            # we can not predict to param if it is not presented in payload
            response.pop('to', None) if not expected_response.get('to') else None   # for close method w/o to param

        convert_date_fields_to_expected(expected_response, fields=['from', 'to'], method='GET')

        assert_that(
            actual, has_entries(expected_response), "Response is not corresponded to expected"
        )

    def validate_get_response_is_correct(self, schema=PRODUCT_GET_SCHEMA, expected_response=None):
        super().validate_get_response_is_correct(schema, expected_response)
