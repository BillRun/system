from core.common.helpers.mixins import GetAttributes


class Operators(GetAttributes):
    EQUALS = 'in'
    NOT_EQUALS = 'nin'
    EXISTS = 'exists'
    CONTAINS = 'regex'
    LTE = 'lte'
    LT = 'lt'
    GT = 'gt'
    GTE = 'gte'


class ServiceConditionFields(GetAttributes):
    FROM = 'from'
    QUANTITY = 'quantity'
    NAME = 'name'
    TO = 'to'


class CustomerConditionFields(GetAttributes):
    AID = 'aid'
    FIRST_NAME = 'firstname'
    LAST_NAME = 'lastname'
    EMAIL = 'email'
    COUNTRY = 'country'
    ADDRESS = 'address'
    ZIP = 'zip_code'
    PAYMENT = 'payment_gateway'
    ID = 'personal_id'
    SALUTATION = 'salutation'
    IN_COLLECTION = 'in_collection'
    INVOICE_DAY = 'invoicing_day'
    SHIPPING_METHOD = 'invoice_shipping_method'
    INVOICE_DETAILED = 'invoice_detailed'
    ALLOWANCES = 'allowances'


class SubscriberConditionFields(GetAttributes):
    ACTIVATION_DATE = 'activation_date'
    ADDRESS = 'address'
    COUNTRY = 'country'
    AID = 'aid'
    DEACTIVATION_DATE = 'deactivation_date'
    FIRST_NAME = 'firstname'
    LAST_NAME = 'lastname'
    SID = 'sid'
    FORMER_PLAN = 'former_plan'
    PLAN = 'plan'
