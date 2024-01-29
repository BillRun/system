import random
from contextlib import suppress
from copy import deepcopy
from datetime import date, timedelta, datetime
from json import dumps
from typing import Any

import pytest
from _pytest.mark import ParameterSet
from faker import Faker
from pytz import utc

from core.common.entities import DATE_PATTERN, DATE_TIME_PATTERN

FAKE = Faker()


def get_random_str(n: int = 10) -> str:
    return FAKE.bothify(text="?" * n)


def get_random_int(start: int = 100000000000000, stop: int = 9999999999999999) -> int:
    return FAKE.random_int(min=start, max=stop)


def dumps_values(init_dict: dict) -> dict:
    if isinstance(init_dict, dict):
        result_dict = deepcopy(init_dict)
        for k, v in result_dict.items():
            if isinstance(v, (list, dict)):
                result_dict[k] = dumps(v)
        return result_dict


def get_random_past_or_future_date(
        range_nearest_days: int = 10, past: bool = True, start_range_from: int = None
) -> date:
    if past:
        return (date.today() - timedelta(days=start_range_from or 0)) - timedelta(
            days=random.randint(1, range_nearest_days))
    else:
        return (date.today() + timedelta(days=start_range_from or 0)) + timedelta(
            days=random.randint(1, range_nearest_days))


def get_random_past_or_future_date_str(
        range_nearest_days: int = 10,
        past: bool = True,
        start_range_from: int = None,
        pattern: str = DATE_PATTERN,
) -> str:
    return convert_date_to_str(
        get_random_past_or_future_date(range_nearest_days, past, start_range_from), pattern
    )


def get_random_date_between_dates(
        start_date: str = None,
        end_date: str = None,
        pattern: str = DATE_PATTERN,
        include_dates: bool = False
) -> date:
    start_date = (datetime.strptime(start_date, pattern) if start_date
                  else datetime.now()) + timedelta(days=int(not include_dates))
    end_date = (datetime.strptime(end_date, pattern) if end_date
                else datetime.now() + timedelta(days=365)) + timedelta(days=int(include_dates))

    return datetime.fromtimestamp(get_random_int(
        start=int(start_date.timestamp()), stop=int(end_date.timestamp()))
    )


def convert_date_to_str(date_: date, pattern: str = DATE_PATTERN) -> str:
    return date_.strftime(pattern)


def convert_date_str_to_datetime_str(date_str: str) -> str:
    date_time = datetime.strptime(date_str, DATE_PATTERN)
    # we cut 2 digits from the end due to specific datetime format in API
    return date_time.strftime(DATE_TIME_PATTERN)[:-2]


def convert_datetime_str_to_timestamp(date_str: str, pattern: str = DATE_PATTERN) -> float:
    return datetime.strptime(date_str, pattern).replace(tzinfo=utc).timestamp()


def convert_date_to_date_obj(date_str: str, pattern: str = DATE_PATTERN) -> dict:
    """:return {"sec": 160000000, "usec": 0}"""
    if date_str:
        date_ = datetime.strptime(date_str, pattern).replace(tzinfo=utc)
        msec = date_.microsecond
        return {
            "sec": int(date_.replace(microsecond=0).timestamp()),
            "usec": msec
        }


def convert_date_fields_to_expected(expected_obj: dict, fields: list, method: str) -> None:
    """we have diff type of date fields between GET and other API methods"""
    for key, value in expected_obj.items():
        if key in fields:
            if method == 'GET':
                expected_obj[key] = convert_date_str_to_datetime_str(value)
            else:
                expected_obj[key] = convert_date_to_date_obj(value)


def to_float_date_obj_form_get(to: dict, msec: bool = True) -> float:
    """as for now 'to' param contains sec and usec values"""
    return float(f'{to.get("sec")}.{to.get("usec")}') if msec else float(to.get("sec"))


def remove_keys_for_missing_values(raw_dict: dict) -> dict:
    if isinstance(raw_dict, dict):
        for key, value in list(
                raw_dict.items()
        ):  # dictionary changed size during iteration
            if not value:
                del raw_dict[key]
            if isinstance(value, dict):
                remove_keys_for_missing_values(value)
            elif isinstance(value, list):
                for element in value:
                    remove_keys_for_missing_values(element)
                    if isinstance(element, dict) and not value[0]:  # for case [{}]
                        del raw_dict[key]
    return raw_dict


def remove_keys_if_value_is_none(raw_dict: dict) -> dict:
    if isinstance(raw_dict, dict):
        for key in list(raw_dict.keys()):
            if raw_dict[key] is None:
                del raw_dict[key]

    return raw_dict


def get_true_or_false() -> bool:
    return random.choice([True, False])


def skip_test(case: Any, reason: str) -> ParameterSet:
    """use inside parametrization"""
    return pytest.param(case, marks=pytest.mark.skip(reason=reason))


def remove_keys_in_nested_dict(initial_dict: dict, key_to_remove: list):
    """Use for case where we need to get-rid of some keys which we can't predict
    or any other cases where we need to remove keys regardless of how deeply it nested"""
    if not key_to_remove or not isinstance(initial_dict, dict):
        return
    for key in key_to_remove:
        with suppress(KeyError):
            del initial_dict[key]
    for value in initial_dict.values():
        if isinstance(value, dict):
            remove_keys_in_nested_dict(value, key_to_remove)
        if isinstance(value, list):
            for nested in value:
                remove_keys_in_nested_dict(nested, key_to_remove)


def get_api_path_name(path: str) -> str:
    return path[path.find('/') + 1:-1].capitalize()
