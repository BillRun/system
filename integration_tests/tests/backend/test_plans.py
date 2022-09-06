import pytest

from core.common.entities import RevisionStatus
from core.common.utils import get_id_from_response
from steps.backend_steps.plans_steps import Plans, PlansAssertionSteps


@pytest.mark.smoke
@pytest.mark.parametrize('connection_type', [
    'postpaid'
    # 'prepaid' not full implemented
])
def test_create_plan(connection_type):
    plan = Plans()

    plan.compose_create_payload(connection_type=connection_type).create()
    PlansAssertionSteps(plan).validate_post_response_is_correct()

    plan.get_by_id()
    PlansAssertionSteps(plan).validate_get_response_is_correct()


@pytest.mark.smoke
@pytest.mark.parametrize('connection_type', [
    'postpaid'
    # 'prepaid' not full implemented
])
def test_update_plan(connection_type):
    plan = Plans()

    plan.compose_create_payload(connection_type=connection_type).create()
    PlansAssertionSteps(plan).validate_post_response_is_correct()

    plan.compose_update_payload().update()
    PlansAssertionSteps(plan).check_update_response_is_successfully()

    plan.get_by_id()
    PlansAssertionSteps(plan).validate_get_response_is_correct(
        expected_response=plan.generate_expected_response_after_updating())


@pytest.mark.smoke
@pytest.mark.parametrize('connection_type', [
    'postpaid'
    # 'prepaid' not full implemented
])
@pytest.mark.parametrize('to', [
    True,  # set random future date
    False,  # w/o to param
    "past_date"  # set random past date
])
def test_close_plan(connection_type, to):
    plan = Plans()

    plan.compose_create_payload(connection_type=connection_type).create()
    PlansAssertionSteps(plan).validate_post_response_is_correct()

    plan.compose_close_payload(
        to=to, date_in_past=False if to != 'past_date' else True).close()
    PlansAssertionSteps(plan).check_close_response_is_successful()

    PlansAssertionSteps(plan).check_object_revision_status(
        status=RevisionStatus.EXPIRED if to in [False, "past_date"] else RevisionStatus.ACTIVE)

    PlansAssertionSteps(plan).validate_get_response_is_correct(
        expected_response=plan.generate_expected_response_after_close())


@pytest.mark.smoke
@pytest.mark.parametrize('connection_type', [
    'postpaid'
    # 'prepaid' not full implemented
])
def test_close_and_new_plan(connection_type):
    plan = Plans()

    get_id_from_response(plan.compose_create_payload(connection_type=connection_type).create())  # init revision
    PlansAssertionSteps(plan).validate_post_response_is_correct()

    plan.get_by_id()
    PlansAssertionSteps(plan).validate_get_response_is_correct()

    new_revision_id = get_id_from_response(plan.compose_close_and_new_payload().close_and_new())
    PlansAssertionSteps(plan).check_object_has_new_to_date_after_close_and_new()

    plan.get_by_id(id_=new_revision_id)
    PlansAssertionSteps(plan).validate_get_response_is_correct(
        expected_response=plan.generate_expected_response_after_close_and_new())


@pytest.mark.smoke
@pytest.mark.parametrize('connection_type', [
    'postpaid'
    # 'prepaid' not full implemented
])
def test_delete_plan(connection_type):
    plan = Plans()

    plan.compose_create_payload(connection_type=connection_type).create()
    PlansAssertionSteps(plan).check_post_response_is_successfully()

    plan.delete()
    PlansAssertionSteps(plan).check_object_is_deleted_successfully()
