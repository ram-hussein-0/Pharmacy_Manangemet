# Phase 2 — Reports & Dashboard

## Branch

`feature/reports-dashboard`

## Purpose

This phase adds dashboard widgets, operational alerts, and reporting pages to the Pharmacy Management System.

The goal is to give the admin a clear view of:

- inventory health
- low stock products
- expiry risks
- sales activity
- purchase activity
- stock movement audit records
- revenue
- gross profit
- expenses
- net profit

This phase focuses on read-only visibility and reporting. It does not change the stock or financial source-of-truth logic.

## Scope

This phase added and enhanced:

- Dashboard overview widgets
- Dashboard chart section
- Low stock alerts page
- Expiry alerts page
- Inventory report
- Sales report
- Purchase report
- Profit & loss report
- Dashboard chart widgets
- Report-only chart widgets
- Currency display changed from `EGP` to `SYP`
- Improved visual layout using Filament widgets, Filament sections, tables, ChartWidget, and custom Blade

## Academic Constraint

The UI work in this phase was built with Laravel Filament primitives and custom Blade.

Allowed tools used:

- Filament Pages
- Filament Widgets
- Filament Tables
- Filament Forms
- Filament Sections
- ChartWidget
- custom Blade with small CSS

No ready-made admin template or dashboard template was added.

The Lovable prototype was used only as a visual reference. Code was not copied blindly into the real Laravel project.

## Important System Rule

All dashboard widgets, alert pages, and report pages in this phase are read-only.

They must not:

- create stock movements
- change product batch quantities
- create sale invoices
- create purchase invoices
- complete purchase invoices
- create product batches
- modify financial totals
- bypass the service layer

Any operation that changes stock, batches, invoices, or money must still go through the domain services.

## Source-of-Truth Rules Preserved

### Stock

`ProductBatch` remains the real source of stock.

Stock-related summaries and reports use batch quantities rather than a product-level stock field.

Examples:

- current stock
- low stock checks
- inventory valuation
- expiry alerts
- stock value by category

### Sales and FEFO

Sales continue to be created through the `NewSale` Filament page and `SaleInvoiceService`.

The service is responsible for:

- applying FEFO
- decrementing `product_batches.quantity`
- creating `sale_items`
- snapshotting `purchase_price_at_sale`
- creating outbound stock movements

### Profit

Profit calculations use:

`quantity * (unit_price - purchase_price_at_sale)`

This is important because historical profit must not change if product or batch purchase prices change later.

## Dashboard Widgets

Dashboard widgets were added under:

`app/Filament/Widgets`

Main widgets include:

- `KpiStats`
- `LowStockTable`
- `ExpiringBatches`
- `RecentSales`
- `RecentStockMovements`
- `StockValueByCategoryChart`
- `DashboardCharts`

## Dashboard KPI Cards

`KpiStats` was expanded from a small overview into a fuller dashboard KPI section.

The dashboard now shows 8 KPI cards:

1. Total products
2. Low stock
3. Expiring batches
4. Today's sales
5. Monthly sales
6. Estimated profit
7. Total expenses
8. Stock movements

The KPI cards provide quick operational context without requiring the admin to open multiple reports.

### KPI Meaning

#### Total products

Shows all products and active products.

#### Low stock

Counts active products where current stock is at or below minimum stock.

Current stock is based on product batch quantities.

#### Expiring batches

Counts batches with remaining quantity that are approaching expiry.

The dashboard uses a wider expiry window for overview purposes.

#### Today's sales

Shows completed sales for the current date.

#### Monthly sales

Shows completed sales for the current month.

#### Estimated profit

Shows current month estimated gross profit based on sale items and captured purchase price at sale.

#### Total expenses

Shows all-time recorded expenses.

#### Stock movements

Shows count of stock movement audit records.

## Dashboard Chart Section

A custom dashboard chart section was added using:

- `app/Filament/Widgets/DashboardCharts.php`
- `resources/views/filament/widgets/dashboard-charts.blade.php`
- `app/Filament/DashboardCharts/SalesProfitChart.php`

The dashboard chart section contains:

- a large `Sales & Profit — Last 14 Days` line chart
- a custom `Top Selling Products` visual card

### Why a Custom DashboardCharts Widget Was Added

At first, charts were separate Filament `ChartWidget` classes under `app/Filament/Widgets`.

This caused layout limitations:

- the dashboard auto-discovered each chart separately
- it was difficult to keep `Sales & Profit` and `Top Selling Products` aligned
- the side chart did not visually match the height of the main chart
- the side chart created awkward empty space when there were few products

