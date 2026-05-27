<x-filament-widgets::widget>
    <div class="pharmacy-dashboard-charts-grid">
        <div class="pharmacy-dashboard-chart-main">
            @livewire(\App\Filament\DashboardCharts\SalesProfitChart::class, [], key('dashboard-sales-profit-chart'))
        </div>

        <div class="pharmacy-dashboard-products-card">
            <div class="pharmacy-dashboard-products-header">
                <div>
                    <h3>Top Selling Products</h3>
                    <p>By sold units from completed sales.</p>
                </div>
            </div>

            <div class="pharmacy-dashboard-products-body">
                @forelse ($topProducts as $product)
                    <div class="pharmacy-dashboard-product-row">
                        <div class="pharmacy-dashboard-product-meta">
                            <span class="pharmacy-dashboard-product-name">{{ $product['name'] }}</span>
                            <span class="pharmacy-dashboard-product-units">{{ number_format($product['units']) }} units</span>
                        </div>

                        <div class="pharmacy-dashboard-product-track">
                            <div
                                class="pharmacy-dashboard-product-bar"
                                style="width: {{ max($product['percentage'], 6) }}%;"
                            ></div>
                        </div>
                    </div>
                @empty
                    <div class="pharmacy-dashboard-products-empty">
                        No completed sale items found.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <style>
        .pharmacy-dashboard-charts-grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(340px, 1fr);
            gap: 1rem;
            align-items: stretch;
        }

        .pharmacy-dashboard-chart-main {
            min-width: 0;
            height: 100%;
        }

        .pharmacy-dashboard-chart-main > * {
            height: 100%;
        }

        .pharmacy-dashboard-products-card {
            min-width: 0;
            height: 100%;
            min-height: 420px;
            border: 1px solid rgba(209, 213, 219, 0.85);
            border-radius: 0.9rem;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .pharmacy-dashboard-products-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(229, 231, 235, 0.95);
        }

        .pharmacy-dashboard-products-header h3 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #111827;
        }

        .pharmacy-dashboard-products-header p {
            margin: 0.35rem 0 0;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .pharmacy-dashboard-products-body {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 1.25rem 1.5rem;
            display: grid;
            gap: 1rem;
            align-content: center;
        }

        .pharmacy-dashboard-product-row {
            display: grid;
            gap: 0.45rem;
        }

        .pharmacy-dashboard-product-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            font-size: 0.85rem;
        }

        .pharmacy-dashboard-product-name {
            color: #374151;
            font-weight: 600;
            line-height: 1.25;
        }

        .pharmacy-dashboard-product-units {
            color: #6b7280;
            white-space: nowrap;
        }

        .pharmacy-dashboard-product-track {
            height: 18px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .pharmacy-dashboard-product-bar {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #16a34a, #22c55e);
            box-shadow: inset 0 0 0 1px rgba(21, 128, 61, 0.25);
        }

        .pharmacy-dashboard-products-empty {
            color: #6b7280;
            text-align: center;
            font-size: 0.95rem;
        }

        @media (prefers-color-scheme: dark) {
            .pharmacy-dashboard-products-card {
                background: #18181b;
                border-color: rgba(63, 63, 70, 0.9);
            }

            .pharmacy-dashboard-products-header {
                border-bottom-color: rgba(63, 63, 70, 0.9);
            }

            .pharmacy-dashboard-products-header h3 {
                color: #f9fafb;
            }

            .pharmacy-dashboard-product-name {
                color: #e5e7eb;
            }

            .pharmacy-dashboard-product-track {
                background: #27272a;
            }
        }

        @media (max-width: 1280px) {
            .pharmacy-dashboard-charts-grid {
                grid-template-columns: 1fr;
            }

            .pharmacy-dashboard-products-card {
                min-height: 320px;
            }
        }
    </style>
</x-filament-widgets::widget>
