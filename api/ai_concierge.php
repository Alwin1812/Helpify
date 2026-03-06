<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';

// No session check here to allow guest assistant access

$input = json_decode(file_get_contents('php://input'), true);
$query = strtolower($input['query'] ?? '');

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Empty query']);
    exit;
}

// Fetch all services and bundles for matching
$services = $pdo->query("SELECT id, name, base_price, description FROM services WHERE parent_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
$bundles = $pdo->query("SELECT id, name, description, discount_percentage FROM service_bundles")->fetchAll(PDO::FETCH_ASSOC);

$response = [
    'text' => "I'm not sure how to help with that yet. Could you try asking about cleaning, cooking, or our special bundles?",
    'recommendations' => [],
    'type' => 'text'
];

// Helper function for keyword matching
function containsAny($str, $keywords)
{
    foreach ($keywords as $kw) {
        if (strpos($str, $kw) !== false)
            return true;
    }
    return false;
}

// 1. Natural Language Routing
if (containsAny($query, ['clean', 'dust', 'tidy', 'sweep'])) {
    $response['text'] = "It sounds like you need some cleaning! I highly recommend our **Deep Clean Duo** bundle for a fresh home, or you can book individual services like **Kitchen Deep Cleaning**.";

    // Find related services/bundles
    foreach ($bundles as $b) {
        if (strpos(strtolower($b['name']), 'clean') !== false || strpos(strtolower($b['description']), 'clean') !== false) {
            $response['recommendations'][] = ['type' => 'bundle', 'id' => $b['id'], 'name' => $b['name']];
        }
    }
    foreach ($services as $s) {
        if (strpos(strtolower($s['name']), 'clean') !== false) {
            $response['recommendations'][] = ['type' => 'service', 'id' => $s['id'], 'name' => $s['name'], 'price' => $s['base_price']];
        }
    }
} elseif (containsAny($query, ['cook', 'food', 'meal', 'dinner', 'chef'])) {
    $response['text'] = "Hungry? Our professional cooks can handle everything from daily meals to special occasions. You might like our **Cooking** service.";
    foreach ($services as $s) {
        if (strpos(strtolower($s['name']), 'cook') !== false) {
            $response['recommendations'][] = ['type' => 'service', 'id' => $s['id'], 'name' => $s['name'], 'price' => $s['base_price']];
        }
    }
} elseif (containsAny($query, ['repair', 'fix', 'broken', 'electric', 'plumb', 'ac', 'fridge'])) {
    $response['text'] = "Something needs fixing! We have expert electricians, plumbers, and appliance repair specialists ready to help.";
    $keywords = ['electric', 'plumb', 'carpenter', 'ac', 'repair', 'fridge', 'washing machine'];
    foreach ($services as $s) {
        $sName = strtolower($s['name']);
        foreach ($keywords as $kw) {
            if (strpos($sName, $kw) !== false) {
                $response['recommendations'][] = ['type' => 'service', 'id' => $s['id'], 'name' => $s['name'], 'price' => $s['base_price']];
                break;
            }
        }
    }
} elseif (containsAny($query, ['baby', 'child', 'kid', 'care', 'elder', 'patient'])) {
    $response['text'] = "Caring for loved ones is important. We offer specialized **Babysitting**, **Elderly Care**, and **Patient Care** services.";
    $keywords = ['baby', 'elderly', 'patient'];
    foreach ($services as $s) {
        $sName = strtolower($s['name']);
        foreach ($keywords as $kw) {
            if (strpos($sName, $kw) !== false) {
                $response['recommendations'][] = ['type' => 'service', 'id' => $s['id'], 'name' => $s['name'], 'price' => $s['base_price']];
                break;
            }
        }
    }
} elseif (containsAny($query, ['offer', 'discount', 'save', 'bundle', 'deal'])) {
    $response['text'] = "Looking for a deal? Our **Service Bundles** offer up to 20% savings! Also, paying via **Helpify Wallet** gives you an instant 5% discount.";
    foreach ($bundles as $b) {
        $response['recommendations'][] = ['type' => 'bundle', 'id' => $b['id'], 'name' => $b['name']];
    }
} elseif (containsAny($query, ['all', 'list', 'every', 'other', 'provide', 'services', 'what do you do'])) {
    $response['text'] = "We provide a wide range of household services! Here are our main categories: \n\n" .
        "• **InstaHelp** (Cleaning, Cooking, Care)\n" .
        "• **Laundry & Dry Cleaning**\n" .
        "• **Car Wash & Detailing**\n" .
        "• **Repairs** (Electrician, Plumber, AC)\n" .
        "• **Personal Care** (Salon, Massage)\n" .
        "• **Home Improvements** (Painting, Water Purifier)\n\n" .
        "What would you like to explore first?";

    // Get one representative service from each major category to show as recommendations
    $parentServices = $pdo->query("SELECT id, name FROM services WHERE parent_id IS NULL LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($parentServices as $ps) {
        $response['recommendations'][] = ['type' => 'category', 'id' => $ps['id'], 'name' => $ps['name']];
    }
} elseif (containsAny($query, ['hi', 'hello', 'hey', 'help'])) {
    $response['text'] = "Hello! I'm your Helpify Concierge. I can help you find the best services for your home. Are you looking for cleaning, repairs, or perhaps some help with cooking?";
}

// Limit recommendations to top 4
$response['recommendations'] = array_slice($response['recommendations'], 0, 4);

echo json_encode($response);
