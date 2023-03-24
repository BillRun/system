from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options

chrome_options = webdriver.ChromeOptions()
chrome_options.add_argument("--no-sandbox")
chrome_options.add_argument("--headless")
chrome_options.add_argument("--disable-gpu")
driver = webdriver.Chrome(options=chrome_options)

# driver.get("https://www.google.com/")
# print(driver.title)
driver.get("http://46.101.14.10/index.html#/")
print(driver.title)
driver.implicitly_wait(100)
username = driver.find_element(By.XPATH, "//input[@placeholder='Email address']")
username.send_keys("admin")
print("username entered")
password = driver.find_element(By.XPATH, "//input[@placeholder='Password']")
password.send_keys("12345678")
print("password entered")
driver.find_element(By.XPATH, "//button[@type='submit']").click()
print("Successfully logged IN")
driver.get("http://46.101.14.10/test/updaterowt?rebalance=1")
logs = driver.find_element(By.XPATH, "/html/body").text
print(logs)
# file1 = open("Logs.txt", "w")  # write mode
# file1.write(logs)
# print("Logs upload in file successfully")
# file1.close()
print("Code is successfully working")
