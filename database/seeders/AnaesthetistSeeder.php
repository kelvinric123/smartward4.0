<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Anaesthetist;

class AnaesthetistSeeder extends Seeder
{
    public function run(): void
    {
        // Anaesthetists from Care Provider Listing (specialty: ANAESTHESIOLOGY AND CRITICAL CARE - code 01)
        $anaesthetists = [
            ['personnel_code' => 'DDASS', 'name' => 'DATO DR DAS, SYLVIAN', 'email' => 'das.sylvian@pantai.com'],
            ['personnel_code' => 'DSATBERP', 'name' => 'DATO DR SATBER KAUR A/P MANJIT SINGH', 'email' => 'satber.kaur@pantai.com'],
            ['personnel_code' => 'DANITAP', 'name' => 'DR ANITA A/P JAMES GONZALES', 'email' => 'anita.gonzales@pantai.com'],
            ['personnel_code' => 'DCHAWSH', 'name' => 'DR CHAW SOOK HUI', 'email' => 'sookhui.chaw@pantai.com'],
            ['personnel_code' => 'DCINDYTJ', 'name' => 'DR CINDY A/P THOMAS JOSEPH', 'email' => 'cindy.joseph@pantai.com'],
            ['personnel_code' => 'DDAVIDCC', 'name' => 'DR DAVID CHIANG CHUN FAI', 'email' => 'david.chiang@pantai.com'],
            ['personnel_code' => 'DGANPOHT', 'name' => 'DR GAN POH TIAN', 'email' => 'pohtian.gan@pantai.com'],
            ['personnel_code' => 'D0033458', 'name' => 'DR GOPINATHAN RAJU', 'email' => 'gopinathan.raju@pantai.com'],
            ['personnel_code' => 'DHAMIDAH', 'name' => 'DR HAMIDAH BINTI ISMAIL', 'email' => 'hamidah.ismail@pantai.com'],
            ['personnel_code' => 'DHASLINI', 'name' => 'DR HASLINI BINTI LIAS', 'email' => 'haslini.lias@pantai.com'],
            ['personnel_code' => 'DINDRAS', 'name' => 'DR INDRA A/P SADASIVAM', 'email' => 'indra.sadasivam@pantai.com'],
            ['personnel_code' => 'DMANAVAJPG', 'name' => 'DR JOSEPH ANTONY MANAVALAN', 'email' => 'joseph.antony@pantai.com'],
            ['personnel_code' => 'DKNADIAH', 'name' => 'DR KHAIRUNNADIAH BINTI KAMARUZAMAN', 'email' => 'khairunnadiah.k@pantai.com'],
            ['personnel_code' => 'DLEEJL', 'name' => 'DR LEE JUNE LYNG', 'email' => 'junelyng.lee@pantai.com'],
            ['personnel_code' => 'DLIMKYP', 'name' => 'DR LIM KIN YUEE', 'email' => 'kinyuee.lim@pantai.com'],
            ['personnel_code' => 'DMAYAP', 'name' => 'DR MAYAVATY NAGARATNAM', 'email' => 'mayavaty.nagaratnam@pantai.com'],
            ['personnel_code' => 'DLMTAQIH', 'name' => 'DR MUHAMMAD TAQI HASAN', 'email' => 'taqi.hasan@pantai.com'],
            ['personnel_code' => 'DNGTYNGY', 'name' => 'DR NG TYNG YAN', 'email' => 'tyngyan.ng@pantai.com'],
            ['personnel_code' => 'D0052127', 'name' => 'DR NG YEW EWE', 'email' => 'yewewe.ng@pantai.com'],
            ['personnel_code' => 'DNAZREEN', 'name' => 'DR NUR AZREEN BINTI HUSSAIN', 'email' => 'azreen.hussain@pantai.com'],
            ['personnel_code' => 'DSPRAVEENA', 'name' => 'DR S PRAVEENA A/P SEEVAUNNAMTUM', 'email' => 'praveena.seeva@pantai.com'],
            ['personnel_code' => 'DSHYMALAP', 'name' => 'DR SHYMALA A/P KUMARASAMY', 'email' => 'shymala.kumarasamy@pantai.com'],
            ['personnel_code' => 'DSIVARAJ', 'name' => 'DR SIVARAJ A/L CHANDRAN', 'email' => 'sivaraj.chandran@pantai.com'],
            ['personnel_code' => 'DSUWEIM', 'name' => 'DR SU WEI MING', 'email' => 'weiming.su@pantai.com'],
            ['personnel_code' => 'DTHAVARA', 'name' => 'DR THAVARANJITHAM A/P SANDRASEGARAM', 'email' => 'thavaranjitham.s@pantai.com'],
            ['personnel_code' => 'DYIPHW', 'name' => 'DR YIP HING WA', 'email' => 'hingwa.yip@pantai.com'],
        ];

        $count = 0;

        foreach ($anaesthetists as $anaesthetist) {
            // Generate registration number from personnel code
            $registrationNumber = 'MMA-' . $anaesthetist['personnel_code'];

            Anaesthetist::updateOrCreate(
                ['personnel_code' => $anaesthetist['personnel_code']],
                [
                    'name' => $anaesthetist['name'],
                    'registration_number' => $registrationNumber,
                    'email' => $anaesthetist['email'],
                    'phone' => null,
                    'qualifications' => null,
                    'years_of_experience' => null,
                    'is_active' => true,
                ]
            );
            $count++;
        }

        $this->command->info("Anaesthetists seeded: {$count} anaesthetists created/updated.");
    }
}
