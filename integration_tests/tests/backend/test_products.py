import allure
import pytest
from pytest_testrail.plugin import pytestrail

from core.common.helpers.api_helpers import get_id_from_response
from core.common.helpers.utils import get_random_str
from steps.backend_steps.products_steps import Products, ProductAssertionSteps


@pytestrail.case('C2684')
@allure.title('Create product by API')
@allure.description('PRODUCT-API-1')
@pytest.mark.smoke
def test_create_product():
    product = Products()
    assertion_steps = ProductAssertionSteps(product)

    product.compose_create_payload().create()
    assertion_steps.check_post_response_is_successful()

    product.get_by_id()
    assertion_steps.validate_get_response_is_correct()


@pytestrail.case('C2685')
@allure.title('Update product by API')
@allure.description('PRODUCT-API-2')
@pytest.mark.smoke
def test_update_product():
    product = Products()
    assertion_steps = ProductAssertionSteps(product)

    product.compose_create_payload().create()
    ProductAssertionSteps(product).check_post_response_is_successful()

    params_to_update = {
        'invoice_label': get_random_str(), 'description': get_random_str(), 'pricing_method': 'volume'
    }
    product.compose_update_payload(**params_to_update).update()
    assertion_steps.check_update_response_is_successful()

    product.get_by_id()
    assertion_steps.validate_get_response_is_correct(
        expected_response=product.generate_expected_response_after_updating())


@pytestrail.case('C2686', 'C2709', 'C2710')
@allure.title('Close product by API')
@allure.description('PRODUCT-API-3')
#@pytest.mark.smoke
@pytest.mark.parametrize('to', [
    True,  # 1, set random future date
    False,  # 2, w/o to param
    "past_date"  # 3, set random past date
])
def test_close_product(to):
    product = Products()
    assertion_steps = ProductAssertionSteps(product)

    product.compose_create_payload().create()
    assertion_steps.check_post_response_is_successful()

    product.compose_close_payload(
        to=to, date_in_past=False if to != 'past_date' else True).close()
    assertion_steps.check_close_response_is_successful()

    assertion_steps.check_object_revision_status()

    assertion_steps.validate_get_response_is_correct(
        expected_response=product.generate_expected_response_after_close())


@pytest.mark.skip('https://billrun.atlassian.net/browse/BRCD-3887')
@pytestrail.case('C2687')
@allure.title('Close and new product by API')
@allure.description('PRODUCT-API-4')
@pytest.mark.smoke
def test_close_and_new_product():
    product = Products()
    assertion_steps = ProductAssertionSteps(product)

    get_id_from_response(product.compose_create_payload().create())  # init revision
    assertion_steps.check_post_response_is_successful()

    product.get_by_id()
    assertion_steps.validate_get_response_is_correct()

    product.compose_close_and_new_payload().close_and_new()
    assertion_steps.check_revision_has_new_to_date_after_close_and_new()
    assertion_steps.check_close_and_new_response_is_successful()

    product.get_by_id(get_id_from_response(product.close_and_new_response))
    assertion_steps.validate_get_response_is_correct(
        expected_response=product.generate_expected_response_after_close_and_new()
    )


@pytestrail.case('C2688')
@allure.title('Delete product by API')
@allure.description('PRODUCT-API-5')
@pytest.mark.smoke
def test_delete_product():
    product = Products()
    assertion_steps = ProductAssertionSteps(product)

    product.compose_create_payload().create()
    assertion_steps.check_post_response_is_successful()

    product.delete()
    assertion_steps.check_object_is_deleted_successfully()
