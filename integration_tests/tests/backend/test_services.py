import allure
import pytest
from pytest_testrail.plugin import pytestrail

from core.common.helpers.api_helpers import get_id_from_response
from core.common.helpers.utils import get_random_str, get_true_or_false
from steps.backend_steps.services_steps import Services, ServicesAssertionSteps


@pytestrail.case('C2694')
@allure.title('Create service by API')
@allure.description('SERVICE-API-1')
@pytest.mark.smoke
def test_create_services():
    service = Services()
    assertion_steps = ServicesAssertionSteps(service)

    service.compose_create_payload().create()
    assertion_steps.validate_create_response_is_correct()

    service.get_by_id()
    assertion_steps.validate_get_response_is_correct()


@pytestrail.case('C2695')
@allure.title('Update service by API')
@allure.description('SERVICE-API-2')
@pytest.mark.smoke
def test_update_services():
    service = Services()
    assertion_steps = ServicesAssertionSteps(service)
    params_to_upd = {
        'description': get_random_str(),
        'prorated': get_true_or_false(),
        'quantitative': get_true_or_false(),
        'billing_frequency_type': 'monthly'
    }
    service.compose_create_payload().create()
    assertion_steps.validate_create_response_is_correct()

    service.compose_update_payload(**params_to_upd).update()
    assertion_steps.check_update_response_is_successful()

    service.get_by_id()
    assertion_steps.validate_get_response_is_correct(
        expected_response=service.generate_expected_response_after_updating())


@pytestrail.case('C2696', 'C2697', 'C2698')
@allure.title('Close service by API')
@allure.description('SERVICE-API-3')
@pytest.mark.smoke
@pytest.mark.parametrize('to', [
    True,  # 1, set random future date
    False,  # 2, w/o to param
    "past_date"  # 3, set random past date
])
def test_close_service(to):
    service = Services()
    assertion_steps = ServicesAssertionSteps(service)

    service.compose_create_payload().create()
    assertion_steps.validate_create_response_is_correct()

    service.compose_close_payload(
        to=to, date_in_past=False if to != 'past_date' else True).close()
    assertion_steps.check_close_response_is_successful()

    assertion_steps.check_object_revision_status()

    assertion_steps.validate_get_response_is_correct(
        expected_response=service.generate_expected_response_after_close()
    )


@pytestrail.case('C2699')
@allure.title('Close and new service by API')
@allure.description('SERVICE-API-4')
@pytest.mark.smoke
def test_close_and_new_service():
    service = Services()
    assertion_steps = ServicesAssertionSteps(service)

    get_id_from_response(service.compose_create_payload().create())  # init revision
    assertion_steps.validate_create_response_is_correct()

    service.get_by_id()
    assertion_steps.validate_get_response_is_correct()

    service.compose_close_and_new_payload().close_and_new()
    assertion_steps.check_revision_has_new_to_date_after_close_and_new()
    assertion_steps.check_close_and_new_response_is_successful()

    service.get_by_id(get_id_from_response(service.close_and_new_response))
    assertion_steps.validate_get_response_is_correct(
        expected_response=service.generate_expected_response_after_close_and_new()
    )


@pytestrail.case('C2700')
@allure.title('Delete service by API')
@allure.description('SERVICE-API-5')
@pytest.mark.smoke
def test_delete_service():
    service = Services()
    assertion_steps = ServicesAssertionSteps(service)

    service.compose_create_payload().create()
    assertion_steps.validate_create_response_is_correct()

    service.delete()
    assertion_steps.check_object_is_deleted_successfully()
