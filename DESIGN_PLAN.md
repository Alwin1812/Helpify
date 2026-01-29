# Helpers & Users Dashboard Redesign - Implementation Plan

## 1. Core Objective
Implement a multi-helper bidding system enabling concurrent request access, helper bidding (price, time, notes), and user-driven selection.

## 2. Database Schema Changes
**Table: `booking_requests`**
Updated to support bidding details.
```sql
CREATE TABLE booking_requests (
    booking_id INT,
    helper_id INT,
    status ENUM('active', 'accepted', 'rejected', 'selected') DEFAULT 'active', -- 'accepted' means Bid Submitted
    arrival_estimate VARCHAR(255),
    bid_price DECIMAL(10, 2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (booking_id, helper_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (helper_id) REFERENCES users(id)
);
```

**Table: `bookings`**
Status flow: `pending` (open for bids) -> `accepted` (has bids) -> `confirmed` (helper selected) -> `completed`.

## 3. API Endpoints (`api/booking_action.php`)

| Action | Role | Description |
| :--- | :--- | :--- |
| `book` | User | Creates a new `pending` booking. Broadcasts to helpers. |
| `accept` | Helper | Submits or updates a bid (Price, Time, Notes). Sets/Keeps booking status `accepted`. |
| `reject` | Helper | Helper ignores/withdraws from a job. Status: `rejected`. |
| `confirm_helper` | User | Selects a specific bid. Updates booking to `confirmed`. Rejects all other active bids. |
| `reject_applicant` | User | Rejects a specific bid. Helper notified. |

## 4. State Management & Workflow

1.  **Initialization**: User creates a request. Booking Status: `pending`.
2.  **Bidding Phase**:
    *   Helpers view list of `pending` or `accepted` (but unassigned) jobs matches their role.
    *   Helper Bids -> `booking_requests` entry created/updated with `bid_price`, `notes`.
3.  **Selection Phase**:
    *   User views "Review Applications" UI.
    *   System aggregates all `booking_requests` with status `accepted`.
    *   User can Sort/Filter bids.
4.  **Confirmation**:
    *   User clicks "Accept" on a specific helper.
    *   Backend Transaction:
        *   set Booking `helper_id` = Selected Helper.
        *   set Booking `status` = `confirmed`.
        *   set Selected Request `status` = `selected`.
        *   set Other Requests `status` = `rejected`.

## 5. UI/UX Flow

**Helpers Dashboard**:
*   **Job Market Tab**: Cards showing User, Service, Date, Location.
*   **Action**: "Submit Bid" form (Price, Arrival, Notes) or "Update Bid" if already applied. "Withdraw" button available.

**Users Dashboard**:
*   **Dashboard Overview**: "Review Applications" cards for bookings with active bids.
*   **Modal**: Lists all applicants.
    *   **Cards**: Helper Photo, Name, Rating, **Bid Amount (Green)**, Arrival Time, Notes.
    *   **Controls**: Sort by "Lowest Price", "Highest Rating".
    *   **Actions**: "Confirm" (locks booking), "Decline" (removes bid).

## 6. Edge Cases & Handling
*   **Race Conditions**: Handled via Database Transactions when confirming a helper.
*   **Withdrawal**: If a helper withdraws, they are removed from the User's view immediately.
*   **Empty Bids**: Logic handles fallback to hourly rate if no specific bid price provided (though form makes it required).

## 7. Future Improvements
*   **Real-time revisions**: Chat interface to negotiate price before confirming.
*   **Automated matching**: Auto-select helper if criteria matched (e.g. price < X).
