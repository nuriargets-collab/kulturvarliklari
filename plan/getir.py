import json,os,re,urllib.request,urllib.parse,time
UA={"User-Agent":"kulturvarliklari-bot/1.0 (https://kulturvarliklari.com.tr)"}
def get(u,p=None):
    if p: u+="?"+urllib.parse.urlencode(p)
    for i in range(4):
        try:
            with urllib.request.urlopen(urllib.request.Request(u,headers=UA),timeout=60) as r: return r.read()
        except Exception as e: print("retry",u[:90],e); time.sleep(3+i*4)
    return None
def J(u,p=None): b=get(u,p); return json.loads(b) if b else {}
API="https://commons.wikimedia.org/w/api.php"
OK=lambda la,lo:(35.7<la<42.2 and 25.6<lo<44.9) or (34.5<la<35.8 and 32.2<lo<34.7)
def ent(q): return J("https://www.wikidata.org/wiki/Special:EntityData/%s.json"%q).get("entities",{}).get(q,{})
def val(c,p): return [x["mainsnak"]["datavalue"]["value"] for x in c.get(p,[]) if "datavalue" in x["mainsnak"]]
def resolve(key):
    if re.fullmatch(r"Q\d+",key): return key,ent(key)
    r=J("https://www.wikidata.org/w/api.php",{"action":"wbsearchentities","search":key,"language":"en","format":"json","limit":6})
    for s in r.get("search",[]):
        e=ent(s["id"]); co=val(e.get("claims",{}),"P625")
        if co and OK(co[0]["latitude"],co[0]["longitude"]): return s["id"],e
    return None,{}
PL=re.compile(r"plan|map|layout|lageplan|übersicht|uebersicht|harita|karte|grundriss|site.?plan|overview|topograph",re.I)
BAD=re.compile(r"locator|location map|relief map|\bin turkey\b|turkey.*map|anatolia.*map|europe|world",re.I)
def members(cat,typ):
    r=J(API,{"action":"query","list":"categorymembers","cmtitle":"Category:"+cat,"cmtype":typ,"cmlimit":200,"format":"json"})
    return [m["title"] for m in r.get("query",{}).get("categorymembers",[])]
def cands(cat,name):
    fs=[]
    if cat:
        cats=[cat]+[c[9:] for c in members(cat,"subcat") if PL.search(c)]
        for sc in [c[9:] for c in members(cat,"subcat") if re.search(r"plan|map|drawing|reconstruct",c,re.I)]:
            cats+= [c[9:] for c in members(sc,"subcat")]
        for c in dict.fromkeys(cats):
            for t in members(c,"file"):
                if (PL.search(t) or c!=cat) and not BAD.search(t): fs.append(t[5:])
    r=J(API,{"action":"query","list":"search","srsearch":'"%s" plan'%name,"srnamespace":6,"srlimit":10,"format":"json"})
    fs+= [m["title"][5:] for m in r.get("query",{}).get("search",[]) if PL.search(m["title"]) and not BAD.search(m["title"])]
    return list(dict.fromkeys(fs))
def info(f):
    r=J(API,{"action":"query","titles":"File:"+f,"prop":"imageinfo","iiprop":"url|extmetadata|size|mime","iiurlwidth":1400,"format":"json"})
    for p in r.get("query",{}).get("pages",{}).values():
        ii=(p.get("imageinfo") or [{}])[0]; m=ii.get("extmetadata",{})
        g=lambda k:re.sub(r"<[^>]+>","",m.get(k,{}).get("value","")).strip()
        return {"file":f,"w":ii.get("width"),"h":ii.get("height"),"thumb":ii.get("thumburl"),"page":ii.get("descriptionurl"),"a":g("Artist")[:120],"l":g("LicenseShortName"),"d":g("ImageDescription")[:200]}
os.makedirs("plan/img",exist_ok=True); out={}
for line in open("plan/liste.txt",encoding="utf-8"):
    sid,key,name=line.rstrip("\n").split("|")
    q,e=resolve(key); c=e.get("claims",{})
    cat=(val(c,"P373") or [None])[0]
    fs=[x for x in val(c,"P3311")]+cands(cat,name)
    res=[]
    for f in list(dict.fromkeys(fs))[:14]:
        x=info(f)
        if not x or not x["thumb"] or not re.search(r"CC|Public domain|PD|GFDL|CC0",x["l"] or "",re.I): continue
        if (x["w"] or 0)<500: continue
        b=get(x["thumb"])
        if not b: continue
        n=len(res); open("plan/img/%s_%d.jpg"%(sid,n),"wb").write(b); x["n"]=n; x["p3311"]=f in val(c,"P3311"); res.append(x)
        if len(res)>=6: break
    out[sid]={"qid":q,"cat":cat,"name":name,"cands":res}
    print(sid,q,cat,len(res),flush=True); time.sleep(0.2)
json.dump(out,open("plan/meta.json","w",encoding="utf-8"),ensure_ascii=False,indent=1)
