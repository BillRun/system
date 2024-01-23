from hamcrest.core.base_matcher import BaseMatcher
from requests import Response

from core.common.helpers.api_helpers import find_item_by_id


class HasStatus(BaseMatcher):
    """
    check status in node status in response
    possible values 1 or 0
    """

    def __init__(self, status):
        self.status = status

    def _matches(self, item: Response):
        self.item_json = item.json()
        return self.item_json.get('status') == self.status

    def describe_to(self, description):
        description.append_text(f'Status node should be {self.status}')

    def describe_mismatch(self, item, mismatch_description):
        mismatch_description.append_text(
            f"\n     Actual is {self.item_json.get('status')},"
            f" message: {self.item_json.get('message')}"
        )


def has_status(status):
    return HasStatus(status)


class HasStatusCode(BaseMatcher):
    """
    check API status code
    """

    def __init__(self, status):
        self.status = status

    def _matches(self, item: Response):
        return item.status_code == self.status

    def describe_to(self, description):
        description.append_text("status code should be ").append_text(str(self.status))

    def describe_mismatch(self, item, mismatch_description):
        mismatch_description.append_text("\n     Actual is ").append_text(
            str(item.status_code)
        )


def has_status_code(status):
    return HasStatusCode(status)


class HasNotExistingEntity(BaseMatcher):
    """
    use for checking deleting entity in response
    """

    def __init__(self, id_):
        self.id = id_
        self.result = None

    def _matches(self, item: Response):
        self.result = find_item_by_id(item, self.id)
        return self.result is None

    def describe_to(self, description):
        description.append_text(f"Entity with id {self.id} should be deleted")

    def describe_mismatch(self, item, mismatch_description):
        mismatch_description.append_text(
            "\n     Actual is ").append_text(str(self.result))


def has_not_existing_entity(id_):
    return HasNotExistingEntity(id_)
