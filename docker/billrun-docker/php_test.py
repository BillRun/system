from selenium import webdriver
from selenium.webdriver.chrome.service import Service as ChromeService
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from webdriver_manager.chrome import ChromeDriverManager

# url = "https://spotfire.mobideocloud.com/spotfire/login.html"
options = webdriver.ChromeOptions()
options.headless = True
options.add_experimental_option("detach", True)
with webdriver.Chrome(service=ChromeService(ChromeDriverManager().install()), options=options)as driver:
    driver.get("http://localhost:8074/index.html#/")
    driver.implicitly_wait(100)
    username = driver.find_element(By.XPATH, "//input[@placeholder='Email address']")
    username.send_keys("admin")
    password = driver.find_element(By.XPATH, "//input[@placeholder='Password']")
    password.send_keys("12345678")
    driver.find_element(By.XPATH, "//button[@type='submit']").click()
    driver.get("http://localhost:8074/test/updaterowt?rebalance=1")
    logs = driver.find_element(By.XPATH, "/html/body").text
    print(logs)
    # file1 = open("Logs.txt", "w")  # write mode
    # file1.write(logs)
    # print("Logs upload in file successfully")
    # file1.close()
    print("Code is successfully working")
