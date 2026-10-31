#!/usr/bin/env python3
"""Temporary data-only DWD CDC 100 cm soil-temperature comparison, no actuator access."""
import urllib.request, re, io, zipfile, csv, math, datetime
BASE="https://opendata.dwd.de/climate_environment/CDC/observations_germany/climate/hourly/soil_temperature/recent/"
hdr={"User-Agent":"HeliosSmart-DWD-soil-check/1.0"}
def fetch(url):
    return urllib.request.urlopen(urllib.request.Request(url, headers=hdr),timeout=35).read()
raw=fetch(BASE+"EB_Stundenwerte_Beschreibung_Stationen.txt")
lines=raw.decode("latin1",errors="replace").splitlines()
print("STATIONS HEADER:",repr(lines[0][:260]))
print("STATIONS EXAMPLE:",repr(lines[2][:260]))
site=(49.9044,6.9781)
stations=[]
for line in lines:
    fields=line.split()
    if len(fields)<7 or not fields[0].isdigit() or not fields[1].isdigit():continue
    try:
        ident=int(fields[0]);date1=fields[1];date2=fields[2]
        height=float(fields[3]);lat=float(fields[4]);lon=float(fields[5])
        if not (48<lat<52 and 5<lon<10):continue
        dlat=math.radians(lat-site[0]);dlon=math.radians(lon-site[1])
        aa=math.sin(dlat/2)**2+math.cos(math.radians(lat))*math.cos(math.radians(site[0]))*math.sin(dlon/2)**2
        distance=6371*2*math.atan2(math.sqrt(aa),math.sqrt(1-aa))
        stations.append((distance,ident,height,lat,lon," ".join(fields[6:]),date2))
    except Exception:continue
stations.sort()
print("NEAREST STATION CANDIDATES:")
for item in stations[:16]:print(item)
listing=fetch(BASE).decode("utf-8",errors="replace")
ids=set(int(k) for k in re.findall(r"stundenwerte_EB_(\d{5})_akt\.zip",listing))
print("RECENT ZIP COUNT:",len(ids))
chosen=[x for x in stations if x[1] in ids][:11]
print("NEAREST WITH ACTIVE ZIP:")
for item in chosen: print(item)
for km,ident,height,lat,lon,name,last in chosen[:8]:
    url=BASE+f"stundenwerte_EB_{ident:05d}_akt.zip"
    try:
        blob=fetch(url)
        with zipfile.ZipFile(io.BytesIO(blob)) as archive:
            filenames=archive.namelist()
            target=next((n for n in filenames if n.lower().endswith(".txt") and "produkt" in n.lower()),None)
            if target is None: target=next((n for n in filenames if n.lower().endswith(".txt")),None)
            if target is None: raise ValueError("No text member "+repr(filenames))
            content=archive.read(target).decode("latin1",errors="replace")
        rows=list(csv.DictReader(io.StringIO(content),delimiter=";"))
        print("DATA STATION:",ident,name,"km",round(km,1),"columns",list(rows[0].keys()) if rows else None)
        recent=[]
        for row in rows:
            v={k.strip():val.strip() for k,val in row.items() if k is not None}
            date=v.get("MESS_DATUM","")
            for col in ["V_TE100","V_TE050","V_TE020","V_TE010","V_TE005"]:
                z=v.get(col)
                if z and z not in ["-999","-999.0","-999.00"]:
                    try: v[col]=float(z.replace(",",".")) 
                    except:pass
            if date>="2026102600" and float(v.get("V_TE100") or -999)>-100:
                recent.append(v)
        for row in recent[-4:]:
            print("DWD_SOIL_REAL",ident, name, "km",round(km,1),
                "UTC",row.get("MESS_DATUM"),
                "100cm",row.get("V_TE100"),
                "50cm",row.get("V_TE050"),
                "20cm",row.get("V_TE020"),
                "10cm",row.get("V_TE010"),
                "5cm",row.get("V_TE005"))
        if not recent:print("NO_RECENT_100_CM",ident,"last",rows[-1] if rows else None)
    except Exception as e: print("STATION ERROR",ident,str(e)[:180])
