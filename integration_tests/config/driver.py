from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager


class Driver:
    def __init__(self, **kwargs):
        self.kwargs = kwargs
        self.kwargs['service'] = Service(ChromeDriverManager().install())
        options = webdriver.ChromeOptions()
        # options.add_argument("--headless")
        if '--headless' in options.arguments:
            options.add_argument("--window-size=1920,1080")
            options.add_argument("--no-sandbox")
        self.kwargs['options'] = options

    def start(self):
        driver = webdriver.Chrome(**self.kwargs)
        return driver
