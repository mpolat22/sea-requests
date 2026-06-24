from __future__ import annotations

import base64
import io
import json
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

from pypdf import PdfReader


if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")


def normalize_line(line: str) -> str:
    return " ".join(str(line).replace("\r", " ").replace("\n", " ").split()).strip()


def split_layout_line(line: str) -> list[str]:
    raw_parts = [normalize_line(part) for part in re.split(r"\s{2,}", line)]
    return [part for part in raw_parts if part]


def pil_image_to_data_url(image) -> str:
    width, height = image.size
    max_width = 1400

    if width > max_width:
        ratio = max_width / float(width)
        image = image.resize((max_width, max(1, int(height * ratio))))

    buffer = io.BytesIO()
    image.convert("RGB").save(buffer, format="JPEG", quality=82, optimize=True)

    return "data:image/jpeg;base64," + base64.b64encode(buffer.getvalue()).decode("ascii")


def render_pdf_pages_with_pypdfium2(path: Path, limit: int = 3) -> list[str]:
    try:
        import pypdfium2  # type: ignore
    except Exception:
        return []

    images: list[str] = []
    document = pypdfium2.PdfDocument(str(path))

    try:
        page_count = min(len(document), limit)

        for page_index in range(page_count):
            page = document[page_index]

            try:
                bitmap = page.render(scale=2)
                pil_image = bitmap.to_pil()
                images.append(pil_image_to_data_url(pil_image))
            finally:
                page.close()
    finally:
        document.close()

    return images


def render_pdf_pages_with_pdftoppm(path: Path, limit: int = 3) -> list[str]:
    pdftoppm_path = shutil.which("pdftoppm")

    if not pdftoppm_path:
        return []

    try:
        from PIL import Image  # type: ignore
    except Exception:
        return []

    with tempfile.TemporaryDirectory(prefix="rfq-pdf-pages-") as temp_dir:
        output_prefix = Path(temp_dir) / "page"
        command = [
            pdftoppm_path,
            "-jpeg",
            "-r",
            "180",
            "-f",
            "1",
            "-l",
            str(limit),
            str(path),
            str(output_prefix),
        ]
        subprocess.run(command, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)

        images: list[str] = []

        for image_path in sorted(Path(temp_dir).glob("page-*.jpg"))[:limit]:
            with Image.open(image_path) as image:
                images.append(pil_image_to_data_url(image))

        return images


def render_pdf_page_images(path: Path, limit: int = 3) -> list[str]:
    for renderer in (render_pdf_pages_with_pypdfium2, render_pdf_pages_with_pdftoppm):
        try:
            rendered = renderer(path, limit)
        except Exception:
            rendered = []

        if rendered:
            return rendered

    return []


def pdf_to_rows(path: Path) -> dict:
    reader = PdfReader(str(path), strict=False)

    if reader.is_encrypted:
        try:
            reader.decrypt("")
        except Exception:
            pass

    rows: list[list[str]] = []
    text_lines: list[str] = []

    for page in reader.pages:
        try:
            text = page.extract_text(extraction_mode="layout") or page.extract_text() or ""
        except Exception:
            text = ""

        lines = [line.rstrip() for line in text.splitlines()]
        lines = [line for line in lines if normalize_line(line)]
        text_lines.extend(lines)
        rows.extend([split_layout_line(line) for line in lines])
        rows.append([])

    while rows and rows[-1] == []:
        rows.pop()

    return {
        "rows": rows,
        "ocr_lines": text_lines,
        "page_images": render_pdf_page_images(path, 3) if not rows else [],
    }


def main() -> int:
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Missing PDF path"}))
        return 1

    path = Path(sys.argv[1])

    if not path.exists():
        print(json.dumps({"error": "PDF file not found"}))
        return 1

    try:
        print(json.dumps(pdf_to_rows(path), ensure_ascii=False))
        return 0
    except Exception as exc:
        print(json.dumps({"error": str(exc)}, ensure_ascii=False))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
