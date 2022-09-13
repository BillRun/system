from core.common.helpers.mixins import GetAttributes


class APIStatus:
    SUCCESSFUL = 1
    FAILED = 0


BILL_API_PREFIX = 'billapi'


class APIPath:
    PRODUCTS = f'{BILL_API_PREFIX}/rates'
    CUSTOMERS = f'{BILL_API_PREFIX}/accounts'
    PLANS = f'{BILL_API_PREFIX}/plans'
    SUBSCRIBERS = f'{BILL_API_PREFIX}/subscribers'
    SERVICES = f'{BILL_API_PREFIX}/services'
    TAX_RATES = f'{BILL_API_PREFIX}/taxes'


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
