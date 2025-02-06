import random

from core.common.helpers.discount_param_builder.condition_builder import Condition
from core.common.helpers.discount_param_builder.condition_sets_builders import (
    ServiceSet, SubscriberSet, CustomerSet
)
from core.common.helpers.discount_param_builder.discount_params_builder import (
    DiscountParamsBuilder, DiscountConditionBuilder
)
from core.common.helpers.discount_param_builder.discount_condition_entities import (
    Operators, ServiceConditionFields,
    CustomerConditionFields, SubscriberConditionFields
)
from core.common.helpers.utils import get_random_str, get_true_or_false


def create_all_types_conditions(
        min_subscribers='',
        max_subscribers='',
        cycles=None
):
    conditions = DiscountConditionBuilder(
        CustomerSet(con=[
            Condition(CustomerConditionFields.COUNTRY, Operators.EQUALS, 'USA')]),
        SubscriberSet(con=[
            Condition(SubscriberConditionFields.ADDRESS, Operators.EXISTS, get_true_or_false())]),
        ServiceSet(con=[
            Condition(ServiceConditionFields.QUANTITY, Operators.GTE, random.randint(1, 10))
        ])
    )
    return DiscountParamsBuilder(
        min_subscribers, max_subscribers, cycles, conditions
    ).build()


def create_random_customer_conditions(
        min_subscribers='',
        max_subscribers='',
        cycles=None,
        count=1
):
    list_of_sets = []
    for _ in range(count):
        list_of_sets.append(
            CustomerSet(
                con=[Condition(
                    random.choice(CustomerConditionFields.as_list()),
                    random.choice(Operators.as_list()),
                    get_random_str()
                )]))
    return DiscountParamsBuilder(
        min_subscribers,
        max_subscribers,
        cycles,
        conditions=DiscountConditionBuilder(*list_of_sets)
    ).build()
