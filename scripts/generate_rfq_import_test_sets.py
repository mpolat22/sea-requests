from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
import csv
import math
import random

from PIL import Image, ImageDraw, ImageFilter, ImageFont
from reportlab.lib import colors
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import SimpleDocTemplate, Spacer, Table, TableStyle, Paragraph


ROOT = Path(__file__).resolve().parents[1]
OUT_PDF = ROOT / "outputs" / "rfq-import-pdf-tests"
OUT_PNG = ROOT / "outputs" / "rfq-import-image-tests"
OUT_MANIFEST = ROOT / "outputs" / "rfq-import-test-manifest.csv"


@dataclass
class Scenario:
    slug: str
    title: str
    general: list[tuple[str, str]]
    item_headers: list[str]
    items: list[list[str]]
    notes: str
    pdf_style: str
    png_style: str


SCENARIOS: list[Scenario] = [
    Scenario(
        slug="01-clean-rfq-standard",
        title="PRF 03 - REQUISITION FORM",
        general=[
            ("Vessel", "MV Horizon"),
            ("Required Delivery Port", "Singapore"),
            ("Reference No", "RFQ-20260517-001"),
            ("Requisition Date", "2026-05-20"),
            ("Priority", "High"),
            ("Company", "Blue Ocean Shipping"),
            ("Country", "Turkey"),
            ("Due Date", "2026-05-22"),
        ],
        item_headers=["#", "Product", "Part No", "Manufacturer", "MFG Model / Type", "Catalog Code", "Serial Number", "Qty", "Unit", "ROB", "Drawing Number"],
        items=[
            ["1", "Charge air cooler seal kit", "322.10.135", "MAN", "12V32/40", "IMPA 123456", "S-88931", "2", "PCS", "0", "DWG-2201"],
            ["2", "Fuel oil filter element", "FO-7781", "Alfa Laval", "MFO Set", "ISSA 771122", "F-1021", "12", "PCS", "1", "DWG-1120"],
            ["3", "Hydraulic pump overhaul service", "-", "Bosch Rexroth", "A10VSO", "-", "-", "1", "LOT", "0", "-"],
        ],
        notes="Clean standard RFQ layout.",
        pdf_style="boxed-form",
        png_style="clean-sheet",
    ),
    Scenario(
        slug="02-vessel-destination-criticality",
        title="RFQ IMPORT SAMPLE",
        general=[
            ("Vessel Name", "MV Orion"),
            ("Curr.", "EUR"),
            ("RFQ No", "RFQ-20260517-004"),
            ("Delivery Destination Country", "Greece"),
            ("Nearest Port", "Piraeus"),
            ("Criticality", "Normal"),
            ("General Notes", "Match maker catalog"),
        ],
        item_headers=["Product", "Maker", "Model / Type", "Catalog Code", "Plate No", "DWG No", "Qty", "Unit", "ROB", "Comments"],
        items=[
            ["Governor spare parts set", "Woodward", "2301A", "STK-9001", "PL-77", "DWG-09", "1", "SET", "0", "Check serial plate"],
            ["Hydraulic hose assembly", "Parker", "HT-550", "MAT-5502", "-", "-", "8", "PCS", "2", "Length 2.4 m"],
            ["Portable gas detector sensor", "Drager", "X-am", "REF-8810", "SN-55", "-", "3", "PCS", "1", "Calibrated item"],
            ["Anchor windlass brake band", "Kawasaki", "WB-90", "CODE-7782", "-", "DWG-119", "2", "PCS", "0", "Starboard side"],
        ],
        notes="Destination-country + criticality aliases.",
        pdf_style="spaced-two-column",
        png_style="excel-grid",
    ),
    Scenario(
        slug="03-mv-inquiry-urgency",
        title="Marine Inquiry Sheet",
        general=[
            ("M/V", "MV Aurora"),
            ("Inquiry No", "INQ-5507"),
            ("Port of Delivery", "Rotterdam"),
            ("Country", "Netherlands"),
            ("Req. Date", "2026-05-18"),
            ("Urgency", "Emergency"),
        ],
        item_headers=["Description", "Maker Ref", "Brand", "Model", "Qty", "UOM", "ROB", "Notes"],
        items=[
            ["Ballast pump mechanical seal", "BP-0041", "DESMI", "SL150", "2", "PCS", "0", "Please quote air freight"],
            ["Auxiliary engine lube oil filter", "AE-8892", "MANN", "WDK", "10", "PCS", "1", "Urgent departure"],
            ["Navigation light replacement service", "-", "Marinelec", "Nav-Serv", "1", "LOT", "0", "Attend next call"],
        ],
        notes="Short narrow table with minimal columns.",
        pdf_style="minimal-form",
        png_style="phone-capture",
    ),
    Scenario(
        slug="04-operator-quote-currency",
        title="Engine Room Request",
        general=[
            ("Ship", "MV Liberty"),
            ("Req No", "REQ-7781"),
            ("Country of Delivery", "UAE"),
            ("Port of Delivery", "Jebel Ali"),
            ("Quote Currency", "USD"),
            ("Urgency", "High"),
        ],
        item_headers=["Product", "Part Number", "Manufacturer", "Model/Type", "Serial No", "Qty", "Unit", "Drawing Ref", "Comments"],
        items=[
            ["Exhaust valve spindle", "EV-2201", "Wartsila", "RT-flex", "SN-220", "2", "PCS", "DRW-200", "Forward engine"],
            ["Cooling water pump repair kit", "CW-778", "Desmi", "NSL 150", "-", "1", "KIT", "-", "On board service"],
            ["Scavenge air drain bottle", "SC-199", "MAN", "S60ME-C", "-", "1", "PCS", "-", "Check flange size"],
        ],
        notes="Quote currency and product/part number aliases.",
        pdf_style="landscape-wide",
        png_style="tilted-paper",
    ),
    Scenario(
        slug="05-fleet-vessel-priority-level",
        title="Spare Parts Purchasing Request",
        general=[
            ("Fleet Vessel", "MV Neptune"),
            ("PR No", "PR-40591"),
            ("Ship Owner", "Atlantic Marine Group"),
            ("Destination Port", "Valencia"),
            ("Priority Level", "Critical"),
            ("Submission Deadline", "2026-05-29"),
        ],
        item_headers=["Material Description", "Article Number", "Manufacturer Name", "Machine Type", "Stock Code", "Engine Number", "Requested Quantity", "Issue Unit", "Current Stock", "Additional Remarks"],
        items=[
            ["Turbocharger nozzle ring", "TZ-455", "ABB", "VTR 304", "MAT-77880", "ENG-11", "2", "PCS", "1", "Match existing sample"],
            ["Fuel pump calibration kit", "FP-CAL-88", "Bosch", "KIT-88", "CAT-4430", "-", "1", "SET", "0", "Chief engineer priority"],
            ["Boiler soot blower chain", "SB-CH-19", "Kangrim", "SB-19", "STK-9980", "-", "4", "PCS", "0", "Keep original pitch"],
        ],
        notes="Owner/company heavy aliases.",
        pdf_style="bold-header-grid",
        png_style="low-contrast-grid",
    ),
    Scenario(
        slug="06-enquiry-offer-currency",
        title="Procurement Enquiry",
        general=[
            ("Vessels", "MV Atlas"),
            ("Enquiry No", "ENQ-20260517-009"),
            ("Managed By", "Blue Wave Shipmanagement"),
            ("Required Port", "Istanbul"),
            ("Offer Currency", "AED"),
            ("Date Requested", "2026-05-17"),
            ("Offer Deadline", "2026-05-25"),
        ],
        item_headers=["Items", "Maker Part No", "Brand Name", "Equipment Type", "Reference Code", "Equipment Serial No", "Qty Ordered", "Measuring Unit", "Available Stock", "Observation"],
        items=[
            ["Main air compressor gasket set", "MAK-0012", "Sauer", "WP300L", "IMPA 771001", "AC-9912", "3", "SET", "0", "Urgent sailing requirement"],
            ["Centrifugal purifier seal ring", "PUR-880", "Alfa Laval", "MOPX 205", "ISSA 550920", "PX-228", "6", "PCS", "1", "Match existing sample"],
            ["Boiler water test reagents", "BW-TEST", "Unitor", "Lab Kit", "UNITOR 7788", "-", "2", "KIT", "0", "Deliver before noon"],
            ["Provision crane brake lining", "CRN-BR", "MacGregor", "BHL-2", "CODE-1128", "MC-77", "4", "PCS", "0", "Check dimensions"],
        ],
        notes="Very strong alias coverage case.",
        pdf_style="landscape-wide",
        png_style="spreadsheet-screenshot",
    ),
    Scenario(
        slug="07-reference-id-rfq-status",
        title="RFQ WORKSHEET",
        general=[
            ("Name of Vessel", "MV Baltic Star"),
            ("Reference ID", "REF-887-11"),
            ("Destination Countries", "Egypt"),
            ("Delivery Ports", "Alexandria"),
            ("RFQ Status", "Open"),
            ("RFQ Date", "2026-05-14"),
            ("Pricing Deadline", "2026-05-21"),
            ("Requirement Notes", "Below items for drydock package"),
        ],
        item_headers=["Subject", "Part Ref", "Supplier Brand", "Type Number", "Article Code", "Plate Number", "Qty Req", "U/M", "Remaining On Board", "Special Instructions"],
        items=[
            ["Sea water pump shaft sleeve", "SWP-21", "KSB", "HN-440", "ART-2100", "PL-903", "2", "PCS", "0", "Port side pump"],
            ["Fresh water generator chemical set", "FWG-SET", "Wilhelmsen", "Chem-Pack", "UNI-4448", "-", "1", "LOT", "0", "Keep MSDS attached"],
            ["Bilge alarm control module", "BAL-778", "Omicron", "BCM-5", "STK-5003", "SN-31", "3", "PCS", "1", "Confirm firmware version"],
        ],
        notes="Status + notes + destination plural aliases.",
        pdf_style="clean-grid",
        png_style="whatsapp-crop",
    ),
    Scenario(
        slug="08-pr-requester-bid-deadline",
        title="Warehouse / Repair Request",
        general=[
            ("Ships", "MV Meridian"),
            ("Req Number", "RQ-66210"),
            ("Requester", "Engine Department"),
            ("Country", "Brazil"),
            ("Discharge Port", "Santos"),
            ("Bid Deadline", "2026-05-27"),
            ("Criticality", "High"),
        ],
        item_headers=["Requested Material", "Catalog Part No", "Manufacturers", "Model Number", "Item Code", "Serials", "Order Quantity", "Units", "Stock On Board", "Usage"],
        items=[
            ["Cylinder lubricator quill", "CLQ-77", "Hans Jensen", "Mark-6", "ITEM-9087", "SER-18", "5", "PCS", "2", "Engine no. 2"],
            ["Hydraulic oil cooler service", "-", "Rexroth", "Service Pack", "-", "-", "1", "LOT", "0", "Confirm manpower"],
            ["Air bottle pressure gauge", "ABG-09", "Wika", "PG-160", "REF-6600", "SN-774", "2", "PCS", "1", "Range 0-40 bar"],
        ],
        notes="Department/requester style general fields.",
        pdf_style="narrow-boxes",
        png_style="camera-shadow",
    ),
    Scenario(
        slug="09-customer-required-port",
        title="Quote Request Form",
        general=[
            ("Ship Name", "MV Borealis"),
            ("Customer", "North Sea Operations"),
            ("Required Port", "Busan"),
            ("Delivery Country", "South Korea"),
            ("Quote Due Date", "2026-05-23"),
            ("Priority", "Low"),
            ("General Notes", "Routine replenishment only"),
        ],
        item_headers=["Equipment Name", "PN", "Make", "Model/Type", "IMPA Code", "S/N", "Qty", "Unit of Measure", "Qty On Board", "Remarks"],
        items=[
            ["Oil mist detector sensor", "OMD-44", "Schaller", "VN115/93", "IMPA 552299", "SN-0091", "2", "PCS", "0", "Use latest revision"],
            ["Deck lighting ballast", "DLB-31", "Philips", "BLS-7", "CAT-7712", "-", "6", "PCS", "2", "Bridge deck only"],
            ["Provision freezer hinge", "PFH-20", "Carrier", "FZ-20", "STK-9902", "-", "4", "PCS", "1", "Right-hand side"],
        ],
        notes="Customer + equipment-name aliases.",
        pdf_style="boxed-form",
        png_style="clean-sheet",
    ),
    Scenario(
        slug="10-managed-by-trading-company",
        title="Stores / Spares / Repairs Request",
        general=[
            ("MV Vessel", "MV Polaris"),
            ("Inquiry Number", "INQ-778899"),
            ("Trading Company", "Eastern Supply & Marine"),
            ("Country of Delivery", "India"),
            ("Delivery Location", "Mumbai"),
            ("Urgency Level", "Normal"),
            ("Additional Notes", "Advise earliest ex-stock option"),
        ],
        item_headers=["Scope of Work", "Maker Reference", "Vendor", "Engine Type", "Catalogue Number", "SR Number", "Required Qty", "UOM Code", "On Board", "Purpose"],
        items=[
            ["Main engine indicator cock", "IC-771", "MAN", "6S50ME-C", "CAT-8842", "SR-17", "3", "PCS", "1", "Engine room stock check"],
            ["Fire line butterfly valve", "FBV-112", "AVK", "DN80", "ART-1182", "-", "2", "PCS", "0", "Muster station line"],
            ["HVAC control panel fan motor", "HV-99", "Ziehl-Abegg", "ZF-A1", "CODE-9081", "SR-990", "1", "PCS", "0", "Send with wiring data"],
        ],
        notes="Trading-company + delivery-location aliases.",
        pdf_style="spaced-two-column",
        png_style="low-contrast-grid",
    ),
]


