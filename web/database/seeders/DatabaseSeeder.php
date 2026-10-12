<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $counties = [
            '001' => 'Mombasa', '002' => 'Kwale', '003' => 'Kilifi', '004' => 'Tana River',
            '005' => 'Lamu', '006' => 'Taita Taveta', '007' => 'Garissa', '008' => 'Wajir',
            '009' => 'Mandera', '010' => 'Marsabit', '011' => 'Isiolo', '012' => 'Meru',
            '013' => 'Tharaka-Nithi', '014' => 'Embu', '015' => 'Kitui', '016' => 'Machakos',
            '017' => 'Makueni', '018' => 'Nyandarua', '019' => 'Nyeri', '020' => 'Kirinyaga',
            "021" => "Murang'a", '022' => 'Kiambu', '023' => 'Turkana', '024' => 'West Pokot',
            '025' => 'Samburu', '026' => 'Trans Nzoia', '027' => 'Uasin Gishu', '028' => 'Elgeyo-Marakwet',
            '029' => 'Nandi', '030' => 'Baringo', '031' => 'Laikipia', '032' => 'Nakuru',
            '033' => 'Narok', '034' => 'Kajiado', '035' => 'Kericho', '036' => 'Bomet',
            '037' => 'Kakamega', '038' => 'Vihiga', '039' => 'Bungoma', '040' => 'Busia',
            '041' => 'Siaya', '042' => 'Kisumu', '043' => 'Homa Bay', '044' => 'Migori',
            '045' => 'Kisii', '046' => 'Nyamira', '047' => 'Nairobi',
        ];
        County::truncate();
        foreach ($counties as $code => $name) {
            County::create(['code' => $code, 'name' => $name]);
        }

        Setting::updateOrCreate(['key' => 'membership_fee_ctp'], ['value' => '500']);
        Setting::updateOrCreate(['key' => 'membership_fee_fundi'], ['value' => '1000']);
        Setting::updateOrCreate(['key' => 'programme_email'], ['value' => 'info@ctp-wirelakes.ke']);
        Setting::updateOrCreate(['key' => 'programme_phone'], ['value' => '+254 700 000 000']);

        User::firstOrCreate(
            ['email' => 'superadmin@ctp-wirelakes.ke'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('REDACTED-DEMO-PASSWORD'),
                'role' => 'superadmin',
            ]
        );

        $this->call(ContentSeeder::class);
    }
}
