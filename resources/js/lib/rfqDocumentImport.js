const PDF_TYPES = new Set(['pdf']);
const IMAGE_TYPES = new Set(['png', 'jpg', 'jpeg', 'webp']);
const MIN_PDF_TEXT_LENGTH = 24;
const LINE_TOLERANCE = 10;
const CELL_GAP = 26;
const OCR_HEADER_ALIASES = {
    product_name: ['product', 'product name', 'item', 'item name', 'description', 'part name', 'equipment name', 'material'],
    part_no: ['part no', 'part number', 'maker ref', 'mfg part', 'part ref', 'maker part'],
    manufacturer: ['manufacturer', 'maker', 'brand', 'vendor', 'make'],
    model_type: ['mfg model / type', 'model / type', 'model type', 'model', 'type', 'model no'],
    catalog_code: ['catalog code', 'impa', 'issa', 'unitor', 'nitor', 'item code', 'code'],
    serial_number: ['serial number', 'serial no', 'serial', 's/n', 'plate no'],
    drawing_number: ['drawing number', 'drawing no', 'drawing ref', 'dwg no', 'drg no'],
    quantity: ['qty', 'quantity', 'requested qty'],
    unit: ['unit', 'uom', 'u/m', 'unit of measure'],
    rob: ['rob', 'on board', 'stock on board'],
    quality: ['quality', 'condition', 'grade'],
    comments: ['comments', 'remarks', 'notes', 'comment'],
};
let pdfModulePromise = null;
let ocrModulePromise = null;

const loadPdfModule = async () => {
    if (!pdfModulePromise) {
        pdfModulePromise = Promise.all([
            import('pdfjs-dist/build/pdf.mjs'),
            import('pdfjs-dist/build/pdf.worker.mjs?url'),
        ]).then(([pdfModule, workerModule]) => {
            pdfModule.GlobalWorkerOptions.workerSrc = workerModule.default;

            return pdfModule;
        });
    }

    return pdfModulePromise;
};

const loadOcrModule = async () => {
    if (!ocrModulePromise) {
        ocrModulePromise = import('tesseract.js');
    }

    return ocrModulePromise;
};

const fileExtension = (file) => String(file?.name ?? '')
    .split('.')
    .pop()
    ?.toLowerCase() ?? '';

const normalizeCellText = (value) => String(value ?? '')
    .replace(/\s+/g, ' ')
    .trim();

const normalizeAlias = (value) => normalizeCellText(value)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

const sortTokens = (left, right) => {
    if (Math.abs(left.top - right.top) > LINE_TOLERANCE) {
        return left.top - right.top;
    }

    return left.left - right.left;
};

const groupTokensIntoLines = (tokens) => {
    const ordered = [...tokens]
        .filter((token) => normalizeCellText(token.text) !== '')
        .sort(sortTokens);
    const lines = [];

    ordered.forEach((token) => {
        const line = lines.find((candidate) => Math.abs(candidate.top - token.top) <= LINE_TOLERANCE);

        if (line) {
            line.tokens.push(token);
            line.top = Math.min(line.top, token.top);
            return;
        }

        lines.push({
            top: token.top,
            tokens: [token],
        });
    });

    return lines;
};

const lineToCells = (line) => {
    const cells = [];
    const rowTokens = [...(line?.tokens ?? [])].sort((left, right) => left.left - right.left);

    rowTokens.forEach((token) => {
        const currentCell = cells[cells.length - 1];

        if (!currentCell) {
            cells.push({
                text: token.text,
                left: token.left,
                right: token.right,
            });
            return;
        }

        const gap = token.left - currentCell.right;

        if (gap > CELL_GAP) {
            cells.push({
                text: token.text,
                left: token.left,
                right: token.right,
            });
            return;
        }

        currentCell.text = `${currentCell.text} ${token.text}`.trim();
        currentCell.right = Math.max(currentCell.right, token.right);
    });

    return cells
        .map((cell) => ({
            ...cell,
            text: normalizeCellText(cell.text),
        }))
        .filter((cell) => cell.text);
};

const tokensToRows = (tokens) => groupTokensIntoLines(tokens)
    .map((line) => lineToCells(line).map((cell) => cell.text))
    .filter((row) => row.length > 0);

const tokensToLineTexts = (tokens) => groupTokensIntoLines(tokens)
    .map((line) => normalizeCellText(lineToCells(line).map((cell) => cell.text).join(' ')))
    .filter(Boolean);

