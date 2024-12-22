import allure
import pytest

from selene.support.conditions import be
from selene.support.shared import browser
from selenium.common import WebDriverException

from config.credentials import USERNAME, PASSWORD
from config.driver import Driver
from core.common.logger import LOGGER
from core.connectors.api_client import APIClient
from core.testlib.UI.billrun_cloud.home_page import HomePage
from core.testlib.UI.billrun_cloud.login_page import LoginPage
from config.env import ENV


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


@pytest.fixture(scope='function')
def login():
    """login to BillRun app"""
    browser.open(ENV)

    @allure.step('Login to BillRun app')
    def inner(username, password):
        LoginPage.login_field.send_keys(username)
        LoginPage.password_field.send_keys(password)
        LoginPage.login_button.click()
        HomePage.product_button.should(be.clickable)

    return inner


def pytest_exception_interact(node, call, report):
    """Attach screenshot if test failed"""
    if report.failed:
        try:
            if browser.driver.title and not browser.config.last_screenshot:
                browser.save_screenshot()
            with open(browser.config.last_screenshot, 'rb') as screen:
                allure.attach(screen.read(), 'screen', allure.attachment_type.PNG)
            browser.config.last_screenshot = ''
            browser.driver.quit()

        except Exception as ex:
            LOGGER.error(ex)
