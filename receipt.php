<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'user') {
    die("Access denied.");
}

$booking_id = $_GET['id'] ?? null;
if (!$booking_id) {
    die("Booking ID missing.");
}

// Fetch booking and payment details
$stmt = $pdo->prepare("
    SELECT b.*, s.name as service_name, s.base_price, u.name as helper_name, u.phone_number as helper_phone
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    LEFT JOIN users u ON b.helper_id = u.id
    WHERE b.id = ? AND b.user_id = ? AND b.payment_status = 'paid'
");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Invalid booking or payment not yet completed.");
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - Helpify</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3B82F6;
            --text-dark: #1F2937;
            --text-light: #6B7280;
            --bg-light: #F9FAFB;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-light);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
        }

        .receipt-card {
            background: white;
            width: 100%;
            max-width: 600px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #E5E7EB;
        }

        .receipt-header {
            background: #0F172A;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .receipt-logo {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .status-badge {
            background: #10B981;
            color: white;
            padding: 4px 12px;
            border-radius: 99px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 10px;
        }

        .receipt-body {
            padding: 30px;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #F3F4F6;
        }

        .receipt-row:last-child {
            border-bottom: none;
        }

        .label {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .value {
            color: var(--text-dark);
            font-weight: 600;
            text-align: right;
        }

        .total-section {
            background: #F8FAFC;
            padding: 20px 30px;
            border-top: 2px dashed #E2E8F0;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-label {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .total-amount {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-color);
        }

        .footer {
            padding: 30px;
            text-align: center;
            color: var(--text-light);
            font-size: 0.85rem;
        }

        .btn-print {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 20px auto 0;
            transition: opacity 0.2s;
        }

        .btn-print:hover {
            opacity: 0.9;
        }

        @media print {
            .btn-print {
                display: none;
            }

            body {
                padding: 0;
                background: white;
            }

            .receipt-card {
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>

<body>
    <div class="receipt-card">
        <div class="receipt-header">
            <div class="receipt-logo">
                <span class="material-icons">handshake</span>
                Helpify
            </div>
            <div style="font-size: 0.9rem; opacity: 0.8;">Payment Successful</div>
            <div class="status-badge">PAID</div>
        </div>

        <div class="receipt-body">
            <div class="receipt-row">
                <span class="label">Transaction ID</span>
                <span class="value">
                    <?php echo $booking['razorpay_payment_id'] ?: 'N/A'; ?>
                </span>
            </div>
            <div class="receipt-row">
                <span class="label">Date</span>
                <span class="value">
                    <?php echo date('d M Y, h:i A'); ?>
                </span>
            </div>
            <div class="receipt-row">
                <span class="label">Booking Reference</span>
                <span class="value">#
                    <?php echo $booking['id']; ?>
                </span>
            </div>
            <div class="receipt-row">
                <span class="label">Service</span>
                <span class="value">
                    <?php echo htmlspecialchars($booking['service_name']); ?>
                </span>
            </div>
            <div class="receipt-row">
                <span class="label">Helper</span>
                <span class="value">
                    <?php echo htmlspecialchars($booking['helper_name'] ?: 'Pending'); ?>
                </span>
            </div>

            <?php if ($booking['promo_code']): ?>
                <div class="receipt-row">
                    <span class="label">Promo Code Applied</span>
                    <span class="value">
                        <?php echo htmlspecialchars($booking['promo_code']); ?>
                    </span>
                </div>
                <div class="receipt-row">
                    <span class="label">Discount</span>
                    <span class="value text-danger">- ₹
                        <?php echo number_format($booking['discount_amount'], 2); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <div class="total-section">
            <div class="total-row">
                <span class="total-label">Total Amount Paid</span>
                <span class="total-amount">₹
                    <?php echo number_format($booking['total_amount'], 2); ?>
                </span>
            </div>

            <button class="btn-print" onclick="window.print()">
                <span class="material-icons">print</span>
                Print Receipt
            </button>
        </div>

        <div class="footer">
            <p>Thank you for choosing Helpify!</p>
            <p style="font-size: 0.75rem;">This is a computer-generated receipt.</p>
        </div>
    </div>
</body>

</html>