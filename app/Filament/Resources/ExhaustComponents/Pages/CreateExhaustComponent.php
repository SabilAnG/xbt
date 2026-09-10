<?php

namespace App\Filament\Resources\ExhaustComponents\Pages;

use App\Filament\Resources\ExhaustComponents\ExhaustComponentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExhaustComponent extends CreateRecord
{
    protected static string $resource = ExhaustComponentResource::class;

    /**
     * Datang dari halaman isi bagian: bagiannya sudah pasti, jadi jangan minta
     * orang memilihnya lagi.
     */
    protected function fillForm(): void
    {
        parent::fillForm();

        if ($bagian = request()->integer('bagian')) {
            $this->form->fill(['parent_id' => $bagian, 'is_active' => true]);
        }
    }
}
