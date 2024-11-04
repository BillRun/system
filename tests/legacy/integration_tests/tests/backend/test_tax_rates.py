import allure
import pytest
from pytest_testrail.plugin import pytestrail

from core.common.helpers.api_helpers import get_id_from_response
from steps.backend_steps.tax_rates_steps import TaxRates, TaxRatesAssertionSteps


@pytestrail.case('C2702')
@allure.title('Create tax rate by API')
@allure.description('TAX-RATE-API-1')
@pytest.mark.smoke
def test_create_tax_rate():
    tax_rate = TaxRates()
    assertion_steps = TaxRatesAssertionSteps(tax_rate)

    tax_rate.compose_create_payload().create()
    assertion_steps.validate_create_response_is_correct()

    tax_rate.get_by_id()
    assertion_steps.validate_get_response_is_correct()


@pytestrail.case('C2703')
@allure.title('Update tax rate by API')
@allure.description('TAX-RATE-API-2')
@pytest.mark.smoke
def test_update_tax_rate():
    tax_rate = TaxRates()
    assertion_steps = TaxRatesAssertionSteps(tax_rate)

    tax_rate.compose_create_payload().create()
    assertion_steps.validate_create_response_is_correct()

    tax_rate.compose_update_payload().update()
    assertion_steps.check_update_response_is_successful()

    tax_rate.get_by_id()
    assertion_steps.validate_get_response_is_correct(
        expected_response=tax_rate.generate_expected_response_after_updating()
    )


@pytestrail.case('C2704', 'C2705', 'C2706')
@allure.title('Close tax rate by API')
@allure.description('TAX-RATE-API-3')
@pytest.mark.smoke
@pytest.mark.parametrize('to', [
    True,  # 1, set random future date
    False,  # 2, w/o to param
    "past_date"  # 3, set random past date
])
def test_close_tax_rate(to):
    tax_rate = TaxRates()
    assertion_steps = TaxRatesAssertionSteps(tax_rate)

    tax_rate.compose_create_payload().create()
    assertion_steps.validate_create_response_is_correct()

    tax_rate.compose_close_payload(
        to=to, date_in_past=False if to != 'past_date' else True).close()
    assertion_steps.check_close_response_is_successful()

    assertion_steps.check_object_revision_status()

    assertion_steps.validate_get_response_is_correct(
        expected_response=tax_rate.generate_expected_response_after_close())


@pytestrail.case('C2707')
@allure.title('Close and new tax rate by API')
@allure.description('TAX-RATE-API-4')
@pytest.mark.smoke
def test_close_and_new_tax_rate():
    tax_rate = TaxRates()
    assertion_steps = TaxRatesAssertionSteps(tax_rate)

    get_id_from_response(tax_rate.compose_create_payload().create())  # init revision
    assertion_steps.validate_create_response_is_correct()

    tax_rate.get_by_id()
    assertion_steps.validate_create_response_is_correct()

    tax_rate.compose_close_and_new_payload().close_and_new()
    assertion_steps.check_revision_has_new_to_date_after_close_and_new()
    assertion_steps.check_close_and_new_response_is_successful()

    tax_rate.get_by_id(get_id_from_response(tax_rate.close_and_new_response))  # new revision
    assertion_steps.validate_get_response_is_correct(
        expected_response=tax_rate.generate_expected_response_after_close_and_new())


@pytestrail.case('C2708')
@allure.title('Delete tax rate by API')
@allure.description('TAX-RATE-API-5')
@pytest.mark.smoke
def test_delete_tax_rate():
    tax_rate = TaxRates()
    assertion_steps = TaxRatesAssertionSteps(tax_rate)

    tax_rate.compose_create_payload().create()
    assertion_steps.check_post_response_is_successful()

    tax_rate.delete()
    assertion_steps.check_object_is_deleted_successfully()
