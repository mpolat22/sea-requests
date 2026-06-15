from pathlib import Path
from PIL import Image, ImageDraw, ImageFont, ImageFilter


ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "outputs" / "rfq-ocr-test-pngs"
OUT_DIR.mkdir(parents=True, exist_ok=True)


def load_font(size: int, bold: bool = False):
    candidates = [
        "C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf",
        "C:/Windows/Fonts/calibrib.ttf" if bold else "C:/Windows/Fonts/calibri.ttf",
    ]
    for path in candidates:
        if Path(path).exists():
            return ImageFont.truetype(path, size=size)
    return ImageFont.load_default()


FONT_12 = load_font(12)
FONT_13 = load_font(13)
FONT_14 = load_font(14)
FONT_16 = load_font(16)
FONT_18_B = load_font(18, bold=True)
FONT_20_B = load_font(20, bold=True)
FONT_12_B = load_font(12, bold=True)
FONT_13_B = load_font(13, bold=True)
FONT_14_B = load_font(14, bold=True)


def canvas(size=(1600, 1100), color="white"):
    return Image.new("RGB", size, color)


def draw_box(draw, xy, outline="#cbd5e1", fill=None, width=1):
    draw.rectangle(xy, outline=outline, fill=fill, width=width)


def draw_text(draw, xy, text, font=FONT_13, fill="#0f172a"):
    draw.text(xy, text, font=font, fill=fill)


def draw_label_value(draw, x, y, label, value, width=330):
    draw_text(draw, (x, y), label, FONT_13_B)
    draw_box(draw, (x, y + 22, x + width, y + 62), outline="#d6dde5", fill="white")
    draw_text(draw, (x + 12, y + 34), value, FONT_14, "#1e293b")


def draw_grid_table(draw, x, y, columns, rows, row_height=44, header_fill="#eef4fb"):
    total_width = sum(width for _, width in columns)
    draw_box(draw, (x, y, x + total_width, y + row_height), outline="#9fb0c3", fill=header_fill, width=1)
    cx = x
    for name, width in columns:
        draw.line((cx, y, cx, y + row_height * (len(rows) + 1)), fill="#9fb0c3", width=1)
        draw_text(draw, (cx + 8, y + 14), name, FONT_12_B, "#334155")
        cx += width
    draw.line((x + total_width, y, x + total_width, y + row_height * (len(rows) + 1)), fill="#9fb0c3", width=1)
    for ridx, row in enumerate(rows, start=1):
        top = y + row_height * ridx
        draw_box(draw, (x, top, x + total_width, top + row_height), outline="#d7e0ea", fill="white", width=1)
        cx = x
        for cidx, (_, width) in enumerate(columns):
            value = row[cidx] if cidx < len(row) else ""
            draw_text(draw, (cx + 8, top + 14), value, FONT_12, "#0f172a")
            cx += width


def generate_clean_form():
    img = canvas()
    draw = ImageDraw.Draw(img)
    draw_text(draw, (60, 34), "PRF 03 - REQUISITION FORM", FONT_20_B)
    draw_box(draw, (55, 85, 1545, 145), outline="#7fa1c7", fill="#cfe0f3", width=1)
    draw_text(draw, (420, 103), "For Spares / Stores / Repairs / Services", FONT_18_B, "#17324d")

    row1 = [("Vessel", "MV Horizon"), ("Required Delivery Port", "Singapore")]
    row2 = [("Reference No", "RFQ-20260517-001"), ("Requisition Date", "2026-05-20"), ("Priority", "High")]
    row3 = [("Company", "Blue Ocean Shipping"), ("Country", "Turkey"), ("Due Date", "2026-05-22")]

    y = 180
    for label, value in row1:
        draw_label_value(draw, 70 + row1.index((label, value)) * 720, y, label, value, 650)
    y += 95
    xs = [70, 560, 1050]
    for i, (label, value) in enumerate(row2):
        draw_label_value(draw, xs[i], y, label, value, 420)
    y += 95
    for i, (label, value) in enumerate(row3):
        draw_label_value(draw, xs[i], y, label, value, 420)

    columns = [
        ("#", 40),
        ("Product", 260),
        ("Part No", 170),
        ("Manufacturer", 170),
        ("MFG Model / Type", 180),
        ("Catalog Code", 180),
        ("Serial Number", 160),
        ("Qty", 70),
        ("Unit", 80),
        ("ROB", 70),
        ("Drawing Number", 170),
        ("Quality", 120),
        ("Comments", 220),
    ]
    rows = [
        ["1", "Charge air cooler seal kit", "322.10.135", "MAN", "12V32/40", "IMPA 123456", "S-88931", "2", "PCS", "0", "DWG-2201", "Genuine", "Side B"],
        ["2", "Fuel oil filter element", "FO-7781", "Alfa Laval", "MFO Set", "ISSA 771122", "F-1021", "12", "PCS", "1", "DWG-1120", "OEM", "Engine stores"],
        ["3", "Hydraulic pump overhaul service", "-", "Bosch Rexroth", "A10VSO", "-", "-", "1", "LOT", "0", "-", "Serviceable", "Attend on board"],
    ]
    draw_grid_table(draw, 55, 470, columns, rows, row_height=48)
    img.save(OUT_DIR / "rfq_test_clean_form.png")


