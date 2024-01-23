from selene.support.shared import browser


class BasePage:
    search_field = browser.element('#filter-string')
    search_in_field_dropdown = browser.element('[title*="Search"]')
    search_button = browser.element(".fa-search")

    @staticmethod
    def tick_search_option(option):
        """
        param option: key or description
        """
        return browser.element(f"[type='checkbox'][value='{option}']")
