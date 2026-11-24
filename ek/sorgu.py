import json, urllib.request, urllib.parse, time
EP="https://query.wikidata.org/sparql"
def q(s):
    u=EP+"?"+urllib.parse.urlencode({"query":s,"format":"json"})
    r=urllib.request.Request(u,headers={"User-Agent":"kulturvarliklari-bot/1.0 (kulturvarliklari.com.tr)"})
    for i in range(4):
        try: return json.load(urllib.request.urlopen(r,timeout=120))["results"]["bindings"]
        except Exception as e: print(e); time.sleep(10)
    return []
T="""SELECT ?it ?tr ?en ?co ?adm ?trw ?sl (GROUP_CONCAT(DISTINCT ?cl;separator=",") AS ?cls) WHERE {
 VALUES ?c {%s}
 ?it wdt:P31/wdt:P279* ?c ; wdt:P17 ?cty ; wdt:P625 ?co .
 VALUES ?cty {wd:Q43 wd:Q23681}
 ?it wikibase:sitelinks ?sl . FILTER(?sl >= %d)
 ?it wdt:P31 ?cl .
 OPTIONAL{?it rdfs:label ?tr FILTER(lang(?tr)="tr")}
 OPTIONAL{?it rdfs:label ?en FILTER(lang(?en)="en")}
 OPTIONAL{?it wdt:P131 ?a . ?a rdfs:label ?adm FILTER(lang(?adm)="tr")}
 OPTIONAL{?trw schema:about ?it ; schema:isPartOf <https://tr.wikipedia.org/>}
} GROUP BY ?it ?tr ?en ?co ?adm ?trw ?sl"""
out={}
for name,cls,mn in [("muze","wd:Q33506",2),("oren","wd:Q839954 wd:Q15661340 wd:Q2065736",3)]:
    rows=q(T%(cls,mn)); print(name,len(rows))
    for b in rows:
        k=b["it"]["value"].rsplit("/",1)[1]
        if k in out: continue
        g=lambda x:b.get(x,{}).get("value","")
        out[k]=dict(k=name,tr=g("tr"),en=g("en"),co=g("co"),adm=g("adm"),trw=g("trw"),sl=int(g("sl") or 0),cls=g("cls"))
    time.sleep(5)
json.dump(out,open("ek/aday.json","w"),ensure_ascii=False,indent=0)
print(len(out))