def generate_excel_like():
    img = canvas((1700, 1050), "white")
    draw = ImageDraw.Draw(img)
    for x in range(40, 1660, 110):
        draw.line((x, 40, x, 1010), fill="#edf1f5", width=1)
    for y in range(40, 1010, 34):
        draw.line((40, y, 1660, y), fill="#edf1f5", width=1)
    draw_text(draw, (60, 55), "Vessels", FONT_13_B)
    draw_text(draw, (185, 55), "MV Atlas", FONT_13)
    draw_text(draw, (620, 55), "Country", FONT_13_B)
    draw_text(draw, (740, 55), "Turkey", FONT_13)
    draw_text(draw, (1040, 55), "Destination Port", FONT_13_B)
    draw_text(draw, (1225, 55), "Istanbul", FONT_13)
    draw_text(draw, (60, 95), "RFQ No", FONT_13_B)
    draw_text(draw, (185, 95), "RFQ-20260517-002", FONT_13)
    draw_text(draw, (620, 95), "Quote Due Date", FONT_13_B)
    draw_text(draw, (815, 95), "2026-05-24", FONT_13)
    draw_text(draw, (1040, 95), "Priority Level", FONT_13_B)
    draw_text(draw, (1190, 95), "Critical", FONT_13)
    columns = [
        ("Description", 290),
        ("Maker Ref", 170),
        ("Brand", 160),
        ("Model No", 150),
        ("IMPA Code", 170),
        ("Serial No", 150),
        ("Qty", 70),
        ("UOM", 80),
        ("ROB", 70),
        ("Remarks", 260),
    ]
    rows = [
        ["Main air compressor gasket set", "MAK-0012", "Sauer", "WP300L", "IMPA 771001", "AC-9912", "3", "SET", "0", "Urgent sailing requirement"],
        ["Centrifugal purifier seal ring", "PUR-880", "Alfa Laval", "MOPX 205", "ISSA 550920", "PX-228", "6", "PCS", "1", "Match existing sample"],
        ["Boiler water test reagents", "BW-TEST", "Unitor", "Lab Kit", "UNITOR 7788", "-", "2", "KIT", "0", "Deliver before noon"],
        ["Provision crane brake lining", "CRN-BR", "MacGregor", "BHL-2", "CODE-1128", "MC-77", "4", "PCS", "0", "Check dimensions"],
    ]
    draw_grid_table(draw, 60, 180, columns, rows, row_height=42, header_fill="#f6f8fb")
    img.save(OUT_DIR / "rfq_test_excel_like.png")


def generate_phone_capture():
    base = canvas((1500, 1000), "#f0f2f5")
    draw = ImageDraw.Draw(base)
    draw.rounded_rectangle((140, 90, 1360, 910), radius=16, fill="white", outline="#d9dde3")
    draw_text(draw, (180, 130), "Ship Name", FONT_13_B)
    draw_text(draw, (310, 130), "MV Liberty", FONT_14)
    draw_text(draw, (720, 130), "Req No", FONT_13_B)
    draw_text(draw, (820, 130), "REQ-7781", FONT_14)
    draw_text(draw, (180, 175), "Country of Delivery", FONT_13_B)
    draw_text(draw, (370, 175), "UAE", FONT_14)
    draw_text(draw, (720, 175), "Port of Delivery", FONT_13_B)
    draw_text(draw, (895, 175), "Jebel Ali", FONT_14)
    draw_text(draw, (180, 220), "Quote Currency", FONT_13_B)
    draw_text(draw, (340, 220), "USD", FONT_14)
    draw_text(draw, (720, 220), "Urgency", FONT_13_B)
    draw_text(draw, (810, 220), "High", FONT_14)
    columns = [
        ("Product", 240),
        ("Part Number", 150),
        ("Manufacturer", 150),
        ("Model/Type", 150),
        ("Serial No", 130),
        ("Qty", 60),
        ("Unit", 70),
        ("Drawing Ref", 140),
        ("Comments", 210),
    ]
    rows = [
        ["Exhaust valve spindle", "EV-2201", "Wartsila", "RT-flex", "SN-220", "2", "PCS", "DRW-200", "Forward engine"],
        ["Cooling water pump repair kit", "CW-778", "Desmi", "NSL 150", "-", "1", "KIT", "-", "On board service"],
        ["Scavenge air drain bottle", "SC-199", "MAN", "S60ME-C", "-", "1", "PCS", "-", "Check flange size"],
    ]
    draw_grid_table(draw, 175, 300, columns, rows, row_height=44, header_fill="#f9fafb")
    tilted = base.rotate(-2.4, expand=True, fillcolor="#f0f2f5")
    shadow = tilted.filter(ImageFilter.GaussianBlur(8))
    final = canvas((1700, 1200), "#e5e7eb")
    final.paste(shadow, (80, 80))
    final.paste(tilted, (60, 55))
    final.save(OUT_DIR / "rfq_test_phone_capture.png")


