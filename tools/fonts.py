import sys, json, os
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer
from fontTools import subset

src, out = sys.argv[1], sys.argv[2]
BG_UP = "АБВГДЕЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЬЮЯ"
BG = BG_UP + BG_UP.lower()
LAT = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz"
DIG = "0123456789"
PUN = " -.,!?&'\"№+/:()„“"
CHARS = BG + LAT + DIG + PUN
assert len(BG) == 60

FONTS = [
  ("russo",  "russoone_RussoOne-Regular.ttf", None, "Russo One"),
  ("oswald", "oswald_Oswald[wght].ttf", 700, "Oswald Bold"),
  ("dela",   "delagothicone_DelaGothicOne-Regular.ttf", None, "Dela Gothic One"),
]
metrics = {}
for key, fn, wght, label in FONTS:
    f = TTFont(os.path.join(src, fn))
    if wght:
        f = instancer.instantiateVariableFont(f, {"wght": wght})
    cmap = f.getBestCmap()
    missing_bg = [c for c in BG if ord(c) not in cmap]
    missing_all = [c for c in CHARS if ord(c) not in cmap]
    print(f"{label}: BG letters {60-len(missing_bg)}/60, missing overall: {''.join(missing_all) or 'none'}")
    gs = f.getGlyphSet()
    from fontTools.pens.boundsPen import BoundsPen
    upm = f["head"].unitsPerEm
    os2 = f["OS/2"]
    def bounds(ch):
        g = cmap[ord(ch)]
        p = BoundsPen(gs); gs[g].draw(p)
        b = p.bounds or (0, 0, 0, 0)
        return [f["hmtx"][g][0], round(b[1]), round(b[3])]
    glyphs = {c: bounds(c) for c in CHARS if ord(c) in cmap}
    cap = glyphs["H"][2]; xh = glyphs["x"][2]
    metrics[key] = {"label": label, "upm": upm, "cap": cap, "xh": xh, "g": glyphs,
                    "osCap": getattr(os2, "sCapHeight", None)}
    # subset + woff2
    opts = subset.Options(); opts.flavor = "woff2"; opts.layout_features = ["kern", "liga"]
    opts.name_IDs = ["*"]; opts.notdef_outline = True
    sub = subset.Subsetter(opts); sub.populate(unicodes=[ord(c) for c in CHARS if ord(c) in cmap])
    sub.subset(f)
    path = os.path.join(out, key + ".woff2")
    f.flavor = "woff2"; f.save(path)
    print("  ->", path, os.path.getsize(path), "bytes; cap", cap, "xh", xh, "upm", upm)
allowed = "".join(c for c in CHARS if all(c in m["g"] for m in metrics.values()))
json.dump({"allowed": allowed, "fonts": metrics}, open(os.path.join(out, "metrics.json"), "w", encoding="utf-8"), ensure_ascii=False, separators=(",", ":"))
print("allowed chars:", len(allowed), "of", len(CHARS))