The final design uses one parent widget:

`DashboardCharts`

This parent widget manually controls the layout using a custom Blade grid.

### Sales & Profit Dashboard Chart

The dashboard chart:

`app/Filament/DashboardCharts/SalesProfitChart.php`

shows the last 14 days of completed sales.

It uses:

- green line for sales
- blue line for profit
- smooth line tension
- filled chart area
- completed sales only
- `sale_items.purchase_price_at_sale` for profit

This chart helps the admin quickly see sales and profitability trends.

### Top Selling Products Dashboard Card

The `Top Selling Products` visual is no longer an auto-discovered `ChartWidget`.

Instead, it is rendered as a custom card inside:

`resources/views/filament/widgets/dashboard-charts.blade.php`

The product data is prepared inside:

`app/Filament/Widgets/DashboardCharts.php`

It shows top products by sold units from completed sales.

The card is intentionally fixed and controlled inside the parent dashboard chart layout so that:

- it stays visually aligned beside the main chart
- it does not create excessive height
- it does not expand the page when more products exist
- it remains readable with up to 8 products
- overflowing content is controlled inside the card

The temporary dashboard top-selling chart class was removed:

`app/Filament/DashboardCharts/TopSellingProductsChart.php`

## Report-Only Widgets

Report-specific charts were moved away from `app/Filament/Widgets` into:

`app/Filament/ReportWidgets`

This was done to prevent them from being auto-discovered by the dashboard.

Current report-only widgets include:

- `app/Filament/ReportWidgets/SalesRevenueByProductChart.php`
- `app/Filament/ReportWidgets/ProfitLossBreakdownChart.php`

## Why ReportWidgets Were Separated

Filament auto-discovers widgets under:

`app/Filament/Widgets`

When report-only charts were placed there, they could appear on the dashboard or cause authorization/routing issues in Livewire requests.

The final structure separates concerns:

- Dashboard widgets live in `app/Filament/Widgets`
- Dashboard internal chart classes live in `app/Filament/DashboardCharts`
- Report-only chart widgets live in `app/Filament/ReportWidgets`

This keeps the dashboard clean and prevents unrelated report widgets from appearing globally.

## Alerts

## Low Stock Alerts

Added:

- `app/Filament/Pages/LowStockAlerts.php`
- `resources/views/filament/pages/low-stock-alerts.blade.php`

The low stock alerts page shows active products where:

`current stock <= minimum stock`

Current stock is calculated from product batch quantities.

The page is read-only and is intended to help the admin decide which products may need purchasing.

## Expiry Alerts

Added:

- `app/Filament/Pages/ExpiryAlerts.php`
- `resources/views/filament/pages/expiry-alerts.blade.php`

The expiry alerts page shows batches with remaining quantity that are:

- already expired
- close to expiry

It is read-only and does not adjust stock.

This page helps the admin identify urgent expiry risks before losses happen.

## Reports

## Inventory Report

Added and enhanced:

- `app/Filament/Pages/InventoryReport.php`
- `resources/views/filament/pages/inventory-report.blade.php`

The inventory report shows product-level inventory status.

It includes:

- summary cards
- stock value by category visual breakdown
- inventory details table

The report shows:

- product
- category
- current stock
- minimum stock
- stock status
- sale price
- stock value

Stock value is calculated from batches:

`product_batches.quantity * product_batches.purchase_price`

This follows the rule that `ProductBatch` is the real source of stock.

## Sales Report

Added and enhanced:

- `app/Filament/Pages/SalesReport.php`
- `resources/views/filament/pages/sales-report.blade.php`
- `app/Filament/ReportWidgets/SalesRevenueByProductChart.php`

The sales report shows completed sales and sales profitability.

It includes:

- summary cards
- revenue by product chart
- sales details table

Summary cards show:

- completed invoice count
- total revenue
- gross profit
- average invoice value

The revenue-by-product chart shows top products by completed sales revenue.

The sales table includes:

- invoice number
- date
- customer
- payment method
- total
- profit
- status

Optional/toggleable columns may include:

- customer phone
- subtotal
- discount
- tax

Profit is calculated using:

`quantity * (unit_price - purchase_price_at_sale)`

This preserves historical profit correctness.

## Purchase Report

Added and enhanced:

- `app/Filament/Pages/PurchaseReport.php`
- `resources/views/filament/pages/purchase-report.blade.php`

The purchase report shows purchase invoice activity.

It includes:

