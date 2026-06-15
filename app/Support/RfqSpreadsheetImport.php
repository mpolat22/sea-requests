<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class RfqSpreadsheetImport
{
    private const FIELD_LABELS = [
        'reference_no' => 'Reference No',
        'company_name' => 'Company',
        'ship_name' => 'Ship',
        'requisition_date' => 'Requisition Date',
        'due_date' => 'Due Date',
        'currency' => 'Currency',
        'priority' => 'Priority',
        'status' => 'RFQ Status',
        'general_notes' => 'General Notes',
        'country' => 'Country',
        'port' => 'Port',
        'product_name' => 'Product',
        'part_no' => 'Part No',
        'catalog_code' => 'Catalog Code',
        'quantity' => 'Qty',
        'unit' => 'Unit',
        'manufacturer' => 'Manufacturer',
        'model_type' => 'MFG Model / Type',
        'serial_number' => 'Serial Number',
        'rob' => 'ROB',
        'drawing_number' => 'Drawing Number',
        'quality' => 'Quality',
        'comments' => 'Comments',
    ];

    private const GENERAL_FIELD_ALIASES = [
        'reference_no' => [
            'reference', 'reference no', 'reference number', 'ref no', 'requisition no',
            'requisition number', 'request no', 'request number', 'rfq no', 'rfq number',
            'inquiry no', 'inquiry number', 'enquiry no', 'enquiry number', 'pr no',
            'pr number', 'req no', 'req number', 'requisition ref', 'reference id',
        ],
        'company_name' => [
            'company', 'buyer', 'customer', 'client', 'owner', 'operator', 'owners',
            'ship owner', 'owner company', 'management company', 'managed by',
            'trading company', 'purchaser', 'purchasing company', 'supplier', 'requester',
            'ordered by', 'ordered for', 'account name',
        ],
        'ship_name' => [
            'ship', 'vessel', 'mv', 'm v', 'm.v', 'm/v', 'vessel name',
            'ship name', 'mv vessel', 'm v vessel', 'm.v vessel', 'name of vessel',
            'vessels', 'ships', 'vessel names', 'fleet vessel',
        ],
        'requisition_date' => [
            'requisition date', 'request date', 'required date', 'delivery date', 'req date',
            'rfq date', 'inquiry date', 'enquiry date', 'date of requisition', 'pr date',
            'date requested',
        ],
        'due_date' => [
            'due date', 'quotation due date', 'offer due date', 'bid due date', 'deadline',
            'quote due date', 'rfq due date', 'submission deadline', 'offer deadline',
            'bid deadline', 'pricing deadline',
        ],
        'currency' => [
            'currency', 'curr', 'quote currency', 'rfq currency', 'offer currency',
            'pricing currency', 'transaction currency', 'curr.', 'cur',
            'currency code', 'quotation currency', 'bid currency', 'tender currency',
            'quote curr', 'price currency', 'valuation currency', 'money',
            'commercial currency', 'billing currency', 'invoice currency',
            'curr code', 'cur code', 'offer curr', 'pricing curr', 'quote in',
            'quoted in', 'quoted currency', 'costing currency', 'purchase currency',
            'purchasing currency', 'monetary unit', 'settlement currency',
            'trading currency', 'pay currency', 'billing curr',
        ],
        'priority' => [
            'priority', 'urgency', 'priority level', 'urgency level', 'criticality',
            'severity', 'importance', 'required priority', 'service priority',
            'emergency level', 'urgency code', 'critical level', 'priority code',
            'response priority', 'procurement priority', 'request priority',
            'urgency status', 'service level', 'criticality level', 'emergency',
            'priority class', 'critical code', 'level of urgency', 'priority req',
            'supply priority', 'importance level', 'response level', 'service urgency',
        ],
        'status' => [
            'status', 'rfq status', 'request status', 'requisition status',
            'inquiry status', 'quote status',
        ],
        'general_notes' => [
            'general notes', 'notes', 'remarks', 'remark', 'comment', 'comments',
            'additional notes', 'additional remarks', 'special instructions',
            'requirement notes',
        ],
        'country' => [
            'country', 'delivery country', 'required country', 'destination country',
            'port country', 'delivery destination country', 'countries', 'destination countries',
            'country of delivery',
        ],
        'port' => [
            'port', 'delivery port', 'required delivery port', 'nearest port', 'port of delivery',
            'destination port', 'delivery destination port', 'discharge port', 'eta port',
            'required port', 'port destination', 'delivery location', 'ports', 'delivery ports',
            'destination ports',
        ],
    ];

    private const ITEM_FIELD_ALIASES = [
        'product_name' => [
            'product', 'product name', 'item', 'item name', 'part name', 'part description',
            'description', 'equipment name', 'service', 'service name', 'subject',
            'material', 'material description', 'item description', 'spare', 'spare part',
            'requested item', 'requested material', 'equipment', 'component', 'job description',
            'products', 'items', 'materials', 'spares', 'scope of work',
        ],
        'part_no' => [
            'part no', 'part number', 'mfg part', 'maker part', 'maker part no', 'pn', 'p n',
            'p/n', 'part ref', 'part reference', 'maker ref', 'maker reference',
            'article no', 'article number', 'catalog part no',
        ],
        'catalog_code' => [
            'catalog code', 'catalog no', 'catalog number', 'impa', 'issa', 'unitor', 'nitor',
            'impa issa unitor nitor code', 'code', 'item code',
            'catalogue code', 'catalogue no', 'catalogue number', 'reference code',
            'stock code', 'material code', 'impa code', 'issa code', 'unitor code',
            'nitor code', 'article code',
        ],
        'quantity' => [
            'qty', 'quantity', 'requested qty', 'request qty', 'req qty', 'order qty',
            'required qty', 'requested quantity', 'order quantity', 'qty req',
            'qty ordered', 'order qtty', 'quantities',
        ],
        'unit' => [
            'unit', 'uom', 'unit of measure', 'measure', 'u/m', 'uom code',
            'measuring unit', 'issue unit', 'units',
        ],
        'manufacturer' => [
            'manufacturer', 'maker', 'brand', 'vendor', 'make', 'makers', 'maker name',
            'manufacture', 'manufacturer name', 'brand name', 'supplier brand', 'manufacturers',
        ],
        'model_type' => [
            'model', 'type', 'model type', 'mfg model', 'mfg type', 'mfg model type',
            'model/type', 'equipment type', 'model no', 'type no', 'model number',
            'type number', 'machine type', 'engine type', 'plate type', 'models',
        ],
        'serial_number' => [
            'serial', 'serial no', 'serial number', 's n', 's/n', 'sr no', 'sr number',
            'plate no', 'plate number', 'engine no', 'engine number', 'equipment serial no',
            'serials',
        ],
        'rob' => [
            'rob', 'onboard', 'on board', 'stock on board', 'remaining on board',
            'qty on board', 'quantity on board', 'current stock', 'available stock',
            'remaining stock', 'balance onboard', 'balance on board', 'present stock',
            'stock balance', 'inventory onboard', 'inventory on board', 'on hand',
            'qty in stock', 'stock qty', 'remaining qty', 'onboard qty',
            'on board qty', 'available qty', 'inventory balance', 'stock onboard qty',
            'qty onboard', 'stock available', 'stock on hand', 'onboard balance',
            'rob qty', 'inventory on hand', 'stock remaining', 'existing stock',
            'current onboard stock', 'on hand qty', 'balance stock',
        ],
        'drawing_number' => [
            'drawing no', 'drawing number', 'drawing', 'drawing ref', 'drawing reference',
            'dwg no', 'dwg number', 'drg no', 'drg number', 'plan no', 'drawings',
        ],
        'quality' => [
            'quality', 'condition', 'specification', 'grade', 'item condition',
            'requested quality', 'preferred quality', 'quality level', 'material grade',
            'product condition', 'requested condition', 'item quality',
            'condition required', 'origin', 'genuine oem', 'quality requirement',
            'supply quality', 'requested origin', 'genuine/oem', 'oem/genuine',
            'quality req', 'quality spec', 'supply condition', 'source',
            'origin required', 'maker quality', 'item grade', 'item origin',
            'requested spec', 'requested grade', 'quality standard', 'item spec',
        ],
        'comments' => [
            'comments', 'comment', 'remarks', 'remark', 'notes', 'other information',
            'additional remarks', 'additional notes', 'purpose', 'usage', 'application',
            'observation', 'special instructions', 'requirement notes',
        ],
    ];

    private const UNIT_SYNONYMS = [
        'PCS' => ['pc', 'pcs', 'piece', 'pieces'],
        'EA' => ['ea', 'each'],
        'SET' => ['set', 'sets'],
        'KIT' => ['kit', 'kits'],
        'LOT' => ['lot', 'lots'],
        'PAIR' => ['pair', 'pairs', 'pr'],
        'PACK' => ['pack', 'packs', 'package'],
        'BOX' => ['box', 'boxes', 'bx'],
        'BAG' => ['bag', 'bags'],
        'ROLL' => ['roll', 'rolls'],
        'MTR' => ['mtr', 'meter', 'meters', 'metre', 'metres', 'm'],
        'CM' => ['cm', 'centimeter', 'centimeters'],
        'MM' => ['mm', 'millimeter', 'millimeters'],
        'KG' => ['kg', 'kilogram', 'kilograms'],
        'G' => ['g', 'gram', 'grams'],
        'TON' => ['ton', 'tons', 'tonne', 'tonnes'],
        'LTR' => ['ltr', 'liter', 'liters', 'litre', 'litres', 'l'],
        'ML' => ['ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres'],
        'GAL' => ['gal', 'gallon', 'gallons'],
        'DRUM' => ['drum', 'drums'],
        'CAN' => ['can', 'cans'],
        'TUBE' => ['tube', 'tubes'],
    ];

    private const QUALITY_SYNONYMS = [
        'genuine' => ['genuine', 'gen', 'original genuine', 'new genuine', 'genuine part', 'genuine supplied'],
        'oem' => ['oem', 'genuine oem', 'new oem', 'o e m', 'genuine/oem', 'oem/genuine', 'maker original'],
        'original' => ['original', 'orig', 'factory original', 'new original', 'unused original'],
        'compatible' => ['compatible', 'comp', 'aftermarket compatible'],
        'equivalent' => ['equivalent', 'equal'],
        'serviceable' => ['serviceable', 'svc', 'servicable', 'usable serviceable'],
        'reconditioned' => ['reconditioned', 'recon', 'repaired', 'overhauled', 'refurbished', 'service exchange', 'overhaul exchange'],
        'used' => ['used', 'second hand'],
        'surplus' => ['surplus', 'ex stock surplus', 'surplus stock'],
        'alternative' => ['alternative', 'alt', 'aftermarket', 'substitute', 'non genuine', 'replacement'],
    ];

    public function parse(UploadedFile $file, array $customAliases = []): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension === 'pdf') {
            $parsed = $this->extractPdfRows($file);
            $preview = $this->parseRows(
                $parsed['rows'],
                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Imported PDF',
                $file->getClientOriginalName(),
                'pdf',
                $customAliases
            );
            $preview['raw']['ocr_lines'] = $parsed['ocr_lines'];
            $preview['raw']['ocr_rows'] = $parsed['rows'];

            return $preview;
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $this->pickWorksheet($spreadsheet);
        $rows = $worksheet->toArray(null, true, true, false);
        $sourceType = in_array($extension, ['csv', 'xlsx', 'xls'], true) ? 'spreadsheet' : 'document';

        return $this->parseRows(
            $rows,
            $worksheet->getTitle(),
            $file->getClientOriginalName(),
            $sourceType,
            $customAliases
        );
    }

    private function extractPdfRows(UploadedFile $file): array
    {
        $python = $this->resolvePythonExecutable();
        $script = base_path('scripts/extract_pdf_rows.py');

        if (! is_file($script)) {
            throw new RuntimeException('PDF import helper script is missing.');
        }

        $process = new Process([$python, $script, $file->getRealPath()]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('We could not read this PDF file.');
        }

        $decoded = json_decode($process->getOutput(), true);

        if (! is_array($decoded) || ! is_array($decoded['rows'] ?? null)) {
            throw new RuntimeException('We could not extract readable rows from this PDF.');
        }

        return [
            'rows' => $decoded['rows'],
            'ocr_lines' => is_array($decoded['ocr_lines'] ?? null) ? $decoded['ocr_lines'] : [],
        ];
    }

    private function resolvePythonExecutable(): string
    {
        $candidates = array_filter([
            env('RFQ_PDF_PYTHON'),
            env('PYTHON_EXECUTABLE'),
            'C:\\Users\\rmust\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\python\\python.exe',
            'python',
            'python3',
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate === 'python' || $candidate === 'python3') {
                return $candidate;
            }

            if (is_file((string) $candidate)) {
                return (string) $candidate;
            }
        }

        throw new RuntimeException('Python runtime for PDF import was not found.');
    }

    public function parseRows(
        array $rows,
        string $sheetName = 'Imported Document',
        string $fileName = 'Imported Document',
        string $sourceType = 'spreadsheet',
        array $customAliases = []
    ): array
    {
        $customAliases = $this->prepareCustomAliasesForRows($rows, $customAliases);
        $generalAliases = $this->mergeAliases(self::GENERAL_FIELD_ALIASES, $customAliases['general'] ?? []);
        $itemAliases = $this->mergeAliases(self::ITEM_FIELD_ALIASES, $customAliases['items'] ?? []);
        $customGeneralAliasMap = $this->buildCustomAliasMap($customAliases['general'] ?? []);
        $customItemAliasMap = $this->buildCustomAliasMap($customAliases['items'] ?? []);

        $rows = collect($rows)
            ->map(function ($row) {
                if (! is_array($row)) {
                    return [];
                }

                return array_values($row);
            })
            ->values()
            ->all();

        $header = $this->detectItemHeader($rows, $itemAliases, $customItemAliasMap);

        if ($header === null) {
            throw new RuntimeException('We could not detect the item header row in this file.');
        }

        $general = $this->extractGeneral(
            $rows,
            $header['row'],
            $sourceType,
            $generalAliases,
            $itemAliases,
            $customGeneralAliasMap
        );
        $itemExtraction = $this->extractItems($rows, $header['row'], $header['columns']);
        $items = $itemExtraction['items'];

        if ($items === []) {
            throw new RuntimeException('We could not extract any valid item rows from this file.');
        }

        $itemColumnConfidence = collect($header['columns'])
            ->map(fn ($field, $columnIndex) => [
                'source' => $this->stringValue($rows[$header['row']][$columnIndex] ?? ''),
                'target' => self::FIELD_LABELS[$field] ?? $field,
                'field' => $field,
                'score' => $header['matches'][$columnIndex]['score'] ?? 0,
                'level' => $this->confidenceLevel(
                    $header['matches'][$columnIndex]['score'] ?? 0,
                    'same_row',
                    $sourceType
                ),
            ])
            ->values()
            ->all();

        $reviewCount = collect($general['confidence'] ?? [])
            ->pluck('level')
            ->filter(fn ($level) => $level !== 'high')
            ->count()
            + collect($itemColumnConfidence)
                ->pluck('level')
                ->filter(fn ($level) => $level !== 'high')
                ->count();

        return [
            'summary' => [
                'file_name' => $fileName,
                'sheet_name' => $sheetName,
                'source_type' => $sourceType,
                'items_count' => count($items),
                'mapped_columns' => array_values($header['columns']),
                'review_count' => $reviewCount,
            ],
            'general' => array_filter($general['values'], fn ($value) => filled($value)),
            'mapping' => [
                'general' => $general['mappings'],
                'items' => $itemColumnConfidence,
            ],
            'confidence' => [
                'general' => $general['confidence'] ?? [],
                'items' => $itemColumnConfidence,
            ],
            'items' => $items,
            'raw' => [
                'applied_template_aliases' => $customAliases,
                'general_pairs' => $general['pairs'],
                'item_columns' => collect($header['columns'])
                    ->map(fn ($field, $columnIndex) => [
                        'index' => $columnIndex,
                        'source' => $this->stringValue($rows[$header['row']][$columnIndex] ?? ''),
                        'detected_field' => $field,
                    ])
                    ->values()
                    ->all(),
                'item_rows' => $itemExtraction['raw_rows'],
                'source_rows' => collect($rows)->take(200)->values()->all(),
            ],
        ];
    }

    public function prepareCustomAliasesForRows(array $rows, array $customAliases): array
    {
        $rows = collect($rows)
            ->map(fn ($row) => is_array($row) ? array_values($row) : [])
            ->values()
            ->all();

        $filterGroup = function (array $groupAliases, int $minimumMatchedFields) use ($rows): array {
            $matched = [];

            foreach ($groupAliases as $field => $aliases) {
                $aliasList = collect(is_array($aliases) ? $aliases : [$aliases])
                    ->map(fn ($alias) => trim((string) $alias))
                    ->filter()
                    ->values();

                if ($aliasList->isEmpty()) {
                    continue;
                }

                $fieldMatched = false;

                foreach ($rows as $row) {
                    foreach ($row as $cell) {
                        $cellValue = (string) $cell;

                        foreach ($aliasList as $alias) {
                            if ($this->isStrongAliasHit($cellValue, (string) $alias)) {
                                $fieldMatched = true;
                                break 3;
                            }
                        }
                    }
                }

                if ($fieldMatched) {
                    $matched[$field] = $aliasList->all();
                }
            }

            return count($matched) >= $minimumMatchedFields ? $matched : [];
        };

        return [
            'general' => $filterGroup((array) ($customAliases['general'] ?? []), 1),
            'items' => $filterGroup((array) ($customAliases['items'] ?? []), 2),
        ];
    }

    private function pickWorksheet(Spreadsheet $spreadsheet): Worksheet
    {
        return collect($spreadsheet->getWorksheetIterator())
            ->map(fn (Worksheet $sheet) => [
                'sheet' => $sheet,
                'score' => $sheet->getHighestDataRow() * Coordinate::columnIndexFromString($sheet->getHighestDataColumn()),
            ])
            ->sortByDesc('score')
            ->first()['sheet'] ?? $spreadsheet->getActiveSheet();
    }

    private function detectItemHeader(array $rows, array $itemAliases, array $customItemAliasMap = []): ?array
    {
        $best = null;

        foreach ($rows as $rowIndex => $row) {
            $columns = [];
            $score = 0;

            foreach ($row as $columnIndex => $value) {
                $match = $this->matchField((string) $value, $itemAliases);

                if ($match === null) {
                    continue;
                }

                if (in_array($match['field'], $columns, true)) {
                    continue;
                }

                $columns[$columnIndex] = $match['field'];
                $score += $match['score'] + $this->customAliasBonus((string) $value, $match['field'], $customItemAliasMap);
            }

            $uniqueFields = array_values($columns);

            if (count($uniqueFields) < 3) {
                continue;
            }

            if (! in_array('product_name', $uniqueFields, true) && ! in_array('quantity', $uniqueFields, true)) {
                continue;
            }

            if ($best === null || count($uniqueFields) > count($best['columns']) || (
                count($uniqueFields) === count($best['columns']) && $score > $best['score']
            )) {
                $best = [
                    'row' => $rowIndex,
                    'columns' => $columns,
                    'matches' => collect($columns)
                        ->mapWithKeys(function ($field, $columnIndex) use ($row, $itemAliases, $customItemAliasMap) {
                            $match = $this->matchField((string) ($row[$columnIndex] ?? ''), $itemAliases);

                            return [(int) $columnIndex => [
                                'field' => $field,
                                'score' => ($match['score'] ?? 0) + $this->customAliasBonus((string) ($row[$columnIndex] ?? ''), $field, $customItemAliasMap),
                            ]];
                        })
                        ->all(),
                    'score' => $score,
                ];
            }
        }

        return $best;
    }

    private function extractGeneral(
        array $rows,
        int $headerRowIndex,
        string $sourceType = 'spreadsheet',
        array $generalAliases = [],
        array $itemAliases = [],
        array $customGeneralAliasMap = []
    ): array
    {
        $general = [];
        $mappings = [];
        $pairs = [];
        $confidence = [];
        $scanCandidates = [];

        foreach ($rows as $rowIndex => $row) {
            if ($sourceType !== 'spreadsheet' && $rowIndex >= $headerRowIndex) {
                continue;
            }

            foreach ($row as $columnIndex => $value) {
                if (
                    $rowIndex >= $headerRowIndex
                    && $this->isExactFieldLabel((string) $value, $itemAliases)
                ) {
                    continue;
                }

                $match = $this->matchField((string) $value, $generalAliases);

                if ($match === null) {
                    continue;
                }

                $match['score'] += $this->customAliasBonus((string) $value, $match['field'], $customGeneralAliasMap);

                $priority = $rowIndex < $headerRowIndex ? 0 : 1;
                $scanCandidates[] = [
                    'row_index' => $rowIndex,
                    'column_index' => $columnIndex,
                    'value' => $value,
                    'match' => $match,
                    'priority' => $priority,
                ];
            }
        }

        usort($scanCandidates, function (array $left, array $right) {
            return [$left['priority'], $left['row_index'], $left['column_index']]
                <=> [$right['priority'], $right['row_index'], $right['column_index']];
        });

        foreach ($scanCandidates as $candidate) {
            $field = $candidate['match']['field'];

            if (filled($general[$field] ?? null)) {
                continue;
            }

            $resolved = $this->findGeneralValueNear(
                $rows,
                $candidate['row_index'],
                $candidate['column_index'],
                $generalAliases
            );

            if (! $resolved || ! filled($resolved['value'] ?? null)) {
                continue;
            }

            $sourceLabel = $this->stringValue($candidate['value']);
            $resolvedValue = $resolved['value'];
            $fieldValue = $this->extractMultilineAlignedGeneralValue(
                $field,
                $candidate['value'],
                $resolvedValue,
                $generalAliases
            );

            if (filled($fieldValue)) {
                $resolvedValue = $fieldValue;
            }

            $general[$field] = match ($field) {
                'requisition_date', 'due_date' => $this->normalizeDateValue($resolvedValue),
                'currency' => $this->normalizeCurrency($resolvedValue),
                'priority' => $this->normalizePriority($resolvedValue),
                'status' => $this->normalizeStatus($resolvedValue),
                'country' => $this->normalizeCountry($resolvedValue),
                default => $this->stringValue($resolvedValue),
            };
            $mappings[] = [
                'source' => $sourceLabel,
                'target' => self::FIELD_LABELS[$field] ?? $field,
                'field' => $field,
            ];
            $pairs[] = [
                'source' => $sourceLabel,
                'value' => $this->stringValue($resolvedValue),
                'detected_field' => $field,
            ];
            $confidence[$field] = [
                'source' => $sourceLabel,
                'score' => $candidate['match']['score'],
                'method' => $resolved['method'],
                'level' => $this->confidenceLevel($candidate['match']['score'], $resolved['method']),
            ];
        }

        return [
            'values' => $general,
            'mappings' => $mappings,
            'pairs' => $pairs,
            'confidence' => $confidence,
        ];
    }

    private function extractMultilineAlignedGeneralValue(
        string $field,
        mixed $rawLabelCell,
        mixed $rawValueCell,
        array $generalAliases
    ): string {
        $labelLines = $this->splitCellLines($rawLabelCell);
        $valueLines = $this->splitCellLines($rawValueCell, false);

        if (count($labelLines) <= 1 || count($valueLines) <= 1) {
            return '';
        }

        foreach ($labelLines as $index => $labelLine) {
            $match = $this->matchField($labelLine, $generalAliases);

            if (($match['field'] ?? null) !== $field) {
                continue;
            }

            return trim((string) ($valueLines[$index] ?? ''));
        }

        return '';
    }

    private function extractItems(array $rows, int $headerRowIndex, array $columnMap): array
    {
        $items = [];
        $rawRows = [];
        $emptyStreak = 0;

        for ($rowIndex = $headerRowIndex + 1; $rowIndex < count($rows); $rowIndex++) {
            $row = $rows[$rowIndex];
            $values = [];

            foreach ($columnMap as $columnIndex => $field) {
                $values[$field] = $row[$columnIndex] ?? null;
            }

            if (! $this->rowHasMeaningfulData($values)) {
                $emptyStreak++;

                if ($items !== [] && $emptyStreak >= 3) {
                    break;
                }

                continue;
            }

            $emptyStreak = 0;

            $item = [
                'product_name' => $this->stringValue($values['product_name'] ?? null),
                'part_no' => $this->stringValue($values['part_no'] ?? null),
                'catalog_code' => $this->stringValue($values['catalog_code'] ?? null),
                'quantity' => $this->numericStringValue($values['quantity'] ?? null),
                'unit' => $this->normalizeUnit($values['unit'] ?? null),
                'manufacturer' => $this->stringValue($values['manufacturer'] ?? null),
                'model_type' => $this->stringValue($values['model_type'] ?? null),
                'serial_number' => $this->stringValue($values['serial_number'] ?? null),
                'rob' => $this->numericStringValue($values['rob'] ?? null),
                'drawing_number' => $this->stringValue($values['drawing_number'] ?? null),
                'quality' => $this->normalizeQuality($values['quality'] ?? null),
                'comments' => $this->stringValue($values['comments'] ?? null),
                'files' => [],
            ];

            if (! filled($item['quantity']) && isset($values['quantity'])) {
                $item['quantity'] = $this->stringValue($values['quantity']);
            }

            if (! filled($item['rob']) && isset($values['rob'])) {
                $item['rob'] = $this->stringValue($values['rob']);
            }

            if (! filled($item['product_name'])) {
                $fallbackText = collect($values)
                    ->map(fn ($value) => $this->stringValue($value))
                    ->first(fn ($value) => filled($value));

                $item['product_name'] = $fallbackText;
            }

            if (! $this->rowHasMeaningfulData($item)) {
                continue;
            }

            $items[] = $item;
            $rawRows[] = [
                'values' => collect($columnMap)
                    ->mapWithKeys(fn ($field, $columnIndex) => [(string) $columnIndex => $this->stringValue($row[$columnIndex] ?? null)])
                    ->all(),
            ];
        }

        return [
            'items' => $items,
            'raw_rows' => $rawRows,
        ];
    }

    private function rowHasMeaningfulData(array $values): bool
    {
        return collect($values)
            ->map(fn ($value) => $this->stringValue($value))
            ->filter()
            ->isNotEmpty();
    }

    private function mergeAliases(array $baseAliases, array $customAliases): array
    {
        $merged = $baseAliases;

        foreach ($customAliases as $field => $aliases) {
            if (! array_key_exists($field, $merged)) {
                continue;
            }

            $incoming = collect(is_array($aliases) ? $aliases : [$aliases])
                ->map(fn ($alias) => trim((string) $alias))
                ->filter()
                ->values()
                ->all();

            if ($incoming === []) {
                continue;
            }

            $merged[$field] = collect([...$merged[$field], ...$incoming])
                ->map(fn ($alias) => trim((string) $alias))
                ->filter()
                ->unique(fn ($alias) => $this->normalizeLabel((string) $alias))
                ->values()
                ->all();
        }

        return $merged;
    }

    private function buildCustomAliasMap(array $customAliases): array
    {
        $map = [];

        foreach ($customAliases as $field => $aliases) {
            $values = collect(is_array($aliases) ? $aliases : [$aliases])
                ->map(fn ($alias) => trim((string) $alias))
                ->filter()
                ->values();

            if ($values->isEmpty()) {
                continue;
            }

            $map[$field] = [
                'normalized' => $values->map(fn ($alias) => $this->normalize((string) $alias))->filter()->values()->all(),
                'labels' => $values->map(fn ($alias) => $this->normalizeLabel((string) $alias))->filter()->values()->all(),
            ];
        }

        return $map;
    }

    private function customAliasBonus(string $rawValue, string $field, array $customAliasMap): int
    {
        $custom = $customAliasMap[$field] ?? null;

        if (! is_array($custom)) {
            return 0;
        }

        $value = $this->normalize($rawValue);
        $label = $this->normalizeLabel($rawValue);

        if ($value === '' && $label === '') {
            return 0;
        }

        if (in_array($value, $custom['normalized'] ?? [], true) || in_array($label, $custom['labels'] ?? [], true)) {
            return 40;
        }

        foreach (($custom['normalized'] ?? []) as $alias) {
            if ($alias !== '' && (str_contains($value, $alias) || str_contains($alias, $value))) {
                return 18;
            }
        }

        foreach (($custom['labels'] ?? []) as $alias) {
            if ($alias !== '' && (str_contains($label, $alias) || str_contains($alias, $label))) {
                return 18;
            }
        }

        return 0;
    }

    private function isStrongAliasHit(string $rawValue, string $alias): bool
    {
        $value = $this->normalize($rawValue);
        $label = $this->normalizeLabel($rawValue);
        $aliasValue = $this->normalize($alias);
        $aliasLabel = $this->normalizeLabel($alias);

        if ($value === '' || $aliasValue === '') {
            return false;
        }

        if ($value === $aliasValue || $label === $aliasLabel) {
            return true;
        }

        if (
            strlen($value) >= 4
            && strlen($aliasValue) >= 4
            && (str_contains($value, $aliasValue) || str_contains($aliasValue, $value))
        ) {
            return true;
        }

        return false;
    }

    private function matchField(string $rawValue, array $aliases): ?array
    {
        $value = $this->normalize($rawValue);
        $canonicalValue = $this->normalizeLabel($rawValue);

        if ($value === '') {
            return null;
        }

        $best = null;

        foreach ($aliases as $field => $fieldAliases) {
            foreach ($fieldAliases as $alias) {
                $score = max(
                    $this->matchScore($value, $this->normalize($alias)),
                    $this->matchScore($canonicalValue, $this->normalizeLabel($alias))
                );

                if ($score <= 0) {
                    continue;
                }

                if ($best === null || $score > $best['score']) {
                    $best = [
                        'field' => $field,
                        'score' => $score,
                    ];
                }
            }
        }

        return $best && $best['score'] >= 70 ? $best : null;
    }

    private function matchScore(string $value, string $alias): int
    {
        if ($value === $alias) {
            return 100;
        }

        if (
            strlen($value) >= 3
            && strlen($alias) >= 3
            && (str_contains($value, $alias) || str_contains($alias, $value))
        ) {
            return 88;
        }

        $valueTokens = collect(explode(' ', $value))->filter()->values();
        $aliasTokens = collect(explode(' ', $alias))->filter()->values();

        if ($aliasTokens->isNotEmpty() && $aliasTokens->every(fn ($token) => $valueTokens->contains($token))) {
            return 82;
        }

        similar_text($value, $alias, $percent);

        return $percent >= 82 ? 72 : 0;
    }

    private function findValueToRight(array $row, int $columnIndex, array $generalAliases): mixed
    {
        for ($i = $columnIndex + 1; $i < count($row); $i++) {
            $candidate = $row[$i] ?? null;
            $candidateString = $this->stringValue($candidate);

            if (! filled($candidateString)) {
                continue;
            }

            if ($this->isExactFieldLabel($candidateString, $generalAliases)) {
                break;
            }

            if (filled($candidateString)) {
                return $row[$i];
            }
        }

        return null;
    }

    private function splitCellLines(mixed $value, bool $filterEmpty = true): array
    {
        $string = (string) $value;

        if ($string === '') {
            return [];
        }

        $normalized = str_replace('_x000D_', "\n", $string);
        $parts = preg_split('/\r\n|\r|\n/', $normalized) ?: [];
        $parts = array_map(fn ($part) => trim((string) $part), $parts);

        if ($filterEmpty) {
            $parts = array_values(array_filter($parts, fn ($part) => $part !== ''));
        }

        return array_values($parts);
    }

    private function findGeneralValueNear(array $rows, int $rowIndex, int $columnIndex, array $generalAliases): ?array
    {
        $sameRowValue = $this->findValueToRight($rows[$rowIndex] ?? [], $columnIndex, $generalAliases);

        if (filled($sameRowValue)) {
            return [
                'value' => $sameRowValue,
                'method' => 'same_row',
            ];
        }

        for ($nextRowIndex = $rowIndex + 1; $nextRowIndex <= min(count($rows) - 1, $rowIndex + 3); $nextRowIndex++) {
            $row = $rows[$nextRowIndex] ?? [];

            for ($i = $columnIndex; $i <= min(count($row) - 1, $columnIndex + 8); $i++) {
                $candidate = $row[$i] ?? null;
                $candidateString = $this->stringValue($candidate);

                if (! filled($candidateString)) {
                    continue;
                }

                if ($this->isExactFieldLabel($candidateString, $generalAliases)) {
                    break;
                }

                return [
                    'value' => $candidate,
                    'method' => 'nearby_row',
                ];
            }
        }

        return null;
    }

    private function confidenceLevel(int $score, string $method = 'same_row', string $sourceType = 'spreadsheet'): string
    {
        $level = match (true) {
            $score >= 96 => 'high',
            $score >= 82 => 'medium',
            default => 'low',
        };

        if ($method !== 'same_row') {
            $level = match ($level) {
                'high' => 'medium',
                'medium' => 'low',
                default => 'low',
            };
        }

        return $level;
    }

    private function isExactFieldLabel(string $rawValue, array $aliases): bool
    {
        $value = $this->normalize($rawValue);
        $canonicalValue = $this->normalizeLabel($rawValue);

        if ($value === '') {
            return false;
        }

        foreach ($aliases as $fieldAliases) {
            foreach ($fieldAliases as $alias) {
                if (
                    $value === $this->normalize($alias)
                    || $canonicalValue === $this->normalizeLabel($alias)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeLabel(string $value): string
    {
        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return '';
        }

        $tokens = collect(explode(' ', $normalized))
            ->filter()
            ->map(function (string $token) {
                if (strlen($token) > 4 && str_ends_with($token, 'ies')) {
                    return substr($token, 0, -3).'y';
                }

                if (strlen($token) > 3 && str_ends_with($token, 's') && ! str_ends_with($token, 'ss')) {
                    return substr($token, 0, -1);
                }

                return $token;
            })
            ->implode(' ');

        return trim($tokens);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->replace(["\n", "\r"], ' ')
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => $this->stringValue($item))
                ->filter()
                ->implode(' ');
        }

        if (is_numeric($value)) {
            $string = (string) $value;

            return str_contains($string, '.') ? rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.') : $string;
        }

        return trim((string) $value);
    }

    private function numericStringValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
        }

        $string = str_replace(',', '.', preg_replace('/[^0-9,.\-]+/', '', (string) $value));

        return is_numeric($string) ? rtrim(rtrim(number_format((float) $string, 4, '.', ''), '0'), '.') : trim((string) $value);
    }

    private function normalizeDateValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return $this->stringValue($value);
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return $this->stringValue($value);
        }
    }

    private function normalizePriority(mixed $value): string
    {
        $normalized = $this->normalize((string) $value);

        return match (true) {
            $normalized === 'p1',
            $normalized === 'priority1',
            $normalized === 'priority 1',
            $normalized === 'urgent',
            $normalized === 'critical',
            $normalized === 'emergency',
            str_contains($normalized, 'critical'),
            str_contains($normalized, 'urgent'),
            str_contains($normalized, 'rush'),
            str_contains($normalized, 'immediate'),
            str_contains($normalized, 'asap'),
            str_contains($normalized, 'hot') => 'critical',
            $normalized === 'p2',
            $normalized === 'priority2',
            $normalized === 'priority 2',
            $normalized === 'high',
            str_contains($normalized, 'high'),
            str_contains($normalized, 'important'),
            str_contains($normalized, 'time sensitive') => 'high',
            $normalized === 'p4',
            $normalized === 'priority4',
            $normalized === 'priority 4',
            $normalized === 'low',
            str_contains($normalized, 'low'),
            str_contains($normalized, 'routine'),
            str_contains($normalized, 'minor') => 'low',
            $normalized === 'p3',
            $normalized === 'priority3',
            $normalized === 'priority 3',
            $normalized === 'normal',
            $normalized === 'medium',
            str_contains($normalized, 'normal'),
            str_contains($normalized, 'medium'),
            str_contains($normalized, 'standard'),
            str_contains($normalized, 'regular') => 'normal',
            default => '',
        };
    }

    private function normalizeCurrency(mixed $value): string
    {
        $normalized = $this->normalize((string) $value);

        return match (true) {
            $normalized === '$',
            str_contains($normalized, 'usd'),
            str_contains($normalized, 'us dollar'),
            str_contains($normalized, 'u s dollar'),
            str_contains($normalized, 'usdollar'),
            str_contains($normalized, 'american dollar'),
            $normalized === 'dollar' => 'USD',
            $normalized === '€',
            str_contains($normalized, 'eur'),
            str_contains($normalized, 'euro'),
            str_contains($normalized, 'euro currency') => 'EUR',
            str_contains($normalized, 'cny'),
            str_contains($normalized, 'rmb'),
            str_contains($normalized, 'yuan'),
            str_contains($normalized, 'renminbi'),
            str_contains($normalized, 'chinese yuan') => 'CNY',
            str_contains($normalized, 'aed'),
            str_contains($normalized, 'dirham'),
            str_contains($normalized, 'uae'),
            str_contains($normalized, 'uae dirham'),
            str_contains($normalized, 'emirati dirham') => 'AED',
            default => '',
        };
    }

    private function normalizeStatus(mixed $value): string
    {
        $normalized = $this->normalize((string) $value);

        return match (true) {
            str_contains($normalized, 'draft') => 'draft',
            str_contains($normalized, 'close'), str_contains($normalized, 'closed'), str_contains($normalized, 'cancel') => 'closed',
            str_contains($normalized, 'open'), str_contains($normalized, 'submit'), str_contains($normalized, 'active') => 'open',
            default => '',
        };
    }

    private function normalizeCountry(mixed $value): string
    {
        $string = $this->stringValue($value);

        return CountryNameResolver::resolve($string) ?? $string;
    }

    private function normalizeUnit(mixed $value): string
    {
        $normalized = $this->normalize((string) $value);

        foreach (self::UNIT_SYNONYMS as $target => $aliases) {
            $normalizedAliases = array_map(fn ($alias) => $this->normalize($alias), $aliases);

            if ($normalized === $this->normalize($target) || in_array($normalized, $normalizedAliases, true)) {
                return $target;
            }
        }

        return strtoupper($this->stringValue($value));
    }

    private function normalizeQuality(mixed $value): string
    {
        $normalized = $this->normalize((string) $value);

        foreach (self::QUALITY_SYNONYMS as $target => $aliases) {
            $normalizedAliases = array_map(fn ($alias) => $this->normalize($alias), $aliases);

            if ($normalized === $this->normalize($target) || in_array($normalized, $normalizedAliases, true)) {
                return $target;
            }
        }

        return '';
    }
}
