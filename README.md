# Helpify - AI-Powered Service Marketplace 🏠✨

Helpify is a modern, web-based marketplace designed to bridge the gap between household service seekers and professional helpers. Built with a focus on **Premium UX (Glassmorphism)** and **AI-driven discovery**, Helpify transforms the fragmented domestic service sector into a structured digital ecosystem.

## 🚀 Key Features

- **AI Concierge**: Smart search and service recommendations powered by Gemini API.
- **Voice UI (VUI)**: Built-in voice commands for hands-free service discovery.
- **Live Tracking**: Real-time status updates and helper arrival estimation via Leaflet.js.
- **Secure Payments**: Integrated Razorpay gateway for safe, cashless transactions.
- **Role-Based Access**: Specialized dashboards for Users, Helpers, and Administrators.
- **Wallet System**: In-app wallet for quick payments and transaction history.

## 🛠️ Tech Stack

- **Frontend**: HTML5, CSS3 (Glassmorphism Design), JavaScript (ES6+), jQuery, AJAX.
- **Backend**: PHP 7.4+ (Clean, modular logic).
- **Database**: MySQL (Relational data management).
- **Maps**: Leaflet.js & OpenStreetMap.
- **AI Integration**: Google Gemini API.
- **Payment Gateway**: Razorpay API.

## 📦 Installation & Setup

1. **Clone the repository**:
   ```bash
   git clone https://github.com/Alwin1812/Helpify.git
   ```
2. **Setup Database**:
   - Import `helpify_db.sql` into your local MySQL server (XAMPP/WAMP).
3. **Configure Environment**:
   - Rename `includes/db_connect.php.example` to `db_connect.php` and add your credentials.
   - Add your Gemini and Razorpay keys in the `api/` and `includes/` config files.
4. **Run Application**:
   - Place the folder in your `htdocs` directory and access via `http://localhost/helpify`.

## 🔒 Security Features

- Prepared SQL Statements (PDO) to prevent SQL Injection.
- Role-based Authorization (RBAC) for data protection.
- Secure session management and CSRF protection.
- Environment variable support for API keys.

---
*Developed with ❤️ for the future of domestic services.*