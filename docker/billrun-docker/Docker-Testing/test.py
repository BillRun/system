import base64
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options

chrome_options = webdriver.ChromeOptions()
chrome_options.add_argument("--no-sandbox")
chrome_options.add_argument("--headless")
chrome_options.add_argument("--disable-gpu")
driver = webdriver.Chrome(options=chrome_options)

driver.get("46.101.14.10:8074")
driver.implicitly_wait(100)

# Encoded username and password
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

# Click on the submit button
driver.find_element(By.XPATH, "//button[@type='submit']").click()

try:
    abc = driver.find_element(By.XPATH, "//div[@role='alert']").text
    print("Login using admin credential didn't work ERROR:- " + abc)
except:
    print("Successfully Login using admin Credentials")
    driver.get("http://46.101.14.10:8074/test/updaterowt?rebalance=1")
    result = driver.find_element(By.XPATH, "//div[contains(text(),'1')]").text
    print(result)
    logs = driver.find_element(By.XPATH, "/html/body").text
    print(logs)

    # Write logs to a file
    with open("Logs.txt", "w") as file1:
        file1.write(logs)
        print("Logs uploaded to file successfully")

print("Code is successfully working")
