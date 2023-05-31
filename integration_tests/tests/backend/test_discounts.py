import allure
import pytest
from pytest_testrail.plugin import pytestrail

from core.common.helpers.api_helpers import get_id_from_response
from steps.backend_steps.discounts_steps import Discounts, DiscountAssertionSteps


@pytestrail.case('C2712', 'C2713')
@allure.title('Create discount by API')
@allure.description('DISCOUNT-API-1')
@pytest.mark.smoke
@pytest.mark.parametrize('discount_type', [
    "monetary",  # 1
    "percentage"  # 2
])
def test_create_discount(discount_type):
    discount = Discounts()
    assertion_steps = DiscountAssertionSteps(discount)

    discount.compose_create_payload(discount_type=discount_type).create()
    assertion_steps.validate_create_response_is_correct()

    discount.get_by_id()
    assertion_steps.validate_get_response_is_correct()


@pytestrail.case('C2714')
@allure.title('Update discount by API')
@allure.description('DISCOUNT-API-2')
@pytest.mark.smoke
def test_update_discount():
    discount = Discounts()
    assertion_steps = DiscountAssertionSteps(discount)

    discount.compose_create_payload().create()
    assertion_steps.validate_create_response_is_correct()

    discount.compose_update_payload().update()
    assertion_steps.check_update_response_is_successful()

    discount.get_by_id()
    assertion_steps.validate_get_response_is_correct(
        expected_response=discount.generate_expected_response_after_updating())


@pytestrail.case('C2715', 'C2716', 'C2717')
@allure.title('Close discount by API')
@allure.description('DISCOUNT-API-3')
@pytest.mark.smoke
@pytest.mark.parametrize('to', [
    True,  # 1, set random future date
    False,  # 2, w/o to param
    "past_date"  # 3, set random past date
])
def test_close_discount(to):
    discount = Discounts()
    assertion_steps = DiscountAssertionSteps(discount)

    discount.compose_create_payload().create()
    assertion_steps.validate_create_response_is_correct()

    discount.compose_close_payload(
        to=to, date_in_past=False if to != 'past_date' else True).close()
    assertion_steps.check_close_response_is_successful()

    assertion_steps.check_object_revision_status()

    assertion_steps.validate_get_response_is_correct(
        expected_response=discount.generate_expected_response_after_close())


@pytestrail.case('C2718')
@allure.title('Close and new discount by API')
@allure.description('DISCOUNT-API-4')
def test_close_and_new_discount():
    discount = Discounts()
    assertion_steps = DiscountAssertionSteps(discount)

    get_id_from_response(discount.compose_create_payload().create())  # init revision
    assertion_steps.check_post_response_is_successful()

    discount.get_by_id()
    assertion_steps.validate_get_response_is_correct()

    discount.compose_close_and_new_payload().close_and_new()
    assertion_steps.check_revision_has_new_to_date_after_close_and_new()
    assertion_steps.check_close_and_new_response_is_successful()

    discount.get_by_id(get_id_from_response(discount.close_and_new_response))
    assertion_steps.validate_get_response_is_correct(
        expected_response=discount.generate_expected_response_after_close_and_new()
    )


@pytestrail.case('C2719')
@allure.title('Delete discount by API')
@allure.description('DISCOUNT-API-5')
@pytest.mark.smoke
def test_delete_discount():
    discount = Discounts()
    assertion_steps = DiscountAssertionSteps(discount)

    discount.compose_create_payload().create()
    assertion_steps.check_post_response_is_successful()

    discount.delete()
    assertion_steps.check_object_is_deleted_successfully()
