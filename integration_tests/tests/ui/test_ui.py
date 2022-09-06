from selene.support.conditions import be
from selene.support.shared import browser

from config.credentials import LOCAL_HOST
from core.testlib.UI.billrun_cloud.home_page import HomePage
from core.testlib.UI.billrun_cloud.login_page import LoginPage
from core.testlib.UI.billrun_cloud.products_page import ProductsPage
from steps.backend_steps.products_steps import Products


def test_login(driver):
    product = Products().compose_create_payload().create()
    product_key = product.json().get('entity').get('key')

    driver.get(LOCAL_HOST)
    LoginPage.login_field.send_keys('admin')
    LoginPage.password_field.send_keys('12345678')
    LoginPage.login_button.click()

    HomePage.product_button.click()
    ProductsPage.search_field.send_keys(product_key)
    ProductsPage.search_in_field_dropdown.click()
    ProductsPage.search_option_key.click()
    ProductsPage.search_button.click()
    ProductsPage.get_key_button(product_key).should(be.clickable)
