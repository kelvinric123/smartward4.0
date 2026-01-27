<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Nurse;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class NurseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = '88888888';
        $department = 'General Ward';

        $nursesData = [
            ['name' => 'VICKNESWARY MAYANDY (PB)', 'code' => 'K000754', 'joined' => '2008-01-07', 'designation' => 'Nurse Manager'],
            ['name' => 'JAYALETCHUMY ARUMUGAM', 'code' => 'K000279', 'joined' => '2004-02-19', 'designation' => 'Assistant Nurse Clinician'],

            // SENIOR STAFF NURSE II
            ['name' => 'NURUL NABILAH ABU OTHMAN (PB)', 'code' => 'K001828', 'joined' => '2012-01-03', 'designation' => 'SENIOR STAFF NURSE II'],
            ['name' => 'NOORHIDAYAH BADROL(PB)', 'code' => 'K002381', 'joined' => '2014-03-24', 'designation' => 'SENIOR STAFF NURSE II'],
            ['name' => 'NOORHUSNA MUSTAFA (PB)', 'code' => 'K002574', 'joined' => '2014-11-06', 'designation' => 'SENIOR STAFF NURSE II'],
            ['name' => 'NORFARAH BT NORSABARUDDIN', 'code' => 'K002984', 'joined' => '2016-05-16', 'designation' => 'SENIOR STAFF NURSE II'],

            // STAFF NURSE I
            ['name' => 'NURHAFIZAH LAMYATI (PB)', 'code' => 'K002626', 'joined' => '2015-01-27', 'designation' => 'STAFF NURSE I'],
            ['name' => 'NUR ATIQAH BAHARUDDIN (PB)', 'code' => 'K002824', 'joined' => '2018-05-03', 'designation' => 'STAFF NURSE I'],
            ['name' => 'NORSURIATI BT HAJUDIN (PB)', 'code' => 'K002923', 'joined' => '2016-02-15', 'designation' => 'STAFF NURSE I'],
            ['name' => 'AZUBAH BTE SHUKOR', 'code' => 'K003507', 'joined' => '2017-08-01', 'designation' => 'STAFF NURSE I'],
            ['name' => 'SOFIYA SYAHIRA', 'code' => 'K004021', 'joined' => '2019-09-24', 'designation' => 'STAFF NURSE I'],
            ['name' => 'WAN NOR AIN BT ABDUL LATIF', 'code' => 'K004278', 'joined' => '2019-12-16', 'designation' => 'STAFF NURSE I'],
            ['name' => 'SER JIA LE', 'code' => 'K004302', 'joined' => '2020-01-06', 'designation' => 'STAFF NURSE I'],
            ['name' => 'NUR AIN HIDAYAH BINTI ABD AZIZ', 'code' => 'K005782', 'joined' => '2024-08-05', 'designation' => 'STAFF NURSE I'],

            // STAFF NURSE II
            ['name' => 'HAZIRAH BINTI ZAINAL ABIDIN', 'code' => 'K005434', 'joined' => '2023-09-18', 'designation' => 'STAFF NURSE II'],
            ['name' => 'SUZIA ANN ANAK JENANG', 'code' => 'K004967', 'joined' => '2022-11-07', 'designation' => 'STAFF NURSE II'],
            ['name' => 'NURUL IZZA SHAFIQA ZAMRI', 'code' => 'K004990', 'joined' => '2022-11-21', 'designation' => 'STAFF NURSE II'],
            ['name' => 'NURANNIESYA', 'code' => 'K005030', 'joined' => '2022-12-07', 'designation' => 'STAFF NURSE II'],
            ['name' => 'NUR IRDINA BINTI SHAMSHUDIN', 'code' => 'K005775', 'joined' => '2024-08-01', 'designation' => 'STAFF NURSE II'],
            ['name' => 'NUR MAIZATUL ANIS BT MASRI', 'code' => 'K005853', 'joined' => '2024-11-04', 'designation' => 'STAFF NURSE II'],

            // GRADUATE NURSE
            ['name' => 'NUR RAUDHAL BINTI CHE RAMLI', 'code' => 'K005976', 'joined' => '2025-02-03', 'designation' => 'GRADUATE NURSE'],

            // HCA (Health Care Assistant)
            ['name' => 'DEVAKI MARDAVERAN', 'code' => 'K000585', 'joined' => '2007-03-26', 'designation' => 'Health Care Assistant'],
            ['name' => 'NUR YASMIN BTE MOHD ZULKAINIE', 'code' => 'K004143', 'joined' => '2019-07-22', 'designation' => 'Health Care Assistant'],
            ['name' => 'SYURAIN IZATIE BINTI MAT TAHA', 'code' => 'K004300', 'joined' => '2020-01-06', 'designation' => 'Health Care Assistant'],
            ['name' => 'KHAIRUL AZMIR BIN ABU BAKAR', 'code' => 'K005120', 'joined' => '2022-11-21', 'designation' => 'Health Care Assistant'],
            ['name' => 'NUR AMIRUL', 'code' => 'K005137', 'joined' => '2024-01-13', 'designation' => 'Health Care Assistant'],
            ['name' => 'ROS ASNIETA BT ABDUL RAFAR', 'code' => 'K000930', 'joined' => '2024-12-09', 'designation' => 'Health Care Assistant'],
        ];

        foreach ($nursesData as $data) {
            $email = strtolower($data['code']) . '@qmed.asia';
            $joinedDate = Carbon::parse($data['joined']);
            $yearsOfExperience = $joinedDate->diffInYears(now());

            // Create User
            // Create User
            // $user = User::firstOrCreate(
            //     ['email' => $email],
            //     [
            //         'name' => $data['name'],
            //         'password' => Hash::make($password),
            //         'role' => User::ROLE_NURSE,
            //         'email_verified_at' => now(),
            //     ]
            // );

            // Ensure role is set correctly
            // if ($user->role !== User::ROLE_NURSE) {
            //     $user->role = User::ROLE_NURSE;
            //     $user->save();
            // }

            // Create Nurse Record
            Nurse::updateOrCreate(
                ['personnel_code' => $data['code']],
                [
                    'name' => $data['name'],
                    'email' => $email,
                    'registration_number' => $data['code'], // Using code as reg number for now
                    'department' => $department,
                    'designation' => $data['designation'],
                    'years_of_experience' => $yearsOfExperience,
                    'is_active' => true,
                ]
            );
        }
    }
}
