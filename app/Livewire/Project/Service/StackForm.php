<?php

namespace App\Livewire\Project\Service;

use Livewire\Component;

class StackForm extends Component
{
    public $service;

    public $dockerCompose;

    public function mount($service)
    {
        $this->service = $service;
        $this->dockerCompose = $service->docker_compose ?? '';
    }

    public function submit(): void
    {
        $this->service->docker_compose = $this->dockerCompose;
        $this->service->save();

        $this->dispatch('refreshServices');
    }

    public function render()
    {
        return view('livewire.project.service.stack-form');
    }
}
