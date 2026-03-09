from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import time

def test_full_journey():
    driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()))
    driver.maximize_window()
    
    try:
        # 1. Registration (to ensure we have a fresh user)
        print("Starting Registration...")
        driver.get("http://localhost:8012/helpify/register.php")
        timestamp = int(time.time())
        test_email = f"journey_user_{timestamp}@example.com"
        test_pass = "password123"
        
        driver.find_element(By.ID, "regName").send_keys("Journey User")
        driver.find_element(By.ID, "regEmail").send_keys(test_email)
        driver.find_element(By.ID, "regPassword").send_keys(test_pass)
        driver.find_element(By.XPATH, "//button[contains(text(), 'Sign Up')]").click()
        
        time.sleep(3)
        print(f"Registered user: {test_email}")

        # 2. Login
        print("Starting Login...")
        driver.get("http://localhost:8012/helpify/login.php")
        driver.find_element(By.NAME, "email").send_keys(test_email)
        driver.find_element(By.NAME, "password").send_keys(test_pass)
        driver.find_element(By.XPATH, "//button[contains(text(), 'Sign In')]").click()
        
        # Wait for Dashboard
        wait = WebDriverWait(driver, 10)
        wait.until(EC.url_contains("dashboard.php"))
        print("Successfully logged in to Dashboard.")
        time.sleep(2)

        # 3. Surf through Dashboard Tabs
        sections = [
            ("Book Service", "book"),
            ("History", "history"),
            ("Profile", "profile"),
            ("My Wallet", "wallet")
        ]

        for name, section_id in sections:
            print(f"Navigating to {name} section...")
            # Use JS to click the sidebar items directly
            xpath = f"//div[contains(@onclick, \"showSection('{section_id}')\")]"
            nav_item = driver.find_element(By.XPATH, xpath)
            driver.execute_script("arguments[0].click();", nav_item)
            time.sleep(2)
            
            # Simple check if current section is visible
            section_el = driver.find_element(By.ID, f"{section_id}-section")
            if section_el.is_displayed():
                print(f"✅ Verified {name} is visible.")
            else:
                print(f"⚠️ {name} might be hidden.")

        # 4. Logout
        print("Attempting to Logout...")
        logout_btn = driver.find_element(By.XPATH, "//a[contains(@href, 'api/logout.php')]")
        driver.execute_script("arguments[0].click();", logout_btn)
        
        # Verify redirect
        time.sleep(2)
        if "login.php" in driver.current_url or "index.php" in driver.current_url:
            print("\n" + "="*50)
            print("           FULL JOURNEY TEST: PASSED")
            print("="*50)
            print(f"✅ Created User: {test_email}")
            print(f"✅ Browsed:      Bookings, History, Profile, Wallet")
            print(f"✅ Final Action: Successful Logout")
            print(f"✅ Final URL:    {driver.current_url}")
            print("="*50 + "\n")
        else:
            print("\n" + "!"*50)
            print("           FULL JOURNEY TEST: FAILED")
            # Set exit code 1 for the runner process
            import sys
            sys.exit(1)
            print("!"*50 + "\n")
            
    except Exception as e:
        print(f"An error occurred during the journey: {e}")
        print("\n!!!!!!!!!! TEST FAILED !!!!!!!!!!\n")
        import sys
        sys.exit(1)
    finally:
        driver.quit()

if __name__ == "__main__":
    test_full_journey()
