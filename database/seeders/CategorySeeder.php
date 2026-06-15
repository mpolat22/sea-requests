<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'name' => 'Calibration & Testing Services',
                'subcategories' => ['Pressure Gauge', 'UTI'],
            ],
            [
                'name' => 'Consultancy & Training',
                'subcategories' => [
                    'Cargo Operations Consultancy',
                    'Cleaning Training & Certification',
                    'Port Cost Optimization',
                    'Remote Supervision',
                    'Risk Assessment',
                    'Supercargo Services',
                    'Surveyor Training',
                ],
            ],
            [
                'name' => 'Crew & Manpower',
                'subcategories' => ['Port Captains', 'Supercargo', 'Surveyors'],
            ],
            [
                'name' => 'Deck Maintenance',
                'subcategories' => [
                    'Deck Hydroblasting',
                    'Deck Painting',
                    'Deck Washing',
                    'Hull Painting',
                    'Ship Name Change',
                ],
            ],
            [
                'name' => 'Documentation & Compliance',
                'subcategories' => [
                    'Charter Party / Cargo Readiness',
                    'Class Certification',
                    'Crew Certificates',
                    'Flag Registration',
                    'ISPS / MLC Compliance',
                    'Safety Management Documents',
                    'Vessel Insurance & P&I',
                ],
            ],
            [
                'name' => 'Drug & Alcohol Testing',
                'subcategories' => ['Health Certificates', 'Onboard Medicals'],
            ],
            [
                'name' => 'Drydock Support',
                'subcategories' => ['Pre DD Preparation'],
            ],
            [
                'name' => 'Electrical & Automation',
                'subcategories' => ['Motor Rewinding', 'Power Panel Cleaning', 'Propeller Shaft Works'],
            ],
            [
                'name' => 'Engine Room Cleaning',
                'subcategories' => ['Bilge Cleaning', 'Boiler Tube Cleaning'],
            ],
            [
                'name' => 'Environmental & Waste Management',
                'subcategories' => [
                    'Ballast Water Compliance',
                    'Carbon Reporting & ESG Services',
                    'Emission Control Systems',
                    'Garbage Disposal (Waste)',
                    'Oily Water Treatment',
                    'Sludge Disposal',
                ],
            ],
            [
                'name' => 'Fabrication',
                'subcategories' => [
                    'Deck Structural Jobs',
                    'Hull Steel Renewal',
                    'Piping Fabrication',
                    'Tank Structural Tank',
                ],
            ],
            [
                'name' => 'Fresh Water Supply',
                'subcategories' => [],
            ],
            [
                'name' => 'Garbage Disposal',
                'subcategories' => [
                    'Engine Room Soot Disposal',
                    'General Garbage Disposal',
                    'Medicine Disposal',
                ],
            ],
            [
                'name' => 'Hold Cleaning',
                'subcategories' => ['Riding Crew', 'Robotics', 'Rope Squad', 'Shore Gang'],
            ],
            [
                'name' => 'Hull Cleaning',
                'subcategories' => [
                    'NZ/AUS Compliance',
                    'Propeller Polishing',
                    'Rudder Cleaning',
                    'Underwater Hull Cleaning',
                    'Underwater Hull Cleaning Inspection',
                ],
            ],
            [
                'name' => 'Hydraulic Repairs',
                'subcategories' => [],
            ],
            [
                'name' => 'Inspection',
                'subcategories' => [
                    'Cargo Hold & Hatch Inspection',
                    'Gas-Free Certification',
                    'Hull & Machinery Survey',
                    'P&I / Class Survey Coordination',
                    'Pre-Purchase Inspection',
                    'Ultrasonic Thickness Measurement',
                ],
            ],
            [
                'name' => 'Marine Supply',
                'subcategories' => ['Marine Spare Parts Supply'],
            ],
            [
                'name' => 'Marine Technology & Digital',
                'subcategories' => [
                    'Cleaning Performance Dashboards',
                    'Digital Certificates & Documentation',
                    'IoT Vessel Monitoring',
                    'Marine Software Solutions',
                    'Predictive Maintenance Tools',
                    'Robotics & Automation',
                    'Voyage Data Analysis',
                ],
            ],
            [
                'name' => 'Mechanical Repair',
                'subcategories' => [
                    'Cabin Stores Supply',
                    'Engine Repair',
                    'Pumps Repair',
                    'Safety Equipment Supply',
                    'Technical Stores',
                    'Valves Repair',
                ],
            ],
            [
                'name' => 'Offshore & Specialized',
                'subcategories' => [
                    'Buoy Maintenance',
                    'Diving & ROV Services',
                    'FPSO Support',
                    'Jack-Up Rig Services',
                    'Mooring Installation',
                    'Pipeline Inspection',
                    'Subsea Engineering',
                ],
            ],
            [
                'name' => 'Riding Repair',
                'subcategories' => ['Fabrication', 'Pipe Fitters', 'Running Squad', 'Welding'],
            ],
            [
                'name' => 'Ship Agency',
                'subcategories' => [
                    'Bunker Supply',
                    'Cash to Master',
                    'Crew Change',
                    'Launch Boat Services',
                    'OPA',
                    'Port Clearance',
                ],
            ],
            [
                'name' => 'Tank Cleaning',
                'subcategories' => [
                    'DPP CPP Changeover',
                    'Demolishing Vessel',
                    'Demucking',
                    'Slop Reception',
                    'Sludge Reception',
                    'Sludge Removal',
                ],
            ],
        ];

        foreach ($catalog as $categoryIndex => $categoryData) {
            $category = Category::query()->updateOrCreate(
                ['slug' => Str::slug($categoryData['name'])],
                [
                    'name' => $categoryData['name'],
                    'has_subcategories' => $categoryData['subcategories'] !== [],
                    'is_active' => true,
                    'sort_order' => $categoryIndex + 1,
                ],
            );

            foreach ($categoryData['subcategories'] as $subcategoryIndex => $subcategoryName) {
                Subcategory::query()->updateOrCreate(
                    ['slug' => Str::slug($categoryData['name'].' '.$subcategoryName)],
                    [
                        'category_id' => $category->id,
                        'name' => $subcategoryName,
                        'is_active' => true,
                        'sort_order' => $subcategoryIndex + 1,
                    ],
                );
            }
        }
    }
}
