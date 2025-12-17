<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DietType;

class DietTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Diet types from PHKL HIS system (10/12/2025)
     */
    public function run(): void
    {
        $dietTypes = [
            ['code' => 'AGED', 'name' => 'Acute gastroenteritis diet'],
            ['code' => 'BLAND', 'name' => 'Bland diet'],
            ['code' => 'BDD', 'name' => 'Blended diet'],
            ['code' => 'BF', 'name' => 'Breastfeeding'],
            ['code' => 'CHYD', 'name' => 'Chylothorax diet'],
            ['code' => 'CLQD', 'name' => 'Clear liquid diet'],
            ['code' => 'COD', 'name' => 'Cold diet'],
            ['code' => 'DMD', 'name' => 'Diabetic diet'],
            ['code' => 'DLD', 'name' => 'Dialysis diet'],
            ['code' => 'ENF', 'name' => 'Enteral feeding'],
            ['code' => 'FFD', 'name' => 'Fat free diet'],
            ['code' => 'FOF', 'name' => 'Formula feeding'],
            ['code' => 'FLD', 'name' => 'Full liquid diet'],
            ['code' => 'GFD', 'name' => 'Gluten free diet'],
            ['code' => 'HCD', 'name' => 'High calorie diet'],
            ['code' => 'HFIBD', 'name' => 'High fiber diet'],
            ['code' => 'PTSD', 'name' => 'High potassium diet'],
            ['code' => 'HPD', 'name' => 'High protein diet'],
            ['code' => 'KETOD', 'name' => 'Ketogenic diet'],
            ['code' => 'LACFD', 'name' => 'Lactose free diet'],
            ['code' => 'LCD', 'name' => 'Low calorie diet'],
            ['code' => 'LCHD', 'name' => 'Low cholesterol diet'],
            ['code' => 'LFD', 'name' => 'Low fat diet'],
            ['code' => 'LFIBD', 'name' => 'Low fiber diet'],
            ['code' => 'LIODD', 'name' => 'Low iodine diet'],
            ['code' => 'LPHOD', 'name' => 'Low phosphate diet'],
            ['code' => 'LPOTD', 'name' => 'Low potassium diet'],
            ['code' => 'LPD', 'name' => 'Low protein diet'],
            ['code' => 'LPRD', 'name' => 'Low purine diet'],
            ['code' => 'LRD', 'name' => 'Low residue diet'],
            ['code' => 'LSD', 'name' => 'Low salt diet'],
            ['code' => 'MSD', 'name' => 'Mechanical soft diet'],
            ['code' => 'MMD', 'name' => 'Minced & moist diet'],
            ['code' => 'NPD', 'name' => 'Neutropenic diet'],
            ['code' => 'NBM', 'name' => 'Nil by mouth'],
            ['code' => 'OTH', 'name' => 'Other (specify in remarks)'],
            ['code' => 'PD', 'name' => 'Paediatric diet'],
            ['code' => 'PF', 'name' => 'Parenteral feeding'],
            ['code' => 'PDLD', 'name' => 'Predialysis diet'],
            ['code' => 'RD', 'name' => 'Regular diet'],
            ['code' => 'SD', 'name' => 'Soft diet'],
            ['code' => 'TKPD', 'name' => 'Thick pureed diet'],
            ['code' => 'TNPD', 'name' => 'Thin pureed diet'],
            ['code' => 'VMA', 'name' => 'VMA (Vanillylmandelic acid) diet'],
            ['code' => 'VEGD', 'name' => 'Vegetarian diet'],
            ['code' => 'WFD', 'name' => 'Warfarin diet'],
        ];

        foreach ($dietTypes as $dietType) {
            DietType::updateOrCreate(
                ['code' => $dietType['code']],
                [
                    'name' => $dietType['name'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Diet Types seeded successfully!');
        $this->command->info('- ' . count($dietTypes) . ' diet types created/updated');
    }
}