- summary cards
- spend by supplier visual breakdown
- purchase invoice details table

Summary cards show:

- completed purchase invoices
- total spend
- units received
- average invoice value

The spend-by-supplier visual groups completed purchase invoice totals by supplier.

The table includes:

- invoice number
- date
- supplier
- total
- status

Optional/toggleable columns may include:

- subtotal
- discount
- tax

This report is read-only.

It does not complete purchase invoices and does not create product batches.

Purchase completion remains controlled by the purchase invoice service flow.

## Profit & Loss Report

Added and enhanced:

- `app/Filament/Pages/ProfitLossReport.php`
- `resources/views/filament/pages/profit-loss-report.blade.php`
- `app/Filament/ReportWidgets/ProfitLossBreakdownChart.php`

The profit and loss report shows financial performance for a selected date range.

It includes:

- date range form
- summary cards
- profit and loss breakdown chart

The summary cards show:

- revenue
- gross profit
- expenses
- net profit

Formula:

`Net Profit = Gross Profit - Expenses`

Revenue is calculated from completed sales only.

Gross profit uses:

`sale_items.purchase_price_at_sale`

Expenses are calculated from the `expenses` table using:

`expenses.expense_date`

The chart compares:

- gross profit
- expenses
- net profit

The chart is report-only and is not auto-discovered by the dashboard.

## Stock Value by Category Chart

Added:

- `app/Filament/Widgets/StockValueByCategoryChart.php`

This dashboard widget shows stock value grouped by category.

Calculation:

`SUM(product_batches.quantity * product_batches.purchase_price)`

It uses remaining batch quantities and purchase prices.

This makes it an inventory valuation chart, not a sales chart.

## Currency Display

The displayed currency text was changed from:

`EGP`

to:

`SYP`

This was a display-only change.

It did not:

- convert values
- modify prices
- modify database records
- change financial logic

Current English UI style displays money like:

`SYP 200.00`

This is acceptable for an English interface using a currency code.

## Date Display

Several report tables were updated to use a clearer English date style:

`26 May 2026`

using:

`->date('d M Y')`

This is more internationally readable than the default US-style date format.

Some existing Filament tables may still use their own date formatting if not yet polished.

## Design Notes

The final design direction for reports and dashboard is:

- summary cards first
- visual chart or breakdown second
- detailed table last

This makes the pages easier to read than plain tables.

The design is intentionally custom and simple.

It is not a ready-made dashboard template.

## Files Added or Significantly Modified in This Phase

### Dashboard Widgets

- `app/Filament/Widgets/KpiStats.php`
- `app/Filament/Widgets/LowStockTable.php`
- `app/Filament/Widgets/ExpiringBatches.php`
- `app/Filament/Widgets/RecentSales.php`
- `app/Filament/Widgets/RecentStockMovements.php`
- `app/Filament/Widgets/StockValueByCategoryChart.php`
- `app/Filament/Widgets/DashboardCharts.php`
- `resources/views/filament/widgets/dashboard-charts.blade.php`

### Dashboard Internal Charts

- `app/Filament/DashboardCharts/SalesProfitChart.php`

### Report Widgets

- `app/Filament/ReportWidgets/SalesRevenueByProductChart.php`
- `app/Filament/ReportWidgets/ProfitLossBreakdownChart.php`

### Alert Pages

- `app/Filament/Pages/LowStockAlerts.php`
- `resources/views/filament/pages/low-stock-alerts.blade.php`
- `app/Filament/Pages/ExpiryAlerts.php`
- `resources/views/filament/pages/expiry-alerts.blade.php`

### Report Pages

- `app/Filament/Pages/InventoryReport.php`
- `resources/views/filament/pages/inventory-report.blade.php`
- `app/Filament/Pages/SalesReport.php`
- `resources/views/filament/pages/sales-report.blade.php`
- `app/Filament/Pages/PurchaseReport.php`
- `resources/views/filament/pages/purchase-report.blade.php`
- `app/Filament/Pages/ProfitLossReport.php`
- `resources/views/filament/pages/profit-loss-report.blade.php`

## Files Removed or Replaced

The old auto-discovered dashboard chart widget was removed:

- `app/Filament/Widgets/SalesProfitChart.php`

It was replaced by:

- `app/Filament/DashboardCharts/SalesProfitChart.php`
- `app/Filament/Widgets/DashboardCharts.php`
- `resources/views/filament/widgets/dashboard-charts.blade.php`

The temporary dashboard top-selling chart class was removed:

- `app/Filament/DashboardCharts/TopSellingProductsChart.php`