def ensure_dirs() -> None:
    OUT_PDF.mkdir(parents=True, exist_ok=True)
    OUT_PNG.mkdir(parents=True, exist_ok=True)


def get_font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    candidates = [
        ("C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf"),
        ("C:/Windows/Fonts/calibrib.ttf" if bold else "C:/Windows/Fonts/calibri.ttf"),
        ("C:/Windows/Fonts/segoeuib.ttf" if bold else "C:/Windows/Fonts/segoeui.ttf"),
    ]

    for candidate in candidates:
        path = Path(candidate)
        if path.exists():
            return ImageFont.truetype(str(path), size=size)

    return ImageFont.load_default()


def build_pdf_table_data(scenario: Scenario) -> list[list[str]]:
    return [scenario.item_headers] + scenario.items


def render_pdf(scenario: Scenario) -> None:
    landscape_mode = scenario.pdf_style in {"landscape-wide", "clean-grid"}
    page_size = landscape(A4) if landscape_mode else A4
    out_path = OUT_PDF / f"{scenario.slug}.pdf"
    doc = SimpleDocTemplate(str(out_path), pagesize=page_size, rightMargin=18 * mm, leftMargin=18 * mm, topMargin=16 * mm, bottomMargin=16 * mm)
    styles = getSampleStyleSheet()
    story = []

    title_style = styles["Heading1"].clone("rfqTitle")
    title_style.fontSize = 17
    title_style.leading = 20
    title_style.textColor = colors.HexColor("#1f2a3d")
    story.append(Paragraph(scenario.title, title_style))
    story.append(Spacer(1, 8))

    if scenario.pdf_style in {"boxed-form", "narrow-boxes", "spaced-two-column", "minimal-form"}:
        pairs = [[f"<b>{label}</b>", value] for label, value in scenario.general]
        general_table = Table(pairs, colWidths=[48 * mm, 110 * mm] if not landscape_mode else [60 * mm, 180 * mm], hAlign="LEFT")
        general_table.setStyle(TableStyle([
            ("FONTNAME", (0, 0), (-1, -1), "Helvetica"),
            ("FONTSIZE", (0, 0), (-1, -1), 10),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
            ("TOPPADDING", (0, 0), (-1, -1), 7),
            ("TEXTCOLOR", (0, 0), (0, -1), colors.HexColor("#23364d")),
            ("LINEBELOW", (1, 0), (1, -1), 0.35, colors.HexColor("#d6deea")),
        ]))
        story.append(general_table)
        story.append(Spacer(1, 12))
    else:
        top_rows = []
        current = []
        for idx, pair in enumerate(scenario.general):
            current.extend(pair)
            if len(current) == 4 or idx == len(scenario.general) - 1:
                while len(current) < 4:
                    current.append("")
                top_rows.append(current)
                current = []

        general_table = Table(top_rows, colWidths=[36 * mm, 46 * mm, 36 * mm, 46 * mm] if not landscape_mode else [42 * mm, 58 * mm, 42 * mm, 58 * mm], hAlign="LEFT")
        general_table.setStyle(TableStyle([
            ("FONTNAME", (0, 0), (-1, -1), "Helvetica"),
            ("FONTNAME", (0, 0), (-1, -1), "Helvetica"),
            ("FONTNAME", (0, 0), (0, -1), "Helvetica-Bold"),
            ("FONTNAME", (2, 0), (2, -1), "Helvetica-Bold"),
            ("FONTSIZE", (0, 0), (-1, -1), 10),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
            ("TOPPADDING", (0, 0), (-1, -1), 6),
        ]))
        story.append(general_table)
        story.append(Spacer(1, 10))

    table_data = build_pdf_table_data(scenario)
    width = page_size[0] - doc.leftMargin - doc.rightMargin
    col_count = len(scenario.item_headers)
    base_col_width = width / col_count
    col_widths = [base_col_width] * col_count
    if col_count >= 10:
        col_widths[1] = base_col_width * 2.1
        if len(col_widths) > 2:
            col_widths[2] = base_col_width * 1.25
    item_table = Table(table_data, colWidths=col_widths, repeatRows=1, hAlign="LEFT")
    item_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#e7eef8")),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.HexColor("#25374d")),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("FONTNAME", (0, 1), (-1, -1), "Helvetica"),
        ("FONTSIZE", (0, 0), (-1, -1), 8.8 if landscape_mode else 8.4),
        ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#a7b7cf")),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ]))
    story.append(item_table)
    story.append(Spacer(1, 10))
    story.append(Paragraph(scenario.notes, styles["BodyText"]))
    doc.build(story)


