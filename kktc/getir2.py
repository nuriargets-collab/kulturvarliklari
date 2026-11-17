import json,os,sys
sys.path.insert(0,"kktc")
import importlib.util
src=open("kktc/getir.py").read().split("os.makedirs")[0]
exec(src)
def info2(f):
    r=J("https://commons.wikimedia.org/w/api.php",{"action":"query","titles":"File:"+f,"prop":"imageinfo|coordinates","iiprop":"url|extmetadata|size","iiurlwidth":1920,"format":"json"})
    for p in r.get("query",{}).get("pages",{}).values():
        ii=p.get("imageinfo",[{}])[0]; m=ii.get("extmetadata",{})
        g=lambda k:re.sub(r"<[^>]+>","",m.get(k,{}).get("value","")).strip()
        c=(p.get("coordinates") or [{}])[0]
        return {"file":f,"w":ii.get("width"),"h":ii.get("height"),"thumb":ii.get("thumburl"),"page":ii.get("descriptionurl"),"a":g("Artist")[:120],"l":g("LicenseShortName"),"lat":c.get("lat"),"lng":c.get("lon")}
os.makedirs("kktc/img3",exist_ok=True); out={}; k={}
for line in open("kktc/liste2.tsv",encoding="utf-8"):
    sid,q=line.rstrip("\n").split("\t")
    for f in search(q,6):
        x=info2(f)
        if not x or not x["thumb"] or (x["w"] or 0)<1000: continue
        i=k.get(sid,0); k[sid]=i+1
        b=get(x["thumb"]); 
        if not b: continue
        open("kktc/img3/%s_%d.jpg"%(sid,i),"wb").write(b); x["n"]=i; out.setdefault(sid,[]).append(x); time.sleep(0.4)
json.dump(out,open("kktc/meta3.json","w",encoding="utf-8"),ensure_ascii=False,indent=1)
