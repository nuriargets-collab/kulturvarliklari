import json,re,sys
sys.argv=["x"]
exec(open("ek/getir.py",encoding="utf-8").read().split("os.makedirs(")[0])
ALT=json.load(open("ek/alt.json",encoding="utf-8"))
m=json.load(open("ek/meta.json",encoding="utf-8"))
for sid,keys in ALT.items():
    rec=m[sid]
    for key in keys:
        q,e,la,lo=resolve(key)
        if q: break
    if not q: print(sid,"yok"); continue
    c=e.get("claims",{}); sl=e.get("sitelinks",{})
    rec.update({"qid":q,"lat":la,"lng":lo,"labels":{l:lab(e,l) for l in ("tr","en","de","es","ar","zh","hi")},
      "trw":sl.get("trwiki",{}).get("title"),"enw":sl.get("enwiki",{}).get("title"),"cat":(val(c,"P373") or [None])[0]})
    adm=val(c,"P131")
    if adm: rec["adm"]=lab(ent(adm[0]["id"]),"tr")
    rec["ex_tr"]=extract("tr",rec["trw"]) if rec["trw"] else ""
    rec["ex_en"]=extract("en",rec["enw"]) if rec["enw"] else ""
    if len(rec["cands"])<2:
        cands=list(dict.fromkeys(val(c,"P18")[:1]+(files_in_cat(rec["cat"]) if rec["cat"] else [])))[:4]
        have={x["file"] for x in rec["cands"]}
        for j,f in enumerate(cands):
            if f in have: continue
            x=info(f)
            if not x or not x["thumb"] or (x["w"] or 0)<900: continue
            b=get(x["thumb"]); 
            if not b: continue
            n=10+j; open("ek/img/%s_%d.jpg"%(sid,n),"wb").write(b); x["n"]=n; rec["cands"].insert(0,x)
    print(sid,q,rec["ad"],len(rec["cands"]),flush=True)
json.dump(m,open("ek/meta.json","w",encoding="utf-8"),ensure_ascii=False,indent=0)
