<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <x-filament::section><div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Completed invoices</div><div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;">{{ $completedInvoicesCount }}</div><div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">Completed purchase invoices</div></x-filament::section>
        <x-filament::section><div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Total spend</div><div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;">{{ $this->money($totalSpend) }}</div><div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">Completed inbound spend</div></x-filament::section>
        <x-filament::section><div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Units received</div><div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;">{{ number_format($unitsReceived) }}</div><div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">From completed purchase items</div></x-filament::section>
        <x-filament::section><div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Average invoice</div><div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;">{{ $this->money($averageInvoiceValue) }}</div><div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">Spend divided by invoices</div></x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Spend by supplier</x-slot>
        <x-slot name="description">Completed purchase invoice totals grouped by supplier.</x-slot>

        <div style="display:grid;gap:.9rem;">
            @forelse ($supplierSpend as $supplier)
                <div>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;font-size:.9rem;">
                        <strong>{{ $supplier['name'] }}</strong>
                        <span>{{ $this->money($supplier['total']) }} · {{ $supplier['invoices'] }} invoices</span>
                    </div>
                    <div style="height:10px;background:#e5e7eb;border-radius:999px;margin-top:.4rem;overflow:hidden;">
                        <div style="height:100%;width:{{ $supplier['percentage'] }}%;background:#f59e0b;border-radius:999px;"></div>
                    </div>
                </div>
            @empty
                <div style="color:#6b7280;">No completed purchase invoices found.</div>
            @endforelse
        </div>
    </x-filament::section>

    <div style="margin-top:1.5rem;">
        <x-filament::section>
            <x-slot name="heading">Purchase invoice details</x-slot>
            <x-slot name="description">Read-only report. Completing invoices and creating batches remains in the Purchase Invoice service flow.</x-slot>
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