def generate_scattered_labels():
    img = canvas((1680, 1120), "white")
    draw = ImageDraw.Draw(img)
    draw_text(draw, (70, 60), "Vessel Name", FONT_13_B)
    draw_text(draw, (280, 60), "MV Orion", FONT_14)
    draw_text(draw, (1120, 60), "Curr.", FONT_13_B)
    draw_text(draw, (1220, 60), "EUR", FONT_14)
    draw_text(draw, (540, 150), "RFQ No", FONT_13_B)
    draw_text(draw, (700, 150), "RFQ-20260517-004", FONT_14)
    draw_text(draw, (70, 240), "Delivery Destination Country", FONT_13_B)
    draw_text(draw, (390, 240), "Greece", FONT_14)
    draw_text(draw, (980, 240), "Nearest Port", FONT_13_B)
    draw_text(draw, (1140, 240), "Piraeus", FONT_14)
    draw_text(draw, (70, 330), "Criticality", FONT_13_B)
    draw_text(draw, (230, 330), "Normal", FONT_14)
    draw_text(draw, (540, 330), "General Notes", FONT_13_B)
    draw_text(draw, (720, 330), "Match maker catalog", FONT_14)
    columns = [
        ("Product", 250),
        ("Maker", 160),
        ("Model / Type", 160),
        ("Catalog Code", 170),
        ("Plate No", 150),
        ("DWG No", 150),
        ("Qty", 70),
        ("Unit", 80),
        ("ROB", 70),
        ("Comments", 260),
    ]
    rows = [
        ["Governor spare parts set", "Woodward", "2301A", "STK-9001", "PL-77", "DWG-09", "1", "SET", "0", "Check serial plate"],
        ["Hydraulic hose assembly", "Parker", "HT-550", "MAT-5502", "-", "-", "8", "PCS", "2", "Length 2.4 m"],
        ["Portable gas detector sensor", "Drager", "X-am", "REF-8810", "SN-55", "-", "3", "PCS", "1", "Calibrated item"],
        ["Anchor windlass brake band", "Kawasaki", "WB-90", "CODE-7782", "-", "DWG-119", "2", "PCS", "0", "Starboard side"],
    ]
    draw_grid_table(draw, 70, 450, columns, rows, row_height=46, header_fill="#f4f7fa")
    img.save(OUT_DIR / "rfq_test_scattered_labels.png")


def generate_whatsapp_crop():
    img = canvas((1600, 1100), "#d7f5d1")
    draw = ImageDraw.Draw(img)
    draw.rounded_rectangle((90, 70, 1510, 1000), radius=18, fill="white", outline="#dfe6e2")
    draw_text(draw, (130, 110), "M/V", FONT_13_B)
    draw_text(draw, (185, 110), "MV Aurora", FONT_14)
    draw_text(draw, (760, 110), "Inquiry No", FONT_13_B)
    draw_text(draw, (890, 110), "INQ-5507", FONT_14)
    draw_text(draw, (130, 150), "Port of Delivery", FONT_13_B)
    draw_text(draw, (300, 150), "Rotterdam", FONT_14)
    draw_text(draw, (760, 150), "Country", FONT_13_B)
    draw_text(draw, (850, 150), "Netherlands", FONT_14)
    draw_text(draw, (130, 190), "Req. Date", FONT_13_B)
    draw_text(draw, (235, 190), "2026-05-18", FONT_14)
    draw_text(draw, (760, 190), "Urgency", FONT_13_B)
    draw_text(draw, (850, 190), "Emergency", FONT_14)
    columns = [
        ("Description", 280),
        ("Maker Ref", 160),
        ("Brand", 150),
        ("Model", 150),
        ("Qty", 70),
        ("UOM", 80),
        ("ROB", 70),
        ("Notes", 260),
    ]
    rows = [
        ["Ballast pump mechanical seal", "BP-0041", "DESMI", "SL150", "2", "PCS", "0", "Please quote air freight"],
        ["Auxiliary engine lube oil filter", "AE-8892", "MANN", "WDK", "10", "PCS", "1", "Urgent departure"],
        ["Navigation light replacement service", "-", "Marinelec", "Nav-Serv", "1", "LOT", "0", "Attend next call"],
    ]
    draw_grid_table(draw, 125, 270, columns, rows, row_height=46, header_fill="#f8fafc")
    img = img.filter(ImageFilter.GaussianBlur(0.2))
    img.save(OUT_DIR / "rfq_test_whatsapp_crop.png")


if __name__ == "__main__":
    generate_clean_form()
    generate_excel_like()
    generate_phone_capture()
    generate_scattered_labels()
    generate_whatsapp_crop()
    print(str(OUT_DIR))