const scoreHeaderCell = (text) => {
    const normalized = normalizeAlias(text);
    let best = null;

    Object.entries(OCR_HEADER_ALIASES).forEach(([field, aliases]) => {
        aliases.forEach((alias) => {
            const normalizedAlias = normalizeAlias(alias);
            let score = 0;

            if (normalized === normalizedAlias) {
                score = 100;
            } else if (normalized.includes(normalizedAlias) || normalizedAlias.includes(normalized)) {
                score = 86;
            }

            if (score > 0 && (!best || score > best.score)) {
                best = { field, score };
            }
        });
    });

    return best;
};

const headerAliasMatchesText = (text) => Boolean(scoreHeaderCell(text));

const isDashLike = (value) => ['-', '—', '_'].includes(normalizeCellText(value));

const mergeText = (left, right) => [normalizeCellText(left), normalizeCellText(right)]
    .filter(Boolean)
    .join(' ')
    .trim();

const splitCombinedSupplyTail = (record, field) => {
    const value = normalizeCellText(record[field]);

    if (!value) {
        return record;
    }

    const match = value.match(/^(.*?)(\d+(?:[.,]\d+)?)\s+([A-Za-z]{2,6})(?:\s+(\d+(?:[.,]\d+)?))?(?:\s+(.+))?$/i);

    if (!match) {
        return record;
    }

    const [, head = '', qty = '', unit = '', rob = '', tail = ''] = match;
    const cleanedHead = normalizeCellText(head);

    if (!cleanedHead && !qty) {
        return record;
    }

    if (!record.quantity && qty) {
        record.quantity = qty;
    }

    if (!record.unit && unit) {
        record.unit = unit.toUpperCase();
    }

    if (!record.rob && rob) {
        record.rob = rob;
    }

    if (!record.comments && tail) {
        record.comments = tail;
    } else if (tail) {
        record.comments = mergeText(record.comments, tail);
    }

    record[field] = cleanedHead;

    return record;
};

const splitQuantityAndUnit = (record) => {
    const value = normalizeCellText(record.quantity);

    if (!value) {
        return record;
    }

    const match = value.match(/^(\d+(?:[.,]\d+)?)(?:\s+([A-Za-z]{2,6}))?(?:\s+(\d+(?:[.,]\d+)?))?(?:\s+(.+))?$/i);

    if (!match) {
        return record;
    }

    const [, qty = '', unit = '', rob = '', tail = ''] = match;

    record.quantity = qty || record.quantity;

    if (!record.unit && unit) {
        record.unit = unit.toUpperCase();
    }

    if (!record.rob && rob) {
        record.rob = rob;
    }

    if (tail) {
        record.comments = mergeText(record.comments, tail);
    }

    return record;
};

const normalizeMappedRecord = (row, fields) => {
    const record = fields.reduce((carry, field, index) => {
        carry[field] = normalizeCellText(row[index] ?? '');
        return carry;
    }, {});

    ['product_name', 'part_no', 'manufacturer', 'model_type', 'comments'].forEach((field) => {
        splitCombinedSupplyTail(record, field);
    });

    splitQuantityAndUnit(record);

    return record;
};

const isHeaderLikeRecord = (record) => {
    const nonEmptyValues = Object.values(record).filter((value) => normalizeCellText(value) !== '');

    if (!nonEmptyValues.length) {
        return true;
    }

    const aliasHits = nonEmptyValues.filter((value) => headerAliasMatchesText(value)).length;

    return aliasHits >= Math.max(2, Math.ceil(nonEmptyValues.length / 2));
};

const isSparseContinuationRecord = (record) => {
    const meaningful = Object.entries(record)
        .filter(([, value]) => normalizeCellText(value) && !isDashLike(value));

    if (!meaningful.length || meaningful.length > 2) {
        return false;
    }

    if (record.quantity || record.unit || record.rob || record.part_no || record.catalog_code || record.serial_number || record.drawing_number) {
        return false;
    }

    return true;
};

const mergeContinuationRecord = (rows, record) => {
    if (!rows.length) {
        return false;
    }

    const previous = rows[rows.length - 1];

    Object.entries(record).forEach(([field, value]) => {
        if (!normalizeCellText(value) || isDashLike(value)) {
            return;
        }

        if (field === 'product_name') {
            previous.product_name = mergeText(previous.product_name, value);
            return;
        }

        if (field === 'comments') {
            previous.comments = mergeText(previous.comments, value);
            return;
        }

        if (previous[field]) {
            previous[field] = mergeText(previous[field], value);
            return;
        }

        previous[field] = value;
    });

    return true;
};

const isMeaningfulRecord = (record) => {
    const values = Object.values(record)
        .map((value) => normalizeCellText(value))
        .filter((value) => value && !isDashLike(value));

    return values.length > 0;
};

