from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import time

def test_ai_concierge():
    driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()))
    driver.maximize_window()
    
    try:
        driver.get("http://localhost:8012/helpify/index.php")
        time.sleep(2)
        
        # Click AI Concierge button
        ai_btn = driver.find_element(By.ID, "aiConciergeBtn")
        ai_btn.click()
        
        # Wait for overlay to appear
        wait = WebDriverWait(driver, 10)
        overlay = wait.until(EC.visibility_of_element_located((By.ID, "aiConciergeOverlay")))
        print("AI Concierge overlay is visible.")
        
        # Type a message
        chat_input = driver.find_element(By.ID, "aiInput")
        chat_input.send_keys("I need cleaning services")
        
        # Click send button
        send_btn = driver.find_element(By.CLASS_NAME, "chat-send-btn")
        send_btn.click()
        
        # Wait for potential response bubble
        time.sleep(3)
        messages = driver.find_elements(By.CLASS_NAME, "message-bubble")
        msg_count = len(messages)
        print(f"Total messages in chat: {msg_count}")
        
        if msg_count > 1:
            print("\n" + "="*40)
            print("        AI CONCIERGE TEST: PASSED")
            print("="*40)
            print(f"Message Sent:  I need cleaning services")
            print(f"Chat History:  {msg_count} bubbles detected")
            print("="*40 + "\n")
        else:
            print("\n" + "!"*40)
            print("        AI CONCIERGE TEST: FAILED")
            print("!"*40 + "\n")
            
    except Exception as e:
        print(f"An error occurred: {e}")
        print("\nTEST FAILED\n")
    finally:
        driver.quit()

if __name__ == "__main__":
    test_ai_concierge()
