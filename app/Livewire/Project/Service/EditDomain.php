<?php

namespace App\Livewire\Project\Service;

use Livewire\Component;

class EditDomain extends Component
{
    public $application;

    public $fqdn;

    public function mount($application)
    {
        $this->application = $application;
        $this->fqdn = $application->fqdn ?? '';
    }

    public function submit(): void
    {
        $this->application->fqdn = $this->fqdn;
        $this->application->save();

        $this->dispatch('refreshServices');
    }

    public function render()
    {
        return view('livewire.project.service.edit-domain');
    }
}