def build_general_blocks(draw: ImageDraw.ImageDraw, scenario: Scenario, x: int, y: int, col_gap: int, row_gap: int, width: int, fonts: dict[str, ImageFont.ImageFont]) -> int:
    left_pairs = scenario.general[::2]
    right_pairs = scenario.general[1::2]
    rows = max(len(left_pairs), len(right_pairs))
    for row in range(rows):
        y0 = y + row * row_gap
        if row < len(left_pairs):
            label, value = left_pairs[row]
            draw.text((x, y0), label, fill="#23364d", font=fonts["label"])
            draw.text((x + 150, y0), value, fill="#1c2736", font=fonts["value"])
        if row < len(right_pairs):
            label, value = right_pairs[row]
            draw.text((x + col_gap, y0), label, fill="#23364d", font=fonts["label"])
            draw.text((x + col_gap + 160, y0), value, fill="#1c2736", font=fonts["value"])
    return y + rows * row_gap


def approximate_col_widths(headers: list[str], total_width: int) -> list[int]:
    weights = []
    for header in headers:
        normalized = header.lower()
        if normalized in {"product", "description", "equipment name", "scope of work", "material description", "requested material"}:
            weights.append(2.5)
        elif normalized in {"comments", "remarks", "notes", "special instructions", "additional remarks", "observation", "purpose"}:
            weights.append(2.2)
        elif normalized in {"qty", "unit", "uom", "rob", "#"}:
            weights.append(0.75)
        else:
            weights.append(1.25)
    total_weight = sum(weights)
    widths = [int(total_width * weight / total_weight) for weight in weights]
    diff = total_width - sum(widths)
    if widths:
        widths[-1] += diff
    return widths


