from selene.support.conditions import be

from core.testlib.UI.billrun_cloud.home_page import HomePage
from core.testlib.UI.billrun_cloud.products_page import ProductsPage


class HomePageSteps(HomePage):

    def navigate_to_product_page(self):
        self.product_button.click()
        ProductsPage.title.should(be.present)
