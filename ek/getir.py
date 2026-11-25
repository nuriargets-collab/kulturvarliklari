import json,os,re,urllib.request,urllib.parse,time
UA={"User-Agent":"kulturvarliklari-bot/1.0 (https://kulturvarliklari.com.tr)"}
def get(u,p=None):
    if p: u+="?"+urllib.parse.urlencode(p)
    for i in range(4):
        try:
            with urllib.request.urlopen(urllib.request.Request(u,headers=UA),timeout=60) as r: return r.read()
        except Exception as e: print("retry",u[:100],e); time.sleep(3+i*4)
    return None
def J(u,p=None): b=get(u,p); return json.loads(b) if b else {}
OK=lambda la,lo:(35.7<la<42.2 and 25.6<lo<44.9) or (34.5<la<35.8 and 32.2<lo<34.7)
def ent(q): return J("https://www.wikidata.org/wiki/Special:EntityData/%s.json"%q).get("entities",{}).get(q,{})
def val(c,p): return [x["mainsnak"]["datavalue"]["value"] for x in c.get(p,[]) if "datavalue" in x["mainsnak"]]
def resolve(key):
    if re.fullmatch(r"Q\d+",key): ids=[key]
    else:
        ids=[]
        for lg in ("en","tr"):
            r=J("https://www.wikidata.org/w/api.php",{"action":"wbsearchentities","search":key,"language":lg,"format":"json","limit":6})
            ids+= [s["id"] for s in r.get("search",[]) if s["id"] not in ids]
    for q in ids:
        e=ent(q); c=e.get("claims",{})
        co=val(c,"P625")
        if not co: continue
        la,lo=co[0]["latitude"],co[0]["longitude"]
        if not OK(la,lo): continue
        return q,e,la,lo
    return None,{},None,None
def lab(e,l): return e.get("labels",{}).get(l,{}).get("value","")
def extract(lang,title):
    r=J("https://%s.wikipedia.org/w/api.php"%lang,{"action":"query","prop":"extracts","exintro":1,"explaintext":1,"titles":title,"format":"json","redirects":1})
    for p in r.get("query",{}).get("pages",{}).values(): return (p.get("extract") or "")[:1800]
    return ""
def files_in_cat(cat,n=3):
    r=J("https://commons.wikimedia.org/w/api.php",{"action":"query","list":"categorymembers","cmtitle":"Category:"+cat,"cmtype":"file","cmlimit":30,"format":"json"})
    return [m["title"][5:] for m in r.get("query",{}).get("categorymembers",[]) if re.search(r"\.jpe?g$",m["title"],re.I)][:n]
def search(q,n=3):
    r=J("https://commons.wikimedia.org/w/api.php",{"action":"query","list":"search","srsearch":q+" filetype:bitmap","srnamespace":6,"srlimit":n,"format":"json"})
    return [m["title"][5:] for m in r.get("query",{}).get("search",[])]
def info(f):
    r=J("https://commons.wikimedia.org/w/api.php",{"action":"query","titles":"File:"+f,"prop":"imageinfo","iiprop":"url|extmetadata|size","iiurlwidth":1600,"format":"json"})
    for p in r.get("query",{}).get("pages",{}).values():
        ii=p.get("imageinfo",[{}])[0]; m=ii.get("extmetadata",{})
        g=lambda k:re.sub(r"<[^>]+>","",m.get(k,{}).get("value","")).strip()
        return {"file":f,"w":ii.get("width"),"h":ii.get("height"),"thumb":ii.get("thumburl"),"page":ii.get("descriptionurl"),"a":g("Artist")[:120],"l":g("LicenseShortName")}
os.makedirs("ek/img",exist_ok=True); out={}
for i,line in enumerate(open("ek/spec.txt",encoding="utf-8")):
    key,ad,il,tur,did,per=line.rstrip("\n").split("|")
    sid="Y%03d"%(i+254)
    q,e,la,lo=resolve(key); c=e.get("claims",{})
    sl=e.get("sitelinks",{})
    rec={"key":key,"ad":ad,"il":il,"qid":q,"lat":la,"lng":lo,"labels":{l:lab(e,l) for l in ("tr","en","de","es","ar","zh","hi")},
         "trw":sl.get("trwiki",{}).get("title"),"enw":sl.get("enwiki",{}).get("title"),"cat":(val(c,"P373") or [None])[0]}
    adm=val(c,"P131")
    if adm:
        a=ent(adm[0]["id"]); rec["adm"]=lab(a,"tr")
    rec["ex_tr"]=extract("tr",rec["trw"]) if rec["trw"] else ""
    rec["ex_en"]=extract("en",rec["enw"]) if rec["enw"] else ""
    cands=list(dict.fromkeys(val(c,"P18")[:1]+(files_in_cat(rec["cat"]) if rec["cat"] else [])+search(ad+" "+il)))[:5]
    res=[]
    for j,f in enumerate(cands):
        x=info(f)
        if not x or not x["thumb"] or (x["w"] or 0)<900: continue
        b=get(x["thumb"])
        if not b: continue
        open("ek/img/%s_%d.jpg"%(sid,j),"wb").write(b); x["n"]=j; res.append(x)
        if len(res)>=3: break
    rec["cands"]=res; out[sid]=rec
    print(sid,q,ad,len(res),flush=True)
    time.sleep(0.3)
json.dump(out,open("ek/meta.json","w",encoding="utf-8"),ensure_ascii=False,indent=0)
