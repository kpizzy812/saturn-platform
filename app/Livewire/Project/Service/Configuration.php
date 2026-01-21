<?php

namespace App\Livewire\Project\Service;

use Livewire\Attributes\On;
use Livewire\Component;

class Configuration extends Component
{
    public $service;

    public $applications;

    public $databases;

    protected $listeners = [
        'refreshServices' => 'refreshServices',
        'refresh' => 'refreshServices',
    ];

    public function mount($service)
    {
        $this->service = $service;
        $this->applications = $service->applications->sort();
        $this->databases = $service->databases->sort();
    }

    #[On('refreshServices')]
    #[On('refresh')]
    public function refreshServices(): void
    {
        $this->service->refresh();
        $this->applications = $this->service->applications->sort();
        $this->databases = $this->service->databases->sort();
    }

    public function render()
    {
        return view('livewire.project.service.configuration');
    }
}
