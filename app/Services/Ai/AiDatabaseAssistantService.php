<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseInvoice;
use App\Models\SaleInvoice;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class AiDatabaseAssistantService
{
    private readonly EntityResolver $entities;

    public function __construct(
        private readonly IntentClassifier $classifier,
        private readonly InventoryService $inventory,
        private readonly LlmClientService $llm,
        ?EntityResolver $entities = null,
    ) {
        $this->entities = $entities ?? app(EntityResolver::class);
    }

    /**
     * @return array{intent:string, answer:string, rows:array, columns:array}
     */
    public function ask(string $question): array
    {
        $question = trim($question);

        if ($question === '') {
            return $this->fail('Please enter a question.');
        }

        if (mb_strlen($question) > 500) {
            return $this->fail('Question is too long. Please keep it under 500 characters.');
        }

        $key = 'ai-assistant:'.(Auth::id() ?: request()->ip());

        if (RateLimiter::tooManyAttempts($key, 30)) {
            return $this->fail('You are sending questions too fast. Please wait a few seconds.');
        }

        RateLimiter::hit($key, 60);

        $started = microtime(true);

        $classified = $this->classifier->classify($question);
        $intent = $classified['intent'];
        $params = $classified['params'];

        [$rows, $columns, $summary] = $this->dispatch($intent, $params);

        $answer = $summary;

        if ($intent !== 'unknown' && count($rows) > 0 && filled(config('llm.api_key'))) {
            try {
                $answer = $this->llm->complete(
                    systemPrompt: 'You are a helpful pharmacy assistant. Be concise and accurate. Use the same language as the user. Never invent values. Do not mention raw JSON, SQL, or technical column keys. Keep product, supplier, staff, and category names exactly as provided.',
                    userPrompt: "User asked: {$question}\nResult JSON: ".json_encode(array_slice($rows, 0, 30))."\nWrite a short natural answer using only values from the JSON. Prefer clear numbers and avoid unnecessary technical words.",
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        Log::info('ai.assistant', [
            'user_id' => Auth::id(),
            'intent' => $intent,
            'rows' => count($rows),
            'latency_ms' => (int) ((microtime(true) - $started) * 1000),
            'question' => config('llm.log_questions') ? $question : null,
        ]);

        return [
            'intent' => $intent,
            'answer' => $answer,
            'rows' => $rows,
            'columns' => $columns,
        ];
    }

    /**
     * @return array{0:array, 1:array, 2:string}
     */
    private function dispatch(string $intent, array $params): array
    {
        return match ($intent) {
            'low_stock_products' => $this->lowStock(),
            'expiring_batches' => $this->expiringBatches((int) ($params['days'] ?? 30)),
            'expired_batches' => $this->expiredBatches(),
            'today_sales' => $this->todaySales(),
            'monthly_sales' => $this->monthlySales(),
            'sales_between_dates' => $this->salesBetween($params['date_from'] ?? null, $params['date_to'] ?? null),
            'top_selling_products' => $this->topProducts((int) ($params['limit'] ?? 5)),
            'purchase_summary' => $this->purchaseSummary(),
            'inventory_summary' => $this->inventorySummary(),
            'profit_loss_summary' => $this->profitLoss(),
            'supplier_summary' => $this->supplierSummary(),
            'product_lookup' => $this->productLookup($params['product_name'] ?? ''),
            'supplier_lookup' => $this->supplierLookup($params['supplier_name'] ?? ''),
            'staff_lookup' => $this->staffLookup($params['staff_name'] ?? ''),
            'category_lookup' => $this->categoryLookup($params['category_name'] ?? ''),
            'stock_movements_summary' => $this->movementsSummary(),
            default => [[], [], 'Sorry — that question is outside what the pharmacy assistant can answer.'],
        };
    }

    private function lowStock(): array
    {
        $rows = $this->inventory->lowStockProducts()
            ->map(fn (Product $product): array => [
                'product' => $product->name,
                'category' => $product->category?->name ?? '—',
                'current_stock' => (int) $product->current_stock,
                'minimum_stock' => (int) $product->minimum_stock,
            ])
            ->all();

        return [$rows, ['product', 'category', 'current_stock', 'minimum_stock'], count($rows).' product(s) at or below minimum stock.'];
    }

    private function expiringBatches(int $days): array
    {
        $days = max(1, min($days, 365));

        $rows = $this->inventory->expiringBatches($days)
            ->map(fn (ProductBatch $batch): array => [
                'product' => $batch->product?->name ?? '—',
                'batch_number' => $batch->batch_number,
                'expiry_date' => $batch->expiry_date->toDateString(),
                'quantity' => (int) $batch->quantity,
            ])
            ->all();

        return [$rows, ['product', 'batch_number', 'expiry_date', 'quantity'], count($rows)." batch(es) expiring within {$days} day(s)."];
    }

    private function expiredBatches(): array
    {
        $rows = $this->inventory->expiredBatches()
            ->map(fn (ProductBatch $batch): array => [
                'product' => $batch->product?->name ?? '—',
                'batch_number' => $batch->batch_number,
                'expiry_date' => $batch->expiry_date->toDateString(),
                'quantity' => (int) $batch->quantity,
            ])
            ->all();

        return [$rows, ['product', 'batch_number', 'expiry_date', 'quantity'], count($rows).' expired batch(es) still on the shelves.'];
    }

    private function todaySales(): array
    {
        $sales = SaleInvoice::query()
            ->with('saleItems')
            ->where('status', 'completed')
            ->whereDate('invoice_date', today())
            ->get();

        $revenue = (float) $sales->sum('total');
        $profit = $this->salesProfit($sales);

        return [
            [[
                'invoices' => $sales->count(),
                'revenue' => $revenue,
                'gross_profit' => $profit,
            ]],
            ['invoices', 'revenue', 'gross_profit'],
            "Today: {$sales->count()} completed sale(s), revenue {$revenue}.",
        ];
    }

    private function monthlySales(): array
    {
        $sales = SaleInvoice::query()
            ->with('saleItems')
            ->where('status', 'completed')
            ->whereYear('invoice_date', now()->year)
            ->whereMonth('invoice_date', now()->month)
            ->get();

        $revenue = (float) $sales->sum('total');
        $profit = $this->salesProfit($sales);

        return [
            [[
                'invoices' => $sales->count(),
                'revenue' => $revenue,
                'gross_profit' => $profit,
            ]],
            ['invoices', 'revenue', 'gross_profit'],
            "This month: {$sales->count()} completed sale(s), revenue {$revenue}.",
        ];
    }

    private function salesBetween(?string $from, ?string $to): array
    {
        $query = SaleInvoice::query()
            ->with('saleItems')
            ->where('status', 'completed');

        if ($from) {
            $query->whereDate('invoice_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('invoice_date', '<=', $to);
        }

        $sales = $query->get();

        return [
            [[
                'from' => $from,
                'to' => $to,
                'invoices' => $sales->count(),
                'revenue' => (float) $sales->sum('total'),
                'gross_profit' => $this->salesProfit($sales),
            ]],
            ['from', 'to', 'invoices', 'revenue', 'gross_profit'],
            "Between {$from} and {$to}: {$sales->count()} completed sale(s).",
        ];
    }

    private function topProducts(int $limit): array
    {
        $limit = max(1, min($limit, 20));

        $rows = SaleItem::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity) AS units_sold')
            ->selectRaw('SUM(total) AS revenue')
            ->whereHas('saleInvoice', fn ($query) => $query->where('status', 'completed'))
            ->groupBy('product_id')
            ->orderByDesc('units_sold')
            ->limit($limit)
            ->with('product:id,name')
            ->get()
            ->map(fn (SaleItem $row): array => [
                'product' => $row->product?->name ?? '—',
                'units_sold' => (int) $row->units_sold,
                'revenue' => (float) $row->revenue,
            ])
            ->all();

        return [$rows, ['product', 'units_sold', 'revenue'], "Top {$limit} best-selling product(s)."];
    }

    private function purchaseSummary(): array
    {
        $rows = PurchaseInvoice::query()
            ->select('supplier_id')
            ->selectRaw('COUNT(*) AS invoices')
            ->selectRaw('SUM(total) AS total_spend')
            ->where('status', 'completed')
            ->groupBy('supplier_id')
            ->with('supplier:id,name')
            ->get()
            ->map(fn (PurchaseInvoice $row): array => [
                'supplier' => $row->supplier?->name ?? '—',
                'invoices' => (int) $row->invoices,
                'total_spend' => (float) $row->total_spend,
            ])
            ->all();

        return [$rows, ['supplier', 'invoices', 'total_spend'], 'Completed purchase spend grouped by supplier.'];
    }

    private function inventorySummary(): array
    {
        $rows = [[
            'total_products' => Product::query()->count(),
            'active_products' => Product::query()->where('is_active', true)->count(),
            'units_in_stock' => (int) ProductBatch::query()->sellable()->sum('quantity'),
            'stock_value' => (float) $this->inventory->totalStockValue(),
        ]];

        return [$rows, array_keys($rows[0]), 'Inventory snapshot.'];
    }

    private function profitLoss(): array
    {
        $sales = SaleInvoice::query()
            ->with('saleItems')
            ->where('status', 'completed')
            ->whereYear('invoice_date', now()->year)
            ->whereMonth('invoice_date', now()->month)
            ->get();

        $revenue = (float) $sales->sum('total');
        $grossProfit = $this->salesProfit($sales);

        $expenses = (float) Expense::query()
            ->whereYear('expense_date', now()->year)
            ->whereMonth('expense_date', now()->month)
            ->sum('amount');

        $net = $grossProfit - $expenses;

        return [
            [[
                'revenue' => $revenue,
                'gross_profit' => $grossProfit,
                'expenses' => $expenses,
                'net_profit' => $net,
            ]],
            ['revenue', 'gross_profit', 'expenses', 'net_profit'],
            'Net '.($net >= 0 ? 'profit' : 'loss').": {$net}.",
        ];
    }

    private function supplierSummary(): array
    {
        $rows = Supplier::query()
            ->withCount('purchaseInvoices')
            ->get()
            ->map(fn (Supplier $supplier): array => [
                'supplier' => $supplier->name,
                'invoices' => (int) $supplier->purchase_invoices_count,
                'total_spend' => (float) PurchaseInvoice::query()
                    ->where('supplier_id', $supplier->id)
                    ->where('status', 'completed')
                    ->sum('total'),
            ])
            ->all();

        return [$rows, ['supplier', 'invoices', 'total_spend'], count($rows).' supplier(s) tracked.'];
    }

    private function productLookup(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return [[], [], 'No product name was provided.'];
        }

        $products = Product::query()
            ->where('name', 'like', "%{$name}%")
            ->with('category:id,name')
            ->limit(20)
            ->get();

        $fuzzy = false;

        if ($products->isEmpty()) {
            $closest = $this->entities->closest('product', $name);

            if ($closest instanceof Product) {
                $products = Product::query()
                    ->whereKey($closest->getKey())
                    ->with('category:id,name')
                    ->get();
                $fuzzy = true;
            }
        }

        $rows = $products
            ->map(fn (Product $product): array => [
                'product' => $product->name,
                'barcode' => $product->barcode,
                'category' => $product->category?->name ?? '—',
                'sale_price' => (float) $product->sale_price,
                'current_stock' => (int) $product->current_stock,
                'minimum_stock' => (int) $product->minimum_stock,
                'is_active' => $product->is_active ? 'yes' : 'no',
            ])
            ->all();

        $summary = $fuzzy && count($rows) === 1
            ? 'No exact product match was found; using closest safe match "'.$rows[0]['product'].'".'
            : count($rows)." match(es) for \"{$name}\".";

        return [$rows, ['product', 'barcode', 'category', 'sale_price', 'current_stock', 'minimum_stock', 'is_active'], $summary];
    }

    private function supplierLookup(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return [[], [], 'No supplier name was provided.'];
        }

        $query = Supplier::query()
            ->where('name', 'like', "%{$name}%")
            ->withCount('purchaseInvoices')
            ->limit(20);
        $suppliers = $query->get();
        $fuzzy = false;

        if ($suppliers->isEmpty()) {
            $closest = $this->entities->closest('supplier', $name);

            if ($closest instanceof Supplier) {
                $suppliers = Supplier::query()
                    ->whereKey($closest->getKey())
                    ->withCount('purchaseInvoices')
                    ->get();
                $fuzzy = true;
            }
        }

        $rows = $suppliers->map(fn (Supplier $supplier): array => [
            'supplier' => $supplier->name,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'invoices' => (int) $supplier->purchase_invoices_count,
            'total_spend' => (float) PurchaseInvoice::query()
                ->where('supplier_id', $supplier->id)
                ->where('status', 'completed')
                ->sum('total'),
        ])->all();

        $summary = $fuzzy && count($rows) === 1
            ? 'No exact supplier match was found; using closest safe match "'.$rows[0]['supplier'].'".'
            : count($rows)." supplier match(es) for \"{$name}\".";

        return [$rows, ['supplier', 'phone', 'email', 'invoices', 'total_spend'], $summary];
    }

    private function staffLookup(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return [[], [], 'No staff name was provided.'];
        }

        $users = User::query()
            ->where('name', 'like', "%{$name}%")
            ->withCount(['saleInvoices', 'purchaseInvoices', 'expenses', 'stockMovements'])
            ->limit(20)
            ->get();
        $fuzzy = false;

        if ($users->isEmpty()) {
            $closest = $this->entities->closest('staff', $name);

            if ($closest instanceof User) {
                $users = User::query()
                    ->whereKey($closest->getKey())
                    ->withCount(['saleInvoices', 'purchaseInvoices', 'expenses', 'stockMovements'])
                    ->get();
                $fuzzy = true;
            }
        }

        $rows = $users->map(fn (User $user): array => [
            'staff' => $user->name,
            'role' => $user->role,
            'is_active' => $user->is_active ? 'yes' : 'no',
            'sales_created' => (int) $user->sale_invoices_count,
            'purchases_created' => (int) $user->purchase_invoices_count,
            'expenses_created' => (int) $user->expenses_count,
            'movements_created' => (int) $user->stock_movements_count,
        ])->all();

        $summary = $fuzzy && count($rows) === 1
            ? 'No exact staff match was found; using closest safe match "'.$rows[0]['staff'].'".'
            : count($rows)." staff match(es) for \"{$name}\".";

        return [$rows, ['staff', 'role', 'is_active', 'sales_created', 'purchases_created', 'expenses_created', 'movements_created'], $summary];
    }

    private function categoryLookup(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return [[], [], 'No category name was provided.'];
        }

        $categories = Category::query()
            ->where('name', 'like', "%{$name}%")
            ->withCount('products')
            ->limit(20)
            ->get();
        $fuzzy = false;

        if ($categories->isEmpty()) {
            $closest = $this->entities->closest('category', $name);

            if ($closest instanceof Category) {
                $categories = Category::query()
                    ->whereKey($closest->getKey())
                    ->withCount('products')
                    ->get();
                $fuzzy = true;
            }
        }

        $rows = $categories->map(fn (Category $category): array => [
            'category' => $category->name,
            'products' => (int) $category->products_count,
        ])->all();

        $summary = $fuzzy && count($rows) === 1
            ? 'No exact category match was found; using closest safe match "'.$rows[0]['category'].'".'
            : count($rows)." category match(es) for \"{$name}\".";

        return [$rows, ['category', 'products'], $summary];
    }

    private function movementsSummary(): array
    {
        $rows = [
            ['type' => 'in', 'units' => (int) StockMovement::query()->where('type', StockMovement::TYPE_IN)->sum('quantity')],
            ['type' => 'out', 'units' => (int) StockMovement::query()->where('type', StockMovement::TYPE_OUT)->sum('quantity')],
            ['type' => 'adjust', 'units' => (int) StockMovement::query()->where('type', StockMovement::TYPE_ADJUST)->sum('quantity')],
        ];

        return [$rows, ['type', 'units'], 'Inventory movement totals.'];
    }

    private function salesProfit($sales): float
    {
        return (float) $sales->sum(function (SaleInvoice $invoice): float {
            return (float) $invoice->saleItems->sum(
                fn ($item): float => (float) $item->quantity * ((float) $item->unit_price - (float) $item->purchase_price_at_sale)
            );
        });
    }

    /**
     * @return array{intent:string, answer:string, rows:array, columns:array}
     */
    private function fail(string $message): array
    {
        return [
            'intent' => 'unknown',
            'answer' => $message,
            'rows' => [],
            'columns' => [],
        ];
    }
}
