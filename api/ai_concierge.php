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

// Helper function for keyword matching with word boundary check
function containsWord($str, $keywords)
{
    foreach ($keywords as $kw) {
        // Use regex for word boundaries
        if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $str)) {
            return true;
        }
    }
    return false;
}

// 1. Natural Language Routing

// Fuzzy Greeting Check for repeated letters (e.g., "haiii", "helloooo")
$isGreeting = false;
$greetings = ['hi', 'hello', 'hey', 'hai', 'yo', 'greet', 'morning', 'evening', 'hie'];
foreach ($greetings as $g) {
    if (preg_match('/' . $g . '[a-z]*/i', $query) && strlen($query) < 15) {
        $isGreeting = true;
        break;
    }
}

// Greetings
if ($isGreeting) {
    $userName = isset($_SESSION['user_name']) ? explode(' ', $_SESSION['user_name'])[0] : 'friend';
    $response['text'] = "Hello $userName! ✨ I'm your Helpify Concierge, and I'm absolutely delighted to help you today! I specialize in making your home life as smooth as possible. Are you looking to refresh your space with a **deep clean**, need an expert to **fix a repair**, or maybe you're dreaming of a **home-cooked meal**? Just let me know!";

    $response['recommendations'][] = ['type' => 'bundle', 'id' => 1, 'name' => 'Deep Clean Duo'];
    $response['recommendations'][] = ['type' => 'service', 'id' => 1, 'name' => 'Maid Service', 'price' => 500];
}
// Casual Talk: Identity
elseif (containsWord($query, ['who are you', 'who r u', 'your name', 'what are you'])) {
    $response['text'] = "I'm your Helpify AI Concierge! 🤖 I'm like a super-powered personal assistant for your home. I can find helpers, check prices, explain our services, and help you book exactly what you need in seconds. Think of me as your shortcut to a happy home! ✨";
}
// Casual Talk: About Helpify
elseif (containsWord($query, ['what is helpify', 'what is this', 'about helpify', 'helpify do'])) {
    $response['text'] = "Helpify is your ultimate home service partner! 🏠 We connect you with top-rated, verified professionals for everything from cleaning and cooking to complex electrical repairs. Our goal is to give you your time back so you can focus on what matters most. Plus, we make booking as easy as a few taps! 📲";
}
// Casual Talk: Jokes
elseif (containsWord($query, ['joke', 'funny', 'laugh'])) {
    $jokes = [
        "Why did the computer go to the dentist? Because it had a hard drive! 😂",
        "Why did the invisible man turn down the job offer? He just couldn't see himself doing it! 🤣",
        "What do you call a fake noodle? An Impasta! 🍝",
        "I told my wife she was drawing her eyebrows too high. She looked surprised! 😲"
    ];
    $response['text'] = $jokes[array_rand($jokes)] . "\n\nBut seriously, I'm much better at finding you a great cleaner than I am at stand-up comedy! Want to see our cleaning services? 🧹";
    $response['recommendations'][] = ['type' => 'category', 'id' => 1, 'name' => 'InstaHelp'];
}
// Casual Talk: Thank you
elseif (containsWord($query, ['thank', 'thanks', 'thx', 'tks', 'thks', 'great', 'awesome', 'perfect'])) {
    $response['text'] = "Aww, you're very welcome! 😊 It's my pleasure to assist. I'm always standing by if you need anything else to make your day sparkle. Would you like to check out some of our current special offers or maybe browse a new department?";
}
// Casual Talk: OK / Filler
elseif (containsWord($query, ['ok', 'okay', 'cool', 'nice', 'understand', 'got it', 'sure', 'fine'])) {
    $response['text'] = "Wonderful! I'm glad we're on the same page. ✨ Think of me as your personal home manager. Is there a specific corner of your home that needs a little extra love right now? I can find you the perfect professional for almost anything!";
    $response['recommendations'][] = ['type' => 'bundle', 'id' => 1, 'name' => 'Deep Clean Duo'];
    $response['recommendations'][] = ['type' => 'category', 'id' => 1, 'name' => 'InstaHelp'];
}
// Casual Talk: Goodbye
elseif (containsWord($query, ['bye', 'goodbye', 'seeya', 'later', 'night'])) {
    $response['text'] = "Farewell for now! It was a pleasure chatting with you. I'll be right here whenever you need a helping hand to keep your home running perfectly. Have an absolutely wonderful day! 👋✨";
}
// Small Talk: How are you
elseif (containsWord($query, ['how are you', 'how r u', 'watsu', 'sup'])) {
    $response['text'] = "I'm doing absolutely fantastic, thank you for asking! 😊 I'm fully charged and ready to find you the most reliable professionals in town. How's your day going? Is there anything I can do to make it even better?";
}
// Specific Pricing Search (e.g., "dry cleaning price")
elseif (containsAny($query, ['price', 'cost', 'charge', 'rate', 'how much'])) {
    $matchedService = null;
    foreach ($services as $s) {
        if (strpos($query, strtolower($s['name'])) !== false) {
            $matchedService = $s;
            break;
        }
    }

    if ($matchedService) {
        $response['text'] = "Our **" . $matchedService['name'] . "** service starts at just **₹" . $matchedService['base_price'] . "**! 💰 It's one of our most popular choices because of the incredible quality. Would you like me to add it to your booking or should I show you a bundle that includes it for even better savings?";
        $response['recommendations'][] = ['type' => 'service', 'id' => $matchedService['id'], 'name' => $matchedService['name'], 'price' => $matchedService['base_price']];
    } else {
        $response['text'] = "We pride ourselves on being super affordable! 💸 Most of our individual services start as low as **₹199**. Plus, if you choose one of our curated bundles, you'll save an extra **20%**! Which specific service can I get the exact price for today?";
    }
}
// Services: Cleaning
elseif (containsAny($query, ['clean', 'dust', 'tidy', 'sweep', 'laundry', 'wash'])) {
    $response['text'] = "Nothing beats the feeling of a spotless home! 🧹 Whether it's a deep kitchen clean or getting your laundry perfectly crisp, our professionals are literal magicians. I highly recommend our **Deep Clean Duo** for the best value, or maybe our **Washing & Ironing** service for your clothes. What should we tackle first?";

    foreach ($bundles as $b) {
        if (strpos(strtolower($b['name']), 'clean') !== false || strpos(strtolower($b['description']), 'clean') !== false) {
            $response['recommendations'][] = ['type' => 'bundle', 'id' => $b['id'], 'name' => $b['name']];
        }
    }
    foreach ($services as $s) {
        if (containsAny(strtolower($s['name']), ['clean', 'laundry', 'wash'])) {
            $response['recommendations'][] = ['type' => 'service', 'id' => $s['id'], 'name' => $s['name'], 'price' => $s['base_price']];
        }
    }
} elseif (containsAny($query, ['cook', 'food', 'meal', 'dinner', 'chef'])) {
    $response['text'] = "Hungry? I've got you covered! 🍲 Our professional cooks can whip up everything from your favorite daily comfort meals to fancy dinner parties. They can even customize the spice levels exactly to your taste. How does a delicious, home-cooked meal sound right about now?";
    foreach ($services as $s) {
        if (strpos(strtolower($s['name']), 'cook') !== false) {
            $response['recommendations'][] = ['type' => 'service', 'id' => $s['id'], 'name' => $s['name'], 'price' => $s['base_price']];
        }
    }
} elseif (containsAny($query, ['repair', 'fix', 'broken', 'electric', 'plumb', 'ac', 'fridge'])) {
    $response['text'] = "Ugh, a broken appliance can be so stressful! 🛠️ But don't worry, we have expert electricians, plumbers, and technicians who can fix it in a jiffy. They're fully equipped and ready to go. What seems to be the trouble? I'll find you a pro right away!";
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
    $response['text'] = "Your loved ones deserve the most dedicated care, and we treat them like family! 👨‍👩‍👧‍👦 We provide highly verified and compassionate professionals for **Babysitting**, **Elderly Care**, and **Patient Care**. How can we help support your family today?";
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
    $response['text'] = "Who doesn't love a great deal? 🎁 Our **Service Bundles** are designed to give you maximum value with up to **20% off**! Plus, did you know that paying with your **Helpify Wallet** gets you an extra **5% cashback**? It's practically a steal! Which offer looks most tempting?";
    foreach ($bundles as $b) {
        $response['recommendations'][] = ['type' => 'bundle', 'id' => $b['id'], 'name' => $b['name']];
    }
} elseif (containsAny($query, ['all', 'list', 'every', 'other', 'provide', 'services', 'what do you do', 'options', 'info'])) {
    $response['text'] = "We are your one-stop shop for everything home-related! 🏠 From sparkling clean floors to fixing that leaky faucet, we do it all. Here's a quick peek at our main departments:\n\n" .
        "✨ **InstaHelp** (Cleaning, Cooking, Care)\n" .
        "🧺 **Laundry & Dry Cleaning**\n" .
        "🚗 **Car Wash & Detailing**\n" .
        "🛠️ **Repairs** (Electrician, Plumber, AC)\n" .
        "💆 **Personal Care** (Salon, Massage)\n" .
        "🎨 **Home Improvements** (Painting, Water Purifier)\n\n" .
        "Which of these can I help you explore in more detail right now?";

    $parentServices = $pdo->query("SELECT id, name FROM services WHERE parent_id IS NULL LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($parentServices as $ps) {
        $response['recommendations'][] = ['type' => 'category', 'id' => $ps['id'], 'name' => $ps['name']];
    }
}

// 2. Default Fallback (More Talkative)
if ($response['text'] === "I'm not sure how to help with that yet. Could you try asking about cleaning, cooking, or our special bundles?") {
    $response['text'] = "I'm so sorry, I didn't quite catch that! I'm still learning some of the more complex requests. 😅 But I'm an expert on everything from **Maid Services** to **AC Repairs**. Why don't you try asking about one of those, or just tell me which part of your home needs help?";
}

// Limit recommendations to top 4
$response['recommendations'] = array_slice($response['recommendations'], 0, 4);

echo json_encode($response);
