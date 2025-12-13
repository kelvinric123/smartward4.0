<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Specialty;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            ['code' => '01', 'name' => 'Anaesthesiology and Critical Care', 'description' => 'Anaesthesia, pain management, and critical care medicine'],
            ['code' => '02', 'name' => 'Otorhinolaryngology (ENT)', 'description' => 'Ear, Nose and Throat disorders'],
            ['code' => '03', 'name' => 'General Paediatrics', 'description' => 'Medical care for infants, children, and adolescents'],
            ['code' => '09', 'name' => 'Paediatric Cardiology', 'description' => 'Heart conditions in children'],
            ['code' => '15', 'name' => 'Paediatric Intensive Care', 'description' => 'Critical care for children'],
            ['code' => '17', 'name' => 'Paediatric Neurology', 'description' => 'Neurological disorders in children'],
            ['code' => '18', 'name' => 'Paediatric Respiratory Medicine', 'description' => 'Respiratory conditions in children'],
            ['code' => '28', 'name' => 'Psychiatry', 'description' => 'Mental health disorders diagnosis and treatment'],
            ['code' => '32', 'name' => 'Clinical Radiology', 'description' => 'Medical imaging and diagnostic radiology'],
            ['code' => '34', 'name' => 'Sports Medicine', 'description' => 'Sports-related injuries and conditions'],
            ['code' => '35', 'name' => 'General Surgery', 'description' => 'Surgical treatment of abdominal organs and related conditions'],
            ['code' => '36', 'name' => 'Breast & Endocrine Surgery', 'description' => 'Surgery for breast and endocrine disorders'],
            ['code' => '37', 'name' => 'Colorectal Surgery', 'description' => 'Surgery for colon, rectum and anus disorders'],
            ['code' => '38', 'name' => 'Hepatobiliary Surgery', 'description' => 'Surgery for liver, gallbladder and bile duct disorders'],
            ['code' => '39', 'name' => 'Thoracic Surgery', 'description' => 'Surgery for chest and thoracic cavity disorders'],
            ['code' => '40', 'name' => 'Upper GI Surgery', 'description' => 'Surgery for upper gastrointestinal disorders'],
            ['code' => '41', 'name' => 'Vascular Surgery', 'description' => 'Surgery for blood vessel disorders'],
            ['code' => '42', 'name' => 'Cardiothoracic Surgery', 'description' => 'Surgery for heart and chest disorders'],
            ['code' => '44', 'name' => 'Emergency Medicine', 'description' => 'Acute illness and injury management'],
            ['code' => '45', 'name' => 'Neurosurgery', 'description' => 'Surgery for nervous system disorders'],
            ['code' => '46', 'name' => 'Paediatric Surgery', 'description' => 'Surgical care for children'],
            ['code' => '47', 'name' => 'Plastic Surgery', 'description' => 'Reconstructive and cosmetic surgery'],
            ['code' => '48', 'name' => 'Urology', 'description' => 'Urinary tract and male reproductive system disorders'],
            ['code' => '50', 'name' => 'Family Medicine', 'description' => 'Primary healthcare for all ages'],
            ['code' => '51', 'name' => 'Internal Medicine', 'description' => 'Adult internal medicine and general medical care'],
            ['code' => '52', 'name' => 'Cardiology', 'description' => 'Heart and cardiovascular system disorders'],
            ['code' => '53', 'name' => 'Clinical Haematology', 'description' => 'Blood disorders and diseases'],
            ['code' => '55', 'name' => 'Dermatology', 'description' => 'Skin, hair, and nail conditions'],
            ['code' => '57', 'name' => 'Endocrinology', 'description' => 'Hormonal and metabolic disorders'],
            ['code' => '58', 'name' => 'Gastroenterology & Hepatology', 'description' => 'Digestive system and liver disorders'],
            ['code' => '59', 'name' => 'Geriatric Medicine', 'description' => 'Healthcare for elderly patients'],
            ['code' => '60', 'name' => 'Infectious Diseases', 'description' => 'Infectious disease diagnosis and treatment'],
            ['code' => '62', 'name' => 'Medical Oncology', 'description' => 'Cancer treatment with medications'],
            ['code' => '63', 'name' => 'Nephrology', 'description' => 'Kidney disorders and diseases'],
            ['code' => '64', 'name' => 'Neurology', 'description' => 'Nervous system disorders'],
            ['code' => '65', 'name' => 'Respiratory Medicine', 'description' => 'Lung and respiratory system disorders'],
            ['code' => '66', 'name' => 'Rheumatology', 'description' => 'Joint, muscle and autoimmune disorders'],
            ['code' => '67', 'name' => 'Palliative Medicine', 'description' => 'End-of-life and comfort care'],
            ['code' => '68', 'name' => 'Nuclear Medicine', 'description' => 'Radioactive materials for diagnosis and treatment'],
            ['code' => '69', 'name' => 'Obstetrics and Gynaecology', 'description' => 'Women\'s reproductive health and pregnancy care'],
            ['code' => '72', 'name' => 'Clinical Oncology', 'description' => 'Cancer diagnosis and treatment'],
            ['code' => '74', 'name' => 'Ophthalmology', 'description' => 'Eye and vision disorders'],
            ['code' => '75', 'name' => 'Orthopaedic Surgery', 'description' => 'Musculoskeletal system disorders'],
            ['code' => '76', 'name' => 'Spine Surgery', 'description' => 'Spinal disorders and surgery'],
            ['code' => '78', 'name' => 'Upper Limb and Microsurgery', 'description' => 'Hand, arm and microsurgical procedures'],
            ['code' => '80', 'name' => 'Oral & Maxillofacial Surgery', 'description' => 'Surgery for mouth, jaw and face'],
            ['code' => '82', 'name' => 'Orthodontics', 'description' => 'Teeth alignment and bite correction'],
            ['code' => '88', 'name' => 'Other Specialities', 'description' => 'Other medical specialities'],
            ['code' => '90', 'name' => 'Dietetic and Nutrition', 'description' => 'Dietary and nutritional services'],
            ['code' => '91', 'name' => 'Interventional Radiology', 'description' => 'Minimally invasive image-guided procedures'],
            ['code' => '92', 'name' => 'General Dentistry', 'description' => 'General dental care'],
            ['code' => '93', 'name' => 'Medical Officer', 'description' => 'General medical officers'],
            ['code' => '95', 'name' => 'Psychology', 'description' => 'Mental health and psychological services'],
            ['code' => '96', 'name' => 'Dental Surgeon', 'description' => 'Dental surgical procedures'],
            ['code' => '97', 'name' => 'Health Screening', 'description' => 'Preventive health screening services'],
        ];

        foreach ($specialties as $specialty) {
            Specialty::updateOrCreate(
                ['code' => $specialty['code']],
                [
                    'name' => $specialty['name'],
                    'description' => $specialty['description'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Specialties seeded: ' . count($specialties) . ' specialties created/updated.');
    }
}
