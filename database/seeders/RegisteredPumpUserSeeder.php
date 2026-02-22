<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InfusionPump;
use App\Models\Ward;

class RegisteredPumpUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pumps = [
            // Spaceplus Infusomat
            ['serial_no' => '51297', 'device_id' => '6efcc159-f62c-556c-b0e8-e285f622847e', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 1'],
            ['serial_no' => '51309', 'device_id' => 'c44ecd73-3be2-5ec1-9a12-dd91dd654430', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 2'],
            ['serial_no' => '51316', 'device_id' => '46637152-3159-5f31-be9c-86e104aaf384', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 3'],
            ['serial_no' => '51340', 'device_id' => '56cefbd8-1371-5b4d-8141-ccad7df7f65b', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 4'],
            ['serial_no' => '51369', 'device_id' => 'afd8c1ab-201a-5cc3-b43f-45be3a72d890', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 5'],
            ['serial_no' => '51373', 'device_id' => '68e25284-8105-516e-9d93-1d9fe4ec6445', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 6'],
            ['serial_no' => '51541', 'device_id' => '189ee1f3-1afb-55a5-9c07-3fdcb0d1bb51', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 7'],
            ['serial_no' => '51543', 'device_id' => 'fc654de2-e2ac-5887-8371-1f5772378dce', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 8'],
            ['serial_no' => '51545', 'device_id' => 'af3dcdb2-3e94-53bc-a48e-d9566f5e59dd', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 9'],
            ['serial_no' => '51550', 'device_id' => '6263933f-fc63-5385-b59b-7417505cd134', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 10'],
            ['serial_no' => '51557', 'device_id' => '25577ec0-dc5d-5c54-a9cb-09eb8ca39e7f', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 11'],
            ['serial_no' => '51558', 'device_id' => '732de6cf-c201-5659-bbc1-54b0ced7ccd6', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 12'],
            ['serial_no' => '51559', 'device_id' => '743d27f8-c72b-516b-8622-e7f2df7b6ec5', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 13'],
            ['serial_no' => '51568', 'device_id' => 'c0a68180-56ec-5097-9ffb-2e8ed3b011cb', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 14'],
            ['serial_no' => '51569', 'device_id' => '360cec21-b06a-5fb8-91cb-d17d2143d38c', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 15'],
            ['serial_no' => '51580', 'device_id' => 'dc727985-7979-5637-a819-cc765579b8ea', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 16'],
            ['serial_no' => '51584', 'device_id' => '463c8af6-d7cf-56f5-84a6-4eeb3a6598fb', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 17'],
            ['serial_no' => '51600', 'device_id' => '7743be86-5abe-5dca-be9f-a68b48d3d366', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 18'],
            ['serial_no' => '51602', 'device_id' => '6babe705-2320-5d56-9a69-9cc2cc801496', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 19'],
            ['serial_no' => '51607', 'device_id' => '1504c0d2-709f-52e8-9dce-eb3163e36485', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 20'],
            ['serial_no' => '51781', 'device_id' => '9ddd520f-e5cd-5f3e-9d69-c94801985cdf', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 21'],
            ['serial_no' => '51787', 'device_id' => '3dac07e9-0d43-57a4-9542-36faccfc259e', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 22'],
            ['serial_no' => '51792', 'device_id' => '23d3dfe8-7b2d-5224-9840-9bbeb84b7fbe', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 23'],
            ['serial_no' => '51796', 'device_id' => '1650481a-c94c-58af-8002-1a0e9caeb8a3', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 24'],
            ['serial_no' => '51801', 'device_id' => '18f4245e-881c-5151-97b4-a26e9fcffee7', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 25'],
            ['serial_no' => '51807', 'device_id' => 'a9943f39-2124-5cef-a040-eca4349ff9c5', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 26'],
            ['serial_no' => '51810', 'device_id' => '7ee3b4cf-abe5-5d1a-9054-a430346bdf26', 'device_type' => 'volumetric_pump', 'device_name' => 'Spaceplus Infusomat 27'],

            // Spaceplus Perfusor
            ['serial_no' => '52769', 'device_id' => '0d19e019-7b75-5161-85cc-5ee2735075e4', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 1'],
            ['serial_no' => '52772', 'device_id' => '528016e3-5036-51b8-b367-325275787ad5', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 2'],
            ['serial_no' => '52778', 'device_id' => 'e8940ac6-1567-5fe4-b803-178ad9cd7ef5', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 3'],
            ['serial_no' => '52786', 'device_id' => '15f99e25-db00-5d8b-b94a-af8767b4fb7a', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 4'],
            ['serial_no' => '52787', 'device_id' => 'bd1a76e6-7af1-5d23-bd7b-125d8d23369e', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 5'],
            ['serial_no' => '52788', 'device_id' => '3c9f85da-314f-508c-a6c4-d0e0ab4e5e2b', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 6'],
            ['serial_no' => '52803', 'device_id' => '0b98625a-14b5-5f1d-af9a-952bb830b6e0', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 7'],
            ['serial_no' => '52806', 'device_id' => 'f29fb93f-4d96-5d9e-95bf-75367be76875', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 8'],
            ['serial_no' => '52808', 'device_id' => '4558035f-a1a7-5aca-a5b1-037df58793c5', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 9'],
            ['serial_no' => '52809', 'device_id' => 'aacae1eb-2dbc-55e7-b857-d4c88eb75484', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 10'],
            ['serial_no' => '52811', 'device_id' => 'a49583ab-74d8-5a69-af1a-4918ac752417', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 11'],
            ['serial_no' => '52812', 'device_id' => '9b0e8e42-2218-569b-8231-0bdc3e8169a6', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 12'],
            ['serial_no' => '52813', 'device_id' => '180d1af2-f6ad-5b34-9878-8f02536c3160', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 13'],
            ['serial_no' => '52814', 'device_id' => '3107c7c4-59d7-54b7-b500-8d716e5ca2cb', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 14'],
            ['serial_no' => '52819', 'device_id' => '62a78f23-6ef1-5efa-9652-c5a3998825a0', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 15'],
            ['serial_no' => '52824', 'device_id' => '8a7103a7-e7c2-538f-9a72-c6aff191050b', 'device_type' => 'syringe_pump', 'device_name' => 'Spaceplus Perfusor 16'],
        ];

        $ward = Ward::where('ward_name', 'like', '%D6%')->first();

        foreach ($pumps as $pump) {
            InfusionPump::updateOrCreate(
                ['device_id' => $pump['device_id']],
                [
                    'serial_no' => $pump['serial_no'],
                    'device_type' => $pump['device_type'],
                    'device_name' => $pump['device_name'],
                    'ward_id' => $ward ? $ward->id : null,
                    'is_active' => true,
                ]
            );
        }
    }
}
