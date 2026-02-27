<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InstanceSettings;
use Illuminate\Http\Request;

class WebAutoProvisioningController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'auto_provision_enabled' => 'boolean',
            'cloud_provider_token_uuid' => 'nullable|string',
            'auto_provision_server_type' => 'nullable|string|max:50',
            'auto_provision_location' => 'nullable|string|max:50',
            'auto_provision_max_servers_per_day' => 'integer|min:1|max:10',
            'auto_provision_cooldown_minutes' => 'integer|min:15|max:240',
            'resource_monitoring_enabled' => 'boolean',
            'resource_warning_cpu_threshold' => 'integer|min:0|max:100',
            'resource_critical_cpu_threshold' => 'integer|min:0|max:100',
            'resource_warning_memory_threshold' => 'integer|min:0|max:100',
            'resource_critical_memory_threshold' => 'integer|min:0|max:100',
        ]);

        $settings = InstanceSettings::get();
        $settings->fill($validated);
        $settings->save();

        return back()->with('success', 'Auto-provisioning settings saved.');
    }
}
