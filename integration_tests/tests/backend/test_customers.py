import allure
import pytest
from pytest_testrail.plugin import pytestrail

from core.common.helpers.utils import skip_test
from steps.backend_steps.customers_steps import Customers, CustomerAssertionSteps


@pytestrail.case('C2672', 'C2693')
@allure.title('Create customer by API')
@allure.description('CUSTOMERS-API-1')
@pytest.mark.parametrize('optional_params', [
    {'invoice_detailed': False, 'invoice_shipping_method': 'email'},  # 1
    skip_test(case={'invoice_detailed': True, 'invoice_shipping_method': None},  # 2
              reason='https://billrun.atlassian.net/browse/BRCD-3826')
])
@pytest.mark.smoke
def test_create_customer(optional_params):
    customer = Customers()
    assertion_steps = CustomerAssertionSteps(customer)

    customer.compose_create_payload(**optional_params).create()
    assertion_steps.validate_create_response_is_correct()

    customer.get_by_id()
    assertion_steps.validate_get_response_is_correct()


@allure.title('Delete customer by API')
@allure.description('CUSTOMERS-API-2')
@pytestrail.case('C2673')
@pytest.mark.smoke
def test_delete_customer():
    customer = Customers()
    assertion_steps = CustomerAssertionSteps(customer)

    customer.compose_create_payload().create()
    assertion_steps.validate_create_response_is_correct()

    customer.delete()
    assertion_steps.check_object_is_deleted_successfully()
