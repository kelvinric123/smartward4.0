<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InfusionPump;
use Illuminate\Support\Facades\DB;

class InfusionPumpSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Infusomat® -> Prefix 'I'
        $infusomats = [
            '51297',
            '51309',
            '51316',
            '51340',
            '51369',
            '51373',
            '51541',
            '51543',
            '51545',
            '51550',
            '51557',
            '51558',
            '51559',
            '51568',
            '51569',
            '51580',
            '51584',
            '51600',
            '51602',
            '51607',
            '51781',
            '51787',
            '51792',
            '51796',
            '51801',
            '51807',
            '51810'
        ];

        // Perfusor® -> Prefix 'P'
        $perfusors = [
            '52769',
            '52772',
            '52778',
            '52786',
            '52787',
            '52788',
            '52803',
            '52806',
            '52808',
            '52809',
            '52811',
            '52812',
            '52813',
            '52814',
            '52819',
            '52824'
        ];

        // Process Infusomats
        foreach ($infusomats as $number) {
            $serialNo = 'I' . $number;
            $this->createPump($serialNo, 'Infusomat Space', 'volumetric_pump');
        }

        // Process Perfusors
        foreach ($perfusors as $number) {
            $serialNo = 'P' . $number;
            $this->createPump($serialNo, 'Perfusor Space', 'syringe_pump');
        }
    }

    private function createPump($serialNo, $modelName, $deviceType)
    {
        // Check if pump already exists by serial_no
        if (InfusionPump::where('serial_no', $serialNo)->exists()) {
            return;
        }

        // Also check if pump exists by device_id (if we used it as ID before)
        // This prevents duplicates if the serial number was previously used as device_id
        if (InfusionPump::where('device_id', $serialNo)->exists()) {
            $pump = InfusionPump::where('device_id', $serialNo)->first();
            $pump->serial_no = $serialNo;
            $pump->save();
            return;
        }

        InfusionPump::create([
            'serial_no' => $serialNo,
            'device_id' => $serialNo, // Use serial no as device_id for consistency
            'device_name' => "B.Braun $modelName $serialNo",
            'device_type' => $deviceType,
            'pump_model' => $modelName,
            'is_active' => true,
            'location' => 'Unassigned',
        ]);
    }
}
