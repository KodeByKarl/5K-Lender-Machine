<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return static::normalizeArea($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /** Administrators see every Area, so they never keep an area_id. */
    public static function normalizeArea(array $data): array
    {
        if (isset($data['role']) && UserRole::from($data['role'] instanceof UserRole ? $data['role']->value : $data['role']) === UserRole::Admin) {
            $data['area_id'] = null;
        }

        return $data;
    }
}
