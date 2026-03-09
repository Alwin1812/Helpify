from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import time

def test_search():
    driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()))
    driver.maximize_window()
    
    try:
        driver.get("http://localhost:8012/helpify/index.php")
        time.sleep(2)
        
        # Find search input
        search_input = driver.find_element(By.ID, "headerSearchInput")
        search_input.click()
        
        # Type a query (using "Cleaning" which is more robust for matching)
        search_query = "Cleaning"
        search_input.send_keys(search_query)
        # Wait for dropdown to appear
        wait = WebDriverWait(driver, 10)
        dropdown = wait.until(EC.visibility_of_element_located((By.ID, "searchDropdown")))
        print("Search test passed: Dropdown is visible after typing.")
        
        # Check if results are present
        results = dropdown.find_elements(By.CLASS_NAME, "search-result-item")
        result_count = len(results)
        print(f"Number of results found: {result_count}")
        
        if result_count > 0:
            print("\n" + "="*40)
            print("           SEARCH TEST: PASSED")
            print("="*40)
            print(f"Search Query:  Cleaning")
            print(f"Results Found: {result_count}")
            print("="*40 + "\n")
        else:
            print("\n" + "!"*40)
            print("           SEARCH TEST: FAILED")
            print("!"*40 + "\n")
            
    except Exception as e:
        print(f"An error occurred: {e}")
        print("\nTEST FAILED\n")
    finally:
        driver.quit()

if __name__ == "__main__":
    test_search()
