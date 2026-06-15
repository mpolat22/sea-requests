<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Port;
use App\Models\Rfq;
use App\Models\SupplierServiceListing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

const DEMO_PASSWORD = 'Rr220915Mm+7';

$now = now();

$buyers = [
    [
        'profile' => [
            'name' => 'Melis Atalay',
            'email' => 'melis.atalay.demo@spareparts.local',
            'company_name' => 'BlueWave Chartering & Procurement',
            'business_type' => 'Fleet Procurement',
            'phone' => '+90 532 410 22 41',
            'whatsapp_number' => '+90 532 410 22 41',
            'country' => 'Turkey',
            'countries' => 'Turkey, United Arab Emirates, Albania, Australia',
            'company_description' => 'Buyer-side marine procurement team handling planned maintenance, service attendance, and urgent spare parts sourcing for dry cargo and tanker fleets.',
            'company_overview' => 'BlueWave Chartering & Procurement coordinates RFQs for managed vessels trading in the Mediterranean, Middle East, and Black Sea. The team consolidates service requests, onboard defect lists, and planned spare procurement into a single buyer workflow.',
            'operating_regions' => 'Mediterranean, Middle East, Black Sea',
            'port_coverage' => 'Durres, Sarande, Jebel Ali, Fujairah, Baku',
            'service_country_codes' => ['TR', 'AL', 'AE', 'AU', 'AZ'],
            'company_address' => 'Atasehir, Istanbul, Turkey',
            'company_address_line' => 'Barbaros Mah. Begonya Sok. No:14',
            'company_city' => 'Istanbul',
            'company_district' => 'Atasehir',
            'company_neighborhood' => 'Barbaros',
            'company_state' => 'Istanbul',
            'company_postal_code' => '34746',
            'company_location_name' => 'BlueWave Operations Center',
            'company_latitude' => '40.990154',
            'company_longitude' => '29.109051',
            'registration_number' => 'BWCP-2026-441',
            'website_url' => 'https://bluewave-procurement.example.com',
            'landline_phone' => '+90 216 410 22 41',
            'contact_email' => 'ops@bluewave-procurement.example.com',
            'linkedin_url' => 'https://linkedin.com/company/bluewave-procurement',
            'instagram_url' => 'https://instagram.com/bluewave.procurement',
            'facebook_url' => 'https://facebook.com/bluewave.procurement',
            'locale' => 'en',
        ],
        'rfqs' => [
            [
                'reference_no' => 'BW-REQ-260520-001',
                'request_type' => 'service_request',
                'status' => 'submitted',
                'company_name' => 'BlueWave Chartering & Procurement',
                'ship_name' => 'MV Blue Horizon',
                'country_names' => ['Albania', 'United Arab Emirates'],
                'ports' => [
                    'Albania' => ['ALDRZ', 'ALSAR'],
                    'United Arab Emirates' => ['AEJEA'],
                ],
                'categories' => ['Deck Maintenance'],
                'subcategories' => ['Deck Hydroblasting'],
                'requisition_date' => $now->copy()->subHours(4)->toDateString(),
                'due_date' => $now->copy()->addDays(5)->toDateString(),
                'submitted_at' => $now->copy()->subHours(4),
                'priority' => 'critical',
                'general_notes' => 'Attendance is required during the vessel’s narrow berth window. Deck team will isolate the affected hatch coaming areas before service starts. Vendors should confirm manpower, jetting equipment, and coating touch-up readiness in one combined response.',
                'service_title' => 'Deck hydroblasting support for hatch coamings',
                'service_description' => 'BlueWave requires an experienced riding squad to perform controlled deck hydroblasting around hatch coamings and crane landing areas while the vessel remains alongside. The team should mobilize with compact water-jetting equipment, surface preparation tools, and a supervisor who can coordinate directly with the chief officer. Scope includes safe area isolation, steel surface cleaning, and daily progress reporting before repainting starts.',
            ],
            [
                'reference_no' => 'BW-REQ-260520-002',
                'request_type' => 'service_request',
                'status' => 'submitted',
                'company_name' => 'BlueWave Chartering & Procurement',
                'ship_name' => 'MT Silver Aster',
                'country_names' => ['Albania', 'United Arab Emirates'],
                'ports' => [
                    'Albania' => ['ALDRZ'],
                    'United Arab Emirates' => ['AEFJR'],
                ],
                'categories' => ['Documentation & Compliance'],
                'subcategories' => ['Class Certification'],
                'requisition_date' => $now->copy()->subHours(13)->toDateString(),
                'due_date' => $now->copy()->addDays(7)->toDateString(),
                'submitted_at' => $now->copy()->subHours(13),
                'priority' => 'high',
                'general_notes' => 'Attendance must include a document review session before vessel arrival. Class remarks and open recommendations should be reviewed line by line with the superintendent before final close-out.',
                'service_title' => 'Class readiness audit before attendance window',
                'service_description' => 'A documentation specialist is required to review class certificates, safety manuals, and open statutory remarks before the tanker enters its next attendance window. The vendor should verify that renewal records, drill reports, and machinery log extracts are aligned, then prepare a compact readiness report for the superintendent. Practical focus is on eliminating class-survey delays and ensuring certificates can be presented without last-minute corrections.',
            ],
            [
                'reference_no' => 'BW-REQ-260520-003',
                'request_type' => 'service_request',
                'status' => 'closed',
                'company_name' => 'BlueWave Chartering & Procurement',
                'ship_name' => 'MV Coral Stream',
                'country_names' => ['Australia', 'Azerbaijan'],
                'ports' => [
                    'Australia' => ['AUBNE', 'AUMEL'],
                    'Azerbaijan' => ['AZBAK'],
                ],
                'categories' => ['Environmental & Waste Management'],
                'subcategories' => ['Ballast Water Compliance'],
                'requisition_date' => $now->copy()->subDays(5)->toDateString(),
                'due_date' => $now->copy()->subDay()->toDateString(),
                'submitted_at' => $now->copy()->subDays(5)->addHours(2),
                'priority' => 'normal',
                'general_notes' => 'This request was kept for audit trail after the due date passed. Findings are expected to remain visible in the buyer dashboard but new offers should not be accepted anymore.',
                'service_title' => 'Ballast water compliance review and sampling',
                'service_description' => 'The vessel required a short-notice ballast water compliance review covering treatment logs, calibration records, and on-site sampling readiness ahead of a PSC-sensitive voyage segment. The attending vendor was expected to validate reporting discipline, sample-point access, and crew familiarity with bypass prevention routines. This request has now closed, but it remains useful as a realistic historical example of a time-bound environmental attendance.',
            ],
            [
                'reference_no' => 'BW-REQ-260520-004',
                'request_type' => 'spare_parts',
                'status' => 'submitted',
                'company_name' => 'BlueWave Chartering & Procurement',
                'ship_name' => 'MV Blue Horizon',
                'country_names' => ['Albania', 'United Arab Emirates', 'Argentina'],
                'ports' => [
                    'Albania' => ['ALDRZ'],
                    'United Arab Emirates' => ['AEJEA', 'AEDXB'],
                    'Argentina' => ['ARBUE'],
                ],
                'categories' => ['Electrical & Automation'],
                'subcategories' => ['Motor Rewinding'],
                'requisition_date' => $now->copy()->subHours(7)->toDateString(),
                'due_date' => $now->copy()->addDays(6)->toDateString(),
                'submitted_at' => $now->copy()->subHours(7),
                'priority' => 'high',
                'general_notes' => 'Parts are intended for scheduled crane motor servicing during cargo downtime. Vendors may quote alternatives only if dimensional equivalence and insulation class are clearly documented.',
                'items' => [
                    [
                        'product_name' => 'Crane slewing motor rewinding kit, 440V / 60Hz',
                        'part_no' => 'CRN-MTR-RWK-440-60',
                        'quantity' => 2,
                        'unit' => 'SET',
                        'manufacturer' => 'Asea Marine',
                        'model_type' => 'SM-440R',
                        'serial_number' => null,
                        'catalog_code' => 'AM-CRN-2041',
                        'rob' => '0',
                        'drawing_number' => 'DWG-CRN-18A',
                        'quality' => null,
                        'comments' => 'Complete rewinding consumable pack for one overhaul cycle per motor.',
                    ],
                    [
                        'product_name' => 'Insulation varnish, marine grade class H',
                        'part_no' => 'VAR-H-5L',
                        'quantity' => 6,
                        'unit' => 'PCS',
                        'manufacturer' => 'VoltShield',
                        'model_type' => 'VH-5000',
                        'serial_number' => null,
                        'catalog_code' => 'VS-1138',
                        'rob' => '1',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'Use for final dip coating after rewind and bake-out.',
                    ],
                    [
                        'product_name' => 'Bearing set for crane motor shaft assembly',
                        'part_no' => 'BRG-6208-2RS-M',
                        'quantity' => 8,
                        'unit' => 'PCS',
                        'manufacturer' => 'SKF',
                        'model_type' => '6208 2RS',
                        'serial_number' => null,
                        'catalog_code' => 'SKF-6208M',
                        'rob' => '2',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'Quote genuine or class-equivalent only.',
                    ],
                    [
                        'product_name' => 'Motor terminal block replacement kit',
                        'part_no' => 'TBK-CRN-16',
                        'quantity' => 4,
                        'unit' => 'SET',
                        'manufacturer' => 'MarElec',
                        'model_type' => 'TB-16',
                        'serial_number' => null,
                        'catalog_code' => 'ME-44016',
                        'rob' => '0',
                        'drawing_number' => 'ELEC-CRN-TB',
                        'quality' => null,
                        'comments' => 'Heat-resistant ceramic base preferred.',
                    ],
                    [
                        'product_name' => 'Rotor balancing shim assortment',
                        'part_no' => 'RBS-ASSY-01',
                        'quantity' => 3,
                        'unit' => 'BOX',
                        'manufacturer' => 'DynAlign',
                        'model_type' => 'Balance Pro',
                        'serial_number' => null,
                        'catalog_code' => 'DA-201',
                        'rob' => '0',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'Required for workshop balancing after rewind completion.',
                    ],
                ],
            ],
            [
                'reference_no' => 'BW-REQ-260520-005',
                'request_type' => 'spare_parts',
                'status' => 'submitted',
                'company_name' => 'BlueWave Chartering & Procurement',
                'ship_name' => 'MT Silver Aster',
                'country_names' => ['Albania', 'United Arab Emirates', 'Australia', 'Argentina'],
                'ports' => [
                    'Albania' => ['ALDRZ'],
                    'United Arab Emirates' => ['AEFJR'],
                    'Australia' => ['AUBNE'],
                    'Argentina' => ['ARBHI'],
                ],
                'categories' => ['Engine Room Cleaning'],
                'subcategories' => ['Boiler Tube Cleaning'],
                'requisition_date' => $now->copy()->subDay()->toDateString(),
                'due_date' => $now->copy()->addDays(3)->toDateString(),
                'submitted_at' => $now->copy()->subDay()->addHours(1),
                'priority' => 'critical',
                'general_notes' => 'Urgent replenishment is needed before the next boiler wash cycle. Fast delivery and clear stock confirmation will be prioritized over long alternative lists.',
                'items' => [
                    [
                        'product_name' => 'Soot blower nozzle assembly, alloy steel',
                        'part_no' => 'SBN-A45-220',
                        'quantity' => 12,
                        'unit' => 'PCS',
                        'manufacturer' => 'Combustech',
                        'model_type' => 'A45',
                        'serial_number' => null,
                        'catalog_code' => 'CT-8820',
                        'rob' => '4',
                        'drawing_number' => 'BLR-SB-220',
                        'quality' => null,
                        'comments' => 'For auxiliary boiler soot blowing line.',
                    ],
                    [
                        'product_name' => 'Boiler tube cleaning brush, medium stiffness',
                        'part_no' => 'BTCB-65M',
                        'quantity' => 24,
                        'unit' => 'PCS',
                        'manufacturer' => 'FurnaceWorks',
                        'model_type' => '65M',
                        'serial_number' => null,
                        'catalog_code' => 'FW-65M',
                        'rob' => '8',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'Use for routine tube-side cleaning.',
                    ],
                    [
                        'product_name' => 'Flexible lance hose set for boiler cleaning gun',
                        'part_no' => 'LHS-10BAR-20M',
                        'quantity' => 4,
                        'unit' => 'SET',
                        'manufacturer' => 'SteamSafe',
                        'model_type' => '20M Flex',
                        'serial_number' => null,
                        'catalog_code' => 'SS-20F',
                        'rob' => '1',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'Each set with couplings and pressure tags.',
                    ],
                    [
                        'product_name' => 'Tube-end inspection mirror kit',
                        'part_no' => 'TIM-300',
                        'quantity' => 3,
                        'unit' => 'SET',
                        'manufacturer' => 'Inspector Marine',
                        'model_type' => '300',
                        'serial_number' => null,
                        'catalog_code' => 'IM-300',
                        'rob' => '0',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'For post-cleaning visual verification.',
                    ],
                ],
            ],
            [
                'reference_no' => 'BW-REQ-260520-006',
                'request_type' => 'spare_parts',
                'status' => 'closed',
                'company_name' => 'BlueWave Chartering & Procurement',
                'ship_name' => 'MV Coral Stream',
                'country_names' => ['Albania', 'Australia', 'Argentina', 'Azerbaijan'],
                'ports' => [
                    'Albania' => ['ALVOA'],
                    'Australia' => ['AUMEL'],
                    'Argentina' => ['ARBUE'],
                    'Azerbaijan' => ['AZBAK'],
                ],
                'categories' => ['Fabrication'],
                'subcategories' => ['Hull Steel Renewal'],
                'requisition_date' => $now->copy()->subDays(4)->toDateString(),
                'due_date' => $now->copy()->subDays(2)->toDateString(),
                'submitted_at' => $now->copy()->subDays(4)->addHours(3),
                'priority' => 'normal',
                'general_notes' => 'Procurement window has expired, but the RFQ remains available as a closed sample with realistic steel repair content and supplier coverage.',
                'items' => [
                    [
                        'product_name' => 'AH36 steel insert plate 12mm cut-to-size',
                        'part_no' => 'AH36-12-INS',
                        'quantity' => 18,
                        'unit' => 'PCS',
                        'manufacturer' => 'Marine Steel Hub',
                        'model_type' => 'AH36',
                        'serial_number' => null,
                        'catalog_code' => 'MSH-AH36-12',
                        'rob' => '0',
                        'drawing_number' => 'HULL-RNW-12A',
                        'quality' => null,
                        'comments' => 'Dimensions will follow class-approved repair sketch.',
                    ],
                    [
                        'product_name' => 'Ceramic backing strip for vertical weld seams',
                        'part_no' => 'CBS-25M',
                        'quantity' => 30,
                        'unit' => 'BOX',
                        'manufacturer' => 'WeldSupport',
                        'model_type' => '25M',
                        'serial_number' => null,
                        'catalog_code' => 'WS-25M',
                        'rob' => '5',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'Required for shell and hopper plating renewal.',
                    ],
                    [
                        'product_name' => 'Ultra-low hydrogen welding electrode E7018',
                        'part_no' => 'E7018-4.0',
                        'quantity' => 80,
                        'unit' => 'KG',
                        'manufacturer' => 'ArcMarine',
                        'model_type' => 'E7018',
                        'serial_number' => null,
                        'catalog_code' => 'AM-7018',
                        'rob' => '10',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'Class-approved brand only.',
                    ],
                ],
            ],
        ],
    ],
    [
        'profile' => [
            'name' => 'Kaan Erdem',
            'email' => 'kaan.erdem.demo@spareparts.local',
            'company_name' => 'Northstar Fleet Procurement',
            'business_type' => 'Ship Management',
            'phone' => '+90 533 782 14 63',
            'whatsapp_number' => '+90 533 782 14 63',
            'country' => 'Turkey',
            'countries' => 'Turkey, Australia, Argentina, Azerbaijan, United Arab Emirates',
            'company_description' => 'Central buyer account for technical attendance, vessel consumables, class-prep services, and voyage-critical marine supply requests.',
            'company_overview' => 'Northstar Fleet Procurement manages RFQs for mixed bulk and project cargo vessels. The buyer team focuses on service attendance, port-specific technical support, and coordinated sourcing from approved marine vendors across selected regions.',
            'operating_regions' => 'Indian Ocean, Far East, South America',
            'port_coverage' => 'Brisbane, Melbourne, Buenos Aires, Baku, Abu Dhabi',
            'service_country_codes' => ['TR', 'AU', 'AR', 'AZ', 'AE'],
            'company_address' => 'Kadikoy, Istanbul, Turkey',
            'company_address_line' => 'Kozyatagi Mah. Cinar Sok. No:22',
            'company_city' => 'Istanbul',
            'company_district' => 'Kadikoy',
            'company_neighborhood' => 'Kozyatagi',
            'company_state' => 'Istanbul',
            'company_postal_code' => '34742',
            'company_location_name' => 'Northstar Procurement Office',
            'company_latitude' => '40.974359',
            'company_longitude' => '29.101531',
            'registration_number' => 'NSFP-2026-208',
            'website_url' => 'https://northstar-procurement.example.com',
            'landline_phone' => '+90 216 782 14 63',
            'contact_email' => 'fleet@northstar-procurement.example.com',
            'linkedin_url' => 'https://linkedin.com/company/northstar-fleet-procurement',
            'instagram_url' => 'https://instagram.com/northstar.fleet',
            'facebook_url' => 'https://facebook.com/northstarfleetprocurement',
            'locale' => 'en',
        ],
        'rfqs' => [
            [
                'reference_no' => 'NS-REQ-260520-001',
                'request_type' => 'service_request',
                'status' => 'submitted',
                'company_name' => 'Northstar Fleet Procurement',
                'ship_name' => 'MV North Ember',
                'country_names' => ['Australia', 'Argentina', 'Azerbaijan'],
                'ports' => [
                    'Australia' => ['AUBNE', 'AUMEL'],
                    'Argentina' => ['ARBUE'],
                    'Azerbaijan' => ['AZBAK'],
                ],
                'categories' => ['Hull Cleaning'],
                'subcategories' => ['Propeller Polishing'],
                'requisition_date' => $now->copy()->subHours(2)->toDateString(),
                'due_date' => $now->copy()->addDays(4)->toDateString(),
                'submitted_at' => $now->copy()->subHours(2),
                'priority' => 'high',
                'general_notes' => 'Quotation should include diving team readiness, polishing grit specification, and expected performance reporting after completion.',
                'service_title' => 'Propeller polishing before Pacific ballast leg',
                'service_description' => 'Northstar is arranging a short maintenance window for propeller polishing before the vessel departs on a long ballast passage. The selected vendor should provide a diving team, underwater photo report, and rough fouling assessment to support fuel-performance review by the superintendent. Preference will be given to teams that can mobilize with minimal berth delay and issue a concise before-and-after condition summary immediately after completion.',
            ],
            [
                'reference_no' => 'NS-REQ-260520-002',
                'request_type' => 'service_request',
                'status' => 'submitted',
                'company_name' => 'Northstar Fleet Procurement',
                'ship_name' => 'MV North Ember',
                'country_names' => ['Australia', 'Argentina', 'Azerbaijan'],
                'ports' => [
                    'Australia' => ['AUBNE'],
                    'Argentina' => ['ARBHI'],
                    'Azerbaijan' => ['AZBAK'],
                ],
                'categories' => ['Hold Cleaning'],
                'subcategories' => ['Robotics'],
                'requisition_date' => $now->copy()->subHours(19)->toDateString(),
                'due_date' => $now->copy()->addDays(6)->toDateString(),
                'submitted_at' => $now->copy()->subHours(19),
                'priority' => 'critical',
                'general_notes' => 'This request is for grain-trade preparation. The vendor should confirm robotic coverage capability, crew interface method, and tank-top sludge handling plan.',
                'service_title' => 'Robotic hold cleaning before grain inspection',
                'service_description' => 'A robotic hold-cleaning team is required to prepare cargo holds for grain inspection on short notice. The assignment includes hold-side residue removal, ladder and stool-space detail cleaning, and a documented final readiness statement for surveyor presentation. Vendors should explain their robotic equipment footprint, power requirement, and the process they use to coordinate safely with ship staff during hatch-by-hatch cleaning progress.',
            ],
            [
                'reference_no' => 'NS-REQ-260520-003',
                'request_type' => 'service_request',
                'status' => 'closed',
                'company_name' => 'Northstar Fleet Procurement',
                'ship_name' => 'MV Iron Crest',
                'country_names' => ['Australia', 'Azerbaijan'],
                'ports' => [
                    'Australia' => ['AUMEL'],
                    'Azerbaijan' => ['AZBAK'],
                ],
                'categories' => ['Inspection'],
                'subcategories' => ['Cargo Hold & Hatch Inspection'],
                'requisition_date' => $now->copy()->subDays(6)->toDateString(),
                'due_date' => $now->copy()->subDays(2)->toDateString(),
                'submitted_at' => $now->copy()->subDays(6)->addHours(4),
                'priority' => 'normal',
                'general_notes' => 'The attendance window is closed, but the request is intentionally left visible as a realistic historical inspection example.',
                'service_title' => 'Cargo hold and hatch integrity inspection',
                'service_description' => 'The vessel required an external inspection team to assess cargo hold coatings, hatch sealing lines, and water-ingress risk indicators before loading high-value steel cargo. The scope covered visual condition reporting, hatch-side observations, and a practical recommendation list for any sealing weaknesses that should be corrected before terminal acceptance. This closed request remains useful as a sample of a detailed, survey-focused buyer requirement.',
            ],
            [
                'reference_no' => 'NS-REQ-260520-004',
                'request_type' => 'spare_parts',
                'status' => 'submitted',
                'company_name' => 'Northstar Fleet Procurement',
                'ship_name' => 'MV North Ember',
                'country_names' => ['Azerbaijan'],
                'ports' => [
                    'Azerbaijan' => ['AZBAK'],
                ],
                'categories' => ['Marine Supply'],
                'subcategories' => ['Marine Spare Parts Supply'],
                'requisition_date' => $now->copy()->subHours(5)->toDateString(),
                'due_date' => $now->copy()->addDays(5)->toDateString(),
                'submitted_at' => $now->copy()->subHours(5),
                'priority' => 'critical',
                'general_notes' => 'The vessel is consolidating one urgent package for purifier and compressor support spares. Split deliveries can be accepted only if all items are confirmed ex-stock.',
                'items' => [
                    [
                        'product_name' => 'Fuel oil purifier seal kit complete',
                        'part_no' => 'FOP-SK-900C',
                        'quantity' => 3,
                        'unit' => 'SET',
                        'manufacturer' => 'Alfa Laval',
                        'model_type' => 'MOPX 309',
                        'serial_number' => null,
                        'catalog_code' => 'AL-309-SK',
                        'rob' => '0',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'Complete overhaul seal kit for one purifier unit.',
                    ],
                    [
                        'product_name' => 'Starting air compressor discharge valve set',
                        'part_no' => 'SAC-DV-42',
                        'quantity' => 4,
                        'unit' => 'PCS',
                        'manufacturer' => 'Sperre',
                        'model_type' => 'HV2/220',
                        'serial_number' => null,
                        'catalog_code' => 'SP-DV42',
                        'rob' => '1',
                        'drawing_number' => 'AIR-CMP-DV',
                        'quality' => null,
                        'comments' => 'Quote complete valve plate with springs and gaskets.',
                    ],
                    [
                        'product_name' => 'Purifier bowl lock ring tool set',
                        'part_no' => 'BLR-TOOL-309',
                        'quantity' => 1,
                        'unit' => 'SET',
                        'manufacturer' => 'Alfa Laval',
                        'model_type' => 'Service Tool',
                        'serial_number' => null,
                        'catalog_code' => 'AL-TL-309',
                        'rob' => '0',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'Workshop-grade tool set required with carrying case.',
                    ],
                    [
                        'product_name' => 'LO purifier gravity disc assortment',
                        'part_no' => 'GD-ASSY-LO',
                        'quantity' => 2,
                        'unit' => 'SET',
                        'manufacturer' => 'Alfa Laval',
                        'model_type' => 'Disc Pack',
                        'serial_number' => null,
                        'catalog_code' => 'AL-GD-LO',
                        'rob' => '0',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'Include standard disc size range currently in use onboard.',
                    ],
                ],
            ],
            [
                'reference_no' => 'NS-REQ-260520-005',
                'request_type' => 'spare_parts',
                'status' => 'submitted',
                'company_name' => 'Northstar Fleet Procurement',
                'ship_name' => 'MV Iron Crest',
                'country_names' => ['United Arab Emirates'],
                'ports' => [
                    'United Arab Emirates' => ['AEAUH', 'AEDXB'],
                ],
                'categories' => ['Calibration & Testing Services'],
                'subcategories' => ['UTI'],
                'requisition_date' => $now->copy()->subHours(10)->toDateString(),
                'due_date' => $now->copy()->addDays(8)->toDateString(),
                'submitted_at' => $now->copy()->subHours(10),
                'priority' => 'high',
                'general_notes' => 'Quoted items should be suitable for tank measurement routines and delivered with calibration references where applicable.',
                'items' => [
                    [
                        'product_name' => 'Portable UTI tape sensor replacement kit',
                        'part_no' => 'UTI-SEN-200',
                        'quantity' => 5,
                        'unit' => 'PCS',
                        'manufacturer' => 'MMC',
                        'model_type' => 'UTI 2000',
                        'serial_number' => null,
                        'catalog_code' => 'MMC-UTI-SR',
                        'rob' => '1',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'Original sensor head preferred.',
                    ],
                    [
                        'product_name' => 'Interface paste for gauging operations',
                        'part_no' => 'IP-150G',
                        'quantity' => 12,
                        'unit' => 'PCS',
                        'manufacturer' => 'GaugeChem',
                        'model_type' => '150G',
                        'serial_number' => null,
                        'catalog_code' => 'GC-IP150',
                        'rob' => '3',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'For water interface detection during cargo calculations.',
                    ],
                    [
                        'product_name' => 'UTI battery and charger dock bundle',
                        'part_no' => 'UTI-PWR-BDL',
                        'quantity' => 2,
                        'unit' => 'SET',
                        'manufacturer' => 'MMC',
                        'model_type' => 'Charge Dock',
                        'serial_number' => null,
                        'catalog_code' => 'MMC-PWR-22',
                        'rob' => '0',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'Each set with charging cradle and spare battery pack.',
                    ],
                ],
            ],
            [
                'reference_no' => 'NS-REQ-260520-006',
                'request_type' => 'spare_parts',
                'status' => 'closed',
                'company_name' => 'Northstar Fleet Procurement',
                'ship_name' => 'MV Iron Crest',
                'country_names' => ['Albania', 'United Arab Emirates'],
                'ports' => [
                    'Albania' => ['ALSHG'],
                    'United Arab Emirates' => ['AEJEA'],
                ],
                'categories' => ['Deck Maintenance'],
                'subcategories' => ['Deck Painting'],
                'requisition_date' => $now->copy()->subDays(3)->toDateString(),
                'due_date' => $now->copy()->subDay()->toDateString(),
                'submitted_at' => $now->copy()->subDays(3)->addHours(6),
                'priority' => 'normal',
                'general_notes' => 'This closed RFQ remains visible to demonstrate how expired paint-material requests should still appear in buyer history and public request cards.',
                'items' => [
                    [
                        'product_name' => 'Marine epoxy deck topcoat, grey 20L',
                        'part_no' => 'EPX-DCK-GRY-20',
                        'quantity' => 14,
                        'unit' => 'PCS',
                        'manufacturer' => 'SeaCoat',
                        'model_type' => 'DeckGuard',
                        'serial_number' => null,
                        'catalog_code' => 'SC-DG20',
                        'rob' => '2',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'RAL tone confirmation required before dispatch.',
                    ],
                    [
                        'product_name' => 'Non-slip aggregate pack for deck coating',
                        'part_no' => 'NSA-25KG',
                        'quantity' => 18,
                        'unit' => 'BAG',
                        'manufacturer' => 'GripDeck',
                        'model_type' => '25KG',
                        'serial_number' => null,
                        'catalog_code' => 'GD-AG25',
                        'rob' => '4',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'For hatch-side traffic areas and walkways.',
                    ],
                    [
                        'product_name' => 'Heavy-duty deck paint roller frame set',
                        'part_no' => 'DPR-FR-SET',
                        'quantity' => 10,
                        'unit' => 'SET',
                        'manufacturer' => 'CoatTools',
                        'model_type' => 'Marine Set',
                        'serial_number' => null,
                        'catalog_code' => 'CT-DS10',
                        'rob' => '1',
                        'drawing_number' => null,
                        'quality' => null,
                        'comments' => 'Include spare sleeves and solvent-resistant handles.',
                    ],
                ],
            ],
        ],
    ],
];

