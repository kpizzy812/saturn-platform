<?php

namespace Database\Seeders;

use App\Models\Application;
use Illuminate\Database\Seeder;

class ApplicationSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $application = Application::find(1);
        if ($application && $application->settings) {
            $application->settings->is_debug_enabled = false;
            $application->settings->save();
        }
    }
}
