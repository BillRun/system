from core.common.helpers.mixins import GetAttributes


class APIStatus:
    SUCCESSFUL = 1
    FAILED = 0


class APIPath:
    PRODUCTS = 'billapi/rates'
    CUSTOMERS = 'billapi/accounts'
    PLANS = 'billapi/plans'
    SUBSCRIBERS = 'billapi/subscribers'
    SERVICES = 'billapi/services'


class RevisionStatus:
    EXPIRED = 'expired'
    ACTIVE = 'active'


class Recurrence(GetAttributes):
    MONTHLY = 1
    BIMONTHLY = 2
    QUARTERLY = 3
    SEMIANNUAL = 6
    ANNUAL = 12


DATE_PATTERN = '%Y-%m-%d'
DATE_TIME_PATTERN = '%Y-%m-%dT%H:%M:%S+%f'
