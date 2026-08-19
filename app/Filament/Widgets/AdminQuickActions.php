<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AiDatabaseAssistant;
use App\Filament\Pages\ExpiryAlerts;
use App\Filament\Pages\LowStockAlerts;
use App\Filament\Pages\NewSale;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\PurchaseInvoiceResource;
use App\Filament\Resources\StaffUserResource;
use Filament\Widgets\Widget;

class AdminQuickActions extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 5;

    protected string $view = 'filament.widgets.admin-quick-actions';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'actions' => [
                ['label' => 'New sale', 'description' => 'Create a FEFO sale', 'url' => NewSale::getUrl(), 'symbol' => '↗'],
                ['label' => 'Products', 'description' => 'Manage pharmacy catalog', 'url' => ProductResource::getUrl('index'), 'symbol' => '▦'],
                ['label' => 'Purchases', 'description' => 'Review purchase invoices', 'url' => PurchaseInvoiceResource::getUrl('index'), 'symbol' => '↓'],
                ['label' => 'Low stock', 'description' => 'See sellable-stock alerts', 'url' => LowStockAlerts::getUrl(), 'symbol' => '!'],
                ['label' => 'Expiry alerts', 'description' => 'Review expiring batches', 'url' => ExpiryAlerts::getUrl(), 'symbol' => '◷'],
                ['label' => 'Staff users', 'description' => 'Manage access and account status', 'url' => StaffUserResource::getUrl('index'), 'symbol' => '◎'],
                ['label' => 'AI Assistant', 'description' => 'Ask pharmacy and business questions', 'url' => AiDatabaseAssistant::getUrl(), 'symbol' => '✦'],
            ],
        ];
    }
}
