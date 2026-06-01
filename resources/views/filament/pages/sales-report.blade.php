<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <x-filament::section>
            <div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Completed invoices</div>
            <div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;">{{ $completedInvoicesCount }}</div>
            <div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">Sales with completed status</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Revenue</div>
            <div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;">{{ $this->money($totalRevenue) }}</div>
            <div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">Total completed sales value</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Gross profit</div>
            <div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;color:#16a34a;">{{ $this->money($totalGrossProfit) }}</div>
            <div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">Uses purchase price at sale</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Average invoice</div>
            <div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;">{{ $this->money($averageInvoiceValue) }}</div>
            <div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">Revenue divided by invoices</div>
        </x-filament::section>
    </div>

    @livewire(\App\Filament\ReportWidgets\SalesRevenueByProductChart::class)

    <div style="margin-top:1.5rem;">
        <x-filament::section>
            <x-slot name="heading">Completed sales details</x-slot>
            <x-slot name="description">
                Profit is calculated from sale_items.purchase_price_at_sale, so historical profit does not change when purchase prices change later.
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
