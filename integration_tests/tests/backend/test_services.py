import pytest
from pytest_testrail.plugin import pytestrail

from core.common.entities import RevisionStatus
from core.common.utils import get_id_from_response, get_random_str, get_true_or_false
from steps.backend_steps.services_steps import Services, ServicesAssertionSteps


@pytestrail.case('C2694')
@pytest.mark.smoke
def test_create_services():
    service = Services()
    assertion_steps = ServicesAssertionSteps(service)

    service.compose_create_payload().create()
    assertion_steps.validate_post_response_is_correct()

    service.get_by_id()
    assertion_steps.validate_get_response_is_correct()


@pytestrail.case('C2695')
@pytest.mark.smoke
def test_update_services():
    service = Services()
    assertion_steps = ServicesAssertionSteps(service)
    params_to_upd = {
        'description': get_random_str(),
        'prorated': get_true_or_false(),
        'quantitative': get_true_or_false()
    }
    service.compose_create_payload().create()
    assertion_steps.validate_post_response_is_correct()

    service.compose_update_payload(**params_to_upd).update()
    assertion_steps.check_update_response_is_successful()

    service.get_by_id()
    assertion_steps.validate_get_response_is_correct(
        expected_response=service.generate_expected_response_after_updating())


@pytestrail.case('C2696', 'C2697', 'C2698')
@pytest.mark.smoke
@pytest.mark.parametrize('to', [
    True,  # set random future date
    False,  # w/o to param
    "past_date"  # set random past date
])
def test_close_service(to):
    service = Services()
    assertion_steps = ServicesAssertionSteps(service)

    service.compose_create_payload().create()
    assertion_steps.validate_post_response_is_correct()

    service.compose_close_payload(
        to=to, date_in_past=False if to != 'past_date' else True).close()
    assertion_steps.check_close_response_is_successful()

    assertion_steps.check_object_revision_status(
        RevisionStatus.EXPIRED if to in [False, "past_date"] else RevisionStatus.ACTIVE
    )
    assertion_steps.validate_get_response_is_correct(
        expected_response=service.generate_expected_response_after_close()
    )


@pytestrail.case('C2699')
@pytest.mark.smoke
def test_close_and_new_service():
    service = Services()
    assertion_steps = ServicesAssertionSteps(service)

    get_id_from_response(service.compose_create_payload().create())  # init revision
    assertion_steps.validate_post_response_is_correct()

    service.get_by_id()
    assertion_steps.validate_get_response_is_correct()

    new_revision_id = get_id_from_response(service.compose_close_and_new_payload().close_and_new())
    assertion_steps.check_object_has_new_to_date_after_close_and_new()

    service.get_by_id(new_revision_id)
    assertion_steps.validate_get_response_is_correct(
        expected_response=service.generate_expected_response_after_close_and_new()
    )


@pytestrail.case('C2700')
@pytest.mark.smoke
def test_delete_service():
    service = Services()
    assertion_steps = ServicesAssertionSteps(service)

    service.compose_create_payload().create()
    assertion_steps.validate_post_response_is_correct()

    service.delete()
    assertion_steps.check_object_is_deleted_successfully()
