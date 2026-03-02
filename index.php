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
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/home_redesign.css">
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
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
                        <img src="assets/images/service-cleaning.png" alt="Cleaning Service">
                        <div class="image-overlay">Home Cleaning</div>
                    </div>
                    <div class="collage-item small">
                        <img src="assets/images/service-cooking.png" alt="Cooking Service">
                        <div class="image-overlay">Home Cooking</div>
                    </div>
                    <div class="collage-item small">
                        <img src="assets/images/service-elderly.png" alt="Elderly Care">
                        <div class="image-overlay">Elderly Care</div>
                    </div>
                    <div class="collage-item small">
                        <img src="assets/images/service-babysitting.png" alt="Babysitting">
                        <div class="image-overlay">Babysitting</div>
                    </div>
                    <div class="collage-item small">
                        <img src="assets/images/service_plumbing.png" alt="Plumbing">
                        <div class="image-overlay">Plumbing</div>
                    </div>
                    <div class="collage-item small">
                        <img src="assets/images/service_electrical.png" alt="Electrical">
                        <div class="image-overlay">Electrical</div>
                    </div>
                </div>
            </div>

            <!-- Stats Section -->
            <div class="hero-stats">
                <div class="stat-item">
                    <span class="material-icons">star_border</span>
                    <div class="stat-text">
                        <strong>4.8</strong>
                        <span>Service Rating</span>
                    </div>
                </div>
                <div class="stat-item">
                    <span class="material-icons">people_outline</span>
                    <div class="stat-text">
                        <strong>12M+</strong>
                        <span>Customers Globally</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer style="background: #111827; color: white; padding: 4rem 0; margin-top: auto;">
        <div class="container text-center">
            <h2 class="logo" style="margin-bottom: 1rem;">Helpify</h2>
            <p style="color: #9CA3AF; margin-bottom: 2rem;">Connecting you with the best household helpers.</p>
            <p style="font-size: 0.9rem; color: #4B5563;">&copy; 2025 Helpify. All rights reserved.</p>
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
</body>

</html>