const detectHeaderLine = (lines) => {
    let best = null;

    lines.forEach((line, index) => {
        const cells = lineToCells(line);
        const matches = cells
            .map((cell) => ({ cell, match: scoreHeaderCell(cell.text) }))
            .filter((entry) => entry.match);

        const fields = [...new Set(matches.map((entry) => entry.match.field))];

        if (fields.length < 3 || (!fields.includes('product_name') && !fields.includes('quantity'))) {
            return;
        }

        const score = matches.reduce((total, entry) => total + entry.match.score, 0);

        if (!best || fields.length > best.fields.length || (fields.length === best.fields.length && score > best.score)) {
            best = {
                index,
                cells,
                matches,
                fields,
                score,
            };
        }
    });

    return best;
};

const tabularizeLines = (lines) => {
    const header = detectHeaderLine(lines);

    if (!header) {
        return null;
    }

    const orderedColumns = header.matches
        .sort((left, right) => left.cell.left - right.cell.left)
        .reduce((carry, entry) => {
            if (!carry.some((column) => column.field === entry.match.field)) {
                carry.push({
                    field: entry.match.field,
                    label: entry.cell.text,
                    left: entry.cell.left,
                    right: entry.cell.right,
                });
            }

            return carry;
        }, []);

    const boundaries = orderedColumns.map((column, index) => {
        const previous = orderedColumns[index - 1];
        const next = orderedColumns[index + 1];

        return {
            ...column,
            min: previous ? (previous.right + column.left) / 2 : -Infinity,
            max: next ? (column.right + next.left) / 2 : Infinity,
        };
    });

    const generalRows = lines
        .slice(0, header.index)
        .map((line) => lineToCells(line).map((cell) => cell.text))
        .filter((row) => row.length > 0);

    const headerRow = boundaries.map((column) => column.label);
    const itemRecords = [];
    const fields = boundaries.map((column) => column.field);
    let emptyStreak = 0;

    lines.slice(header.index + 1).forEach((line) => {
        const tokens = [...(line.tokens ?? [])].sort((left, right) => left.left - right.left);
        const values = boundaries.map(() => []);

        tokens.forEach((token) => {
            const center = (token.left + token.right) / 2;
            const targetIndex = boundaries.findIndex((column) => center >= column.min && center < column.max);

            if (targetIndex >= 0) {
                values[targetIndex].push(token.text);
            }
        });

        const row = values.map((parts) => normalizeCellText(parts.join(' ')));

        if (row.every((value) => !value)) {
            emptyStreak += 1;
            return;
        }

        if (itemRecords.length && emptyStreak >= 3) {
            return;
        }

        emptyStreak = 0;
        const record = normalizeMappedRecord(row, fields);

        if (isHeaderLikeRecord(record)) {
            return;
        }

        if (!isMeaningfulRecord(record)) {
            return;
        }

        if (isSparseContinuationRecord(record) && mergeContinuationRecord(itemRecords, record)) {
            return;
        }

        itemRecords.push(record);
    });

    const itemRows = itemRecords
        .map((record) => fields.map((field) => record[field] || ''))
        .filter((row) => row.some((value) => normalizeCellText(value) && !isDashLike(value)));

    return [...generalRows, headerRow, ...itemRows];
};

const extractPdfPageTokens = async (page) => {
    const viewport = page.getViewport({ scale: 1 });
    const textContent = await page.getTextContent();

    return textContent.items
        .map((item) => {
            const text = normalizeCellText(item.str ?? '');

            if (!text) {
                return null;
            }

            const left = Number(item.transform?.[4] ?? 0);
            const top = viewport.height - Number(item.transform?.[5] ?? 0);
            const width = Number(item.width ?? 0);
            const height = Number(item.height ?? 0);

            return {
                text,
                left,
                top,
                right: left + width,
                bottom: top + height,
            };
        })
        .filter(Boolean);
};

const renderPdfPageToCanvas = async (page) => {
    const viewport = page.getViewport({ scale: 2 });
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });

    canvas.width = Math.ceil(viewport.width);
    canvas.height = Math.ceil(viewport.height);

    await page.render({
        canvasContext: context,
        viewport,
    }).promise;

    return canvas;
};

const sourceToCanvas = async (source) => {
    if (source instanceof HTMLCanvasElement) {
        return source;
    }

    const imageBitmap = await createImageBitmap(source);
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });

    canvas.width = imageBitmap.width;
    canvas.height = imageBitmap.height;
    context.drawImage(imageBitmap, 0, 0);

    return canvas;
};

