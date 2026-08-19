<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;

class Login extends BaseLogin
{
    public function getTitle(): string
    {
        return 'Sign in';
    }

    public function getHeading(): string
    {
        return 'Welcome back';
    }

    public function getSubheading(): string
    {
        return 'Sign in to manage pharmacy operations, inventory, staff, and analytics.';
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('Email address')
            ->placeholder('admin@example.com');
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Password');
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()
            ->label('Keep me signed in');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label('Sign in');
    }
}
