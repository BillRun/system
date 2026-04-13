import base64
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options

chrome_options = webdriver.ChromeOptions()
chrome_options.add_argument("--no-sandbox")
chrome_options.add_argument("--headless")
chrome_options.add_argument("--disable-gpu")
driver = webdriver.Chrome(options=chrome_options)

root_url = "http://46.101.14.10:8074/"

driver.get(f"{root_url}index.html#/")
driver.maximize_window()
driver.implicitly_wait(100)

encoded_credentials = 'YWRtaW46MTIzNDU2Nzg='

# Decode the username and password from base64
decoded_credentials = base64.b64decode(encoded_credentials).decode().split(':')
username = decoded_credentials[0]
password = decoded_credentials[1]

# Enter the decoded username and password
username_field = driver.find_element(By.XPATH, "//input[@placeholder='Email address']")
username_field.send_keys(username)
print("Username entered")
password_field = driver.find_element(By.XPATH, "//input[@placeholder='Password']")
password_field.send_keys(password)
print("Password entered")

driver.find_element(By.XPATH, "//button[@type='submit']").click()

try:
    abc = driver.find_element(By.XPATH, "//div[@role='alert']").text
    print("Login using admin credential didn't work ERROR:- " + abc)
except:
    print("Successfully Login using admin Credentials")

    driver.get(f"{root_url}/test/updaterowt?rebalance=1")
    UpdateRowt_Rebalance_logs = driver.find_element(By.XPATH, "/html/body").text
    print(UpdateRowt_Rebalance_logs)

print(
    "----------------------------------------------------------------------------------------UpdateRowt Rebalance--------------------------------------------------------------------------------")

driver.get(f"{root_url}test/Aggregatortest?skip=1,2,40")
Aggregatortest_logs = driver.find_element(By.XPATH, "/html/body").text
print(Aggregatortest_logs)

print(
    "----------------------------------------------------------------------------------------Aggregatortest--------------------------------------------------------------------------------")
driver.get(f"{root_url}test/updaterowt?skip=k12,d81")
updaterowt_logs = driver.find_element(By.XPATH, "/html/body").text
print(updaterowt_logs)

print(
    "----------------------------------------------------------------------------------------updaterowt--------------------------------------------------------------------------------")

driver.get(f"{root_url}test/RateTest?skip=k12,d81")
Rate_Test_logs = driver.find_element(By.XPATH, "/html/body").text
print(Rate_Test_logs)

print(
    "----------------------------------------------------------------------------------------Rate Test--------------------------------------------------------------------------------")

driver.get(f"{root_url}test/monthsdifftest?skip1,4")
month_diff_test_logs = driver.find_element(By.XPATH, "/html/body").text
print(month_diff_test_logs)

print(
    "----------------------------------------------------------------------------------------get month diff test --------------------------------------------------------------------------------")

driver.get(f"{root_url}test/CustomerCalculatorTest?skip=k12,d81")
CustomerCalculatorTest_logs = driver.find_element(By.XPATH, "/html/body").text
print(CustomerCalculatorTest_logs)

print(
    "----------------------------------------------------------------------------------------CustomerCalculatorTest --------------------------------------------------------------------------------")

driver.get(f"{root_url}test/Taxmappingtest?skip=1,2,40")
Taxmappingtest_logs = driver.find_element(By.XPATH, "/html/body").text
print(Taxmappingtest_logs)

print(
    "----------------------------------------------------------------------------------------Taxmappingtest --------------------------------------------------------------------------------")

driver.get(f"{root_url}test/discounttest?skip=1,2,40")
discounttest_logs = driver.find_element(By.XPATH, "/html/body").text
print(discounttest_logs)

print(
    "----------------------------------------------------------------------------------------discounttest --------------------------------------------------------------------------------")
print("Code is successfully working")