const enhanceCanvasForOcr = async (source) => {
    const baseCanvas = await sourceToCanvas(source);
    const enhanced = document.createElement('canvas');
    const context = enhanced.getContext('2d', { willReadFrequently: true });

    enhanced.width = baseCanvas.width;
    enhanced.height = baseCanvas.height;
    context.drawImage(baseCanvas, 0, 0);

    const imageData = context.getImageData(0, 0, enhanced.width, enhanced.height);
    const { data } = imageData;

    for (let index = 0; index < data.length; index += 4) {
        const grayscale = (0.299 * data[index]) + (0.587 * data[index + 1]) + (0.114 * data[index + 2]);
        const normalized = grayscale > 180 ? 255 : 0;

        data[index] = normalized;
        data[index + 1] = normalized;
        data[index + 2] = normalized;
    }

    context.putImageData(imageData, 0, 0);

    return enhanced;
};

const createOcrWorkerInstance = async () => {
    const { createWorker } = await loadOcrModule();

    return createWorker('eng');
};

const extractTokensFromBlocks = (blocks = []) => blocks
    .flatMap((block) => block?.paragraphs ?? [])
    .flatMap((paragraph) => paragraph?.lines ?? [])
    .flatMap((line) => line?.words ?? [])
    .map((word) => {
        const text = normalizeCellText(word.text ?? '');

        if (!text) {
            return null;
        }

        return {
            text,
            left: Number(word.bbox?.x0 ?? 0),
            top: Number(word.bbox?.y0 ?? 0),
            right: Number(word.bbox?.x1 ?? 0),
            bottom: Number(word.bbox?.y1 ?? 0),
        };
    })
    .filter(Boolean);

const ocrCanvasToRows = async (source) => {
    const worker = await createOcrWorkerInstance();

    try {
        const attemptRows = async (input) => {
        const result = await worker.recognize(input, {}, { blocks: true });
        const tokens = extractTokensFromBlocks(result?.data?.blocks ?? []);

        if (tokens.length) {
            const lines = groupTokensIntoLines(tokens);
            const lineTexts = tokensToLineTexts(tokens);
            const tabularRows = tabularizeLines(lines);

            if (tabularRows?.length) {
                return {
                    rows: tabularRows,
                    ocrLines: lineTexts,
                };
            }

            return {
                rows: tokensToRows(tokens),
                ocrLines: lineTexts,
            };
        }

            const textLines = String(result?.data?.text ?? '')
                .split(/\r?\n/)
                .map((line) => normalizeCellText(line))
                .filter(Boolean);

            return {
                rows: textLines.map((line) => [line]),
                ocrLines: textLines,
            };
        };

        const primaryRows = await attemptRows(source);

        if (primaryRows.rows.length) {
            return primaryRows;
        }

        const enhancedSource = await enhanceCanvasForOcr(source);

        return attemptRows(enhancedSource);
    } finally {
        await worker.terminate();
    }
};

const extractPdfRows = async (file) => {
    const { getDocument } = await loadPdfModule();
    const buffer = await file.arrayBuffer();
    const pdf = await getDocument({ data: new Uint8Array(buffer) }).promise;
    const rows = [];
    const ocrLines = [];

    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
        const page = await pdf.getPage(pageNumber);
        const tokens = await extractPdfPageTokens(page);
        const textLength = tokens.reduce((total, token) => total + token.text.length, 0);

        let pageRows = tokensToRows(tokens);
        let pageLines = tokensToLineTexts(tokens);

        if (textLength < MIN_PDF_TEXT_LENGTH) {
            const canvas = await renderPdfPageToCanvas(page);
            const ocrResult = await ocrCanvasToRows(canvas);
            pageRows = ocrResult.rows;
            pageLines = ocrResult.ocrLines;
        }

        rows.push(...pageRows);
        ocrLines.push(...pageLines);
        rows.push([]);
    }

    while (rows.length && rows.at(-1)?.length === 0) {
        rows.pop();
    }

    return {
        rows,
        ocrLines,
        sheetName: file.name.replace(/\.[^.]+$/, '') || 'Imported PDF',
    };
};

const extractImageRows = async (file) => {
    const result = await ocrCanvasToRows(file);

    return {
        rows: result.rows,
        ocrLines: result.ocrLines,
        sheetName: file.name.replace(/\.[^.]+$/, '') || 'Imported Image',
    };
};

export const isStructuredDocumentImport = (file) => {
    const extension = fileExtension(file);

    return PDF_TYPES.has(extension) || IMAGE_TYPES.has(extension);
};

export const extractDocumentRows = async (file) => {
    const extension = fileExtension(file);

    if (PDF_TYPES.has(extension)) {
        return extractPdfRows(file);
    }

    if (IMAGE_TYPES.has(extension)) {
        return extractImageRows(file);
    }

    throw new Error('Unsupported document type.');
};
