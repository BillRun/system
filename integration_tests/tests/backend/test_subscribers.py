import allure
import pytest
from pytest_testrail.plugin import pytestrail

from core.common.helpers.api_helpers import get_id_from_response
from steps.backend_steps.subscribers_steps import Subscribers, SubscribersAssertionSteps


@pytestrail.case('C2674')
@allure.title('Create subscriber by API')
@allure.description('SUBSCRIBER-API-1')
@pytest.mark.smoke
def test_create_subscriber():
    subscriber = Subscribers()
    assertion_steps = SubscribersAssertionSteps(subscriber)

    subscriber.compose_create_payload().create()
    assertion_steps.validate_create_response_is_correct()

    subscriber.get_by_id()
    assertion_steps.validate_get_response_is_correct()


@pytestrail.case('C2675')
@allure.title('Update subscriber by API')
@allure.description('SUBSCRIBER-API-2')
@pytest.mark.smoke
def test_update_subscriber():
    subscriber = Subscribers()
    assertion_steps = SubscribersAssertionSteps(subscriber)

    subscriber.compose_create_payload().create()
    assertion_steps.validate_create_response_is_correct()

    subscriber.compose_update_payload().update()
    assertion_steps.check_update_response_is_successful()

    subscriber.get_by_id()
    assertion_steps.validate_get_response_is_correct(
        expected_response=subscriber.generate_expected_response_after_updating())


@pytestrail.case('C2676', 'C2691', 'C2692')
@allure.title('Close subscriber by API')
@allure.description('SUBSCRIBER-API-3')
@pytest.mark.parametrize('to', [
    True,  # 1, set random future date
    False,  # 2, w/o to param
    "past_date"  # 3, set random past date
])
@pytest.mark.smoke
def test_close_subscriber(to):
    subscriber = Subscribers()
    assertion_steps = SubscribersAssertionSteps(subscriber)

    subscriber.compose_create_payload().create()
    assertion_steps.check_post_response_is_successful()

    subscriber.compose_close_payload(
        to=to, date_in_past=False if to != 'past_date' else True).close()
    assertion_steps.check_close_response_is_successful()

    assertion_steps.check_object_revision_status()

    assertion_steps.validate_get_response_is_correct(
        expected_response=subscriber.generate_expected_response_after_close())


@pytestrail.case('C2677')
@allure.title('Close and new subscriber by API')
@allure.description('SUBSCRIBER-API-4')
@pytest.mark.smoke
def test_close_and_new_subscriber():
    subscriber = Subscribers()
    assertion_steps = SubscribersAssertionSteps(subscriber)

    get_id_from_response(subscriber.compose_create_payload().create())  # init revision
    assertion_steps.validate_create_response_is_correct()

    subscriber.get_by_id()
    assertion_steps.validate_get_response_is_correct()

    subscriber.compose_close_and_new_payload().close_and_new()
    assertion_steps.check_revision_has_new_to_date_after_close_and_new()
    assertion_steps.check_close_and_new_response_is_successful()

    subscriber.get_by_id(get_id_from_response(subscriber.close_and_new_response))  # new revision
    assertion_steps.validate_get_response_is_correct(
        expected_response=subscriber.generate_expected_response_after_close_and_new())


@pytestrail.case('C2678')
@allure.title('Delete subscriber by API')
@allure.description('SUBSCRIBER-API-5')
@pytest.mark.smoke
def test_delete_subscriber():
    subscriber = Subscribers()
    assertion_steps = SubscribersAssertionSteps(subscriber)

    subscriber.compose_create_payload().create()
    assertion_steps.check_post_response_is_successful()

    subscriber.delete()
    assertion_steps.check_object_is_deleted_successfully()


@pytest.mark.skip('https://billrun.atlassian.net/browse/BRCD-3932')
@pytestrail.case('C2711')
@allure.title('Permanentchange subscriber by API')
@allure.description('SUBSCRIBER-API-5')
@pytest.mark.smoke
def test_permanentchange_subscriber():
    subscriber = Subscribers()
    assertion_steps = SubscribersAssertionSteps(subscriber)

    subscriber.compose_create_payload().create()
    assertion_steps.validate_create_response_is_correct()

    subscriber.compose_permanent_change_payload().do_permanent_change()
    assertion_steps.check_permanent_change_is_successful(
        expected_objects=subscriber.generate_expected_objects_after_permanent_change())
