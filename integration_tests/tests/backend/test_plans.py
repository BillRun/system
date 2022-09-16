import pytest
from pytest_testrail.plugin import pytestrail

from core.common.helpers.api_helpers import get_id_from_response
from steps.backend_steps.plans_steps import Plans, PlansAssertionSteps


@pytestrail.case('C2679')
@pytest.mark.smoke
@pytest.mark.parametrize('connection_type', ['postpaid'])
def test_create_plan(connection_type):
    plan = Plans()
    assertion_steps = PlansAssertionSteps(plan)

    plan.compose_create_payload(connection_type=connection_type).create()
    assertion_steps.validate_post_response_is_correct()

    plan.get_by_id()
    assertion_steps.validate_get_response_is_correct()


@pytestrail.case('C2680')
@pytest.mark.smoke
@pytest.mark.parametrize('connection_type', ['postpaid'])
def test_update_plan(connection_type):
    plan = Plans()
    assertion_steps = PlansAssertionSteps(plan)

    plan.compose_create_payload(connection_type=connection_type).create()
    assertion_steps.validate_post_response_is_correct()

    plan.compose_update_payload().update()
    assertion_steps.check_update_response_is_successful()

    plan.get_by_id()
    assertion_steps.validate_get_response_is_correct(
        expected_response=plan.generate_expected_response_after_updating())


@pytestrail.case('C2681', 'C2689', 'C2690')
@pytest.mark.smoke
@pytest.mark.parametrize('connection_type', ['postpaid'])
@pytest.mark.parametrize('to', [
    True,  # set random future date
    False,  # w/o to param
    "past_date"  # set random past date
])
def test_close_plan(connection_type, to):
    plan = Plans()
    assertion_steps = PlansAssertionSteps(plan)

    plan.compose_create_payload(connection_type=connection_type).create()
    assertion_steps.validate_post_response_is_correct()

    plan.compose_close_payload(
        to=to, date_in_past=False if to != 'past_date' else True).close()
    assertion_steps.check_close_response_is_successful()

    assertion_steps.check_object_revision_status()

    assertion_steps.validate_get_response_is_correct(
        expected_response=plan.generate_expected_response_after_close())


@pytestrail.case('C2682')
@pytest.mark.smoke
@pytest.mark.parametrize('connection_type', ['postpaid'])
def test_close_and_new_plan(connection_type):
    plan = Plans()
    assertion_steps = PlansAssertionSteps(plan)

    get_id_from_response(plan.compose_create_payload(connection_type=connection_type).create())  # init revision
    assertion_steps.validate_post_response_is_correct()

    plan.get_by_id()
    assertion_steps.validate_get_response_is_correct()

    plan.compose_close_and_new_payload().close_and_new()
    assertion_steps.check_revision_has_new_to_date_after_close_and_new()
    assertion_steps.check_close_and_new_response_is_successful()

    plan.get_by_id(get_id_from_response(plan.close_and_new_response))  # new revision
    assertion_steps.validate_get_response_is_correct(
        expected_response=plan.generate_expected_response_after_close_and_new())


@pytestrail.case('C2683')
@pytest.mark.smoke
@pytest.mark.parametrize('connection_type', ['postpaid'])
def test_delete_plan(connection_type):
    plan = Plans()
    assertion_steps = PlansAssertionSteps(plan)

    plan.compose_create_payload(connection_type=connection_type).create()
    assertion_steps.check_post_response_is_successful()

    plan.delete()
    assertion_steps.check_object_is_deleted_successfully()
