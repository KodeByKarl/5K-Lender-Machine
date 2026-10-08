<?php

namespace App\Filament\Resources\LoanPlans\Pages;

use App\Filament\Resources\LoanPlans\LoanPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLoanPlans extends ManageRecords
{
    protected static string $resource = LoanPlanResource::class;

    public function getSubheading(): ?string
    {
        return 'Set the interest rates and terms your staff can offer. Staff choose a plan and cannot change its interest or term.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New plan')->modalWidth('3xl'),
        ];
    }
}
