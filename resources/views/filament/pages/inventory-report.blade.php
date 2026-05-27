<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <x-filament::section><div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Active products</div><div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;">{{ $productsCount }}</div><div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">Products currently enabled</div></x-filament::section>
        <x-filament::section><div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Units in stock</div><div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;">{{ number_format($unitsInStock) }}</div><div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">From product batches</div></x-filament::section>
        <x-filament::section><div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Low stock</div><div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;color:#f59e0b;">{{ $lowStockCount }}</div><div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">At or below minimum</div></x-filament::section>
        <x-filament::section><div style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;font-weight:700;">Stock value</div><div style="margin-top:.5rem;font-size:1.8rem;font-weight:800;">{{ $this->money($totalStockValue) }}</div><div style="margin-top:.25rem;color:#6b7280;font-size:.9rem;">Quantity × purchase price</div></x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Stock value by category</x-slot>
        <x-slot name="description">Calculated from remaining batch quantities and purchase prices.</x-slot>

        <div style="display:grid;gap:.9rem;">
            @forelse ($categoryValues as $category)
                <div>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;font-size:.9rem;">
                        <strong>{{ $category['name'] }}</strong>
                        <span>{{ $this->money($category['value']) }}</span>
                    </div>
                    <div style="height:10px;background:#e5e7eb;border-radius:999px;margin-top:.4rem;overflow:hidden;">
                        <div style="height:100%;width:{{ $category['percentage'] }}%;background:#3b82f6;border-radius:999px;"></div>
                    </div>
                </div>
            @empty
                <div style="color:#6b7280;">No category stock value found.</div>
            @endforelse
        </div>
    </x-filament::section>

    <div style="margin-top:1.5rem;">
        <x-filament::section>
            <x-slot name="heading">Inventory details</x-slot>
            <x-slot name="description">ProductBatch remains the real source of stock.</x-slot>
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
