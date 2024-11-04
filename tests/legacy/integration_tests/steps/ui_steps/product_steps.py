import allure

from core.testlib.UI.billrun_cloud.products_page import ProductsPage


class ProductsUISteps(ProductsPage):

    @allure.step('Search product by key')
    def search_product_by_key(self, key):
        self.search_field.send_keys(key)
        self.search_in_field_dropdown.click()
        self.tick_search_option(option='key').click()
        self.search_button.click()
