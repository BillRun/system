from core.common.helpers.discount_param_builder.condition_builder import Condition
from core.common.helpers.discount_param_builder.condition_sets_builders import (
    ServiceSet, SubscriberSet, CustomerSet
)
from core.common.helpers.discount_param_builder.discount_params_builder import (
    DiscountParamsBuilder, DiscountConditionBuilder
)
from core.common.helpers.discount_param_builder.entries import (
    Operators, ServiceConditionFields,
    CustomerConditionFields, SubscriberConditionFields
)


def create_all_types_conditions(
        min_subscribers='',
        max_subscribers='',
        cycles=None
):
    conditions = DiscountConditionBuilder(
        CustomerSet(con=[
            Condition(CustomerConditionFields.COUNTRY, Operators.EQUALS, 'USA')]),
        SubscriberSet(con=[
            Condition(SubscriberConditionFields.ADDRESS, Operators.EXISTS, True)]),
        ServiceSet(con=[
            Condition(ServiceConditionFields.QUANTITY, Operators.GTE, '3')
        ])
    )
    return DiscountParamsBuilder(
        min_subscribers, max_subscribers, cycles, conditions
    ).build()
