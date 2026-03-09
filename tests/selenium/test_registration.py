from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from webdriver_manager.chrome import ChromeDriverManager
import time

def test_registration():
    # Setup Chrome options
    chrome_options = Options()
    # chrome_options.add_argument("--headless")  # Uncomment for headless mode
    
    # Initialize driver
    driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=chrome_options)
    driver.maximize_window()
    
    try:
        # Navigate to registration page
        driver.get("http://localhost:8012/helpify/register.php")
        time.sleep(2)
        
        # Find elements
        name_input = driver.find_element(By.ID, "regName")
        email_input = driver.find_element(By.ID, "regEmail")
        password_input = driver.find_element(By.ID, "regPassword")
        submit_btn = driver.find_element(By.XPATH, "//button[contains(text(), 'Sign Up')]")
        
        # Fill form
        name_val = "Test User"
        email_val = f"testuser_{int(time.time())}@example.com"
        name_input.send_keys(name_val)
        email_input.send_keys(email_val)
        password_input.send_keys("password123")
        
        # Submit
        submit_btn.click()
        time.sleep(3)
        
        if "dashboard.php" in driver.current_url or "login.php" in driver.current_url:
            print("\n" + "="*40)
            print("         REGISTRATION TEST: PASSED")
            print("="*40)
            print(f"Name Used:     {name_val}")
            print(f"Email Created: {email_val}")
            print(f"Final URL:     {driver.current_url}")
            print("="*40 + "\n")
        else:
            print("\n" + "!"*40)
            print("         REGISTRATION TEST: FAILED")
            print("!"*40 + "\n")
        
    except Exception as e:
        print(f"An error occurred: {e}")
        print("\nTEST FAILED\n")
    finally:
        driver.quit()

if __name__ == "__main__":
    test_registration()
