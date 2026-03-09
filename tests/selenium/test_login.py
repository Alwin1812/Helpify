from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager
import time

def test_login():
    driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()))
    driver.maximize_window()
    
    try:
        # 1. First, register a unique user to ensure login always has valid credentials
        print("Pre-test: Registering a fresh user...")
        driver.get("http://localhost:8012/helpify/register.php")
        timestamp = int(time.time())
        test_email = f"login_test_{timestamp}@example.com"
        test_pass = "password123"
        
        driver.find_element(By.ID, "regName").send_keys("Login Tester")
        driver.find_element(By.ID, "regEmail").send_keys(test_email)
        driver.find_element(By.ID, "regPassword").send_keys(test_pass)
        driver.find_element(By.XPATH, "//button[contains(text(), 'Sign Up')]").click()
        
        time.sleep(2)
        # Logout after registration (since it may auto-login)
        print("Pre-test: Logging out to test login flow...")
        driver.get("http://localhost:8012/helpify/api/logout.php")
        time.sleep(1)

        # 2. Perform the actual Login Test
        print(f"Starting Login Test with: {test_email}")
        driver.get("http://localhost:8012/helpify/login.php")
        time.sleep(1)
        
        # Form fields
        email_input = driver.find_element(By.NAME, "email")
        password_input = driver.find_element(By.NAME, "password")
        submit_btn = driver.find_element(By.XPATH, "//button[contains(text(), 'Sign In')]")
        
        # Enter captured credentials
        email_input.send_keys(test_email)
        password_input.send_keys(test_pass)
        
        submit_btn.click()
        time.sleep(3)
        
        if "dashboard.php" in driver.current_url:
            print("\n" + "="*40)
            print("            LOGIN TEST: PASSED")
            print("="*40)
            print(f"Login Email: {test_email}")
            print(f"Final URL:   {driver.current_url}")
            print("="*40 + "\n")
        else:
            print("\n" + "!"*40)
            print("            LOGIN TEST: FAILED")
            print("!"*40 + "\n")
            
    except Exception as e:
        print(f"An error occurred: {e}")
        print("\nTEST FAILED\n")
    finally:
        driver.quit()

if __name__ == "__main__":
    test_login()