def draw_table(draw: ImageDraw.ImageDraw, scenario: Scenario, origin_x: int, origin_y: int, table_width: int, fonts: dict[str, ImageFont.ImageFont], light: bool = False) -> int:
    headers = scenario.item_headers
    rows = [headers] + scenario.items
    col_widths = approximate_col_widths(headers, table_width)
    line_color = "#d3dbe8" if light else "#a8b7ca"
    header_fill = "#eef4fb" if light else "#e4edf8"
    row_fill = "#ffffff"
    row_height = 44
    table_height = row_height * len(rows)

    y = origin_y
    for row_index, row in enumerate(rows):
        x = origin_x
        fill = header_fill if row_index == 0 else row_fill
        for col_index, cell in enumerate(row):
            width = col_widths[col_index]
            draw.rectangle([x, y, x + width, y + row_height], fill=fill, outline=line_color, width=1)
            font = fonts["header"] if row_index == 0 else fonts["cell"]
            text_x = x + 10
            text_y = y + 11
            draw.text((text_x, text_y), str(cell), fill="#223246", font=font)
            x += width
        y += row_height

    return origin_y + table_height


def apply_png_style(image: Image.Image, scenario: Scenario) -> Image.Image:
    styled = image
    if scenario.png_style == "tilted-paper":
        styled = styled.rotate(-3.2, expand=True, fillcolor="#eef2f7")
    elif scenario.png_style == "phone-capture":
        styled = styled.filter(ImageFilter.GaussianBlur(radius=0.2))
    elif scenario.png_style == "low-contrast-grid":
        overlay = Image.new("RGBA", styled.size, (255, 255, 255, 32))
        styled = Image.alpha_composite(styled.convert("RGBA"), overlay).convert("RGB")
    elif scenario.png_style == "camera-shadow":
        canvas = Image.new("RGB", (styled.width + 60, styled.height + 60), "#edf1f6")
        shadow = Image.new("RGBA", (styled.width, styled.height), (0, 0, 0, 0))
        shadow_draw = ImageDraw.Draw(shadow)
        shadow_draw.rounded_rectangle([0, 0, styled.width - 1, styled.height - 1], radius=24, fill=(0, 0, 0, 40))
        shadow = shadow.filter(ImageFilter.GaussianBlur(radius=12))
        canvas.paste(shadow.convert("RGB"), (26, 26))
        canvas.paste(styled, (18, 14))
        styled = canvas
    elif scenario.png_style == "whatsapp-crop":
        crop = styled.crop((24, 20, styled.width - 22, styled.height - 20))
        bg = Image.new("RGB", crop.size, "#f7f8fb")
        bg.paste(crop, (0, 0))
        styled = bg
    elif scenario.png_style == "spreadsheet-screenshot":
        draw = ImageDraw.Draw(styled)
        for x in range(0, styled.width, 110):
            draw.line((x, 0, x, styled.height), fill="#eef2f6", width=1)
        for y in range(0, styled.height, 42):
            draw.line((0, y, styled.width, y), fill="#eef2f6", width=1)
    return styled