$portIdsByCode = Port::query()->pluck('id', 'unlocode');
$portMetaByCode = Port::query()->get(['id', 'unlocode', 'port_name'])->keyBy('unlocode');

$supplierDeliveryKey = static fn (?int $sellerId, ?string $companyName): string => sprintf('%s::%s', (string) ($sellerId ?? 0), mb_strtolower(trim((string) $companyName)));

$destroyBuyerRfqs = static function (User $buyer): void {
    $buyer->rfqs()->with(['items.attachments', 'attachments', 'supplierRecipients'])->get()->each(function (Rfq $rfq) {
        $rfq->items->each(function ($item) {
            $item->attachments()->delete();
            $item->delete();
        });

        $rfq->attachments()->delete();
        $rfq->supplierRecipients()->delete();
        $rfq->delete();
    });
};

$matchRecipients = static function (array $countryNames, array $selectedPortCodes, array $categoryNames, array $subcategoryNames) use ($supplierDeliveryKey) {
    $selectedPorts = Port::query()->whereIn('unlocode', $selectedPortCodes)->get(['port_name', 'unlocode', 'country_code']);
    $selectedCountryCodes = $selectedPorts->pluck('country_code')->filter()->unique()->values()->all();

    return SupplierServiceListing::query()
        ->visible()
        ->with(['ports', 'seller'])
        ->when($categoryNames !== [], fn ($query) => $query->whereIn('category_name', $categoryNames))
        ->when($subcategoryNames !== [], fn ($query) => $query->whereIn('subcategory_name', $subcategoryNames))
        ->when($countryNames !== [], function ($query) use ($countryNames, $selectedCountryCodes) {
            $query->where(function ($countryScope) use ($countryNames, $selectedCountryCodes) {
                $countryScope
                    ->whereIn('country', $countryNames)
                    ->orWhereHas('ports', function ($portsQuery) use ($countryNames, $selectedCountryCodes) {
                        $portsQuery->where(function ($countryQuery) use ($countryNames, $selectedCountryCodes) {
                            $countryQuery->whereIn('country_name', $countryNames);

                            if ($selectedCountryCodes !== []) {
                                $countryQuery->orWhereIn('country_code', $selectedCountryCodes);
                            }
                        });
                    });
            });
        })
        ->when($selectedPorts->isNotEmpty(), function ($query) use ($selectedPorts) {
            $query->whereHas('ports', function ($portsQuery) use ($selectedPorts) {
                $portsQuery->where(function ($portScope) use ($selectedPorts) {
                    foreach ($selectedPorts as $port) {
                        $portScope->orWhere(function ($singlePortQuery) use ($port) {
                            $singlePortQuery->where('port_name', $port->port_name);

                            if ($port->unlocode) {
                                $singlePortQuery->orWhere('unlocode', $port->unlocode);
                            }
                        });
                    }
                });
            });
        })
        ->when($countryNames !== [] && $selectedPorts->isNotEmpty(), function ($query) use ($countryNames, $selectedCountryCodes) {
            $query->whereHas('ports', function ($portsQuery) use ($countryNames, $selectedCountryCodes) {
                $portsQuery->where(function ($countryQuery) use ($countryNames, $selectedCountryCodes) {
                    $countryQuery->whereIn('country_name', $countryNames);

                    if ($selectedCountryCodes !== []) {
                        $countryQuery->orWhereIn('country_code', $selectedCountryCodes);
                    }
                });
            });
        })
        ->orderBy('company_name')
        ->orderBy('category_name')
        ->orderBy('subcategory_name')
        ->get()
        ->unique(fn (SupplierServiceListing $listing) => $supplierDeliveryKey($listing->seller_id, $listing->company_name))
        ->values();
};

