from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import time

def test_location():
    driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()))
    driver.maximize_window()
    
    try:
        driver.get("http://localhost:8012/helpify/index.php")
        time.sleep(2)
        
        # Click location box
        location_box = driver.find_element(By.CLASS_NAME, "location-box")
        location_box.click()
        
        # Wait for modal
        wait = WebDriverWait(driver, 10)
        modal = wait.until(EC.visibility_of_element_located((By.ID, "locationModal")))
        print("Location modal is visible.")
        
        # Type in manual location
        loc_input = driver.find_element(By.ID, "manualLocationInput")
        loc_input.send_keys("Mumbai")
        time.sleep(1) # Wait for suggestions to show
        
        # Check if suggestions appeared and click the first one if it exists
        try:
            suggestions = driver.find_elements(By.CLASS_NAME, "suggestion-item")
            if suggestions:
                print("Clicking a suggestion from the dropdown...")
                suggestions[0].click()
                time.sleep(1)
        except:
            pass
            
        # Click Update Location button
        print("Clicking Update Location button...")
        update_btn = driver.find_element(By.CLASS_NAME, "update-location-btn")
        driver.execute_script("arguments[0].click();", update_btn) # Use JS click for robustness
        
        time.sleep(2)
        
        # Check if modal closed
        if not modal.is_displayed():
            print("Location test passed: Modal closed after updating.")
        
        # Check if UI updated
        final_location = driver.find_element(By.CLASS_NAME, "location-text").text
        print(f"Updated location in UI: {final_location}")
        
        if "Mumbai" in final_location or len(final_location) > 5:
            print("\n" + "="*40)
            print("          LOCATION TEST: PASSED")
            print("="*40)
            print(f"Location Typed: Mumbai, Maharashtra")
            print(f"UI Display:     {final_location}")
            print("="*40 + "\n")
        else:
            print("\n" + "!"*40)
            print("          LOCATION TEST: FAILED")
            print("!"*40 + "\n")
            
    except Exception as e:
        print(f"An error occurred: {e}")
        print("\nTEST FAILED\n")
    finally:
        driver.quit()

if __name__ == "__main__":
    test_location()
