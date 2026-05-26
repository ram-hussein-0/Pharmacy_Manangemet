<x-filament-panels::page>
    <form wire:submit="updateReport">
        {{ $this->form }}

        <div style="margin-top: 1rem;">
            <x-filament::button
                type="submit"
                icon="heroicon-o-arrow-path"
            >
                Refresh report
            </x-filament::button>
        </div>
    </form>

    <div style="margin-top: 1.5rem;">
        <x-filament::section>
            <x-slot name="heading">
                Profit & Loss Summary
            </x-slot>

            <x-slot name="description">
                Revenue and gross profit are calculated from completed sales only. Gross profit uses purchase_price_at_sale.
            </x-slot>

            <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                <div>
                    <div style="font-size: 0.875rem; opacity: 0.75;">Revenue</div>
                    <div style="font-size: 1.75rem; font-weight: 700;">{{ $this->money($revenue) }}</div>
                    <div style="font-size: 0.875rem; opacity: 0.75;">Completed sales: {{ $salesCount }}</div>
                </div>

                <div>
                    <div style="font-size: 0.875rem; opacity: 0.75;">Gross profit</div>
                    <div style="font-size: 1.75rem; font-weight: 700; color: {{ $grossProfit >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $this->money($grossProfit) }}
                    </div>
                    <div style="font-size: 0.875rem; opacity: 0.75;">Uses captured sale item cost</div>
                </div>

                <div>
                    <div style="font-size: 0.875rem; opacity: 0.75;">Expenses</div>
                    <div style="font-size: 1.75rem; font-weight: 700; color: #dc2626;">
                        {{ $this->money($expenses) }}
                    </div>
                    <div style="font-size: 0.875rem; opacity: 0.75;">Expense records: {{ $expensesCount }}</div>
                </div>

                <div>
                    <div style="font-size: 0.875rem; opacity: 0.75;">Net profit</div>
                    <div style="font-size: 1.75rem; font-weight: 700; color: {{ $netProfit >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $this->money($netProfit) }}
                    </div>
                    <div style="font-size: 0.875rem; opacity: 0.75;">Gross profit minus expenses</div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
