<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class LegacyUsersSeeder extends Seeder
{
    public function run(): void
    {
        $csv = <<<'CSV'
"name";"role";"is_active";"email";"email_verified_at";"access_tier_id";"total_access_duration_seconds";"first_name";"last_name";"whatsapp";"preferred_certificate_picture";"profile_photo";"instagram";"country";"birth_date";"gender";"practicing_yoga_for";"yoga_sequence_experience";"hours_per_week";"current_fitness_level";"flexibility_rating";"motivation";"why_yogafx";"how_did_you_find_us";"created_at";"updated_at"
"Ivara Kartika";"admin";"0";"marketing@yogafx.com";"2024-02-15 03:46:27";"";"584514";"Ivara";"Kartika";"+628113867337";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-02-15 03:46:27";"2024-02-15 03:46:27"
"Catherine Dale";"student";"0";"ozdorothy@hotmail.com";"2024-02-17 02:35:39";"";"0";"Catherine";"Dale";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-02-17 02:35:39";"2024-02-17 02:35:39"
"Ivara Candra";"admin";"0";"ivaracandra840@gmail.com";"2024-02-27 00:29:50";"";"0";"Ivara";"Candra";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-02-27 00:29:50";"2024-02-27 00:29:50"
"Christopher Andrew Kusznir";"student";"0";"reskusznir@gmail.com";"2024-03-14 01:23:37";"";"0";"Christopher";"Andrew Kusznir";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-14 01:23:37";"2024-03-14 01:23:37"
"Fiona Krause";"student";"0";"fi2369@yahoo.com";"2024-03-14 01:23:37";"";"0";"Fiona";"Fiona Krause";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-14 01:23:37";"2024-03-14 01:23:37"
"Ash";"student";"0";"angsihan@hotmail.com";"2024-03-14 01:23:37";"";"0";"Ash";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-14 01:23:37";"2024-03-14 01:23:37"
"Adam Cefai";"student";"0";"adamcefai@hotmail.com";"2024-03-14 01:23:37";"";"0";"Adam";"Cefai";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-14 01:23:37";"2024-03-14 01:23:37"
"Shannon De Abreu";"student";"0";"shannon.deabreu@hotmail.com";"2024-03-14 01:23:37";"";"0";"Shannon";"De Abreu";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-14 01:23:37";"2024-03-14 01:23:37"
"Ajay Agrawal";"student";"0";"ajay006@gmail.com";"2024-03-14 01:23:37";"";"0";"Ajay";"Agrawal";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-14 01:23:37";"2024-03-14 01:23:37"
"Septa Raharassati Djojoadikusumo";"student";"0";"missepta@gmail.com";"2024-03-14 01:23:37";"";"0";"Septa";"Raharassati Djojoadikusumo";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-14 01:23:37";"2024-03-14 01:23:37"
"Jennifer Rose Olayvar";"student";"0";"jen_nox@yahoo.com";"2024-03-14 01:23:37";"";"0";"Jennifer";"Rose Olayvar";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-14 01:23:37";"2024-03-14 01:23:37"
"Rachel Smith";"student";"0";"nirvanasnest@gmail.com";"2024-03-14 01:23:37";"";"0";"Rachel";"Smith";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-14 01:23:37";"2024-03-14 01:23:37"
"Chris Heunes";"student";"0";"chris.heunes@cbris.net";"2024-03-14 01:23:38";"";"0";"Chris";"Heunes";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-14 01:23:38";"2024-03-14 01:23:38"
"Selly Marina";"student";"0";"sellymarina@gmail.com";"2024-03-14 01:23:38";"";"0";"Selly";"Marina";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-14 01:23:38";"2024-03-14 01:23:38"
"Saul Taruk Allo Rantetasak";"student";"0";"saulrtasak@gmail.com";"2024-03-14 01:23:38";"";"0";"Saul";"Taruk Allo Rantetasak";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-14 01:23:38";"2024-03-14 01:23:38"
"Katie Pollock";"student";"0";"katp2000@hotmail.com";"2024-03-21 02:24:51";"";"0";"Katie";"Pollock";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-21 02:24:51";"2024-03-21 02:24:51"
"Stacy Wilson Turney";"student";"0";"turneystacy1@gmail.com";"2024-03-21 02:26:13";"";"0";"Stacy";"Wilson Turney";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-21 02:26:13";"2024-03-21 02:26:13"
"Angela Damaso Peksa";"student";"0";"damaso.angela@gmail.com";"2024-03-27 01:46:14";"";"0";"Angela Damaso";"Peksa";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2024-03-27 01:46:14";"2024-03-27 01:46:14"
"Matt Farnsworth";"student";"0";"farnicans@gmail.com";"2025-01-28 01:36:36";"";"0";"Matt";"Farnsworth";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-01-28 01:36:36";"2025-01-28 01:36:36"
"Celia";"student";"0";"healthiswealthct1@gmail.com";"2025-01-30 03:00:07";"";"0";"Celia";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-01-30 03:00:07";"2025-01-30 03:00:07"
"Alena";"student";"0";"ivanina.alen@gmail.com";"2025-01-30 03:01:11";"";"0";"Alena";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-01-30 03:01:11";"2025-01-30 03:01:11"
"Herman";"student";"0";"ngoudjo@gmail.com";"2025-01-30 03:01:53";"2";"4680";"Herman";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-01-30 03:01:53";"2025-01-30 03:01:53"
"Siobhan";"student";"0";"sflaherty2@hotmail.com";"2025-01-30 03:02:29";"";"0";"Siobhan";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-01-30 03:02:29";"2025-01-30 03:02:29"
"Lilywati";"student";"0";"gouwlilywati@gmail.com";"2025-01-30 03:03:37";"";"0";"Lilywati";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-01-30 03:03:37";"2025-01-30 03:03:37"
"Daniela";"student";"0";"dany.hit@gmail.com";"2025-02-03 01:50:13";"";"0";"Daniela";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-02-03 01:50:13";"2025-02-03 01:50:13"
"Samantha";"student";"0";"sf5@qad.com";"2025-02-03 01:50:49";"";"0";"Samantha";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-02-03 01:50:49";"2025-02-03 01:50:49"
"Karen";"student";"0";"kndatkin@gmail.com";"2025-02-03 01:51:19";"";"0";"Karen";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-02-03 01:51:19";"2025-02-03 01:51:19"
"Anika";"student";"0";"anikajaneday@gmail.com";"2025-02-03 01:51:43";"";"0";"Anika";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-02-03 01:51:43";"2025-02-03 01:51:43"
"Anne";"student";"0";"annekeuper@gmail.com";"2025-02-03 03:36:33";"";"0";"Anne";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-02-03 03:36:33";"2025-02-03 03:36:33"
"Liz";"student";"0";"munayki123@gmail.com";"2025-02-03 05:53:04";"";"0";"Liz";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-02-03 05:53:04";"2025-02-03 05:53:04"
"Malini";"student";"0";"maline.108@gmail.com";"2025-02-05 05:15:41";"";"0";"Malini";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-02-05 05:15:41";"2025-02-05 05:15:41"
"Sy";"student";"0";"yogagirlsy@gmail.com";"2025-02-05 05:16:11";"";"0";"Sy";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-02-05 05:16:11";"2025-02-05 05:16:11"
"Michelle";"student";"0";"michelle.govender12@gmail.com";"2025-02-05 05:16:38";"";"0";"Michelle";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-02-05 05:16:38";"2025-02-05 05:16:38"
"Rita";"student";"0";"rita.fonte@hotmail.com";"2025-02-05 05:17:05";"";"0";"Rita";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-02-05 05:17:05";"2025-02-05 05:17:05"
"Konrad";"student";"0";"konrad@neolink.com.au";"2025-02-06 01:26:24";"";"0";"Konrad";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-02-06 01:26:24";"2025-02-06 01:26:24"
"Peter";"student";"0";"proshop000@gmail.com";"2025-02-10 07:02:55";"";"0";"Peter";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-02-10 07:02:55";"2025-02-10 07:02:55"
"Gary";"student";"0";"egburgess@yahoo.com";"2025-02-10 07:21:51";"";"0";"Gary";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-02-10 07:21:51";"2025-02-10 07:21:51"
"Ozge";"student";"0";"ozge-yurtsever@hotmail.com";"2025-04-11 05:12:24";"";"0";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-04-11 05:12:24";"2025-04-11 05:12:24"
"Judith";"student";"0";"jbnetworks100@gmail.com";"2025-04-11 05:13:11";"";"0";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-04-11 05:13:11";"2025-04-11 05:13:11"
"Joanne";"student";"0";"23jgll78@gmail.com";"2025-05-19 07:11:11";"";"0";"Joanne";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-05-19 07:11:11";"2025-05-19 07:11:11"
"Marta";"student";"0";"mnicolaucaparros@gmail.com";"2025-05-19 07:11:40";"";"0";"Marta";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-05-19 07:11:40";"2025-05-19 07:11:40"
"Olga";"student";"0";"omantula76@gmail.com";"2025-06-03 03:17:22";"";"0";"Olga";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-06-03 03:17:22";"2025-06-03 03:17:22"
"Ian Terry";"student";"0";"yogafx11@gmail.com";"2025-10-20 07:15:12";"2";"5340";"Ian Terry";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-10-20 07:15:12";"2025-10-20 07:15:12"
"Ian K Terry";"student";"0";"mrianyoga@yahoo.com";"2025-10-21 06:24:33";"2";"61734";"Ian K Terry";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2025-10-21 06:24:33";"2025-10-21 06:24:33"
"Sara Varela";"student";"0";"wallersara@gmail.com";"2025-10-29 07:23:23";"";"0";"Sara";"Varela";"";"";"https://yogafx.wufoo.com/cabinet/c64181c2-a5c6-4517-af55-9385082b6356";"";"";"";"";"";"";"";"";"";"";"";"";"2025-10-29 07:23:23";"2025-10-29 07:23:23"
"Sarah Hopkins";"student";"0";"sayhop@gmail.com";"2025-11-01 23:39:10";"";"15300";"Sarah";"Hopkins";"";"";"https://yogafx.wufoo.com/cabinet/5feb296b-e4e1-4238-b47f-125b9a686ecc";"";"";"";"";"";"";"";"";"";"";"";"";"2025-11-01 23:39:10";"2025-11-01 23:39:10"
"kayley jeffery";"student";"0";"kay.leyj@hotmail.co.uk";"2025-12-17 09:06:54";"";"12120";"kayley";"jeffery";"";"";"https://yogafx.wufoo.com/cabinet/f1c8489e-208d-490b-be5d-bc353081804b";"";"";"";"";"";"";"";"";"";"";"";"";"2025-12-17 09:06:54";"2025-12-17 09:06:54"
"Ivara Kartika";"student";"0";"zalnative@gmail.com";"2025-12-18 07:19:15";"2";"20490";"Ivara";"Kartika";"";"";"https://yogafx.wufoo.com/cabinet/1dafa937-c537-4345-beee-b1a6bed8d031";"";"";"";"";"";"";"";"";"";"";"";"";"2025-12-18 07:19:15";"2025-12-18 07:19:15"
"Carlota Sanchez";"student";"0";"carlota.sanchez.ramirez@gmail.com";"2026-01-07 08:24:49";"";"72330";"Carlota";"Sanchez";"";"";"https://yogafx.wufoo.com/cabinet/aac84456-0a3f-409b-92d2-973ec9e0671c";"";"";"";"";"";"";"";"";"";"";"";"";"2026-01-07 08:24:49";"2026-01-07 08:24:49"
"Maximo Castelli";"student";"0";"maximocastelli315@hotmail.com";"2026-01-22 04:44:07";"2";"16320";"Maximo";"Castelli";"+4591725788";"";"https://yogafx.wufoo.com/cabinet/7b1778a9-03df-44f4-a8f5-2b4c10161735";"";"";"";"";"";"";"";"";"";"";"";"";"2026-01-22 04:44:07";"2026-01-22 04:44:07"
"Christopher Hatton";"student";"0";"djfriendlyness@gmail.com";"2026-03-05 03:38:04";"";"11010";"Christopher";"Hatton";"";"";"https://yogafx.wufoo.com/cabinet/1d60b961-a920-43d9-ad79-f50fbbc05cec";"";"";"";"";"";"";"";"";"";"";"";"";"2026-03-05 03:38:04";"2026-03-05 03:38:04"
"Ian Terry";"student";"0";"yogafx1@gmail.com";"2026-03-10 03:23:06";"2";"0";"Ian";"Terry";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-03-10 03:23:06";"2026-03-10 03:23:06"
"Gaelle Normand";"student";"0";"gaellenormand@gmail.com";"2026-03-22 06:12:55";"";"120";"Gaelle";"Normand";"";"";"https://yogafx.wufoo.com/cabinet/677e124e-eae6-4308-941e-c16133d824f0";"";"";"";"";"";"";"";"";"";"";"";"";"2026-03-22 06:12:55";"2026-03-22 06:12:55"
"Sally";"student";"0";"sally@swellpacific.com";"2026-04-05 05:30:32";"";"6180";"Sally";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-04-05 05:30:32";"2026-04-05 05:30:32"
"Deborah";"student";"0";"debmarques@hotmail.fr";"2026-04-05 11:33:28";"";"1680";"Deborah";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-04-05 11:33:28";"2026-04-05 11:33:28"
"Stephanie Van Kerckhove";"student";"0";"stepha.vk@hotmail.com";"2026-04-06 01:33:04";"";"10680";"Stephanie";"Van Kerckhove";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-04-06 01:33:04";"2026-04-06 01:33:04"
"kari";"student";"0";"kariandkids@gmail.com";"2026-04-06 12:58:40";"";"2250";"kari";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-04-06 12:58:40";"2026-04-06 12:58:40"
"Maya";"student";"0";"mayarblue@gmail.com";"2026-04-06 23:15:43";"";"305430";"Maya";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-04-06 23:15:43";"2026-04-06 23:15:43"
"Gabriel";"student";"0";"gabriellgg@hotmail.com";"2026-04-07 13:27:09";"";"4110";"Gabriel";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-04-07 13:27:09";"2026-04-07 13:27:09"
"Phyllis";"student";"0";"plpallo@icloud.com";"2026-04-12 17:16:09";"";"90600";"Phyllis";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-04-12 17:16:09";"2026-04-12 17:16:09"
"Yui";"student";"0";"yuiyamamoto@hotmail.com";"2026-04-14 04:12:32";"";"3150";"Yui";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-04-14 04:12:32";"2026-04-14 04:12:32"
"Aideen";"student";"0";"aideenodonogh@gmail.com";"2026-04-17 01:09:14";"";"18120";"Aideen";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-04-17 01:09:14";"2026-04-17 01:09:14"
"Jennifer";"student";"0";"travellingjenn@gmail.com";"2026-04-18 00:54:42";"";"6150";"Jennifer";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-04-18 00:54:42";"2026-04-18 00:54:42"
"Ian";"student";"0";"oscarboardman@hotmail.com";"2026-04-23 03:16:36";"";"14610";"Ian";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-04-23 03:16:36";"2026-04-23 03:16:36"
"Joanne";"student";"0";"joanneoneillmor@hotmail.co.uk";"2026-04-23 12:25:20";"2";"510";"Joanne";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-04-23 12:25:20";"2026-04-23 12:25:20"
"Jenna";"student";"0";"jenphil1980@gmail.com";"2026-04-23 23:21:15";"2";"104430";"Jenna";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-04-23 23:21:15";"2026-04-23 23:21:15"
"Ian";"student";"0";"oscarboarman@hotmail.com";"2026-05-13 07:53:03";"";"2400";"Ian";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-05-13 07:53:03";"2026-05-13 07:53:03"
"Riza";"student";"0";"zalnative+3@gmail.com";"2026-05-14 05:42:22";"";"5580";"Riza";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-05-14 05:42:22";"2026-05-14 05:42:22"
"tanya";"student";"0";"drtanyacrowle@gmail.com";"2026-05-15 02:47:01";"";"54660";"tanya";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-05-15 02:47:01";"2026-05-15 02:47:01"
"Irene Setiawati";"student";"0";"irenesetiawati77@gmail.com";"2026-05-18 07:46:12";"1";"2460";"Irene";"Setiawati";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-05-18 07:46:12";"2026-05-18 07:46:12"
"Noah Sternchos";"student";"0";"noahwrote@outlook.com";"2026-05-19 04:50:29";"";"13680";"Noah";"Sternchos";"+13472173157";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-05-19 04:50:29";"2026-05-19 04:50:29"
"Marcela";"student";"0";"mlalope@hotmail.com";"2026-05-22 00:02:03";"";"30";"Marcela";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-05-22 00:02:03";"2026-05-22 00:02:03"
"Adriana";"student";"0";"adrianathomas@sky.com";"2026-06-21 14:46:00";"1";"1530";"Adriana";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-06-21 14:46:00";"2026-06-21 14:46:00"
"Utami";"student";"0";"tami_agung@hotmail.com";"2026-07-09 08:27:32";"";"15930";"Utami";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"";"2026-07-09 08:27:32";"2026-07-09 08:27:32"
CSV;

        $lines = preg_split('/\R/', trim($csv));

        if ($lines === false || count($lines) < 2) {
            throw new RuntimeException('Data legacy user tidak valid.');
        }

        $headers = str_getcsv(array_shift($lines), ';', '"', '\\');
        $created = 0;
        $skipped = 0;

        $pilotLines = array_values(array_filter(
    $lines,
    static function (string $line) use ($headers): bool {
        if (trim($line) === '') {
            return false;
        }

        $values = str_getcsv($line, ';', '"', '\\');

        if (count($headers) !== count($values)) {
            return false;
        }

        $row = array_combine($headers, $values);

        return $row !== false
            && ($row['role'] ?? null) === 'student';
    }
));

$pilotLines = array_slice($pilotLines, 0, 3);

DB::transaction(function () use (
    $pilotLines,
    $headers,
    &$created,
    &$skipped
): void {
    foreach ($pilotLines as $line) {
                if (trim($line) === '') {
                    continue;
                }

                $values = str_getcsv($line, ';', '"', '\\');

                if (count($headers) !== count($values)) {
                    throw new RuntimeException(
                        'Jumlah kolom data legacy user tidak sesuai header.'
                    );
                }

                $user = array_combine($headers, $values);

                if ($user === false) {
                    throw new RuntimeException(
                        'Gagal membentuk data legacy user.'
                    );
                }

                $user = array_map(
                    static fn (mixed $value): mixed => $value === ''
                        ? null
                        : $value,
                    $user
                );

                $user['is_active'] = false;

                $user['access_tier_id'] = $user['access_tier_id'] === null
                    ? null
                    : (int) $user['access_tier_id'];

                $user['total_access_duration_seconds'] =
                    (int) ($user['total_access_duration_seconds'] ?? 0);

                $user['password'] = Hash::make('password');
                $user['remember_token'] = null;

                $emailAlreadyExists = DB::table('users')
                    ->whereRaw(
                        'LOWER(TRIM(email)) = ?',
                        [strtolower(trim((string) $user['email']))]
                    )
                    ->exists();

                if ($emailAlreadyExists) {
                    $skipped++;

                    continue;
                }

                DB::table('users')->insert($user);
                $created++;
            }
        });

        $this->command?->info(
            "Legacy user selesai: {$created} dibuat, {$skipped} dilewati."
        );
    }
}