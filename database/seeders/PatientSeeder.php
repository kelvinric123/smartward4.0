<?php

namespace Database\Seeders;

use App\Models\Patient;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Malaysian-style names
        $malayNames = [
            'Ahmad bin Abdullah', 'Siti Nurhaliza binti Hassan', 'Muhammad Hafiz bin Rahman',
            'Nur Aisyah binti Ibrahim', 'Mohd Azizi bin Ismail', 'Fatimah binti Yusof',
            'Khairul Anuar bin Mahmud', 'Nurul Ain binti Ahmad', 'Faiz bin Mohamed',
            'Zarina binti Zainuddin', 'Irfan bin Rashid', 'Aishah binti Kamal',
        ];
        
        $chineseNames = [
            'Tan Wei Liang', 'Lee Mei Ling', 'Wong Kai Ming', 'Lim Siew Hua',
            'Ng Chee Keong', 'Chan Pei Shan', 'Teo Boon Hock', 'Ong Li Ying',
            'Chong Kar Wai', 'Lau Hui Min', 'Koh Jin Hong', 'Goh Xin Yi',
        ];
        
        $indianNames = [
            'Rajan a/l Subramaniam', 'Priya a/p Krishnan', 'Kumar a/l Rajendran',
            'Kavitha a/p Sivalingam', 'Vijay a/l Murugan', 'Deepa a/p Selvam',
        ];
        
        $allNames = array_merge($malayNames, $chineseNames, $indianNames);
        
        // Malaysian phone prefixes
        $phonePrefix = ['010', '011', '012', '013', '014', '016', '017', '018', '019'];
        
        for ($i = 1; $i <= 30; $i++) {
            $name = $allNames[array_rand($allNames)];
            $gender = rand(0, 1) ? 'Male' : 'Female';
            $age = rand(18, 85);
            
            // Generate Malaysian IC format (YYMMDD-PB-####)
            $year = rand(1940, 2005);
            $month = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
            $day = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
            $yearShort = substr($year, -2);
            $pb = rand(1, 16); // State code (1-16 for different states in Malaysia)
            $pbCode = str_pad($pb, 2, '0', STR_PAD_LEFT);
            $lastFour = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $icNumber = "{$yearShort}{$month}{$day}-{$pbCode}-{$lastFour}";
            
            // Generate MRN (Medical Record Number)
            $mrn = 'MRN' . str_pad($i, 6, '0', STR_PAD_LEFT);
            
            // Generate RN (Registration Number)
            $rn = 'RN' . date('Y') . str_pad($i, 5, '0', STR_PAD_LEFT);
            
            // Generate Malaysian phone number
            $prefix = $phonePrefix[array_rand($phonePrefix)];
            $phone = $prefix . '-' . rand(1000000, 9999999);
            
            Patient::create([
                'name' => $name,
                'mrn' => $mrn,
                'rn' => $rn,
                'ic_passport' => $icNumber,
                'age' => $age,
                'gender' => $gender,
                'phone' => $phone,
                'is_active' => rand(0, 10) > 1, // 90% active
            ]);
        }
    }
}
