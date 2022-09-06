from selenium.webdriver.common.by import By


def id(_id):
    return By.ID, _id


def xpath(_xpath):
    return By.XPATH, _xpath


def css(_css):
    return By.CSS_SELECTOR, _css


def link(_link):
    return By.LINK_TEXT, _link


def name(_name):
    return By.NAME, _name


def class_name(_class_name):
    return By.CLASS_NAME, _class_name
