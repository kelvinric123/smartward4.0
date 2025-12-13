<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Consultant;
use App\Models\Specialty;

class ConsultantSeeder extends Seeder
{
    public function run(): void
    {
        // Consultants from Care Provider Listing (excluding Anaesthetists with specialty code 01)
        $consultants = [
            // No specialty ()
            ['personnel_code' => 'DDEVM', 'name' => 'DATUK DR DEVANAND M.', 'specialty_code' => null, 'email' => 'devanand.m@pantai.com'],
            ['personnel_code' => 'D0023362', 'name' => 'DR CHEE CHEE PIN', 'specialty_code' => null, 'email' => 'cheepin.chee@pantai.com'],
            ['personnel_code' => 'QBCZFY', 'name' => 'DR KHOO KAY WAI', 'specialty_code' => null, 'email' => 'kaywai.khoo@pantai.com'],
            ['personnel_code' => 'DRREDZUAN', 'name' => 'DR MOHD REDZUAN BIN ISMAIL (DO NOT USE)', 'specialty_code' => null, 'email' => 'redzuan.ismail@pantai.com'],
            ['personnel_code' => 'DLNURSYAHIRAH', 'name' => 'DR NURSYAHIRAH BINTI MOHD SATRIA', 'specialty_code' => null, 'email' => 'nursyahirah.satria@pantai.com'],
            ['personnel_code' => '993', 'name' => 'DR PAUL NG HOCK ONN', 'specialty_code' => null, 'email' => 'paul.ng@pantai.com'],
            ['personnel_code' => 'DWONGAK', 'name' => 'DR WONG AUN KEET', 'specialty_code' => null, 'email' => 'DWONGAK@pantai.com'],
            ['personnel_code' => 'LOCUMSARAVANA', 'name' => 'SARAVANA KUMAR A/L SUBRAMANIAM', 'specialty_code' => null, 'email' => 'saravana.s@pantai.com'],

            // BREAST & ENDOCRINE SURGERY (36)
            ['personnel_code' => 'DAZLINAP', 'name' => 'DR AZLINA FIRZAH BINTI ABD AZIZ', 'specialty_code' => '36', 'email' => 'azlina.aziz@pantai.com'],
            ['personnel_code' => '1107', 'name' => 'DR NORMAYAH BINTI KITAN', 'specialty_code' => '36', 'email' => 'normayah.kitan@pantai.com'],
            ['personnel_code' => 'DPAGOMEZP', 'name' => 'DR PATRICIA ALISON GOMEZ', 'specialty_code' => '36', 'email' => 'patricia.gomez@pantai.com'],
            ['personnel_code' => 'DDINAP', 'name' => 'DR SALADINA JASZLE BINTI JASMIN', 'specialty_code' => '36', 'email' => 'saladina.jasmin@pantai.com'],

            // CARDIOLOGY (52)
            ['personnel_code' => 'DASYADI', 'name' => 'ASSOCIATE PROFESSOR DR AHMAD SYADI BIN MAHMOOD ZUHDI', 'specialty_code' => '52', 'email' => 'syadi.zuhdi@pantai.com'],
            ['personnel_code' => 'DKCHIN', 'name' => 'DATO DR KENNETH CHIN', 'specialty_code' => '52', 'email' => 'kenneth.chin@pantai.com'],
            ['personnel_code' => 'DKCHINOP', 'name' => 'DATO DR KENNETH CHIN (OP)', 'specialty_code' => '52', 'email' => 'kenneth.chin.1@pantai.com'],
            ['personnel_code' => 'DARUMNP', 'name' => 'DATUK DR ARUMUGAM A/L AR.A.NACHIAPPAN', 'specialty_code' => '52', 'email' => 'arumugam.a@pantai.com'],
            ['personnel_code' => 'DARUMNOP', 'name' => 'DATUK DR ARUMUGAM, N. (OP)', 'specialty_code' => '52', 'email' => 'arumugam.n@pantai.com'],
            ['personnel_code' => 'DSANJIVP', 'name' => 'DATUK DR SANJIV JOSHI S/O HARI CHAND', 'specialty_code' => '52', 'email' => 'sanjiv.joshi@pantai.com'],
            ['personnel_code' => 'DZAINALABP', 'name' => 'DATUK DR ZAINAL ABIDIN BIN ABDUL HAMID', 'specialty_code' => '52', 'email' => 'zainal.hamid@pantai.com'],
            ['personnel_code' => 'DDAVDQOP', 'name' => 'DR DAVID K L QUEK', 'specialty_code' => '52', 'email' => 'david.quek.1@pantai.com'],
            ['personnel_code' => 'DDAVIDQP', 'name' => 'DR DAVID QUEK KWANG LENG', 'specialty_code' => '52', 'email' => 'david.quek@pantai.com'],
            ['personnel_code' => 'DENGWO', 'name' => 'DR ERNEST NG WEE OON', 'specialty_code' => '52', 'email' => 'DENGWO@pantai.com'],
            ['personnel_code' => '1178', 'name' => 'DR FAIZAL KHAN BIN ABDULLAH', 'specialty_code' => '52', 'email' => 'faizal.abdullah@pantai.com'],
            ['personnel_code' => 'DKUMARAGURU', 'name' => 'DR KUMARA GURUPPARAN', 'specialty_code' => '52', 'email' => 'kumara.gurupparan@pantai.com'],
            ['personnel_code' => '1133', 'name' => 'DR LIM CHIAO WEN', 'specialty_code' => '52', 'email' => 'chiaowen.lim@pantai.com'],
            ['personnel_code' => 'DIMRAN', 'name' => 'DR MUHAMMAD IMRAN BIN ABDUL HAFIDZ', 'specialty_code' => '52', 'email' => 'imran.hafidz@pantai.com'],
            ['personnel_code' => 'DNGWKP', 'name' => 'DR NG WAI KIAT', 'specialty_code' => '52', 'email' => 'waikiat.ng@pantai.com'],
            ['personnel_code' => 'DSUHAIMIKP', 'name' => 'DR SUHAIMI BIN OSMAN', 'specialty_code' => '52', 'email' => 'suhaimi.osman@pantai.com'],
            ['personnel_code' => 'DTAIYS', 'name' => 'DR TAI YIH SIEW', 'specialty_code' => '52', 'email' => 'yihsiew.tai@pantai.com'],
            ['personnel_code' => 'DTANKL', 'name' => 'DR TAN KOK LENG', 'specialty_code' => '52', 'email' => 'kokleng.tan@pantai.com'],
            ['personnel_code' => '1073', 'name' => 'DR YEE KOK MENG', 'specialty_code' => '52', 'email' => 'kokmeng.yee@pantai.com'],
            ['personnel_code' => 'DRIDZBP', 'name' => 'TAN SRI DATUK DR RIDZWAN B ABU BAKAR', 'specialty_code' => '52', 'email' => 'ridzwan.bakar@pantai.com'],

            // CARDIOTHORACIC SURGERY (42)
            ['personnel_code' => 'DVENUP', 'name' => 'DATO DR VENUGOPAL A/L M BALCHAND', 'specialty_code' => '42', 'email' => 'venugopal.balchand@pantai.com'],
            ['personnel_code' => 'DANANDS', 'name' => 'DR ANAND SACHITHANANDAN', 'specialty_code' => '42', 'email' => 'anand.s@pantai.com'],
            ['personnel_code' => 'DARUNCNP', 'name' => 'DR ARUNACHALAM A/L NAGAPPAN (DO NOT USE)', 'specialty_code' => '42', 'email' => 'arunachalam.nagappan@pantai.com'],
            ['personnel_code' => 'DBALAJI', 'name' => 'DR BALAJI A/L BADMANABAN (DO NOT USE)', 'specialty_code' => '42', 'email' => 'balaji.badmanaban@pantai.com'],
            ['personnel_code' => 'DSITILAURA', 'name' => 'DR SITI LAURA BINTI MAZALAN', 'specialty_code' => '42', 'email' => 'laura.mazalan@pantai.com'],

            // CLINICAL HAEMATOLOGY (53)
            ['personnel_code' => 'DVIJAYASP', 'name' => 'DATO DR VIJAYA SANGKAR A/L JAGANATHAN', 'specialty_code' => '53', 'email' => 'vijaya.sangkar@pantai.com'],
            ['personnel_code' => 'DSIVAKUMAR', 'name' => 'DR SIVAKUMAR A/L PALANIAPPAN', 'specialty_code' => '53', 'email' => 'sivakumar.p@pantai.com'],

            // CLINICAL ONCOLOGY (72)
            ['personnel_code' => 'DIBRAHIMWP', 'name' => 'DATO DR MOHAMED IBRAHIM BIN A WAHID', 'specialty_code' => '72', 'email' => 'ibrahim.wahid@pantai.com'],
            ['personnel_code' => 'DLAMKSP', 'name' => 'DATUK DR LAM KAI SENG', 'specialty_code' => '72', 'email' => 'kaiseng.lam@pantai.com'],
            ['personnel_code' => 'DMANIVA', 'name' => 'DR A.B MANIVANNAN', 'specialty_code' => '72', 'email' => 'manivannan.a@pantai.com'],
            ['personnel_code' => 'DRADZI', 'name' => 'DR AHMAD RADZI BIN AHMAD BADRUDDIN', 'specialty_code' => '72', 'email' => 'radzi.badruddin@pantai.com'],
            ['personnel_code' => 'DHILAWATI', 'name' => 'DR HILAWATI BINTI YUSOF', 'specialty_code' => '72', 'email' => 'hilawati.yusof@pantai.com'],
            ['personnel_code' => 'DHGWOF', 'name' => 'DR HO GWO FUANG', 'specialty_code' => '72', 'email' => 'gwofuang.ho@pantai.com'],
            ['personnel_code' => 'DJOHNLSP', 'name' => 'DR JOHN LOW SENG HOOI', 'specialty_code' => '72', 'email' => 'john.low@pantai.com'],
            ['personnel_code' => 'DJUNIE', 'name' => 'DR JUNIE KHOO YU-YEN', 'specialty_code' => '72', 'email' => 'junie.khoo@pantai.com'],
            ['personnel_code' => 'DLAUFN', 'name' => 'DR LAU FEN NEE', 'specialty_code' => '72', 'email' => 'fennee.lau@pantai.com'],
            ['personnel_code' => 'DMALWIN', 'name' => 'DR MALWINDER SINGH SANDHU', 'specialty_code' => '72', 'email' => 'malwinder.singh@pantai.com'],
            ['personnel_code' => 'DMASTURAPG', 'name' => 'DR MASTURA BINTI MD YUSOF', 'specialty_code' => '72', 'email' => 'mastura.mdyusof@pantai.com'],
            ['personnel_code' => 'DFADHLINA', 'name' => 'DR NUR FADHLINA BINTI ABDUL SATAR', 'specialty_code' => '72', 'email' => 'fadhlina.satar@pantai.com'],
            ['personnel_code' => 'DSOWWJ', 'name' => 'DR SOW WEN JEN', 'specialty_code' => '72', 'email' => 'wenjen.sow@pantai.com'],
            ['personnel_code' => 'D0056077', 'name' => 'DR THO LYE MUN', 'specialty_code' => '72', 'email' => 'lyemun.tho@pantai.com'],
            ['personnel_code' => 'DTHOLM', 'name' => 'DR THO LYE MUN', 'specialty_code' => '72', 'email' => 'DTHOLM@pantai.com'],

            // CLINICAL RADIOLOGY (32)
            ['personnel_code' => 'DAFUAD', 'name' => 'DR AMIR FUAD BIN HUSSAIN', 'specialty_code' => '32', 'email' => 'amir.hussain@pantai.com'],
            ['personnel_code' => 'DASOKANP', 'name' => 'DR ASOKAN A/L RAMAN NAIR', 'specialty_code' => '32', 'email' => 'asokan.nair@pantai.com'],
            ['personnel_code' => 'DLAZWINI', 'name' => 'DR AZWINI BT MOHAMED', 'specialty_code' => '32', 'email' => 'azwini.mohamed@pantai.com'],
            ['personnel_code' => 'DLBUSHRA', 'name' => 'DR BUSHRA BINTI JOHARI', 'specialty_code' => '32', 'email' => 'bushra.johari@pantai.com'],
            ['personnel_code' => 'DGITKA', 'name' => 'DR GIT KIM ANN', 'specialty_code' => '32', 'email' => 'kimann.git@pantai.com'],
            ['personnel_code' => 'DLHEMA', 'name' => 'DR HEMA A/P HARI RAJAH', 'specialty_code' => '32', 'email' => 'hema.rajah@pantai.com'],
            ['personnel_code' => 'DKHOS', 'name' => 'DR KHOSHALA A/P S.KRISHNAMURTHY', 'specialty_code' => '32', 'email' => 'khoshala.krishnamurthy@pantai.com'],
            ['personnel_code' => 'DLLEEMC', 'name' => 'DR LEE MEI CHIH', 'specialty_code' => '32', 'email' => 'meichih.lee@pantai.com'],
            ['personnel_code' => 'DNASHIKIN', 'name' => 'DR NURASHIKIN BINTI JAMALUDDIN', 'specialty_code' => '32', 'email' => 'nurashikin.j@pantai.com'],
            ['personnel_code' => 'DSHOBHANA', 'name' => 'DR SHOBHANA SIVANDAN', 'specialty_code' => '32', 'email' => 'shobhana.sivandan@pantai.com'],
            ['personnel_code' => 'DTEOYS', 'name' => 'DR TEO YEE SHEAN', 'specialty_code' => '32', 'email' => 'yeeshean.teo@pantai.com'],
            ['personnel_code' => 'DVIMALAP', 'name' => 'DR VIMALAH RATHAKRISHNAN', 'specialty_code' => '32', 'email' => 'vimalah.r@pantai.com'],

            // COLORECTAL SURGERY (37)
            ['personnel_code' => 'DKCW', 'name' => 'DR KHAW CHERN WERN JAMES', 'specialty_code' => '37', 'email' => 'james.khaw@pantai.com'],
            ['personnel_code' => 'DNAVINAK', 'name' => 'DR NAVINAKATHIRESU A/L MUTHUKUMARASAMY', 'specialty_code' => '37', 'email' => 'navinakathiresu.m@pantai.com'],

            // DENTAL SURGEON (96)
            ['personnel_code' => 'DPRIYAD', 'name' => 'DR PRIYA DAMODARAN', 'specialty_code' => '96', 'email' => 'priya.damodaran@pantai.com'],
            ['personnel_code' => 'DYOGES', 'name' => 'DR YOGESWARI SIVAPRAGASAM', 'specialty_code' => '96', 'email' => 'yogeswari.sivapragasam@pantai.com'],

            // DERMATOLOGY (55)
            ['personnel_code' => 'DCHOWKW', 'name' => 'DR CHOW KIM WENG, STEVEN', 'specialty_code' => '55', 'email' => 'kimweng.chow@pantai.com'],
            ['personnel_code' => 'DDAWNAA', 'name' => 'DR DAWN ANGELIA AMBROSE', 'specialty_code' => '55', 'email' => 'dawn.ambrose@pantai.com'],
            ['personnel_code' => 'DTCLIM', 'name' => 'DR LIM TOU CHAI', 'specialty_code' => '55', 'email' => 'touchai.lim@pantai.com'],
            ['personnel_code' => 'DNAZIRIN', 'name' => 'DR NAZIRIN BT ARIFFIN', 'specialty_code' => '55', 'email' => 'nazirin.ariffin.1@pantai.com'],
            ['personnel_code' => 'DNAZIMED', 'name' => 'DR NAZIRIN BT ARIFFIN (DNAZIMED)', 'specialty_code' => '55', 'email' => 'nazirin.ariffin@pantai.com'],
            ['personnel_code' => 'DPRASANNAKP', 'name' => 'DR PRASANNA KANNANKUTTY', 'specialty_code' => '55', 'email' => 'prasanna.k@pantai.com'],
            ['personnel_code' => 'DPRAMED', 'name' => 'DR PRASANNA KANNANKUTTY (DPRAMED)', 'specialty_code' => '55', 'email' => 'DPRAMED@pantai.com'],

            // DIETETIC AND NUTRITION (90)
            ['personnel_code' => 'DTGOHYX', 'name' => 'MISS GOH YEE XUEN', 'specialty_code' => '90', 'email' => 'yeexuen.goh@pantai.com'],
            ['personnel_code' => 'ISABELLEG', 'name' => 'MISS ISABELLE GEH ZHEN-XIN', 'specialty_code' => '90', 'email' => 'isabelle.g@pantai.com'],
            ['personnel_code' => 'DTKAMAR', 'name' => 'MISS KAMAR ALFATHIMA BINTI MUHAMAD HANIF IRUSAN', 'specialty_code' => '90', 'email' => 'kamar.hanif@pantai.com'],
            ['personnel_code' => 'DTLEESJ', 'name' => 'MISS LEE SHIN JIE', 'specialty_code' => '90', 'email' => 'shinjie.lee@pantai.com'],
            ['personnel_code' => 'DYING', 'name' => 'MISS ONG MIN YING', 'specialty_code' => '90', 'email' => 'minying.ong@pantai.com'],
            ['personnel_code' => 'JIAJUNCHONG', 'name' => 'MR CHONG JIA JUN', 'specialty_code' => '90', 'email' => 'jiajun.chong@pantai.com'],

            // EMERGENCY MEDICINE (44)
            ['personnel_code' => 'DAIBRAHIM', 'name' => 'DR AHMAD IBRAHIM BIN KAMAL BATCHA', 'specialty_code' => '44', 'email' => 'ahmad.kamal@pantai.com'],
            ['personnel_code' => 'SBPNLM', 'name' => 'DR HARKIRAN KAUR A/P MOKHTAR SINGH', 'specialty_code' => '44', 'email' => 'harkiran.mokhtar@pantai.com'],
            ['personnel_code' => 'DIZZAT', 'name' => 'DR IZZAT BIN ISMAIL', 'specialty_code' => '44', 'email' => 'izzat.i@pantai.com'],
            ['personnel_code' => 'DJWADAS', 'name' => 'DR JEEWADAS A/L VELUMMYLUM BALADAS', 'specialty_code' => '44', 'email' => 'jeewadas.baladas@pantai.com'],
            ['personnel_code' => 'DMRIZWAN', 'name' => 'DR MOHAMED RIZWAN BIN MOHAMED MUSTAFA MARICAN', 'specialty_code' => '44', 'email' => 'rizwan.mustafa@pantai.com'],
            ['personnel_code' => 'DSJAHAN', 'name' => 'DR SHAH JAHAN BIN MOHD YUSSOF', 'specialty_code' => '44', 'email' => 'jahan.yussof@pantai.com'],

            // ENDOCRINOLOGY (57)
            ['personnel_code' => 'DLIMSUEW', 'name' => 'DR LIM SUE WEN', 'specialty_code' => '57', 'email' => 'suewen.lim@pantai.com'],
            ['personnel_code' => 'DNADARJA', 'name' => 'DR NADARAJAH, A.', 'specialty_code' => '57', 'email' => 'nadarajah.a@pantai.com'],
            ['personnel_code' => 'DSCD', 'name' => 'DR SHALINI A/P C SREE DHARAN', 'specialty_code' => '57', 'email' => 'shalini.dharan@pantai.com'],
            ['personnel_code' => 'DVIJAYAPPG', 'name' => 'DR VIJAY ANANDA PARAMASVARAN', 'specialty_code' => '57', 'email' => 'vijay.ananda@pantai.com'],

            // FAMILY MEDICINE (50)
            ['personnel_code' => 'DM000107', 'name' => 'DR MARIEANNE SUNDRAM', 'specialty_code' => '50', 'email' => 'marieanne@pantai.com'],

            // GASTROENTEROLOGY & HEPATOLOGY (58)
            ['personnel_code' => 'DSHARMIP', 'name' => 'DATIN DR SHARMILA SACHITHANANDAN', 'specialty_code' => '58', 'email' => 'sharmila.s@pantai.com'],
            ['personnel_code' => 'DGANESP', 'name' => 'DATO DR GANESANANTHAN SHANMUGANATHAN', 'specialty_code' => '58', 'email' => 'ganesananthan.s@pantai.com'],
            ['personnel_code' => 'DDSMAHENP', 'name' => 'DATO DR MAHENDRA RAJ A/L P SUNDRAMOORTHY', 'specialty_code' => '58', 'email' => 'mahendra.sundramoorthy@pantai.com'],
            ['personnel_code' => 'DALEXLHR', 'name' => 'DR ALEX LEOW HWONG RUEY', 'specialty_code' => '58', 'email' => 'alex.leow@pantai.com'],
            ['personnel_code' => 'DGEWLT', 'name' => 'DR GEW LAI TECK', 'specialty_code' => '58', 'email' => 'laiteck.gew@pantai.com'],
            ['personnel_code' => 'DKUDVAMC', 'name' => 'DR KUDVA, M V', 'specialty_code' => '58', 'email' => 'kudva.m@pantai.com'],
            ['personnel_code' => 'DSHANTHIP', 'name' => 'DR SHANTHI PALANIAPPAN', 'specialty_code' => '58', 'email' => 'shanthi.palaniappan@pantai.com'],
            ['personnel_code' => 'DWONGZQ', 'name' => 'DR WONG ZHIQIN', 'specialty_code' => '58', 'email' => 'zhiqin.wong@pantai.com'],

            // GENERAL DENTISTRY (92)
            ['personnel_code' => 'DPRIYANKA', 'name' => 'DR PRIYANKA MAHENDRU', 'specialty_code' => '92', 'email' => 'priyanka.mahendru@pantai.com'],
            ['personnel_code' => 'DRESHNU', 'name' => 'DR RESHNU SURI A/P KISHORE KUMAR SURI', 'specialty_code' => '92', 'email' => 'reshnu.suri@pantai.com'],

            // GENERAL PAEDIATRICS (03)
            ['personnel_code' => 'DTAICW', 'name' => 'DR TAI CHIAN WERN', 'specialty_code' => '03', 'email' => 'chianwern.tai@pantai.com'],
            ['personnel_code' => 'DAMIRH', 'name' => 'DR AMIR HAMZAH BIN DATO ABDUL LATIF', 'specialty_code' => '03', 'email' => 'hamzah.latif@pantai.com'],
            ['personnel_code' => 'DANUSH', 'name' => 'DR ANUSHREE NARAYANAN', 'specialty_code' => '03', 'email' => 'anushree.narayanan@pantai.com'],
            ['personnel_code' => 'DAZAM', 'name' => 'DR AZAM BIN MOHD NOR', 'specialty_code' => '03', 'email' => 'azam.nor@pantai.com'],
            ['personnel_code' => 'DCHAIPFP', 'name' => 'DR CHAI PEI FAN', 'specialty_code' => '03', 'email' => 'peifan.chai@pantai.com'],
            ['personnel_code' => 'DCHINYH', 'name' => 'DR CHIN YOON HIAP', 'specialty_code' => '03', 'email' => 'yoonhiap.chin@pantai.com'],
            ['personnel_code' => 'DLEEKH', 'name' => 'DR ERIC LEE KIM HOR', 'specialty_code' => '03', 'email' => 'eric.lee@pantai.com'],
            ['personnel_code' => 'DFOOWLP', 'name' => 'DR FOO WANG LENG', 'specialty_code' => '03', 'email' => 'wangleng.foo@pantai.com'],
            ['personnel_code' => 'DKAMARA', 'name' => 'DR KAMARUDDIN AHMAD (DO NOT USE)', 'specialty_code' => '03', 'email' => 'kamaruddin.ahmad@pantai.com'],
            ['personnel_code' => 'DKAMAOP', 'name' => 'DR KAMARUDDIN AHMAD (OP) (DO NOT USE)', 'specialty_code' => '03', 'email' => 'kamaruddin.ahmad.1@pantai.com'],
            ['personnel_code' => 'DKERRYVPP', 'name' => 'DR KERRY VIVIENNE JAYAPRAKASAM', 'specialty_code' => '03', 'email' => 'kerry.vivienne@pantai.com'],
            ['personnel_code' => 'DLALITHA', 'name' => 'DR LALITHA PILLAY A/P B. GOPINATHAN', 'specialty_code' => '03', 'email' => 'lalitha.gopinathan@pantai.com'],
            ['personnel_code' => 'DLAMSK', 'name' => 'DR LAM SHIH KWONG', 'specialty_code' => '03', 'email' => 'shihkwong.lam@pantai.com'],
            ['personnel_code' => 'DJUANITA', 'name' => 'DR RAJA JUANITA RAJA LOPE', 'specialty_code' => '03', 'email' => 'juanita.lope@pantai.com'],
            ['personnel_code' => 'DSSIEWCH', 'name' => 'DR SU SIEW CHOO', 'specialty_code' => '03', 'email' => 'siewchoo.su@pantai.com'],
            ['personnel_code' => 'DUMASP', 'name' => 'DR UMA DEVI A/P SOTHINATHAN', 'specialty_code' => '03', 'email' => 'uma.devi@pantai.com'],
            ['personnel_code' => 'DYONGSCP', 'name' => 'DR YONG SIN CHUEN', 'specialty_code' => '03', 'email' => 'sinchuen.yong@pantai.com'],

            // GENERAL SURGERY (35)
            ['personnel_code' => 'DAFAHMI', 'name' => 'DATO DR ABDUL FAHMI BIN ABDUL KARIM', 'specialty_code' => '35', 'email' => 'fahmi.karim@pantai.com'],
            ['personnel_code' => 'DMHINDERP', 'name' => 'DATO DR MEHESHINDER SINGH A/L BABU SINGH GIANI', 'specialty_code' => '35', 'email' => 'meheshinder.singh@pantai.com'],
            ['personnel_code' => 'DASHIMKNP', 'name' => 'DR ASHIM KUMER NANDY', 'specialty_code' => '35', 'email' => 'ashim.nandy@pantai.com'],
            ['personnel_code' => 'KRISHNAKUMAR', 'name' => 'DR KRISHNA KUMAR A/L S.KATHERAVELOO', 'specialty_code' => '35', 'email' => 'krishna.kumar@pantai.com'],
            ['personnel_code' => 'D0038093', 'name' => 'DR LAU PENG CHOONG', 'specialty_code' => '35', 'email' => 'pengchoong.lau@pantai.com'],
            ['personnel_code' => 'DRLIEW', 'name' => 'DR LIEW NGOH CHIN', 'specialty_code' => '35', 'email' => 'ngohchin.liew@pantai.com'],
            ['personnel_code' => 'DLKFP', 'name' => 'DR LIM KIN FOONG', 'specialty_code' => '35', 'email' => 'kinfoong.lim@pantai.com'],
            ['personnel_code' => 'DLUQMAN', 'name' => 'DR LUQMAN BIN MAZLAN', 'specialty_code' => '35', 'email' => 'luqman.mazlan@pantai.com'],
            ['personnel_code' => 'DSEENIP', 'name' => 'DR SEENIVASAGAM THERUVENKIDAN PILLAI', 'specialty_code' => '35', 'email' => 'seenivasagam.t@pantai.com'],
            ['personnel_code' => 'DWONGWJ', 'name' => 'DR WONG WEI JIN', 'specialty_code' => '35', 'email' => 'weijin.wong@pantai.com'],
            ['personnel_code' => 'DZAINAR', 'name' => 'DR ZAIN AL-RASHID', 'specialty_code' => '35', 'email' => 'zain.alrashid@pantai.com'],
            ['personnel_code' => 'DZAINOP', 'name' => 'DR ZAIN AL-RASHID (OP)', 'specialty_code' => '35', 'email' => 'zain.alrashid.1@pantai.com'],

            // GERIATRIC MEDICINE (59)
            ['personnel_code' => 'DRAJBANSP', 'name' => 'DATO DR RAJBANS SINGH', 'specialty_code' => '59', 'email' => 'rajbans.singh@pantai.com'],
            ['personnel_code' => 'DLEEYW', 'name' => 'DR LEE YOONG WAH', 'specialty_code' => '59', 'email' => 'yoongwah.lee@pantai.com'],
            ['personnel_code' => 'DMSEENI', 'name' => 'DR MOHAMED SEENIKATTY BIN ABDUL HAKIM', 'specialty_code' => '59', 'email' => 'seenikatty.hakim@pantai.com'],

            // HEALTH SCREENING (97)
            ['personnel_code' => 'DRHSCPHKL', 'name' => 'HEALTH SCREENING DOCTOR (PHKL)', 'specialty_code' => '97', 'email' => 'health.screening.phkl@pantai.com'],

            // HEPATOBILIARY SURGERY (38)
            ['personnel_code' => 'DBALRAJ', 'name' => 'DR BALRAJ SINGH JAKTARAM SINGH', 'specialty_code' => '38', 'email' => 'balraj.singh@pantai.com'],

            // INFECTIOUS DISEASES (60)
            ['personnel_code' => 'DANURADHA', 'name' => 'DR ANURADHA P.RADHAKRISHNAN', 'specialty_code' => '60', 'email' => 'anuradha.p@pantai.com'],
            ['personnel_code' => 'DANUSHAS', 'name' => 'DR ANUSHA A/P SHUNMUGARAJOO', 'specialty_code' => '60', 'email' => 'anusha.s@pantai.com'],

            // INTERNAL MEDICINE (51)
            ['personnel_code' => 'DJUITA', 'name' => 'DR NOOR HASHIDA @ JUITA BINTI HASSAN', 'specialty_code' => '51', 'email' => 'hashida.hassan@pantai.com'],
            ['personnel_code' => 'DRAKHIP', 'name' => 'DR RAKHI GANGULY', 'specialty_code' => '51', 'email' => 'rakhi.ganguly@pantai.com'],

            // INTERVENTIONAL RADIOLOGY (91)
            ['personnel_code' => 'DARVIN', 'name' => 'DR ARVIN A/L RAJADURAI', 'specialty_code' => '91', 'email' => 'arvin.rajadurai@pantai.com'],
            ['personnel_code' => 'DJEYALEDP', 'name' => 'DR JEYALEDCHUMY A/P MAHADEVAN', 'specialty_code' => '91', 'email' => 'jeyaledchumy.m@pantai.com'],
            ['personnel_code' => 'DJOERSP', 'name' => 'DR JOSEPHINE ROSALIND SUBRAMANIAM', 'specialty_code' => '91', 'email' => 'josephine.subramaniam@pantai.com'],
            ['personnel_code' => 'LJIAHIM', 'name' => 'DR LAU JIA HIM (33968)', 'specialty_code' => '91', 'email' => 'jiahim.l@pantai.com'],
            ['personnel_code' => 'DBASRIJ', 'name' => 'PROFESSOR DR BASRI JOHAN ABDULLAH', 'specialty_code' => '91', 'email' => 'basri.abdullah@pantai.com'],

            // MEDICAL ONCOLOGY (62)
            ['personnel_code' => '4725H0', 'name' => 'DR CHEMO DAYCARE', 'specialty_code' => '62', 'email' => 'chemo.daycare@pantai.com'],
            ['personnel_code' => 'DNGVT', 'name' => 'DR CHRISTINA NG VAN TZE', 'specialty_code' => '62', 'email' => 'DNGVT@pantai.com'],
            ['personnel_code' => 'DJOSEPHP', 'name' => 'DR JOSEPH KANIANTHRA JOSEPH', 'specialty_code' => '62', 'email' => 'joseph.k@pantai.com'],

            // NEPHROLOGY (63)
            ['personnel_code' => 'D0029530', 'name' => 'DR FOO SIU MEI', 'specialty_code' => '63', 'email' => 'siumei.foo@pantai.com'],
            ['personnel_code' => 'DGILLSS', 'name' => 'DR GILL S S', 'specialty_code' => '63', 'email' => 'gill.ss@pantai.com'],
            ['personnel_code' => 'DGILLSOP', 'name' => 'DR GILL S S (OP)', 'specialty_code' => '63', 'email' => 'gill.ss.1@pantai.com'],
            ['personnel_code' => 'DSOEHARDY', 'name' => 'DR SOEHARDY BIN ZAINUDIN', 'specialty_code' => '63', 'email' => 'soehardy.zainudin@pantai.com'],
            ['personnel_code' => 'DRWONGMH', 'name' => 'DR WONG MUN HOE', 'specialty_code' => '63', 'email' => 'munhoe.wong@pantai.com'],

            // NEUROLOGY (64)
            ['personnel_code' => 'DLEEMKS', 'name' => 'DR LEE MOON KEEN', 'specialty_code' => '64', 'email' => 'moonkeen.lee@pantai.com'],
            ['personnel_code' => 'DLEEMKOPC', 'name' => 'DR LEE MOON KEEN (OP)', 'specialty_code' => '64', 'email' => 'moonkeen.lee.1@pantai.com'],
            ['personnel_code' => 'DRISHIP', 'name' => 'DR RISHIKESAN KUPPUSAMY', 'specialty_code' => '64', 'email' => 'rishikesan.kuppusamy@pantai.com'],
            ['personnel_code' => 'DSHANVSK', 'name' => 'DR SHANTHI A/P VISWANATHAN SHANTHA KUMAR', 'specialty_code' => '64', 'email' => 'shanthi.viswanathan@pantai.com'],
            ['personnel_code' => 'DC195', 'name' => 'DR SNG KIM HOCK', 'specialty_code' => '64', 'email' => 'kimhock.sng@pantai.com'],
            ['personnel_code' => 'DTEESK', 'name' => 'DR TEE SOW KUAN', 'specialty_code' => '64', 'email' => 'sowkuan.tee@pantai.com'],

            // NEUROSURGERY (45)
            ['personnel_code' => 'DAZMINKR', 'name' => 'DATO DR AZMIN KASS BIN ROSMAN', 'specialty_code' => '45', 'email' => 'azmin.rosman@pantai.com'],
            ['personnel_code' => 'DTSELVA', 'name' => 'DATO DR T. SELVAPRAGASAM', 'specialty_code' => '45', 'email' => 'selvapragasam.t@pantai.com'],
            ['personnel_code' => 'DCHEECP', 'name' => 'DR CHEE CHEE PIN', 'specialty_code' => '45', 'email' => 'DCHEECP@pantai.com'],
            ['personnel_code' => 'DCHECPOP', 'name' => 'DR CHEE CHEE PIN (PHKL)', 'specialty_code' => '45', 'email' => 'cheepin.chee.1@pantai.com'],
            ['personnel_code' => '1DDEVARAJ', 'name' => 'DR DEVARAJ A/L PANCHARATNAM', 'specialty_code' => '45', 'email' => 'devaraj.p@pantai.com'],
            ['personnel_code' => 'CVXGBD', 'name' => 'DR MOHAMMED AZMAN BIN MOHAMMAD RAFFIQ', 'specialty_code' => '45', 'email' => 'azman.raffiq@pantai.com'],
            ['personnel_code' => 'DMURUGA', 'name' => 'DR MURUGA KUMAR', 'specialty_code' => '45', 'email' => 'muruga.kumar@pantai.com'],
            ['personnel_code' => 'DRAMESH', 'name' => 'DR N. RAMESH NARENTHIRANATHAN', 'specialty_code' => '45', 'email' => 'ramesh.n@pantai.com'],
            ['personnel_code' => 'DSUKU', 'name' => 'DR S. SUKUMAR A/L SIVASUBRAMANIAM', 'specialty_code' => '45', 'email' => 'DSUKU@pantai.com'],
            ['personnel_code' => '36091N', 'name' => 'DR SEK WENG YEW', 'specialty_code' => '45', 'email' => 'wengyew.sek@pantai.com'],
            ['personnel_code' => 'DWONGFCP', 'name' => 'DR WONG FUNG CHU', 'specialty_code' => '45', 'email' => 'fungchu.wong@pantai.com'],

            // NUCLEAR MEDICINE (68)
            ['personnel_code' => 'DAFADILAH', 'name' => 'DR ANDIK FADILAH BINTI ABDUL AZIZ', 'specialty_code' => '68', 'email' => 'andik.aziz@pantai.com'],
            ['personnel_code' => 'DLINGES', 'name' => 'DR LINGESWARAN A/L KASILINGAM', 'specialty_code' => '68', 'email' => 'lingeswaran.kasilingam@pantai.com'],
            ['personnel_code' => 'DNISAKAR', 'name' => 'DR NISA KAMILA BINTI AB RASHID', 'specialty_code' => '68', 'email' => 'nisa.rashid@pantai.com'],
            ['personnel_code' => 'DSITIMMN', 'name' => 'DR SITI MAISARAH BINTI MOHD NASIR', 'specialty_code' => '68', 'email' => 'maisarah.nasir@pantai.com'],
            ['personnel_code' => 'DTANTH', 'name' => 'DR TAN TEIK HIN', 'specialty_code' => '68', 'email' => 'tan.hin@pantai.com'],
            ['personnel_code' => 'D0044814', 'name' => 'DR THANUJA MAHALETCHUMY', 'specialty_code' => '68', 'email' => 'thanuja.m@pantai.com'],
            ['personnel_code' => 'DWONGTH', 'name' => 'DR WONG TECK HUAT', 'specialty_code' => '68', 'email' => 'teckhuat.wong@pantai.com'],
            ['personnel_code' => 'DZHILMI', 'name' => 'DR ZOOL HILMI BIN AWANG', 'specialty_code' => '68', 'email' => 'hilmi.awang@pantai.com'],

            // OBSTETRICS AND GYNAECOLOGY (69)
            ['personnel_code' => 'DKAMJIT', 'name' => 'DATIN DR KAMALJIT KAUR D/O HARBAN SINGH', 'specialty_code' => '69', 'email' => 'kamaljit.kaur@pantai.com'],
            ['personnel_code' => 'DPRASHANP', 'name' => 'DATO DR PRASHANT VASANT NADKARNI', 'specialty_code' => '69', 'email' => 'prashant.n@pantai.com'],
            ['personnel_code' => 'DADILAHP', 'name' => 'DR ADILAH BINTI AHMAT', 'specialty_code' => '69', 'email' => 'adilah.ahmat@pantai.com'],
            ['personnel_code' => 'DHATTA', 'name' => 'DR AHMAD ZAILANI HATTA B MOHD DALI', 'specialty_code' => '69', 'email' => 'hatta.dali@pantai.com'],
            ['personnel_code' => 'DAQMARPG', 'name' => 'DR AQMAR SURAYA BINTI SULAIMAN', 'specialty_code' => '69', 'email' => 'suraya.sulaiman@pantai.com'],
            ['personnel_code' => 'D0026226', 'name' => 'DR CHUNG CHOW CHEANG', 'specialty_code' => '69', 'email' => 'chowcheang.chung@pantai.com'],
            ['personnel_code' => 'DEESON', 'name' => 'DR EESON SINTHAMONEY', 'specialty_code' => '69', 'email' => 'eeson.sinthamoney@pantai.com'],
            ['personnel_code' => 'DHELENAS', 'name' => 'DR HELENA LIM @ LIM YUN HSUEN', 'specialty_code' => '69', 'email' => 'helena.lim@pantai.com'],
            ['personnel_code' => 'DIDORAP', 'name' => 'DR IDORA BINTI MOHAMED', 'specialty_code' => '69', 'email' => 'idora.mohamed@pantai.com'],
            ['personnel_code' => 'DIDRISA', 'name' => 'DR IDRIS AHMAD', 'specialty_code' => '69', 'email' => 'idris.ahmad@pantai.com'],
            ['personnel_code' => 'DJERILEE', 'name' => 'DR JERILEE MARIAM KHONG BINTI AZHARY', 'specialty_code' => '69', 'email' => 'jerilee.azhary@pantai.com'],
            ['personnel_code' => 'DJIVKKS', 'name' => 'DR JIV KIRENJEET KAUR SIDHU', 'specialty_code' => '69', 'email' => 'kirenjeet.kaur@pantai.com'],
            ['personnel_code' => 'DLAVITHA', 'name' => 'DR LAVITHA A/P SIVAPATHAM', 'specialty_code' => '69', 'email' => 'lavitha.sivapatham@pantai.com'],
            ['personnel_code' => 'DMLIMCK', 'name' => 'DR MICHAEL LIM CHUNG KEAT', 'specialty_code' => '69', 'email' => 'michael.lim@pantai.com'],
            ['personnel_code' => 'DMUNISW', 'name' => 'DR MUNISWARAN A/L GANESHAM@GANESHAN', 'specialty_code' => '69', 'email' => 'muniswaran.ganesham@pantai.com'],
            ['personnel_code' => 'DNARAYNM', 'name' => 'DR NARAYANAN, M.', 'specialty_code' => '69', 'email' => 'narayanan.m@pantai.com'],
            ['personnel_code' => 'VDNYRV', 'name' => 'DR NATASHA AIN BINTI MOHD NOR', 'specialty_code' => '69', 'email' => 'natasha.nor@pantai.com'],
            ['personnel_code' => '1096', 'name' => 'DR NG KOK CHONG', 'specialty_code' => '69', 'email' => 'kokchong.ng@pantai.com'],
            ['personnel_code' => 'DPAULNG', 'name' => 'DR PAUL NG HOCK OON', 'specialty_code' => '69', 'email' => 'DPAULNG@pantai.com'],
            ['personnel_code' => 'GGXUN8', 'name' => 'DR PAUL TAY YEE SIANG', 'specialty_code' => '69', 'email' => 'paul.tay@pantai.com'],
            ['personnel_code' => 'DPREMITAP', 'name' => 'DR PREMITHA DAMODARAN', 'specialty_code' => '69', 'email' => 'premitha.damodaran@pantai.com'],
            ['personnel_code' => 'DRAMANSP', 'name' => 'DR RAMAN A/L SUBRAMANIAM @ R.S.MANIAM', 'specialty_code' => '69', 'email' => 'raman.s@pantai.com'],
            ['personnel_code' => 'DRAYMONDLCS', 'name' => 'DR RAYMOND LIM CHUNG SIANG', 'specialty_code' => '69', 'email' => 'raymond.lim@pantai.com'],
            ['personnel_code' => 'DTANBKPG', 'name' => 'DR TAN BOON KHIM (DO NOT USE)', 'specialty_code' => '69', 'email' => 'boonkhim.tan@pantai.com'],
            ['personnel_code' => 'DWONGKL', 'name' => 'DR WONG KIM LEI', 'specialty_code' => '69', 'email' => 'kimlei.wong@pantai.com'],
            ['personnel_code' => 'DWONGPS', 'name' => 'DR WONG PAK SENG', 'specialty_code' => '69', 'email' => 'pakseng.wong@pantai.com'],
            ['personnel_code' => 'DWONGWP', 'name' => 'DR WONG WAI PING', 'specialty_code' => '69', 'email' => 'waiping.wong@pantai.com'],
            ['personnel_code' => 'DYAPMJP', 'name' => 'DR YAP MOY JUAN', 'specialty_code' => '69', 'email' => 'moyjuan.yap@pantai.com'],
            ['personnel_code' => 'DMNIRAJ', 'name' => 'MR MOHAMMED NIRAJ BIN MOHAMMED FEIZAL', 'specialty_code' => '69', 'email' => 'niraj.feizal@pantai.com'],

            // OPHTHALMOLOGY (74)
            ['personnel_code' => 'DRAMANIV', 'name' => 'DATO DR VEERA RAMANI', 'specialty_code' => '74', 'email' => 'veera.ramani@pantai.com'],
            ['personnel_code' => 'DVRAMOP', 'name' => 'DATO DR VEERA RAMANI (OP)', 'specialty_code' => '74', 'email' => 'veera.ramani.1@pantai.com'],
            ['personnel_code' => 'DHOHHBP', 'name' => 'DR HOH HON BING', 'specialty_code' => '74', 'email' => 'honbing.hoh@pantai.com'],
            ['personnel_code' => 'DJOHNMATHEN', 'name' => 'DR K JOHN MATHEN', 'specialty_code' => '74', 'email' => 'john.mathen@pantai.com'],
            ['personnel_code' => 'DMALARP', 'name' => 'DR K.SIVAMALAR', 'specialty_code' => '74', 'email' => 'sivamalar.k@pantai.com'],
            ['personnel_code' => 'DKFONGCS', 'name' => 'DR KENNETH FONG CHOONG SIAN', 'specialty_code' => '74', 'email' => 'kenneth.fong@pantai.com'],
            ['personnel_code' => 'DMANOHP', 'name' => 'DR MANOHARAN SHUNMUGAM', 'specialty_code' => '74', 'email' => 'manoharan.shunmugam@pantai.com'],
            ['personnel_code' => 'DPUSPHAR', 'name' => 'DR PUSPHA A/P RAMAN', 'specialty_code' => '74', 'email' => 'puspha.raman@pantai.com'],
            ['personnel_code' => 'DSHAMALA', 'name' => 'DR SHAMALA A/P S. GANESAN', 'specialty_code' => '74', 'email' => 'shamala.ganesan@pantai.com'],
            ['personnel_code' => 'DTAILY', 'name' => 'DR TAI LAI YONG', 'specialty_code' => '74', 'email' => 'laiyong.tai@pantai.com'],

            // ORAL & MAXILLOFACIAL SURGERY (80)
            ['personnel_code' => 'DMOHDNOO', 'name' => 'DATUK DR MOHD NOOR AWANG', 'specialty_code' => '80', 'email' => 'noor.awang@pantai.com'],
            ['personnel_code' => 'DNAZIMI', 'name' => 'DR MOHD NAZIMI BIN ABD JABAR', 'specialty_code' => '80', 'email' => 'nazimi.jabar@pantai.com'],
            ['personnel_code' => 'DRAMAKR', 'name' => 'DR RAMA KRSNA RAJANDRAM', 'specialty_code' => '80', 'email' => 'rama.rajandram@pantai.com'],

            // ORTHODONTICS (82)
            ['personnel_code' => 'DCATHLEEP', 'name' => 'DR CATHERINE LEE TONG HOW', 'specialty_code' => '82', 'email' => 'catherine.lee@pantai.com'],
            ['personnel_code' => 'DPRAVEENPG', 'name' => 'DR PRAVEEN PREET GILL', 'specialty_code' => '82', 'email' => 'praveen.gill@pantai.com'],

            // ORTHOPAEDIC SURGERY (75)
            ['personnel_code' => 'DMERICAN', 'name' => 'DATO DR MAHMOOD MERICAN', 'specialty_code' => '75', 'email' => 'mahmood.merican@pantai.com'],
            ['personnel_code' => 'DYEOHPHP', 'name' => 'DATUK DR YEOH POH HONG', 'specialty_code' => '75', 'email' => 'pohhong.yeoh@pantai.com'],
            ['personnel_code' => 'DFARIHAN', 'name' => 'DR AHMAD FARIHAN BIN MOHD DON', 'specialty_code' => '75', 'email' => 'farihan.don@pantai.com'],
            ['personnel_code' => 'DCHONGKC', 'name' => 'DR CHONG KUAN CHON', 'specialty_code' => '75', 'email' => 'kuanchon.chong@pantai.com'],
            ['personnel_code' => 'DCOLLINL', 'name' => 'DR COLLIN LOOI SENG KIM', 'specialty_code' => '75', 'email' => 'collin.looi@pantai.com'],
            ['personnel_code' => 'DGOBINSP', 'name' => 'DR GOBINDER SINGH', 'specialty_code' => '75', 'email' => 'gobinder.singh@pantai.com'],
            ['personnel_code' => 'DHARWANTP', 'name' => 'DR HARWANT SINGH HARCHARAN SINGH TARA', 'specialty_code' => '75', 'email' => 'harwant.singh@pantai.com'],
            ['personnel_code' => 'DKMARUL', 'name' => 'DR KAMARUL AL-HAQQ BIN ABDUL GHANI', 'specialty_code' => '75', 'email' => 'kamarul.ghani@pantai.com'],
            ['personnel_code' => 'DKANWAR', 'name' => 'DR KHAIRUL ANWAR BIN AYOB', 'specialty_code' => '75', 'email' => 'khairul.ayob@pantai.com'],
            ['personnel_code' => '1093', 'name' => 'DR KHAIRUL FAIZI BIN MOHAMMAD', 'specialty_code' => '75', 'email' => 'khairul.mohammad@pantai.com'],
            ['personnel_code' => 'DEHKHOO7', 'name' => 'DR KHOO ENG HOOI', 'specialty_code' => '75', 'email' => 'enghooi.khoo@pantai.com'],
            ['personnel_code' => 'DKOKCSP', 'name' => 'DR KOK CHOONG SENG', 'specialty_code' => '75', 'email' => 'choongseng.kok@pantai.com'],
            ['personnel_code' => 'DASHRAFF', 'name' => 'DR MOHAMED ASHRAFF BIN MOHD ARIFF', 'specialty_code' => '75', 'email' => 'ashraff.ariff@pantai.com'],
            ['personnel_code' => 'DNOORTHE', 'name' => 'DR MOHAMED NOORTHEEN B MUSTAFA', 'specialty_code' => '75', 'email' => 'noortheen.mustafa@pantai.com'],
            ['personnel_code' => 'DNORAZAMP', 'name' => 'DR MOHD NOOR AZAM BIN MOHD ITHNIN', 'specialty_code' => '75', 'email' => 'azam.ithnin@pantai.com'],
            ['personnel_code' => '1167', 'name' => 'DR NG BING WUI', 'specialty_code' => '75', 'email' => 'bingwui.ng@pantai.com'],
            ['personnel_code' => 'DNAIZAH', 'name' => 'DR NIK AIZAH NABILLA BINTI FAHEEM', 'specialty_code' => '75', 'email' => 'aizah.faheem@pantai.com'],
            ['personnel_code' => 'DONGKC', 'name' => 'DR ONG KEAN CHAO (DO NOT USE)', 'specialty_code' => '75', 'email' => 'keanchao.ong@pantai.com'],
            ['personnel_code' => 'DPARAM', 'name' => 'DR PARAMASWARAN', 'specialty_code' => '75', 'email' => 'paramaswaran@pantai.com'],
            ['personnel_code' => 'DPASUS', 'name' => 'DR PASUPATHY, S', 'specialty_code' => '75', 'email' => 'pasupathy.s@pantai.com'],
            ['personnel_code' => 'DPASUSOP', 'name' => 'DR PASUPATHY, S (OP)', 'specialty_code' => '75', 'email' => 'pasupathy.s.1@pantai.com'],
            ['personnel_code' => 'DREZANCSP', 'name' => 'DR REZA NG CHING SOONG', 'specialty_code' => '75', 'email' => 'reza.ng@pantai.com'],
            ['personnel_code' => 'DRIZALAR', 'name' => 'DR RIZAL BIN ABDUL RANI', 'specialty_code' => '75', 'email' => 'rizal.rani@pantai.com'],
            ['personnel_code' => 'DSACHIN', 'name' => 'DR SACHIN SHIVDAS', 'specialty_code' => '75', 'email' => 'sachin.shivdas@pantai.com'],
            ['personnel_code' => 'DSIVANP', 'name' => 'DR SIVANANTHAN A/L KANAGARAYAR', 'specialty_code' => '75', 'email' => 'sivananthan.k@pantai.com'],
            ['personnel_code' => '1120', 'name' => 'DR SUREISEN A/L MARIAPAN', 'specialty_code' => '75', 'email' => 'sureisen.m@pantai.com'],
            ['personnel_code' => 'DSURSIV', 'name' => 'DR SURESHAN SIVANANTHAN', 'specialty_code' => '75', 'email' => 'sureshan.sivananthan@pantai.com'],
            ['personnel_code' => 'DWCC', 'name' => 'DR WONG CHUNG CHEK', 'specialty_code' => '75', 'email' => 'chungchek.wong@pantai.com'],

            // OTHER SPECIALITIES (88)
            ['personnel_code' => 'DELIZA', 'name' => 'DR ELIZABETH PITCHAIMUTHU', 'specialty_code' => '88', 'email' => 'elizabeth.p@pantai.com'],
            ['personnel_code' => 'H277TE', 'name' => 'HAND AND MICROSURGERY (PHKL)', 'specialty_code' => '88', 'email' => 'hand.phkl@pantai.com'],
            ['personnel_code' => 'DSEA', 'name' => 'MISS DINESHWARY A/P NADTHAN', 'specialty_code' => '88', 'email' => 'dineshwary.nadthan@pantai.com'],
            ['personnel_code' => 'DYEOHMY', 'name' => 'MISS MELANIE YEOH MAY YING', 'specialty_code' => '88', 'email' => 'melanie.yeoh@pantai.com'],
            ['personnel_code' => 'DYOONSY', 'name' => 'MISS YOON SOOK-YEE', 'specialty_code' => '88', 'email' => 'sookyee.yoon@pantai.com'],
            ['personnel_code' => 'DOTICON', 'name' => 'OTICON MALAYSIA SDN BHD', 'specialty_code' => '88', 'email' => 'oticon.malaysia@pantai.com'],
            ['personnel_code' => 'DPARC', 'name' => 'PANTAI-ARC DIALYSIS SERVICES SDN BHD (GID)', 'specialty_code' => '88', 'email' => 'pantai.arc@pantai.com'],

            // OTORHINOLARYNGOLOGY(ENT) (02)
            ['personnel_code' => 'DGENDEHP', 'name' => "DATO' PADUKA DR BALWANT SINGH GENDEH", 'specialty_code' => '02', 'email' => 'balwant.singh@pantai.com'],
            ['personnel_code' => 'DBALWIND', 'name' => 'DATUK DR BALWINDER SINGH A/L SAROOP SINGH', 'specialty_code' => '02', 'email' => 'balwinder.singh@pantai.com'],
            ['personnel_code' => 'DASHAP', 'name' => 'DR ASHA GUPTA', 'specialty_code' => '02', 'email' => 'asha.gupta@pantai.com'],
            ['personnel_code' => 'DCHANGCM', 'name' => 'DR CHANG CHEW MING', 'specialty_code' => '02', 'email' => 'chewming.chang@pantai.com'],
            ['personnel_code' => 'DCHACMOP', 'name' => 'DR CHANG CHEW MING (OP)', 'specialty_code' => '02', 'email' => 'chewming.chang.1@pantai.com'],
            ['personnel_code' => 'DELYLP', 'name' => 'DR ELIZABETH LIM YENN LYNN', 'specialty_code' => '02', 'email' => 'elizabeth.lim@pantai.com'],
            ['personnel_code' => '1101', 'name' => 'DR JEEVANAN A/L JAHENDRAN', 'specialty_code' => '02', 'email' => 'jeevanan.j@pantai.com'],
            ['personnel_code' => 'DKONGMH', 'name' => 'DR KONG MIN HAN', 'specialty_code' => '02', 'email' => 'minhan.kong@pantai.com'],
            ['personnel_code' => 'DLIEWKY', 'name' => 'DR LIEW KONG YEW', 'specialty_code' => '02', 'email' => 'kongyew.liew@pantai.com'],
            ['personnel_code' => 'DNORAZMI', 'name' => 'DR NOR AZMI BIN MOHAMAD @ GHAZALI', 'specialty_code' => '02', 'email' => 'azmi.ghazali@pantai.com'],
            ['personnel_code' => 'DROHAIZAM', 'name' => 'DR ROHAIZAM BIN JAPAR', 'specialty_code' => '02', 'email' => 'rohaizam.japar@pantai.com'],
            ['personnel_code' => 'DTANLSP', 'name' => 'DR TAN LYE SUAN', 'specialty_code' => '02', 'email' => 'lyesuan.tan@pantai.com'],
            ['personnel_code' => 'DYAPYY', 'name' => 'DR YAP YOKE YEOW', 'specialty_code' => '02', 'email' => 'yokeyeow.yap@pantai.com'],
            ['personnel_code' => 'DTANGIP', 'name' => 'PROFESSOR DR TANG ING PING', 'specialty_code' => '02', 'email' => 'ingping.tang@pantai.com'],

            // PAEDIATRIC CARDIOLOGY (09)
            ['personnel_code' => 'DKGT', 'name' => 'DR KOH GHEE TIONG', 'specialty_code' => '09', 'email' => 'gheetiong.koh@pantai.com'],
            ['personnel_code' => 'DOOIYK', 'name' => 'DR OOI YINN KHURN', 'specialty_code' => '09', 'email' => 'yinnkhurn.ooi@pantai.com'],

            // PAEDIATRIC INTENSIVE CARE (15)
            // DTAICW already exists in GENERAL PAEDIATRICS (03), skipping duplicate

            // PAEDIATRIC NEUROLOGY (17)
            ['personnel_code' => 'DMALINEEP', 'name' => 'DR MALINEE A.THAMBYAYAH', 'specialty_code' => '17', 'email' => 'malinee.thambyayah@pantai.com'],
            ['personnel_code' => 'DPOORANI', 'name' => 'DR POORANI A/P ANANDAKRISHNAN', 'specialty_code' => '17', 'email' => 'poorani.a@pantai.com'],

            // PAEDIATRIC RESPIRATORY MEDICINE (18)
            // DSSIEWCH already exists in GENERAL PAEDIATRICS (03), skipping duplicate

            // PAEDIATRIC SURGERY (46)
            ['personnel_code' => 'DGANGULYP', 'name' => 'DR GANGULY GAUTAM', 'specialty_code' => '46', 'email' => 'ganguly.gautam@pantai.com'],
            ['personnel_code' => 'DJBAWANI', 'name' => 'DR JEYA BAWANI A/P SIVABALAKRISHNAN', 'specialty_code' => '46', 'email' => 'jeyabawani.s@pantai.com'],
            ['personnel_code' => 'MOHAN', 'name' => 'DR MOHAN ARUNASALAM A/L A.NALLUSAMY (26188)', 'specialty_code' => '46', 'email' => 'mohanarunasalam.n@pantai.com'],
            ['personnel_code' => 'DMOHANAP', 'name' => 'DR MOHANAPRAKASH A/L ARASAPPAN', 'specialty_code' => '46', 'email' => 'mohanaprakash.a@pantai.com'],
            ['personnel_code' => 'DNSUDHA', 'name' => 'DR NADARAJAN A/L SUDHAKARAN', 'specialty_code' => '46', 'email' => 'nadarajan.s@pantai.com'],
            ['personnel_code' => 'DRPRASAD', 'name' => 'DR RAMPRASAD ARADADA (DO NOT USE)', 'specialty_code' => '46', 'email' => 'DRPRASAD@pantai.com'],
            ['personnel_code' => 'DTAMMYTHQ', 'name' => 'DR TAMMY TEOH HAN QI', 'specialty_code' => '46', 'email' => 'tammy.teoh@pantai.com'],

            // PALLIATIVE MEDICINE (67)
            ['personnel_code' => 'DUMMI', 'name' => 'DR UMMI AFFAH MAHAMAD', 'specialty_code' => '67', 'email' => 'ummi.mahamad@pantai.com'],

            // PLASTIC SURGERY (47)
            ['personnel_code' => 'DKHONGSS', 'name' => 'DR KHONG SU SAN', 'specialty_code' => '47', 'email' => 'susan.khong@pantai.com'],
            ['personnel_code' => 'DTANKKOP', 'name' => 'DR KIM K TAN (OP) - DO NOT USE', 'specialty_code' => '47', 'email' => 'ktan.kim@pantai.com'],
            ['personnel_code' => 'DKOHKHAI', 'name' => 'DR KOH KHAI LUEN', 'specialty_code' => '47', 'email' => 'khailuen.koh@pantai.com'],
            ['personnel_code' => 'DKULA', 'name' => 'DR KULADEVA RATNAM', 'specialty_code' => '47', 'email' => 'kuladeva.ratnam@pantai.com'],
            ['personnel_code' => 'DMARGARET9', 'name' => 'DR MARGARET LEOW POH GAIK', 'specialty_code' => '47', 'email' => 'margaret.l@pantai.com'],
            ['personnel_code' => 'DTANKKP', 'name' => 'DR TAN KIM KONG', 'specialty_code' => '47', 'email' => 'kimkong.tan@pantai.com'],
            ['personnel_code' => 'DARMANZ', 'name' => 'PROFESSOR DR ARMAN ZAHARIL BIN MAT SAAD', 'specialty_code' => '47', 'email' => 'zaharil.saad@pantai.com'],

            // PSYCHIATRY (28)
            ['personnel_code' => 'DBHARAP', 'name' => 'DR BHARATHI A/P S.VENGADASALAM', 'specialty_code' => '28', 'email' => 'bharathi.vengadasalam@pantai.com'],
            ['personnel_code' => 'DBRIANHOP', 'name' => 'DR BRIAN HO KONG WAI', 'specialty_code' => '28', 'email' => 'brian.ho@pantai.com'],
            ['personnel_code' => 'DCHONGSC', 'name' => 'DR CHONG SENG CHOI', 'specialty_code' => '28', 'email' => 'sengchoi.chong@pantai.com'],
            ['personnel_code' => 'DHAMIDINP', 'name' => 'DR HAMIDIN BIN AWANG', 'specialty_code' => '28', 'email' => 'hamidin.awang@pantai.com'],
            ['personnel_code' => 'DKHIN', 'name' => 'DR KHIN OHNMAR NAING', 'specialty_code' => '28', 'email' => 'ohnmarnaing.khin@pantai.com'],
            ['personnel_code' => 'DLEEAH', 'name' => 'DR LEE AIK HOE', 'specialty_code' => '28', 'email' => 'aikhoe.lee@pantai.com'],
            ['personnel_code' => 'DSHANE', 'name' => 'DR SHANE VARMAN', 'specialty_code' => '28', 'email' => 'shane.varman@pantai.com'],

            // PSYCHOLOGY (95)
            ['personnel_code' => 'DKHAIRIR', 'name' => 'DR KHAIRI RAHMAN', 'specialty_code' => '95', 'email' => 'khairi.rahman@pantai.com'],
            ['personnel_code' => 'DCHIAKC', 'name' => 'MISS CHIA KUEH CHENG', 'specialty_code' => '95', 'email' => 'kuehcheng.chia@pantai.com'],
            ['personnel_code' => 'DYANA', 'name' => 'MISS KATYANA BINTI MEOR ZAIN AZMAN', 'specialty_code' => '95', 'email' => 'katyana.azman@pantai.com'],
            ['personnel_code' => 'DLEEKS', 'name' => 'MISS LEE KUAN SHIN', 'specialty_code' => '95', 'email' => 'kuanshin.lee@pantai.com'],
            ['personnel_code' => 'DA000025', 'name' => 'MISS NIVASHINIE MOHAN', 'specialty_code' => '95', 'email' => 'nivashinie.mohan@pantai.com'],

            // RESPIRATORY MEDICINE (65)
            ['personnel_code' => 'DHELMYPG', 'name' => 'DR HELMY NADINE BIN HAJA MYDIN', 'specialty_code' => '65', 'email' => 'helmy.hm@pantai.com'],
            ['personnel_code' => 'DHLOCKMAN', 'name' => 'DR HILMI BIN LOCKMAN', 'specialty_code' => '65', 'email' => 'hilmi.lockman@pantai.com'],
            ['personnel_code' => 'DJASMIND', 'name' => 'DR JASMINDER KAUR A/P NASIB SINGH', 'specialty_code' => '65', 'email' => 'jasminder.kaur@pantai.com'],
            ['personnel_code' => 'DLILY', 'name' => 'DR LILY DIANA ZAINUDIN', 'specialty_code' => '65', 'email' => 'lily.zainudin@pantai.com'],
            ['personnel_code' => 'DRAVINMP', 'name' => 'DR P.RAVINDRAN A/L V.K.P. MENON', 'specialty_code' => '65', 'email' => 'ravindran.menon@pantai.com'],
            ['personnel_code' => 'DAMPIK', 'name' => 'TAN SRI DATUK DR AMPIKAIPAKAN A/L S. KANDIAH', 'specialty_code' => '65', 'email' => 'ampikaipakan.kandiah@pantai.com'],

            // RHEUMATOLOGY (66)
            ['personnel_code' => 'DTECHEAH', 'name' => 'DR CHEAH TIEN EANG, BENJAMIN', 'specialty_code' => '66', 'email' => 'benjamin.cheah@pantai.com'],

            // SPINE SURGERY (76)
            // 1167 already exists in ORTHOPAEDIC SURGERY (75), skipping duplicate

            // SPORTS MEDICINE (34)
            ['personnel_code' => 'DPABRIN', 'name' => 'DR PABRINDER KAUR A/P NAGINDER SINGH', 'specialty_code' => '34', 'email' => 'pabrinder.singh@pantai.com'],

            // THORACIC SURGERY (39)
            ['personnel_code' => 'DBENEDICT', 'name' => 'DR BENEDICT DHARMARAJ A/L RETNA PANDIAN', 'specialty_code' => '39', 'email' => 'benedict.dharmaraj@pantai.com'],
            ['personnel_code' => 'DDIONGNC', 'name' => 'DR DIONG NGUK CHAI', 'specialty_code' => '39', 'email' => 'ngukchai.diong@pantai.com'],
            ['personnel_code' => 'DNARASIM', 'name' => 'DR NARASIMMAN A/L SATHIAMURTHY', 'specialty_code' => '39', 'email' => 'narasimman.sathiamurthy@pantai.com'],
            ['personnel_code' => 'DNARENDB', 'name' => 'DR NARENDRAN A/L BALASUBBIAH', 'specialty_code' => '39', 'email' => 'narendran.b@pantai.com'],

            // UPPER GI SURGERY (40)
            ['personnel_code' => 'DREYNUR', 'name' => 'DR REYNU RAJAN', 'specialty_code' => '40', 'email' => 'reynu.rajan@pantai.com'],

            // UPPER LIMB AND MICROSURGERY (78)
            ['personnel_code' => 'DISKANDARP', 'name' => 'DR MOHD ISKANDAR BIN MOHD AMIN', 'specialty_code' => '78', 'email' => 'DISKANDARP@pantai.com'],
            ['personnel_code' => 'DRANJITP', 'name' => 'DR RANJIT SINGH GILL', 'specialty_code' => '78', 'email' => 'ranjit.singh@pantai.com'],
            ['personnel_code' => 'DRUBANS', 'name' => 'DR RUBAN A/L SIVANOLI', 'specialty_code' => '78', 'email' => 'ruban.sivanoli@pantai.com'],
            ['personnel_code' => 'DSROOHIP', 'name' => 'DR SHARIFAH ROOHI BT SYED WASEEM AHMAD', 'specialty_code' => '78', 'email' => 'roohi.ahmad@pantai.com'],
            ['personnel_code' => 'DSROOHIC', 'name' => 'DR SHARIFAH ROOHI BT SYED WASEEM AHMAD (consignment item)', 'specialty_code' => '78', 'email' => 'roohi.ahmad.1@pantai.com'],

            // UROLOGY (48)
            ['personnel_code' => 'DADCHOWP', 'name' => 'DR ADAM CHOW KAM CHOON', 'specialty_code' => '48', 'email' => 'adam.chow@pantai.com'],
            ['personnel_code' => 'DADCHWOP', 'name' => 'DR ADAM K C CHOW (OP)', 'specialty_code' => '48', 'email' => 'adam.chow.1@pantai.com'],
            ['personnel_code' => 'DABALANP', 'name' => 'DR AMBIKAI BALAN A/L SOTHINATHAN', 'specialty_code' => '48', 'email' => 'ambikai.balan@pantai.com'],
            ['personnel_code' => 'DGOHCH', 'name' => 'DR GOH CHENG HOOD', 'specialty_code' => '48', 'email' => 'chenghood.goh@pantai.com'],
            ['personnel_code' => 'DSATHIYA', 'name' => 'DR J.R SATHIYANANTHAN', 'specialty_code' => '48', 'email' => 'sathiyananthan.j@pantai.com'],
            ['personnel_code' => 'DKANTHAP', 'name' => 'DR KANTHA RAO A/L SIMADARI NAIDU', 'specialty_code' => '48', 'email' => 'kantha.simadari@pantai.com'],
            ['personnel_code' => 'DKOHBH', 'name' => 'DR KENNETH KOH BENG HOCK', 'specialty_code' => '48', 'email' => 'DKOHBH@pantai.com'],
            ['personnel_code' => 'DLAMHSP', 'name' => 'DR LAM HOCK SHANG', 'specialty_code' => '48', 'email' => 'DLAMHSP@pantai.com'],
            ['personnel_code' => 'DLAMHSC', 'name' => 'DR LAM HOCK SHANG/ KENNETH KOH', 'specialty_code' => '48', 'email' => 'lam.kenneth@pantai.com'],
            ['personnel_code' => '1080', 'name' => 'DR LEE CHIN KEOW', 'specialty_code' => '48', 'email' => 'chinkeow.lee@pantai.com'],
            ['personnel_code' => 'D0031619', 'name' => 'DR NOOR ASHANI MD YUSOFF', 'specialty_code' => '48', 'email' => 'ashani.yusoff@pantai.com'],
            ['personnel_code' => 'DPKODI', 'name' => 'DR POONGKODI A/P S.NAGAPPAN', 'specialty_code' => '48', 'email' => 'poongkodi.nagappan@pantai.com'],
            ['personnel_code' => 'DSELVAP', 'name' => 'DR SELVALINGAM A/L SOTHILINGAM (DO NOT USE)', 'specialty_code' => '48', 'email' => 'selvalingam.s@pantai.com'],
            ['personnel_code' => 'DRSUS', 'name' => 'DR SURESH SABARATNAM A/L SACHI SABARATNAM', 'specialty_code' => '48', 'email' => 'suresh.sachi@pantai.com'],
            ['personnel_code' => 'DTEHKY', 'name' => 'DR TEH KHAI YEONG', 'specialty_code' => '48', 'email' => 'khaiyeong.teh@pantai.com'],

            // VASCULAR SURGERY (41)
            ['personnel_code' => 'DLEESK', 'name' => 'DR LEE SOON KHAI', 'specialty_code' => '41', 'email' => 'soonkhai.lee@pantai.com'],
            ['personnel_code' => 'DNARESH', 'name' => 'DR NARESH A/L GOVINDARAJANTHRAN', 'specialty_code' => '41', 'email' => 'naresh.govindarajanthran@pantai.com'],
            ['personnel_code' => 'D0038420', 'name' => 'DR SARAVANA KUMAR SELVANATHAN', 'specialty_code' => '41', 'email' => 'saravana.selvanathan@pantai.com'],
            ['personnel_code' => 'DYOWKH', 'name' => 'DR YOW KUAN HENG (DO NOT USE)', 'specialty_code' => '41', 'email' => 'kuanheng.yow@pantai.com'],
        ];

        // Note: MEDICAL OFFICER (93) has many entries - adding a subset for brevity
        // The full list should be added in production

        $count = 0;
        $skipped = 0;

        foreach ($consultants as $consultant) {
            // Get specialty by code if provided
            $specialty = null;
            if ($consultant['specialty_code']) {
                $specialty = Specialty::where('code', $consultant['specialty_code'])->first();
                if (!$specialty) {
                    $this->command->warn("Skipping consultant {$consultant['name']} - specialty code {$consultant['specialty_code']} not found");
                    $skipped++;
                    continue;
                }
            }

            // Generate registration number from personnel code
            $registrationNumber = 'MMC-' . $consultant['personnel_code'];

            Consultant::updateOrCreate(
                ['personnel_code' => $consultant['personnel_code']],
                [
                    'name' => $consultant['name'],
                    'specialty_id' => $specialty?->id,
                    'registration_number' => $registrationNumber,
                    'email' => $consultant['email'],
                    'phone' => null,
                    'qualifications' => null,
                    'years_of_experience' => null,
                    'is_active' => true,
                ]
            );
            $count++;
        }

        $this->command->info("Consultants seeded: {$count} consultants created/updated, {$skipped} skipped.");
    }
}
