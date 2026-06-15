from __future__ import annotations

import json
import re
import sys
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


def pdf_to_rows(path: Path) -> dict:
    reader = PdfReader(str(path))
    rows: list[list[str]] = []
    text_lines: list[str] = []

    for page in reader.pages:
        text = page.extract_text(extraction_mode="layout") or page.extract_text() or ""
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
