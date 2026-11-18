import re, json, time, urllib.request, urllib.parse, http.cookiejar, html
UA = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36"
cj = http.cookiejar.CookieJar(); op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
def get(u, data=None, hdr={}):
    r = urllib.request.Request(u, data=data, headers={"User-Agent": UA, **hdr}); return op.open(r, timeout=60).read().decode("utf-8", "ignore")
out = {}
CATS = ["KRT-MZA01-08-008", "KRT-MBL01-08-008"]
try:
    for u in ["https://muze.gov.tr/MuzeKart", "https://muze.gov.tr/MuseumPass?culture=tr", "https://muze.gov.tr/muzekart"]:
        try:
            pg = get(u); open("veri/cikti/tr_" + re.sub(r"\W", "_", u[-20:]) + ".html", "w").write(pg)
            for c in re.findall(r"satis-noktasi\?CatalogNo=([A-Z0-9-]+)", pg):
                if c not in CATS: CATS.append(c)
        except Exception as e: print(u, e)
except Exception as e: print(e)
print("CATS", CATS)
for cat in CATS:
    page = get(f"https://muze.gov.tr/satis-noktasi?CatalogNo={cat}")
    tok = re.search(r'name="csrf-token"\s+content="([^"]+)"', page) or re.search(r'content="([^"]+)"\s+name="csrf-token"', page) or re.search(r'name="__RequestVerificationToken"[^>]*value="([^"]+)"', page)
    tok = tok.group(1) if tok else ""
    sel = page[page.find('id="city"'):]; sel = sel[:sel.find("</select>")]
    cities = [(v, html.unescape(n).strip()) for v, n in re.findall(r'<option value="([^"]+)"[^>]*>([^<]+)</option>', sel) if v]
    print("token", bool(tok), "cities", len(cities))
    for v, n in cities:
        try:
            d = urllib.parse.urlencode({"provinceId": v, "provinceName": n, "catalogNo": cat}).encode()
            h = get("https://muze.gov.tr/PointOfSales/GetPointData", d, {"RequestVerificationToken": tok, "X-Requested-With": "XMLHttpRequest", "Referer": f"https://muze.gov.tr/satis-noktasi?CatalogNo={cat}"})
            t = re.sub(r"<script.*?</script>", "", h, flags=re.S)
            names = [html.unescape(re.sub(r"\s+", " ", x)).strip() for x in re.findall(r"<h\d[^>]*>(.*?)</h\d>", t, flags=re.S)]
            names = [re.sub(r"<[^>]+>", "", x).strip() for x in names]
            out[cat + "|" + n] = {"names": [x for x in names if x], "raw_len": len(h)}
            open(f"veri/cikti/satis_{cat}_{v}.html", "w").write(h)
        except Exception as e:
            out[cat + "|" + n] = {"error": str(e)}
        time.sleep(0.7)
json.dump(out, open("veri/cikti/satis.json", "w"), ensure_ascii=False, indent=1)
