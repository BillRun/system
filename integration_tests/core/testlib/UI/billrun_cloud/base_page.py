from selene.support.shared import browser


class BasePage:
    search_field = browser.element('#filter-string')
    search_in_field_dropdown = browser.element('[title*="Search"]')
    search_option_key = browser.element("[type='checkbox'][value='key']")
    search_option_title = browser.element("[type='checkbox'][value='description']")
    search_button = browser.element(".fa-search")

