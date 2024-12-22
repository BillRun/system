from selene.support.shared import browser

from core.testlib.UI.billrun_cloud.base_page import BasePage


class ProductsPage(BasePage):
    title = browser.element('//h1[contains(text(), "Products")]')

    @staticmethod
    def get_key_button(key: str):
        return browser.element(f'//button[contains(text(), "{key}" )]')
