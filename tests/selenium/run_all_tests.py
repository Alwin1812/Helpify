import subprocess
import os

def run_test(file_name):
    print(f"\n{'#'*60}")
    print(f"  EXECUTING: {file_name}")
    print(f"{'#'*60}\n")
    try:
        # Run test and direct output to current terminal
        process = subprocess.Popen(['python', file_name])
        process.wait()
        
        if process.returncode == 0:
            return True
        else:
            return False
    except Exception as e:
        print(f"Error executing {file_name}: {e}")
        return False

if __name__ == "__main__":
    tests = [
        "test_registration.py",
        "test_login.py",
        "test_search.py",
        "test_ai_concierge.py",
        "test_location.py",
        "test_full_journey.py"
    ]
    
    results = {}
    for test in tests:
        if os.path.exists(test):
            success = run_test(test)
            results[test] = "PASSED" if success else "FAILED"
        else:
            print(f"File not found: {test}")
            results[test] = "NOT_FOUND"

    print(f"\n{'='*60}")
    print(f"{' '*18}OVERALL TEST SUMMARY")
    print(f"{'='*60}")
    for test, result in results.items():
        padding = " " * (30 - len(test))
        print(f" {test}{padding}: {result}")
    print(f"{'='*60}\n")
