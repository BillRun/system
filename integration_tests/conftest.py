import pytest

from selene.support.shared import browser
from selenium.common import WebDriverException

from config.credentials import USERNAME, PASSWORD
from config.driver import Driver
from core.common.logger import LOGGER
from core.connectors.api_client import APIClient


@pytest.fixture(scope='session', autouse=True)
def log_in_api():
    APIClient(username=USERNAME, password=PASSWORD)


@pytest.fixture(scope='function')
def driver():
    LOGGER.info('Starting driver')
    ui_driver = Driver().start()
    browser.config.driver = ui_driver

    yield ui_driver

    try:
        LOGGER.info(f'Quit driver')
        ui_driver.quit()
        LOGGER.info(f'Driver quit')
    except WebDriverException:
        LOGGER.info(f'Driver is already closed by exception interact hook')
