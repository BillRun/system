from selene.support.shared import browser

from core.testlib.UI.billrun_cloud.base_page import BasePage


class ProductsPage(BasePage):

    @staticmethod
    def get_key_button(key: str):
        return browser.element(f'//button[contains(text(), {key} )]')
