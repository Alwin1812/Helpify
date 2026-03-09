<?php
// index.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helpify - Simple Household Services</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/home_redesign.css?v=<?php echo time(); ?>">
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/chat.css?v=<?php echo time(); ?>">
</head>

<body>
    <header class="uc-header">
        <div class="header-container">
            <!-- Left: Logo & Nav -->
            <div class="header-left">
                <a href="index.php" class="logo" style="text-decoration: none; display: flex; align-items: center;">
                    <span
                        style="background: black; color: white; padding: 4px 8px; border-radius: 6px; margin-right: 8px; font-weight: 700; font-size: 1.1rem; line-height: 1;">HF</span>
                    <span
                        style="color: #111827; font-weight: 800; font-size: 1.4rem; letter-spacing: -0.5px;">HELPIFY</span>
                </a>
                <nav class="nav-links">
                    <a href="dashboard.php">Revamp</a>
                    <a href="dashboard.php">Native</a>
                    <a href="dashboard.php">Beauty</a>
                </nav>
            </div>

            <!-- Right: Location, Search, User -->
            <div class="header-right">
                <div class="location-box" onclick="openLocationModal()">
                    <span class="material-icons location-icon">location_on</span>
                    <span class="location-text">1201, Cliff Ave- ...</span>
                    <span class="material-icons arrow-icon">keyboard_arrow_down</span>
                </div>

                <div class="search-box">
                    <span class="material-icons search-icon">search</span>
                    <input type="text" id="headerSearchInput" placeholder="Search for 'AC service'">
                    <div id="searchDropdown" class="search-dropdown-menu"></div>
                </div>

                <div class="header-actions">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="icon-btn">
                            <span class="material-icons">shopping_cart</span>
                        </a>
                        <a href="dashboard.php" class="icon-btn user-profile">
                            <span class="material-icons">account_circle</span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="icon-btn">
                            <span class="material-icons">shopping_cart</span>
                        </a>
                        <a href="login.php" class="icon-btn">
                            <span class="material-icons">account_circle</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <section class="hero-section">
        <div class="container">
            <h1 class="hero-title">Home services at your<br>doorstep</h1>

            <div class="hero-layout">
                <!-- Left Side: Service Selection Card -->
                <div class="service-selection-card">
                    <div class="card-header">
                        <h3>What are you looking for?</h3>
                    </div>
                    <div class="service-grid-icons">
                        <!-- Row 1 -->
                        <?php
                        $service_link = isset($_SESSION['user_id']) ? 'dashboard.php' : 'login.php';
                        ?>
                        <a href="<?php echo $service_link; ?>" class="service-icon-item item-cleaning">
                            <div class="icon-box"><span class="material-icons">cleaning_services</span></div>
                            <span>InstaHelp</span>
                        </a>
                        <a href="<?php echo $service_link; ?>" class="service-icon-item item-women">
                            <div class="icon-box"><span class="material-icons">local_laundry_service</span></div>
                            <span>Laundry & Dry Cleaning</span>
                        </a>
                        <a href="<?php echo $service_link; ?>" class="service-icon-item item-men">
                            <div class="icon-box"><span class="material-icons">directions_car</span></div>
                            <span>Car Wash & Detailing</span>
                        </a>

                        <!-- Row 2 -->
                        <a href="<?php echo $service_link; ?>" class="service-icon-item item-pest">
                            <div class="icon-box"><span class="material-icons">bug_report</span></div>
                            <span>Cleaning & Pest Control</span>
                        </a>
                        <a href="<?php echo $service_link; ?>" class="service-icon-item item-electric">
                            <div class="icon-box"><span class="material-icons">handyman</span></div>
                            <span>Electrician, Plumber & Carpenter</span>
                        </a>
                        <a href="<?php echo $service_link; ?>" class="service-icon-item item-water">
                            <div class="icon-box"><span class="material-icons">water_drop</span></div>
                            <span>Native Water Purifier</span>
                        </a>

                        <!-- Row 3 -->
                        <a href="<?php echo $service_link; ?>" class="service-icon-item item-paint">
                            <div class="icon-box"><span class="material-icons">format_paint</span></div>
                            <span>Painting & Waterproofing</span>
                        </a>
                        <a href="<?php echo $service_link; ?>" class="service-icon-item item-ac">
                            <div class="icon-box"><span class="material-icons">ac_unit</span></div>
                            <span>AC & Appliance Repair</span>
                        </a>
                        <a href="<?php echo $service_link; ?>" class="service-icon-item item-wall">
                            <div class="icon-box"><span class="material-icons">wallpaper</span></div>
                            <span>Wall makeover by Revamp</span>
                        </a>
                    </div>
                </div>

                <!-- Right Side: Image Collage -->
                <div class="hero-collage">
                    <div class="collage-item large">
                        <img src="assets/images/feat_cleaning.png" alt="Cleaning Service">
                        <div class="image-overlay">Home Cleaning</div>
                    </div>
                    <div class="collage-item small">
                        <img src="assets/images/feat_cooking.png" alt="Cooking Service">
                        <div class="image-overlay">Home Cooking</div>
                    </div>
                    <div class="collage-item small">
                        <img src="assets/images/feat_elderly.png" alt="Elderly Care">
                        <div class="image-overlay">Elderly Care</div>
                    </div>
                    <div class="collage-item small">
                        <img src="assets/images/feat_babysitting.png" alt="Babysitting">
                        <div class="image-overlay">Babysitting</div>
                    </div>
                    <div class="collage-item small">
                        <img src="assets/images/feat_plumbing.png" alt="Plumbing">
                        <div class="image-overlay">Plumbing</div>
                    </div>
                    <div class="collage-item small">
                        <img src="assets/images/feat_electrical.png" alt="Electrical">
                        <div class="image-overlay">Electrical</div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Helpify Bundles Section -->
    <section class="bundles-section" id="bundles" style="padding: 5rem 2rem; background: #f8fafc;">
        <div style="max-width: 1200px; margin: 0 auto; text-align: center;">
            <h2 style="font-size: 2.5rem; margin-bottom: 1rem; color: #0F172A;">Helpify Bundles <span
                    style="background: #2563EB; color: white; padding: 0.2rem 0.8rem; border-radius: 99px; font-size: 1rem; vertical-align: middle; margin-left: 10px;">SAVE
                    UP TO 20%</span></h2>
            <p style="color: #64748B; margin-bottom: 3rem; font-size: 1.1rem;">Multi-service packages for a complete
                home
                care experience.</p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2.5rem;">
                <?php
                require_once 'includes/db_connect.php';
                $bundles = $pdo->query("SELECT * FROM service_bundles")->fetchAll();
                foreach ($bundles as $bundle): ?>
                    <div style="border: 1px solid #E2E8F0; border-radius: 24px; overflow: hidden; transition: all 0.3s; cursor: pointer; background: white; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);"
                        onmouseover="this.style.transform='translateY(-10px)'; this.style.boxShadow='0 20px 25px -5px rgba(0, 0, 0, 0.1)'"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 10px 15px -3px rgba(0, 0, 0, 0.05)'"
                        onclick="window.location.href='<?php echo isset($_SESSION['user_id']) ? 'dashboard.php?bundle_id=' . $bundle['id'] : 'login.php'; ?>'">
                        <div style="height: 200px; overflow: hidden; position: relative;">
                            <?php if (!empty($bundle['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($bundle['image_url']); ?>?v=<?php echo time(); ?>"
                                    alt="<?php echo htmlspecialchars($bundle['name']); ?>"
                                    style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div
                                    style="width: 100%; height: 100%; background: #EFF6FF; display: flex; align-items: center; justify-content: center;">
                                    <span class="material-icons" style="font-size: 48px; color: #2563EB;">card_giftcard</span>
                                </div>
                            <?php endif; ?>
                            <div
                                style="position: absolute; top: 1.5rem; left: 1.5rem; background: rgba(37, 99, 235, 0.9); color: white; padding: 0.4rem 1rem; border-radius: 99px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase;">
                                SAVE <?php echo $bundle['discount_percentage']; ?>%
                            </div>
                        </div>
                        <div style="padding: 2rem; text-align: left; background: white;">
                            <h3 style="font-size: 1.75rem; margin-bottom: 0.75rem; color: #0F172A;">
                                <?php echo htmlspecialchars($bundle['name']); ?>
                            </h3>
                            <p style="color: #64748B; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.5rem;">
                                <?php echo htmlspecialchars($bundle['description']); ?>
                            </p>
                            <div style="font-weight: 600; color: #0F172A; margin-bottom: 1rem; font-size: 0.9rem;">What's
                                included:</div>
                            <ul style="list-style: none; padding: 0; margin-bottom: 2rem;">
                                <?php
                                $stmt = $pdo->prepare("SELECT s.name FROM bundle_items bi JOIN services s ON bi.service_id = s.id WHERE bi.bundle_id = ?");
                                $stmt->execute([$bundle['id']]);
                                $items = $stmt->fetchAll();
                                foreach ($items as $item): ?>
                                    <li
                                        style="display: flex; align-items: center; gap: 10px; margin-bottom: 0.75rem; font-size: 1rem; color: #334155;">
                                        <span class="material-icons"
                                            style="color: #10B981; font-size: 20px;">check_circle</span>
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="<?php echo isset($_SESSION['user_id']) ? 'dashboard.php?bundle_id=' . $bundle['id'] : 'login.php'; ?>"
                                class="btn btn-primary"
                                style="width: 100%; text-align: center; display: block; background: #0F172A; padding: 1rem; border-radius: 12px; font-weight: 600; text-decoration: none; color: white;">Book
                                Bundle</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Minimal Stats Bar Section -->
    <section class="stats-bar-section">
        <div class="container">
            <div class="footer-stats-bar">
                <div class="stat-item">
                    <span class="material-icons">star_outline</span>
                    <div class="stat-text">
                        <div class="stat-value">4.8</div>
                        <div class="stat-label">Service Rating</div>
                    </div>
                </div>
                <div class="stat-item">
                    <span class="material-icons">group_outline</span>
                    <div class="stat-text">
                        <div class="stat-value">12M+</div>
                        <div class="stat-label">Customers Globally</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="main-footer">
        <div class="container">
            <div class="footer-minimal-content">
                <p class="footer-tagline">Connecting you with the best household helpers.</p>
                <p class="footer-copyright">&copy; 2025 Helpify. All rights reserved.</p>
            </div>
        </div>
    </footer>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- Location Selection Modal -->
    <div id="locationModal" class="modal-overlay">
        <div class="modal-content location-modal-content">
            <div class="modal-header">
                <h3>Select Location</h3>
                <button onclick="closeLocationModal()" class="close-modal">
                    <span class="material-icons">close</span>
                </button>
            </div>

            <div class="location-body">
                <!-- Map Container (Hidden initially) -->
                <div id="mapContainer"
                    style="height: 250px; width: 100%; border-radius: 12px; margin-bottom: 1.5rem; display: none;">
                </div>

                <!-- Option 1: Detect Location -->
                <div class="detect-location-btn" onclick="detectLocation()">
                    <div class="icon-circle">
                        <span class="material-icons">my_location</span>
                    </div>
                    <div class="text-content">
                        <strong>Detect Current Location</strong>
                        <p>Using GPS</p>
                    </div>
                </div>

                <div class="divider-text">OR</div>

                <!-- Option 2: Manual Input -->
                <div class="manual-location-container">
                    <div class="input-wrapper">
                        <span class="material-icons search-icon-input">search</span>
                        <input type="text" id="manualLocationInput" placeholder="Search for area, street name..."
                            autocomplete="off">
                    </div>
                    <!-- Suggestions List -->
                    <div id="locationSuggestions" class="suggestions-dropdown" style="display: none;"></div>
                </div>

                <button onclick="saveManualLocation()" class="btn-primary update-location-btn">Update Location</button>

                <!-- Recent Locations -->
                <div id="recentLocations" class="recent-locations-section" style="display: none;">
                    <h4>Recent Locations</h4>
                    <div id="recentList" class="recent-list"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Map Variables
        let map;
        let marker;
        const defaultLat = 20.5937; // India Center Lat
        const defaultLng = 78.9629; // India Center Lng

        // Modal Functions
        function openLocationModal() {
            document.getElementById('locationModal').style.display = 'flex';
            loadRecentLocations();
            document.getElementById('manualLocationInput').focus();

            // Initialize Map if not already
            if (!map) {
                setTimeout(() => {
                    initMap();
                }, 100);
            } else {
                setTimeout(() => {
                    map.invalidateSize(); // Fix map rendering in modal
                }, 100);
            }
        }

        function closeLocationModal() {
            document.getElementById('locationModal').style.display = 'none';
        }

        // Close modal if clicking outside content
        window.onclick = function (event) {
            const modal = document.getElementById('locationModal');
            if (event.target == modal) {
                closeLocationModal();
            }
        }

        // Location Logic
        const locationTextElement = document.querySelector('.location-box .location-text');

        function initMap() {
            // Show map container
            document.getElementById('mapContainer').style.display = 'block';

            map = L.map('mapContainer').setView([defaultLat, defaultLng], 5);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            // Add Click Event to Map
            map.on('click', function (e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;
                updateMapMarker(lat, lng);
                fetchAddress(lat, lng);
            });
        }

        function updateMapMarker(lat, lng) {
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng]).addTo(map);
            }
            map.setView([lat, lng], 13);
        }

        // Load saved location on startup
        window.onload = function () {
            const savedLocation = localStorage.getItem('userLocation');
            if (savedLocation) {
                updateLocationUI(savedLocation, false); // Don't save again on load
            }
        }

        function updateLocationUI(address, save = true) {
            // Truncate if too long for UI
            const displayText = address.length > 20 ? address.substring(0, 18) + '...' : address;
            locationTextElement.textContent = displayText;

            if (save) {
                localStorage.setItem('userLocation', address);
                saveRecentLocation(address);
            }
            closeLocationModal();
        }

        function detectLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(showPosition, showError);
            } else {
                alert("Geolocation is not supported by this browser.");
            }
        }

        function showPosition(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            // Show on map
            updateMapMarker(lat, lng);
            fetchAddress(lat, lng);
        }

        function fetchAddress(lat, lng) {
            const gpsText = document.querySelector('.detect-location-btn .text-content p');
            gpsText.textContent = "Fetching address...";

            // Use OpenStreetMap Nominatim API for better details
            fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    // Extract a better address format
                    let fullAddress = data.display_name;

                    // Try to make it shorter if possible (Road, Suburb, City)
                    if (data.address) {
                        const addr = data.address;
                        const parts = [];
                        if (addr.road) parts.push(addr.road);
                        if (addr.suburb) parts.push(addr.suburb);
                        if (addr.city || addr.town || addr.village) parts.push(addr.city || addr.town || addr.village);
                        if (addr.state) parts.push(addr.state);

                        if (parts.length > 0) fullAddress = parts.join(', ');
                    }

                    // Update Input Field with detected address
                    document.getElementById('manualLocationInput').value = fullAddress;

                    // Update Helper Text in the Detect Button
                    gpsText.textContent = fullAddress;
                    gpsText.style.color = "var(--primary-color)";
                    gpsText.style.fontWeight = "500";
                })
                .catch(() => {
                    const fallback = `Lat: ${lat.toFixed(4)}, Lng: ${lng.toFixed(4)}`;
                    document.getElementById('manualLocationInput').value = fallback;
                    gpsText.textContent = "Address not found";
                });
        }

        function showError(error) {
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    alert("User denied the request for Geolocation.");
                    break;
                case error.POSITION_UNAVAILABLE:
                    alert("Location information is unavailable.");
                    break;
                case error.TIMEOUT:
                    alert("The request to get user location timed out.");
                    break;
                case error.UNKNOWN_ERROR:
                    alert("An unknown error occurred.");
                    break;
            }
            // locationTextElement.textContent = "Select Location"; // Removed this line
        }

        function saveManualLocation() {
            const input = document.getElementById('manualLocationInput').value;
            if (input.trim() !== "") {
                updateLocationUI(input);
            } else {
                alert("Please enter a valid location.");
            }
        }

        /* --- Auto-Complete & Recents Logic --- */

        // Mock Data for Suggestions (Major Indian Cities)
        const cityList = [
            "Mumbai, Maharashtra", "Delhi, NCR", "Bangalore, Karnataka", "Hyderabad, Telangana",
            "Chennai, Tamil Nadu", "Kolkata, West Bengal", "Pune, Maharashtra", "Ahmedabad, Gujarat",
            "Jaipur, Rajasthan", "Surat, Gujarat", "Lucknow, Uttar Pradesh", "Kanpur, Uttar Pradesh",
            "Nagpur, Maharashtra", "Indore, Madhya Pradesh", "Thane, Maharashtra", "Bhopal, Madhya Pradesh",
            "Visakhapatnam, Andhra Pradesh", "Pimpri-Chinchwad, Maharashtra", "Patna, Bihar", "Vadodara, Gujarat",
            "Ghaziabad, Uttar Pradesh", "Ludhiana, Punjab", "Agra, Uttar Pradesh", "Nashik, Maharashtra",
            "Faridabad, Haryana", "Meerut, Uttar Pradesh", "Rajkot, Gujarat", "Kalyan-Dombivli, Maharashtra",
            "Vasai-Virar, Maharashtra", "Varanasi, Uttar Pradesh", "Srinagar, Jammu and Kashmir", "Aurangabad, Maharashtra",
            "Dhanbad, Jharkhand", "Amritsar, Punjab", "Navi Mumbai, Maharashtra", "Allahabad, Uttar Pradesh"
        ];

        const inputField = document.getElementById('manualLocationInput');
        const suggestionsBox = document.getElementById('locationSuggestions');

        inputField.addEventListener('input', function () {
            const query = this.value.toLowerCase();
            suggestionsBox.innerHTML = '';

            if (query.length > 0) {
                const matches = cityList.filter(city => city.toLowerCase().includes(query));

                if (matches.length > 0) {
                    suggestionsBox.style.display = 'block';
                    matches.forEach(city => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item';
                        div.innerText = city;
                        div.onclick = function () {
                            inputField.value = city;
                            suggestionsBox.style.display = 'none';
                            // Focus map on selected city (Simple mock coords for demo)
                            // In real app, you'd geocode this name to get coords
                            updateLocationUI(city);
                        };
                        suggestionsBox.appendChild(div);
                    });
                } else {
                    suggestionsBox.style.display = 'none';
                }
            } else {
                suggestionsBox.style.display = 'none';
            }
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', function (e) {
            if (e.target !== inputField && e.target !== suggestionsBox) {
                suggestionsBox.style.display = 'none';
            }
        });

        // Recent Locations Functions
        function saveRecentLocation(location) {
            let recents = JSON.parse(localStorage.getItem('recentLocations') || '[]');
            // Remove if exists to push to top
            recents = recents.filter(item => item !== location);
            recents.unshift(location);
            // Keep max 5
            if (recents.length > 5) recents.pop();
            localStorage.setItem('recentLocations', JSON.stringify(recents));
        }

        function loadRecentLocations() {
            const recents = JSON.parse(localStorage.getItem('recentLocations') || '[]');
            const recentContainer = document.getElementById('recentLocations');
            const recentList = document.getElementById('recentList');

            if (recents.length > 0) {
                recentContainer.style.display = 'block';
                recentList.innerHTML = '';
                recents.forEach(loc => {
                    const div = document.createElement('div');
                    div.className = 'recent-item';
                    div.innerHTML = `<span class="material-icons" style="font-size: 16px; color: var(--text-light);">history</span> ${loc}`;
                    div.onclick = () => updateLocationUI(loc);
                    recentList.appendChild(div);
                });
            } else {
                recentContainer.style.display = 'none';
            }
        }
    </script>

    <!-- Search Functionality Script -->
    <script>
        const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

        // Service Data
        const services = [
            { name: "InstaHelp", icon: "cleaning_services", keywords: ["maid", "clean", "help"] },
            { name: "Laundry & Dry Cleaning", icon: "local_laundry_service", keywords: ["laundry", "dry", "clean", "wash"] },
            { name: "Car Wash & Detailing", icon: "directions_car", keywords: ["car", "wash", "detail", "auto"] },
            { name: "Cleaning & Pest Control", icon: "bug_report", keywords: ["pest", "control", "insect", "cleaning"] },
            { name: "Electrician, Plumber & Carpenter", icon: "handyman", keywords: ["electrician", "plumber", "carpenter", "repair"] },
            { name: "Native Water Purifier", icon: "water_drop", keywords: ["water", "purifier", "filter"] },
            { name: "Painting & Waterproofing", icon: "format_paint", keywords: ["paint", "waterproof", "wall"] },
            { name: "AC & Appliance Repair", icon: "ac_unit", keywords: ["ac", "appliance", "repair", "fridge"] },
            { name: "Wall makeover by Revamp", icon: "wallpaper", keywords: ["wall", "decor", "makeover"] }
        ];

        const searchInput = document.getElementById('headerSearchInput');
        const searchDropdown = document.getElementById('searchDropdown');

        // Show all services on focus
        searchInput.addEventListener('focus', function () {
            renderSearchResults(services); // Show all initially
            searchDropdown.style.display = 'block';
        });

        // Filter on input
        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase();
            if (query.length === 0) {
                renderSearchResults(services);
                return;
            }

            const filtered = services.filter(service => {
                return service.name.toLowerCase().includes(query) ||
                    service.keywords.some(k => k.includes(query));
            });

            renderSearchResults(filtered);
        });

        // Hide on click outside
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
                searchDropdown.style.display = 'none';
            }
        });

        function renderSearchResults(list) {
            searchDropdown.innerHTML = '';

            if (list.length === 0) {
                searchDropdown.innerHTML = '<div class="no-results">No services found</div>';
                return;
            }

            // Group Title if showing all
            if (searchInput.value === '') {
                const title = document.createElement('div');
                title.className = 'search-group-title';
                title.textContent = 'Popular Services';
                searchDropdown.appendChild(title);
            }

            list.forEach(service => {
                const item = document.createElement('a');
                item.href = isLoggedIn ? 'dashboard.php' : 'login.php'; // Link to service or login page
                item.className = 'search-result-item';

                item.innerHTML = `
                    <div class="result-icon">
                        <span class="material-icons">${service.icon}</span>
                    </div>
                    <div class="result-info">
                        <span class="service-name">${service.name}</span>
                    </div>
                `;
                searchDropdown.appendChild(item);
            });
        }
    </script>
    <!-- FAQ Toggle Script -->
    <script>
        document.querySelectorAll('.faq-question').forEach(button => {
            button.addEventListener('click', () => {
                const faqItem = button.parentElement;
                const faqAnswer = button.nextElementSibling;

                // Toggle active class for icon animation
                faqItem.classList.toggle('active');

                // Toggle 'show' class for the answer
                if (faqAnswer.classList.contains('show')) {
                    faqAnswer.classList.remove('show');
                } else {
                    faqAnswer.classList.add('show');
                }
            });
        });
    </script>
    <!-- AI Concierge Floating Button -->
    <div id="aiConciergeBtn"
        style="position: fixed; bottom: 30px; right: 30px; width: 65px; height: 65px; background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4); z-index: 2100; transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
        <span class="material-icons" style="font-size: 35px;">smart_toy</span>
    </div>

    <!-- AI Concierge Modal -->
    <div id="aiConciergeOverlay" class="chat-modal-overlay"
        style="display: none; height: 480px; bottom: 110px; right: 30px; flex-direction: column; overflow: hidden;">
        <div class="chat-header" style="background: linear-gradient(135deg, #059669 0%, #10B981 100%);">
            <h4 style="margin: 0; display: flex; align-items: center; gap: 8px;"><span
                    class="material-icons">smart_toy</span> Helpify AI Concierge</h4>
            <span class="material-icons close-chat" onclick="toggleAIConcierge()">close</span>
        </div>
        <div id="aiChatMessages" class="chat-messages">
            <div class="message-bubble received">
                Hello! I'm your <b>Helpify Assistant</b>. How can I sparkle your day? ✨
                <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:8px;">
                    <button class="quick-reply-btn" onclick="sendAIQuery('I need cleaning')">
                        <span class="material-icons" style="font-size:16px;">cleaning_services</span> Cleaning
                    </button>
                    <button class="quick-reply-btn" onclick="sendAIQuery('What are your deals?')">
                        <span class="material-icons" style="font-size:16px;">local_offer</span> Deals
                    </button>
                    <button class="quick-reply-btn" onclick="sendAIQuery('Need repairs')">
                        <span class="material-icons" style="font-size:16px;">build</span> Repairs
                    </button>
                </div>
            </div>
            <div id="typingIndicator" class="typing-indicator" style="display: none;">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            </div>
        </div>
        <div class="chat-input-area">
            <input type="text" id="aiInput" placeholder="Ask me anything..."
                onkeypress="if(event.key === 'Enter') sendAIChat()">
            <button class="chat-send-btn" onclick="sendAIChat()">
                <span class="material-icons">send</span>
            </button>
        </div>
    </div>

    <script>
        document.getElementById('aiConciergeBtn').onclick = toggleAIConcierge;

        function toggleAIConcierge() {
            const overlay = document.getElementById('aiConciergeOverlay');
            overlay.style.display = overlay.style.display === 'none' ? 'flex' : 'none';
        }

        function sendAIQuery(q) {
            document.getElementById('aiInput').value = q;
            sendAIChat();
        }

        async function sendAIChat() {
            const input = document.getElementById('aiInput');
            const query = input.value.trim();
            if (!query) return;

            addAIMessage(query, 'sent');
            input.value = '';
            
            // Show typing indicator
            const indicator = document.getElementById('typingIndicator');
            indicator.style.display = 'flex';
            const container = document.getElementById('aiChatMessages');
            container.scrollTop = container.scrollHeight;

            try {
                const res = await fetch('api/ai_concierge.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query })
                });
                const data = await res.json();
                
                // Keep typing indicator for a realistic feel
                await new Promise(r => setTimeout(r, 600));
                indicator.style.display = 'none';

                let html = data.text;
                if (data.recommendations && data.recommendations.length > 0) {
                    html += '<div style="margin-top: 12px; border-top: 1px dashed var(--chat-secondary); padding-top: 10px;">';
                    data.recommendations.forEach(rec => {
                        const label = rec.type === 'bundle' ? 'Bundle Offer' : (rec.type === 'category' ? 'Department' : 'Service');
                        const icon = rec.type === 'bundle' ? 'card_giftcard' : 'arrow_forward';
                        html += `
                            <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 12px; border-radius: 12px; margin-bottom: 8px; border: 1px solid var(--chat-secondary); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                <div style="display: flex; flex-direction: column;">
                                    <span style="font-size: 0.85rem; font-weight: 700; color: #065F46;">${rec.name}</span>
                                    <span style="font-size: 0.75rem; color: #059669;">${label}</span>
                                </div>
                                <a href="dashboard.php" style="background: var(--chat-primary); color: white; border: none; padding: 8px 14px; border-radius: 20px; font-size: 0.75rem; cursor: pointer; text-decoration: none; font-weight: 700; display: flex; align-items: center; gap: 4px;">
                                    Go <span class="material-icons" style="font-size:14px;">${icon}</span>
                                </a>
                            </div>`;
                    });
                    html += '</div>';
                }

                addAIMessage(html, 'received');
            } catch (err) {
                indicator.style.display = 'none';
                addAIMessage("I'm resting right now. Visit us later! 😴", 'received');
            }
        }

        function addAIMessage(text, type) {
            const container = document.getElementById('aiChatMessages');
            const indicator = document.getElementById('typingIndicator');
            const div = document.createElement('div');
            div.className = `message-bubble ${type}`;
            div.innerHTML = text;
            
            // Insert before typing indicator
            container.insertBefore(div, indicator);
            container.scrollTop = container.scrollHeight;
        }
    </script>
</body>

</html>