import time

from hamcrest import assert_that
from hamcrest.core.matcher import Matcher


def check_that(actual, matcher=None, message='message', timeout=10, polling=0.1):
    __tracebackhide__ = True
    if not isinstance(matcher, Matcher) and not message:
        message = matcher
        # with allure.step("Check that " + message):
    if callable(actual):
        assertion_retrier(actual, matcher, message, timeout, polling)
    else:
        assert_that(actual, matcher, message)


def assertion_retrier(actual, matcher=None, message=None, timeout=10, polling=0.1):
    end_time = time.time() + timeout
    exception = None
    while time.time() < end_time:
        try:
            assert_that(actual(), matcher, message)
            return
        except Exception as ex:
            exception = ex
            time.sleep(polling)

    raise AssertionError(message, str(exception))