Top selling product data is now rendered directly inside the custom DashboardCharts Blade card.

## Safety Notes

This phase did not add migrations.

This phase did not modify:

- `.env`
- old migrations
- `app/Models/User.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Providers/AppServiceProvider.php`
- `routes/api.php`

This phase did not add new Composer or npm packages.

## Validation Performed During This Phase

Validation included:

- PHP syntax checks with `php -l`
- `composer dump-autoload`
- `php artisan optimize:clear`
- Filament route checks
- browser checks under `/admin`
- browser checks for report pages
- domain tests on the testing database

Domain tests were run using:

    APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=pharmacy_system_test php artisan test tests/Feature/Domain

Expected stable result:

`11 passed / 58 assertions`

## Latest Manual Validation Notes

After the latest dashboard chart restructuring:

- `app/Filament/Widgets/DashboardCharts.php` passed `php -l`
- `app/Filament/DashboardCharts/SalesProfitChart.php` passed `php -l`
- `composer dump-autoload` completed successfully
- `php artisan optimize:clear` completed successfully
- browser review confirmed that the dashboard layout is visually acceptable after replacing the side chart with a custom top products card

## Expected Admin Routes

Expected report and alert routes include:

- `/admin/low-stock-alerts`
- `/admin/expiry-alerts`
- `/admin/inventory-report`
- `/admin/sales-report`
- `/admin/purchase-report`
- `/admin/profit-loss-report`

Related operational routes include:

- `/admin/new-sale`
- `/admin/sale-invoices`
- `/admin/purchase-invoices`
- `/admin/product-batches`
- `/admin/stock-movements`
- `/admin/expenses`

## Current Recommended Final Validation

Before committing or merging this phase, run:

    cd /Users/fangyuan/Desktop/LaravelProjects/Pharmacy_Manangemet

    git status --short
    git log --oneline -n 12

    grep -R "EGP" -n app/Filament app/Services app/Models resources/views config 2>/dev/null || true
    grep -R "SYP" -n app/Filament resources/views 2>/dev/null || true

    php artisan route:list | grep -E "low-stock|expiry|inventory-report|sales-report|purchase-report|profit-loss|new-sale|sale-invoices|purchase-invoices|product-batches|stock-movements|expenses" || true

    for f in \
      app/Filament/Widgets/KpiStats.php \
      app/Filament/Widgets/DashboardCharts.php \
      app/Filament/DashboardCharts/SalesProfitChart.php \
      app/Filament/ReportWidgets/SalesRevenueByProductChart.php \
      app/Filament/ReportWidgets/ProfitLossBreakdownChart.php \
      app/Filament/Pages/InventoryReport.php \
      app/Filament/Pages/SalesReport.php \
      app/Filament/Pages/PurchaseReport.php \
      app/Filament/Pages/ProfitLossReport.php
    do
      [ -f "$f" ] && php -l "$f"
    done

    composer dump-autoload
    php artisan optimize:clear

    APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=pharmacy_system_test php artisan test tests/Feature/Domain

## Suggested Commit Order

Because the latest work includes both code changes and documentation, a safe commit order is:

1. Commit dashboard/report UI enhancements.
2. Commit this documentation update.

Suggested code commit message:

`enhance reports dashboard visual summaries`

Suggested documentation commit message:

`update reports dashboard phase documentation`

## Next Possible Phase

After this branch is clean and committed, possible next phases include:

- report feature tests
- dashboard polish
- authorization and user management
- API review
- AI assistant integration review
- print/PDF invoice support
- notification planning
---

## Final Phase 2 Testing Result

After adding the report and dashboard feature tests, the current validation result is:

```text
Tests: 21 passed (124 assertions)
```

This includes:

- 10 Phase 2 report/dashboard tests:
  - report and alert page access
  - guest redirect protection
  - low stock alert data
  - expiry alert data
  - inventory report stock quantity/value
  - sales report revenue/profit
  - purchase report totals
  - profit & loss report revenue/gross profit/expenses/net profit
  - dashboard route and widget class availability

- 11 existing domain tests:
  - ExpenseService
  - InventoryService
  - PurchaseInvoiceService
  - SaleInvoiceService FEFO and insufficient stock rollback

The command used for final validation was:

```bash
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=pharmacy_system_test php artisan test tests/Feature/Reports tests/Feature/Domain
```

Current Phase 2 testing is considered sufficient for this stage. More tests can be added later when adding exports, PDF printing, advanced filters, policies, or browser-level interactions.

