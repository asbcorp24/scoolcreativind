from pathlib import Path
from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "assets"
ASSETS.mkdir(parents=True, exist_ok=True)

SIZES = [(16,16),(24,24),(32,32),(48,48),(64,64),(128,128),(256,256)]

def rounded(draw, box, radius, fill):
    draw.rounded_rectangle(box, radius=radius, fill=fill)

def teacher_icon():
    im = Image.new("RGBA", (512,512), (0,0,0,0))
    d = ImageDraw.Draw(im)
    rounded(d, (24,24,488,488), 104, (8,48,98,255))
    rounded(d, (40,40,472,472), 92, (8,83,150,255))
    # board
    d.rounded_rectangle((205,96,432,282), radius=24, fill=(234,247,255,255))
    d.rounded_rectangle((225,116,412,262), radius=16, fill=(20,145,235,255))
    # teacher head/body
    d.ellipse((78,102,162,186), fill=(255,184,64,255))
    d.rounded_rectangle((72,186,176,326), radius=42, fill=(255,151,35,255))
    # pointing arm
    d.line((150,215,272,142), fill=(255,177,52,255), width=34)
    # students
    for x in (115,255,390):
        d.ellipse((x-34,318,x+34,386), fill=(244,249,255,255))
        d.rounded_rectangle((x-55,382,x+55,448), radius=30, fill=(225,240,252,255))
    return im

def student_icon():
    im = Image.new("RGBA", (512,512), (0,0,0,0))
    d = ImageDraw.Draw(im)
    rounded(d, (24,24,488,488), 104, (0,126,220,255))
    rounded(d, (40,40,472,472), 92, (0,157,240,255))
    # headset
    d.arc((122,92,390,340), 185, 355, fill=(247,252,255,255), width=28)
    d.rounded_rectangle((103,215,140,318), radius=18, fill=(247,252,255,255))
    d.rounded_rectangle((372,215,409,318), radius=18, fill=(247,252,255,255))
    # head
    d.ellipse((166,126,346,306), fill=(248,252,255,255))
    # body / laptop
    d.rounded_rectangle((130,295,382,446), radius=72, fill=(226,242,253,255))
    d.rounded_rectangle((154,338,358,448), radius=18, fill=(15,117,214,255))
    return im

for name, image in (("teacher", teacher_icon()), ("student", student_icon())):
    png = ASSETS / f"{name}.png"
    ico = ASSETS / f"{name}.ico"
    image.save(png)
    image.save(ico, format="ICO", sizes=SIZES)
    print(f"Generated {ico}")
