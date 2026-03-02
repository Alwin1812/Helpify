<?php
$queries = [
    'water filter' => 'assets/images/service_ro_service.jpg',
    'water pipe valve wrench' => 'assets/images/service_ro_installation.jpg'
];

foreach ($queries as $q => $file) {
    $url = "https://unsplash.com/napi/search/photos?query=" . urlencode($q) . "&per_page=5";
    $json = @file_get_contents($url);
    if ($json) {
        $data = json_decode($json);
        if (isset($data->results[0]->urls->regular)) {
            // let's grab the best looking one, e.g. index 0 or 1
            $img_url = $data->results[0]->urls->regular;
            $img_data = @file_get_contents($img_url);
            if ($img_data) {
                file_put_contents($file, $img_data);
                echo "Downloaded $q to $file\n";
            }
        }
    } else {
        echo "Failed to fetch for $q\n";
    }
}
?>