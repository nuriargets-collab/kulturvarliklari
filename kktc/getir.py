import json,os,re,sys,urllib.request,urllib.parse,time
UA={"User-Agent":"kulturvarliklari-bot/1.0 (https://kulturvarliklari.com.tr)"}
def get(u,p=None):
    if p: u+="?"+urllib.parse.urlencode(p)
    for i in range(4):
        try:
            with urllib.request.urlopen(urllib.request.Request(u,headers=UA),timeout=60) as r: return r.read()
        except Exception as e: print("retry",u[:120],e); time.sleep(3+i*3)
    return None
def J(u,p): b=get(u,p); return json.loads(b) if b else {}
CY=lambda la,lo:34.4<la<35.8 and 32.2<lo<34.7
def wd(label):
    r=J("https://www.wikidata.org/w/api.php",{"action":"wbsearchentities","search":label,"language":"en","format":"json","limit":7})
    for s in r.get("search",[]):
        e=J("https://www.wikidata.org/wiki/Special:EntityData/%s.json"%s["id"],None).get("entities",{}).get(s["id"],{})
        c=e.get("claims",{})
        try: v=c["P625"][0]["mainsnak"]["datavalue"]["value"]; la,lo=v["latitude"],v["longitude"]
        except Exception: continue
        if not CY(la,lo): continue
        p18=[x["mainsnak"]["datavalue"]["value"] for x in c.get("P18",[]) if "datavalue" in x["mainsnak"]]
        cc=[x["mainsnak"]["datavalue"]["value"] for x in c.get("P373",[]) if "datavalue" in x["mainsnak"]]
        return {"qid":s["id"],"label":s.get("label"),"desc":s.get("description"),"lat":la,"lng":lo,"p18":p18,"cat":cc[0] if cc else None}
    return {}
def files_in_cat(cat,n=6):
    r=J("https://commons.wikimedia.org/w/api.php",{"action":"query","list":"categorymembers","cmtitle":"Category:"+cat,"cmtype":"file","cmlimit":40,"format":"json"})
    return [m["title"][5:] for m in r.get("query",{}).get("categorymembers",[]) if re.search(r"\.jpe?g$",m["title"],re.I)][:n]
def search(q,n=4):
    r=J("https://commons.wikimedia.org/w/api.php",{"action":"query","list":"search","srsearch":q+" filetype:bitmap","srnamespace":6,"srlimit":n,"format":"json"})
    return [m["title"][5:] for m in r.get("query",{}).get("search",[])]
def info(f):
    r=J("https://commons.wikimedia.org/w/api.php",{"action":"query","titles":"File:"+f,"prop":"imageinfo","iiprop":"url|extmetadata|size","iiurlwidth":1920,"format":"json"})
    for p in r.get("query",{}).get("pages",{}).values():
        ii=p.get("imageinfo",[{}])[0]; m=ii.get("extmetadata",{})
        g=lambda k:re.sub(r"<[^>]+>","",m.get(k,{}).get("value","")).strip()
        return {"file":f,"w":ii.get("width"),"h":ii.get("height"),"thumb":ii.get("thumburl"),"page":ii.get("descriptionurl"),"a":g("Artist")[:120],"l":g("LicenseShortName")}
os.makedirs("kktc/img",exist_ok=True); out={}
for line in open("kktc/liste.tsv",encoding="utf-8"):
    sid,label,q=line.rstrip("\n").split("\t")
    w=wd(label); time.sleep(0.5)
    cands=list(dict.fromkeys(w.get("p18",[])+(files_in_cat(w["cat"]) if w.get("cat") else [])+search(q)))[:7]
    res=[]
    for i,f in enumerate(cands):
        x=info(f)
        if not x or not x["thumb"] or (x["w"] or 0)<1000: continue
        b=get(x["thumb"])
        if not b: continue
        open("kktc/img/%s_%d.jpg"%(sid,i),"wb").write(b); x["n"]=i; res.append(x); time.sleep(0.5)
    out[sid]={"wd":w,"cands":res}; print(sid,w.get("qid"),len(res))
json.dump(out,open("kktc/meta.json","w",encoding="utf-8"),ensure_ascii=False,indent=1)