def render_png(scenario: Scenario) -> None:
    width, height = 1600, 1000
    image = Image.new("RGB", (width, height), "#ffffff")
    draw = ImageDraw.Draw(image)
    fonts = {
        "title": get_font(22, bold=True),
        "label": get_font(16, bold=True),
        "value": get_font(15, bold=False),
        "header": get_font(14, bold=True),
        "cell": get_font(14, bold=False),
        "note": get_font(13, bold=False),
    }

    if scenario.png_style in {"phone-capture", "tilted-paper", "camera-shadow"}:
        draw.rectangle([0, 0, width, height], fill="#eef2f7")
        paper = Image.new("RGB", (1330, 760), "#ffffff")
        paper_draw = ImageDraw.Draw(paper)
        paper_draw.text((50, 40), scenario.title, fill="#1e2a3d", font=fonts["title"])
        current_y = 110
        current_y = build_general_blocks(paper_draw, scenario, 60, current_y, 600, 72, 1200, fonts)
        current_y += 24
        current_y = draw_table(paper_draw, scenario, 55, current_y, 1210, fonts)
        paper_draw.text((60, min(current_y + 26, 720)), scenario.notes, fill="#637288", font=fonts["note"])
        image.paste(paper, (140, 110))
    else:
        draw.text((56, 36), scenario.title, fill="#1f2a3d", font=fonts["title"])
        top_y = 90
        if scenario.png_style in {"clean-sheet", "excel-grid"}:
            draw.rectangle([54, top_y, width - 54, top_y + 60], fill="#cfe0f6", outline="#88a7cf", width=1)
            banner_text = "For Spares / Stores / Repairs / Services"
            banner_width = draw.textlength(banner_text, font=fonts["title"])
            draw.text(((width - banner_width) / 2, top_y + 16), banner_text, fill="#24384f", font=fonts["title"])
            top_y += 90
        general_end = build_general_blocks(draw, scenario, 70, top_y, 730, 64, width - 140, fonts)
        table_y = general_end + 34
        draw_table(draw, scenario, 54, table_y, width - 108, fonts, light=scenario.png_style == "low-contrast-grid")
        draw.text((60, height - 42), scenario.notes, fill="#6a7788", font=fonts["note"])

    image = apply_png_style(image, scenario)
    out_path = OUT_PNG / f"{scenario.slug}.png"
    image.save(out_path, format="PNG")


def write_manifest() -> None:
    with OUT_MANIFEST.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(["slug", "title", "pdf", "png", "notes"])
        for scenario in SCENARIOS:
            writer.writerow([
                scenario.slug,
                scenario.title,
                str((OUT_PDF / f"{scenario.slug}.pdf").relative_to(ROOT)),
                str((OUT_PNG / f"{scenario.slug}.png").relative_to(ROOT)),
                scenario.notes,
            ])


def main() -> None:
    random.seed(17)
    ensure_dirs()

    for scenario in SCENARIOS:
        render_pdf(scenario)
        render_png(scenario)

    write_manifest()
    print(f"Generated {len(SCENARIOS)} PDFs in {OUT_PDF}")
    print(f"Generated {len(SCENARIOS)} PNGs in {OUT_PNG}")
    print(f"Manifest: {OUT_MANIFEST}")


if __name__ == "__main__":
    main()
