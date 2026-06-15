from pathlib import Path
import json
import sys

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer


def build_pdf(data, output_path: Path) -> None:
    styles = getSampleStyleSheet()
    title_style = ParagraphStyle(
        "TitleStyle",
        parent=styles["Title"],
        fontName="Helvetica-Bold",
        fontSize=20,
        leading=24,
        textColor=colors.HexColor("#0f172a"),
        spaceAfter=12,
    )
    intro_style = ParagraphStyle(
        "IntroStyle",
        parent=styles["BodyText"],
        fontName="Helvetica",
        fontSize=10,
        leading=15,
        textColor=colors.HexColor("#475569"),
        spaceAfter=16,
    )
    category_style = ParagraphStyle(
        "CategoryStyle",
        parent=styles["Heading2"],
        fontName="Helvetica-Bold",
        fontSize=13,
        leading=17,
        textColor=colors.HexColor("#0f172a"),
        spaceBefore=8,
        spaceAfter=6,
    )
    subcategory_style = ParagraphStyle(
        "SubcategoryStyle",
        parent=styles["BodyText"],
        fontName="Helvetica",
        fontSize=10.5,
        leading=14,
        textColor=colors.HexColor("#334155"),
        leftIndent=12,
        bulletIndent=0,
        spaceAfter=3,
    )
    empty_style = ParagraphStyle(
        "EmptyStyle",
        parent=styles["Italic"],
        fontName="Helvetica-Oblique",
        fontSize=10,
        leading=14,
        textColor=colors.HexColor("#64748b"),
        leftIndent=12,
        spaceAfter=4,
    )

    doc = SimpleDocTemplate(
        str(output_path),
        pagesize=A4,
        leftMargin=18 * mm,
        rightMargin=18 * mm,
        topMargin=18 * mm,
        bottomMargin=18 * mm,
        title="Spare Parts Categories and Subcategories",
        author="OpenAI Codex",
    )

    story = []
    total_categories = len(data)
    total_subcategories = sum(len(item.get("subcategories", [])) for item in data)

    story.append(Paragraph("Spare Parts Categories and Subcategories", title_style))
    story.append(
        Paragraph(
            f"Active categories: <b>{total_categories}</b><br/>"
            f"Active subcategories: <b>{total_subcategories}</b>",
            intro_style,
        )
    )

    for item in data:
        story.append(Paragraph(item["name"], category_style))

        subcategories = item.get("subcategories", [])
        if subcategories:
            for subcategory in subcategories:
                story.append(Paragraph(subcategory, subcategory_style, bulletText="\u2022"))
        else:
            story.append(Paragraph("No subcategories defined.", empty_style))

        story.append(Spacer(1, 4))

    doc.build(story)


def main() -> int:
    if len(sys.argv) != 3:
        raise SystemExit("Usage: export_categories_pdf.py <json-input> <pdf-output>")

    input_path = Path(sys.argv[1])
    output_path = Path(sys.argv[2])

    data = json.loads(input_path.read_text(encoding="utf-8-sig"))
    output_path.parent.mkdir(parents=True, exist_ok=True)
    build_pdf(data, output_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
