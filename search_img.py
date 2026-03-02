import urllib.request, re

req = urllib.request.Request('https://pixabay.com/images/search/painting%20wall/?type=illustration', headers={'User-Agent': 'Mozilla/5.0'})
try:
    html = urllib.request.urlopen(req).read().decode('utf-8')
    links = re.findall(r'https://cdn\.pixabay\.com/photo/[^"]+(?:jpg|png)', html)
    print("Links found: ", links[:3])
except Exception as e:
    print(e)
