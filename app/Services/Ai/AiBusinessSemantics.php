<?php

namespace App\Services\Ai;

use App\Models\Expense;
use App\Models\StockMovement;
use App\Models\User;

class AiBusinessSemantics
{
    public function promptJson(): string
    {
        $rules = [
            'sellable_stock' => 'Sellable inventory means product_batches.quantity > 0 AND product_batches.expiry_date >= today. Expired positive quantity is physical stock but not sellable stock.',
            'stock_value' => 'Sellable stock value is SUM(product_batches.quantity * product_batches.purchase_price) over sellable batches. Physical stock value includes expired batches.',
            'sales' => 'Revenue, units sold, and gross profit use sale_invoices with status = completed unless the user explicitly asks about cancelled/non-completed sales.',
            'gross_profit' => 'Gross profit is SUM(sale_items.quantity * (sale_items.unit_price - sale_items.purchase_price_at_sale)) for the relevant completed sale rows.',
            'purchases' => 'Purchase spend/inventory acquisition normally uses purchase_invoices with status = completed unless the user explicitly requests another status.',
            'net_profit' => 'Net profit for a period is gross profit from completed sales minus expenses.amount in the same period. Use separate scalar queries when joining the facts would create fanout.',
            'purchase_batch_link' => 'purchase_items materialize into product_batches through product_batches.purchase_item_id. Treat the relationship as the provenance of the batch.',
            'sale_batch_link' => 'sale_items.product_batch_id identifies the exact batch consumed by the sale.',
            'stock_movements' => 'stock_movements.type values are in/out/adjust. Movement rows are audit evidence; do not infer current sellable stock solely from movement totals.',
            'causality' => 'Database correlations and rankings do not prove causation. Do not claim causal explanations unless the data directly supports them.',
        ];

        return json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }

    public function isTrustedLiteral(string $qualifiedColumn, mixed $value): bool
    {
        $normalized = is_bool($value) ? ($value ? '1' : '0') : strtolower(trim((string) $value));

        $trusted = [
            'sale_invoices.status' => ['completed', 'cancelled'],
            'purchase_invoices.status' => ['completed', 'draft', 'pending', 'cancelled'],
            'stock_movements.type' => [StockMovement::TYPE_IN, StockMovement::TYPE_OUT, StockMovement::TYPE_ADJUST],
            'stock_movements.reference_type' => [StockMovement::REF_PURCHASE, StockMovement::REF_SALE, StockMovement::REF_MANUAL],
            'expenses.type' => Expense::TYPES,
            'users.role' => [User::ROLE_ADMIN, User::ROLE_PHARMACIST],
            'users.is_active' => ['0', '1', 'true', 'false'],
            'products.is_active' => ['0', '1', 'true', 'false'],
        ];

        return in_array($normalized, $trusted[$qualifiedColumn] ?? [], true);
    }
}
