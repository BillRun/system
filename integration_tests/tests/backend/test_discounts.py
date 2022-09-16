import pytest

from core.common.helpers.api_helpers import get_id_from_response
from steps.backend_steps.discounts_steps import Discounts, DiscountAssertionSteps


@pytest.mark.smoke
@pytest.mark.parametrize('discount_type', [
    "monetary",
    "percentage"
])
def test_create_discount(discount_type):
    discount = Discounts()
    assertion_steps = DiscountAssertionSteps(discount)

    discount.compose_create_payload(discount_type=discount_type).create()
    assertion_steps.validate_post_response_is_correct()

    discount.get_by_id()
    assertion_steps.validate_get_response_is_correct()


@pytest.mark.smoke
def test_update_discount():
    discount = Discounts()
    assertion_steps = DiscountAssertionSteps(discount)

    discount.compose_create_payload().create()
    assertion_steps.validate_post_response_is_correct()

    discount.compose_update_payload().update()
    assertion_steps.check_update_response_is_successful()

    discount.get_by_id()
    assertion_steps.validate_get_response_is_correct(
        expected_response=discount.generate_expected_response_after_updating())


@pytest.mark.smoke
@pytest.mark.parametrize('to', [
    True,  # set random future date
    False,  # w/o to param
    "past_date"  # set random past date
])
def test_close_discount(to):
    discount = Discounts()
    assertion_steps = DiscountAssertionSteps(discount)

    discount.compose_create_payload().create()
    assertion_steps.validate_post_response_is_correct()

    discount.compose_close_payload(
        to=to, date_in_past=False if to != 'past_date' else True).close()
    assertion_steps.check_close_response_is_successful()

    assertion_steps.check_object_revision_status()

    assertion_steps.validate_get_response_is_correct(
        expected_response=discount.generate_expected_response_after_close())


@pytest.mark.smoke
def test_close_and_new_discount():
    discount = Discounts()
    assertion_steps = DiscountAssertionSteps(discount)

    get_id_from_response(discount.compose_create_payload().create())  # init revision
    assertion_steps.check_post_response_is_successful()

    discount.get_by_id()
    assertion_steps.validate_get_response_is_correct()

    new_revision_id = get_id_from_response(discount.compose_close_and_new_payload().close_and_new())
    assertion_steps.check_object_has_new_to_date_after_close_and_new()

    discount.get_by_id(new_revision_id)
    assertion_steps.validate_get_response_is_correct(
        expected_response=discount.generate_expected_response_after_close_and_new()
    )


@pytest.mark.smoke
def test_delete_product():
    discount = Discounts()
    assertion_steps = DiscountAssertionSteps(discount)

    discount.compose_create_payload().create()
    assertion_steps.check_post_response_is_successful()

    discount.delete()
    assertion_steps.check_object_is_deleted_successfully()
