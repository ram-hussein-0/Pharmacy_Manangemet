<x-filament-panels::page>
    <form wire:submit="updateReport">
        {{ $this->form }}

        <div style="margin-top:1rem;">
            <x-filament::button type="submit" icon="heroicon-o-arrow-path">
                Refresh report
            </x-filament::button>
        </div>
    </form>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-top:1.5rem;margin-bottom:1.5rem;">
        <x-filament::section>
            <div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Revenue</div>
            <div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;">{{ $this->money($revenue) }}</div>
            <div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">Completed sales: {{ $salesCount }}</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Gross profit</div>
            <div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;color:#16a34a;">{{ $this->money($grossProfit) }}</div>
            <div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">Uses captured sale item cost</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Expenses</div>
            <div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;color:#dc2626;">{{ $this->money($expenses) }}</div>
            <div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">Expense records: {{ $expensesCount }}</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Net profit</div>
            <div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;color:{{ $netProfit >= 0 ? '#16a34a' : '#dc2626' }};">{{ $this->money($netProfit) }}</div>
            <div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">Gross profit minus expenses</div>
        </x-filament::section>
    </div>

    @livewire(\App\Filament\ReportWidgets\ProfitLossBreakdownChart::class, [
        'from' => $data['from'] ?? null,
        'to' => $data['to'] ?? null,
    ], key('profit-loss-breakdown-' . ($data['from'] ?? 'from') . '-' . ($data['to'] ?? 'to')))
</x-filament-panels::page>
