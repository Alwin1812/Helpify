<?php
$pages = ['Wallpaper', 'House_painter_and_decorator', 'Waterproofing'];
foreach ($pages as $p) {
    $url = "https://en.wikipedia.org/w/api.php?action=query&prop=pageimages&format=json&piprop=original&titles=" . $p;
    $j = json_decode(file_get_contents($url), true);
    print_r($j['query']['pages']);
}
?>