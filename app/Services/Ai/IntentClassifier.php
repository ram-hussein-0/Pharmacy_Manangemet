<?php

namespace App\Services\Ai;

use Throwable;

class IntentClassifier
{
    public function __construct(private readonly LlmClientService $llm)
    {
    }

    /**
     * @return array{intent:string, params:array}
     */
    public function classify(string $question): array
    {
        $allowed = config('llm.allowed_intents', ['unknown']);

        $ruleBased = $this->classifyByRules($question);

        if ($ruleBased['intent'] !== 'unknown') {
            if (in_array($ruleBased['intent'], $allowed, true)) {
                return $ruleBased;
            }

            return ['intent' => 'unknown', 'params' => []];
        }

        $list = collect($allowed)->map(fn (string $intent): string => "- {$intent}")->implode("\n");

        $prompt = <<<PROMPT
The user asked in Arabic or English:
"{$question}"

Classify into exactly one of these allowed intents:
{$list}

Optional params:
- product_name (string) for product_lookup
- supplier_name (string) for supplier_lookup
- staff_name (string) for staff_lookup
- category_name (string) for category_lookup
- days (int, default 30) for expiring_batches
- date_from and date_to (YYYY-MM-DD) for sales_between_dates
- limit (int, default 5) for top_selling_products

Return strict JSON only:
{"intent":"...","params":{}}

If the question is unrelated to pharmacy data, return:
{"intent":"unknown","params":{}}
PROMPT;

        try {
            $raw = $this->llm->complete(
                systemPrompt: 'You output only valid minified JSON. No prose. No markdown. No SQL.',
                userPrompt: $prompt,
                jsonMode: true,
            );

            $parsed = json_decode($raw, true);

            if (! is_array($parsed)) {
                return ['intent' => 'unknown', 'params' => []];
            }

            $intent = $parsed['intent'] ?? 'unknown';

            if (! in_array($intent, $allowed, true)) {
                $intent = 'unknown';
            }

            $params = $parsed['params'] ?? [];

            return [
                'intent' => $intent,
                'params' => is_array($params) ? $params : [],
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['intent' => 'unknown', 'params' => []];
        }
    }

    /**
     * @return array{intent:string, params:array}
     */
    private function classifyByRules(string $question): array
    {
        $q = mb_strtolower(trim($question));

        if ($q === '') {
            return ['intent' => 'unknown', 'params' => []];
        }

        if ($name = $this->extractTaggedEntity($question, ['product', 'medicine', 'drug', 'منتج', 'دواء'])) {
            return ['intent' => 'product_lookup', 'params' => ['product_name' => $name]];
        }

        if ($name = $this->extractTaggedEntity($question, ['supplier', 'مورد'])) {
            return ['intent' => 'supplier_lookup', 'params' => ['supplier_name' => $name]];
        }

        if ($name = $this->extractTaggedEntity($question, ['staff', 'employee', 'user', 'موظف', 'مستخدم'])) {
            return ['intent' => 'staff_lookup', 'params' => ['staff_name' => $name]];
        }

        if ($name = $this->extractTaggedEntity($question, ['category', 'تصنيف', 'فئة'])) {
            return ['intent' => 'category_lookup', 'params' => ['category_name' => $name]];
        }

        if ($this->hasAny($q, ['low stock', 'minimum stock', 'قليل', 'منخفض', 'ناقص', 'تحت الحد', 'اقل من الحد'])) {
            return ['intent' => 'low_stock_products', 'params' => []];
        }

        if ($this->hasAny($q, ['expired', 'منتهي', 'منتهية', 'انتهت'])) {
            return ['intent' => 'expired_batches', 'params' => []];
        }

        if ($this->hasAny($q, ['expiring', 'expiry', 'expire soon', 'قريب الانتهاء', 'قريبة الانتهاء', 'ستنتهي', 'الصلاحية'])) {
            return ['intent' => 'expiring_batches', 'params' => ['days' => 30]];
        }

        if ($this->hasAny($q, ['today sales', 'sales today', 'مبيعات اليوم', 'اليوم مبيعات'])) {
            return ['intent' => 'today_sales', 'params' => []];
        }

        if ($this->hasAny($q, ['monthly sales', 'this month sales', 'مبيعات الشهر', 'هذا الشهر'])) {
            return ['intent' => 'monthly_sales', 'params' => []];
        }

        if ($this->hasAny($q, ['top selling', 'best selling', 'الأكثر مبيعا', 'الاكثر مبيعا', 'أكثر المنتجات', 'اكثر المنتجات'])) {
            return ['intent' => 'top_selling_products', 'params' => ['limit' => 5]];
        }

        if ($this->hasAny($q, ['purchase summary', 'purchases', 'purchase report', 'المشتريات', 'تقرير الشراء', 'ملخص الشراء'])) {
            return ['intent' => 'purchase_summary', 'params' => []];
        }

        if ($this->hasAny($q, ['profit', 'loss', 'profit loss', 'ربح', 'ارباح', 'أرباح', 'خسارة', 'الخسارة'])) {
            return ['intent' => 'profit_loss_summary', 'params' => []];
        }

        if ($this->hasAny($q, ['supplier', 'suppliers', 'مورد', 'الموردين', 'الموردون'])) {
            return ['intent' => 'supplier_summary', 'params' => []];
        }

        if ($this->hasAny($q, ['stock movements', 'movement', 'حركات المخزون', 'حركة المخزون', 'حركة مخزون'])) {
            return ['intent' => 'stock_movements_summary', 'params' => []];
        }

        if ($this->hasAny($q, ['inventory', 'stock', 'warehouse', 'المخزن', 'المخزون', 'وضع المخزن', 'حالة المخزن', 'وضع المخزون', 'حالة المخزون'])) {
            return ['intent' => 'inventory_summary', 'params' => []];
        }

        return ['intent' => 'unknown', 'params' => []];
    }

    /**
     * @param array<int, string> $labels
     */
    private function extractTaggedEntity(string $question, array $labels): ?string
    {
        $alternation = implode('|', array_map(fn (string $label): string => preg_quote($label, '/'), $labels));
        $quotedPattern = '/(?:'.$alternation.')\s*(?::|=|-)?\s*["“”\']([^"“”\']{1,180})["“”\']/iu';

        if (preg_match($quotedPattern, $question, $matches) === 1) {
            $value = trim($matches[1]);

            return $value !== '' ? $value : null;
        }

        $taggedPattern = '/(?:'.$alternation.')\s*:\s*([^\r\n]{1,180})$/iu';

        if (preg_match($taggedPattern, $question, $matches) === 1) {
            $value = trim($matches[1], " \t\n\r\0\x0B\"'“”");

            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * @param array<int, string> $needles
     */
    private function hasAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
