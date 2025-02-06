from selene.support.shared import browser


class LoginPage:
    login_field = browser.element('[placeholder*="Email"]')
    password_field = browser.element('[type="password"]')
    login_button = browser.element('.btn-lg')

