<?php
$query = "water%20purifier";
$url = 'https://pixabay.com/images/search/' . $query . '/?type=illustration';
$context = stream_context_create(['http' => ['header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36']]);
$html = file_get_contents($url, false, $context);
preg_match_all('/https:\/\/cdn\.pixabay\.com\/photo\/[^\"]+(?:jpg|png)/i', $html, $matches);
print_r(array_slice($matches[0], 0, 5));
?>