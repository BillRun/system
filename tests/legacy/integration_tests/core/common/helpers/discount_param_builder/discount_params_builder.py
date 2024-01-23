from typing import Union

from core.common.helpers.discount_param_builder.condition_sets_builders import ServiceSet, SubscriberSet, CustomerSet
from core.common.helpers.utils import remove_keys_if_value_is_none


class DiscountConditionBuilder:

    def __init__(self, *sets: Union[CustomerSet, SubscriberSet, ServiceSet]):
        self.sets = sets

    def __call__(self, *args, **kwargs):
        return list(map(lambda x: x(), self.sets))


class DiscountParamsBuilder:

    def __init__(
            self,
            min_subscribers: str = '',
            max_subscribers: str = '',
            cycles: int = None,
            conditions: DiscountConditionBuilder = None
    ):
        self.min_subscribers = min_subscribers
        self.max_subscribers = max_subscribers
        self.cycles = cycles
        self.conditions = conditions() if conditions else conditions

    def build(self):
        params = {
            'min_subscribers': self.min_subscribers,
            'max_subscribers': self.max_subscribers,
            'cycles': self.cycles,
            'conditions': self.conditions
        }
        remove_keys_if_value_is_none(params)

        return params
