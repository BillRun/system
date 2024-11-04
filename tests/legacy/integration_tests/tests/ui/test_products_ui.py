import allure
import pytest

from config.credentials import USERNAME, PASSWORD
from core.common.helpers.api_helpers import get_entity
from steps.assertion_steps.ui_assertion_steps.products_assertion_steps import ProductsAssertionUISteps
from steps.backend_steps.products_steps import Products
from steps.ui_steps.home_page_steps import HomePageSteps
from steps.ui_steps.product_steps import ProductsUISteps


@pytest.mark.ui
@allure.title('Create product by API and then check it is appeared on UI')
@allure.description('PRODUCT-UI-1')
def test_create_product_by_api_and_check_on_ui(driver, login):
    product_key = get_entity(Products().compose_create_payload().create()).get('key')

    login(USERNAME, PASSWORD)
    HomePageSteps().navigate_to_product_page()
    ProductsUISteps().search_product_by_key(product_key)

    ProductsAssertionUISteps().check_product_presents_on_the_page(product_key)