$createdUsers = [];
$createdRfqs = [];

DB::transaction(function () use (
    $buyers,
    $now,
    $portIdsByCode,
    $portMetaByCode,
    $destroyBuyerRfqs,
    $matchRecipients,
    &$createdUsers,
    &$createdRfqs
): void {
    foreach ($buyers as $buyerConfig) {
        $profile = $buyerConfig['profile'];

        $buyer = User::query()->firstOrNew(['email' => $profile['email']]);

        if ($buyer->exists) {
            $destroyBuyerRfqs($buyer);
        }

        $buyer->fill(array_merge($profile, [
            'role' => 'buyer',
            'approval_status' => 'approved',
            'approved_at' => $buyer->approved_at ?? $now,
            'email_verified_at' => $buyer->email_verified_at ?? $now,
            'password' => Hash::make(DEMO_PASSWORD),
        ]));
        $buyer->save();

        $createdUsers[] = [
            'name' => $buyer->name,
            'email' => $buyer->email,
            'company_name' => $buyer->company_name,
        ];

        foreach ($buyerConfig['rfqs'] as $definition) {
            $selectedCountries = $definition['country_names'];
            $selectedPortCodes = collect($definition['ports'])
                ->flatMap(fn (array $codes) => $codes)
                ->values();

            $storedPortsByCountry = [];

            foreach ($definition['ports'] as $country => $unlocodes) {
                $storedPortsByCountry[$country] = collect($unlocodes)
                    ->map(fn (string $code) => $portIdsByCode[$code] ?? null)
                    ->filter()
                    ->values()
                    ->all();
            }

            $firstCountry = $selectedCountries[0] ?? null;
            $firstPortCode = collect($definition['ports'][$firstCountry] ?? [])->first();
            $firstPortName = $firstPortCode ? ($portMetaByCode[$firstPortCode]->port_name ?? null) : null;

            $rfq = Rfq::query()->create([
                'buyer_id' => $buyer->id,
                'reference_no' => $definition['reference_no'],
                'company_name' => $definition['company_name'],
                'ship_name' => $definition['ship_name'],
                'request_type' => $definition['request_type'],
                'country_name' => $firstCountry,
                'port_name' => $firstPortName,
                'country_names' => $selectedCountries,
                'ports_by_country' => $storedPortsByCountry,
                'requisition_date' => $definition['requisition_date'],
                'due_date' => $definition['due_date'],
                'currency' => 'USD',
                'priority' => $definition['priority'],
                'status' => $definition['status'],
                'general_notes' => $definition['general_notes'],
                'service_title' => $definition['request_type'] === 'service_request' ? $definition['service_title'] : null,
                'service_description' => $definition['request_type'] === 'service_request' ? $definition['service_description'] : null,
                'items_count' => $definition['request_type'] === 'service_request'
                    ? 1
                    : count($definition['items']),
                'submitted_at' => $definition['submitted_at'],
            ]);

            if ($definition['request_type'] === 'spare_parts') {
                foreach ($definition['items'] as $index => $item) {
                    $rfq->items()->create(array_merge($item, [
                        'line_no' => $index + 1,
                    ]));
                }
            }

            $matchedRecipients = $matchRecipients(
                $selectedCountries,
                $selectedPortCodes->all(),
                $definition['categories'],
                $definition['subcategories']
            );

            foreach ($matchedRecipients as $recipient) {
                $primaryPort = $recipient->ports
                    ->first(fn ($port) => in_array($port->country_name, $selectedCountries, true))
                    ?? $recipient->ports->first();

                $rfq->supplierRecipients()->create([
                    'supplier_service_listing_id' => $recipient->id,
                    'seller_id' => $recipient->seller_id,
                    'company_name' => $recipient->company_name,
                    'category_name' => $recipient->category_name,
                    'subcategory_name' => $recipient->subcategory_name,
                    'country_name' => $recipient->country ?: $primaryPort?->country_name,
                    'port_name' => $primaryPort?->port_name,
                    'delivery_status' => 'delivered',
                    'queued_at' => $definition['submitted_at']->copy()->addMinutes(2),
                    'delivered_at' => $definition['submitted_at']->copy()->addMinutes(7),
                    'delivery_attempts' => 1,
                ]);
            }

            $rfq->forceFill([
                'created_at' => $definition['submitted_at'],
                'updated_at' => $definition['submitted_at']->copy()->addMinutes(12),
            ])->saveQuietly();

            $createdRfqs[] = [
                'reference_no' => $rfq->reference_no,
                'buyer_email' => $buyer->email,
                'request_type' => $rfq->request_type,
                'status' => $rfq->status,
                'recipients' => $matchedRecipients->count(),
            ];
        }
    }
});

echo "Created buyers:\n";
foreach ($createdUsers as $user) {
    echo "- {$user['name']} | {$user['email']} | {$user['company_name']}\n";
}

echo "\nCreated RFQs:\n";
foreach ($createdRfqs as $rfq) {
    echo "- {$rfq['reference_no']} | {$rfq['buyer_email']} | {$rfq['request_type']} | {$rfq['status']} | recipients={$rfq['recipients']}\n";
}

echo "\nShared password: " . DEMO_PASSWORD . "\n";
