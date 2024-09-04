from selene.support.conditions import be

from core.testlib.UI.billrun_cloud.products_page import ProductsPage


class ProductsAssertionUISteps(ProductsPage):

    def check_product_presents_on_the_page(self, product_key):
        self.get_key_button(product_key).should(be.clickable)
