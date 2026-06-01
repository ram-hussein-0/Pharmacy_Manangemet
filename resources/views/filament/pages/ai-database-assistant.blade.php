<x-filament-panels::page>
    <style>
        .ai-shell {
            display: grid;
            gap: 18px;
        }

        .ai-hero {
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 22px;
            padding: 22px;
            background:
                radial-gradient(circle at top left, rgba(245, 158, 11, 0.18), transparent 30%),
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 28%),
                linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        .dark .ai-hero {
            background:
                radial-gradient(circle at top left, rgba(245, 158, 11, 0.14), transparent 30%),
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 28%),
                linear-gradient(135deg, rgba(24, 24, 27, 0.98), rgba(9, 9, 11, 0.96));
            border-color: rgba(63, 63, 70, 0.85);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.28);
        }

        .ai-hero-top {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 18px;
        }

        .ai-title-wrap {
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .ai-icon,
        .ai-avatar {
            display: grid;
            place-items: center;
            flex: none;
            color: rgb(180, 83, 9);
            background: rgba(245, 158, 11, 0.14);
            border: 1px solid rgba(245, 158, 11, 0.25);
        }

        .ai-icon {
            width: 44px;
            height: 44px;
            border-radius: 16px;
            font-size: 22px;
        }

        .ai-avatar {
            width: 30px;
            height: 30px;
            border-radius: 12px;
            margin-top: 2px;
            box-shadow: 0 8px 18px rgba(245, 158, 11, 0.12);
        }

        .dark .ai-icon,
        .dark .ai-avatar {
            color: rgb(253, 186, 116);
            background: rgba(245, 158, 11, 0.12);
            border-color: rgba(245, 158, 11, 0.22);
        }

        .ai-avatar svg {
            width: 18px;
            height: 18px;
        }

        .ai-title {
            margin: 0;
            font-size: 20px;
            font-weight: 750;
            letter-spacing: -0.02em;
            color: rgb(15, 23, 42);
        }

        .dark .ai-title {
            color: rgb(250, 250, 250);
        }

        .ai-subtitle {
            margin-top: 5px;
            color: rgb(100, 116, 139);
            font-size: 14px;
            line-height: 1.55;
            max-width: 780px;
        }

        .dark .ai-subtitle {
            color: rgb(161, 161, 170);
        }

        .ai-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 12px;
            font-weight: 650;
            color: rgb(21, 128, 61);
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.22);
            white-space: nowrap;
        }

        .ai-warning {
            border: 1px solid rgba(245, 158, 11, 0.35);
            background: rgba(245, 158, 11, 0.10);
            color: rgb(146, 64, 14);
            border-radius: 16px;
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.55;
            margin-bottom: 16px;
        }

        .dark .ai-warning {
            color: rgb(253, 230, 138);
            background: rgba(245, 158, 11, 0.09);
            border-color: rgba(245, 158, 11, 0.22);
        }

        .ai-thread {
            border: 1px solid rgba(148, 163, 184, 0.24);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.72);
            min-height: 0;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .dark .ai-thread {
            background: rgba(24, 24, 27, 0.72);
            border-color: rgba(63, 63, 70, 0.85);
        }

        .ai-turn {
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }

        .ai-turn-assistant {
            justify-content: flex-start;
        }

        .ai-turn-user {
            justify-content: flex-end;
        }

        .ai-bubble {
            display: inline-block;
            width: fit-content;
            max-width: min(760px, 82%);
            border-radius: 16px;
            padding: 10px 12px;
            font-size: 14px;
            line-height: 1.5;
            text-align: start;
            unicode-bidi: isolate;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        }

        .ai-bubble-user {
            width: max-content;
            max-width: min(460px, 70%);
            min-width: 0;
            color: white;
            background: linear-gradient(135deg, rgb(217, 119, 6), rgb(234, 88, 12));
            border-bottom-right-radius: 6px;
            padding: 9px 13px;
        }

        .ai-bubble-assistant {
            color: rgb(30, 41, 59);
            background: white;
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-bottom-left-radius: 6px;
        }

        .dark .ai-bubble-assistant {
            color: rgb(244, 244, 245);
            background: rgb(39, 39, 42);
            border-color: rgb(63, 63, 70);
        }

        .ai-message-text {
            white-space: pre-line;
            overflow-wrap: anywhere;
            word-break: normal;
            line-height: 1.55;
            text-align: start;
            unicode-bidi: plaintext;
        }

        .ai-bubble-user .ai-message-text {
            display: inline;
            white-space: normal;
            line-height: 1.4;
        }

        .ai-intent {
            display: inline-flex;
            margin-bottom: 8px;
            border-radius: 999px;
            padding: 4px 8px;
            direction: ltr;
            unicode-bidi: isolate;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 11px;
            color: rgb(180, 83, 9);
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.22);
        }

        .ai-table-wrap {
            margin-top: 12px;
            overflow-x: auto;
            border-radius: 14px;
            border: 1px solid rgba(226, 232, 240, 0.95);
        }

        .dark .ai-table-wrap {
            border-color: rgb(63, 63, 70);
        }

        .ai-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .ai-table th {
            text-align: start;
            padding: 9px 11px;
            background: rgb(248, 250, 252);
            color: rgb(71, 85, 105);
            font-weight: 700;
            white-space: nowrap;
        }

        .dark .ai-table th {
            background: rgb(24, 24, 27);
            color: rgb(212, 212, 216);
        }

        .ai-table td {
            padding: 9px 11px;
            border-top: 1px solid rgba(226, 232, 240, 0.95);
            color: rgb(51, 65, 85);
            white-space: normal;
            vertical-align: top;
        }

        .dark .ai-table td {
            border-top-color: rgb(63, 63, 70);
            color: rgb(228, 228, 231);
        }

        .ai-column-label,
        .ai-number {
            direction: ltr;
            unicode-bidi: isolate;
        }

        .ai-number {
            display: inline-block;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .ai-cell-text {
            unicode-bidi: isolate;
            overflow-wrap: anywhere;
        }

        .ai-composer {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: center;
            margin-top: 16px;
        }

        .ai-input {
            width: 100%;
            min-height: 44px;
            border-radius: 15px;
            border: 1px solid rgba(148, 163, 184, 0.45);
            background: white;
            color: rgb(15, 23, 42);
            padding: 10px 13px;
            font-size: 14px;
            outline: none;
            transition: border-color 120ms ease, box-shadow 120ms ease;
        }

        .ai-input:focus {
            border-color: rgba(245, 158, 11, 0.8);
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.13);
        }

        .dark .ai-input {
            background: rgb(24, 24, 27);
            border-color: rgb(63, 63, 70);
            color: rgb(250, 250, 250);
        }

        .ai-footnote {
            display: flex;
            gap: 9px;
            align-items: flex-start;
            margin-top: 14px;
            color: rgb(100, 116, 139);
            font-size: 12px;
            line-height: 1.55;
        }

        .dark .ai-footnote {
            color: rgb(161, 161, 170);
        }

        .ai-examples {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .ai-example {
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.3);
            background: rgba(255, 255, 255, 0.55);
            color: rgb(71, 85, 105);
            padding: 6px 10px;
            font-size: 12px;
        }

        .dark .ai-example {
            background: rgba(39, 39, 42, 0.8);
            border-color: rgba(82, 82, 91, 0.85);
            color: rgb(212, 212, 216);
        }

        @media (max-width: 720px) {
            .ai-hero-top {
                flex-direction: column;
            }

            .ai-composer {
                grid-template-columns: 1fr;
            }

            .ai-bubble,
            .ai-bubble-user,
            .ai-bubble-assistant {
                max-width: 100%;
            }
        }
    

        /* Assistant bubble compact override */
        .ai-thread .ai-turn:not(.ai-turn-user) {
            justify-content: flex-start;
            align-items: flex-start;
            gap: 8px;
        }

        .ai-thread .ai-turn:not(.ai-turn-user) > :last-child {
            flex: 0 1 auto !important;
            width: auto !important;
            max-width: min(760px, calc(100% - 44px)) !important;
        }

        .ai-thread .ai-turn:not(.ai-turn-user) .ai-bubble {
            display: inline-flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            width: fit-content !important;
            min-height: 0 !important;
            max-width: min(760px, calc(100% - 44px)) !important;
            padding: 10px 12px !important;
            text-align: start !important;
        }

        .ai-thread .ai-turn:not(.ai-turn-user) .ai-message-text {
            width: auto !important;
            margin: 0 !important;
            text-align: start !important;
            line-height: 1.55 !important;
        }

        .ai-thread .ai-turn:not(.ai-turn-user) .ai-bubble > * {
            margin-inline-start: 0 !important;
            margin-inline-end: 0 !important;
        }

        .ai-thread .ai-turn:not(.ai-turn-user) .ai-bubble p,
        .ai-thread .ai-turn:not(.ai-turn-user) .ai-bubble div,
        .ai-thread .ai-turn:not(.ai-turn-user) .ai-bubble span {
            text-align: start !important;
        }

        .ai-thread .ai-turn:not(.ai-turn-user) table {
            width: auto !important;
            min-width: 440px;
            max-width: 100%;
        }

        .ai-thread .ai-turn:not(.ai-turn-user) .overflow-x-auto,
        .ai-thread .ai-turn:not(.ai-turn-user) .table-wrapper {
            width: auto !important;
            max-width: 100% !important;
        }

    
        .ai-bubble-assistant:has(.ai-table-wrap) {
            width: fit-content !important;
            max-width: min(760px, calc(100% - 44px)) !important;
        }

        .ai-bubble-assistant:not(:has(.ai-table-wrap)) {
            width: max-content !important;
            max-width: min(680px, calc(100% - 44px)) !important;
        }

    
        .ai-turn-assistant .ai-bubble-rtl .ai-message-text {
            align-self: flex-end !important;
            direction: rtl !important;
            text-align: right !important;
            max-width: 100%;
        }

        .ai-turn-assistant .ai-bubble-rtl .ai-intent,
        .ai-turn-assistant .ai-bubble-rtl .ai-table-wrap {
            align-self: flex-start !important;
            direction: ltr !important;
        }

        .ai-turn-assistant .ai-bubble-rtl .ai-table {
            direction: ltr !important;
        }

    </style>

    <div class="ai-shell">
        @php
            $formatAiColumn = function (string $column): string {
                return (string) \Illuminate\Support\Str::of($column)
                    ->replace('_', ' ')
                    ->headline();
            };

            $formatAiValue = function ($value): string {
                if ($value === null || $value === '') {
                    return '—';
                }

                if (is_bool($value)) {
                    return $value ? 'Yes' : 'No';
                }

                if (is_int($value)) {
                    return number_format($value);
                }

                if (is_float($value)) {
                    return number_format($value, floor($value) == $value ? 0 : 2);
                }

                if (is_string($value) && is_numeric($value)) {
                    $number = (float) $value;

                    return number_format($number, floor($number) == $number ? 0 : 2);
                }

                if (is_scalar($value)) {
                    return (string) $value;
                }

                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
            };

            $isAiNumeric = function ($value): bool {
                return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
            };
        @endphp

        <div class="ai-hero">
            <div class="ai-hero-top">
                <div class="ai-title-wrap">
                    <div class="ai-icon">✦</div>

                    <div>
                        <h2 class="ai-title">Safe Pharmacy Data Assistant</h2>
                        <div class="ai-subtitle">
                            Ask about stock, expiry, sales, profit, suppliers, products, or stock movements.
                            The assistant classifies your question into a fixed intent and reads only approved pharmacy data.
                        </div>

                        <div class="ai-examples">
                            <span class="ai-example">low stock products</span>
                            <span class="ai-example">expiring batches</span>
                            <span class="ai-example">today sales</span>
                            <span class="ai-example">profit this month</span>
                            <span class="ai-example">find product by name</span>
                        </div>
                    </div>
                </div>

                <div class="ai-badge">
                    ● Intent-only mode
                </div>
            </div>

            @if (blank(config('llm.api_key')))
                <div class="ai-warning">
                    <strong>LLM is not configured.</strong>
                    Set <code>LLM_PROVIDER</code>, <code>LLM_MODEL</code>, and <code>LLM_API_KEY</code> in your local <code>.env</code>.
                    The page is available, but real classification will return a safe fallback until the key is configured.
                </div>
            @endif

            <div class="ai-thread" wire:loading.class="opacity-60" wire:target="send">
                @foreach ($messages as $message)
                    @php
                        $isUser = ($message['role'] ?? 'assistant') === 'user';
                        $content = trim((string) ($message['content'] ?? ''));
                        $rows = $message['rows'] ?? [];
                        $columns = $message['columns'] ?? [];
                        $isAssistantRtl = ! $isUser && preg_match('/\p{Arabic}/u', $content) === 1;
                    @endphp

                    <div class="ai-turn {{ $isUser ? 'ai-turn-user' : 'ai-turn-assistant' }}">
                        @if (! $isUser)
                            <div class="ai-avatar" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M12 3.75L13.85 9.3L19.5 11.25L13.85 13.2L12 18.75L10.15 13.2L4.5 11.25L10.15 9.3L12 3.75Z" fill="currentColor"/>
                                    <path d="M18.5 3.5L19.15 5.35L21 6L19.15 6.65L18.5 8.5L17.85 6.65L16 6L17.85 5.35L18.5 3.5Z" fill="currentColor" opacity=".65"/>
                                </svg>
                            </div>
                        @endif

                        <div class="ai-bubble {{ $isUser ? 'ai-bubble-user' : 'ai-bubble-assistant' }} {{ $isAssistantRtl ? 'ai-bubble-rtl' : '' }}" dir="auto">
                            @if (! $isUser && ! empty($message['intent']) && $message['intent'] !== 'unknown')
                                <div class="ai-intent" dir="ltr"><bdi>{{ $message['intent'] }}</bdi></div>
                            @endif

                            <div class="ai-message-text" dir="auto">{{ $content }}</div>

                            @if (! $isUser && ! empty($rows) && ! empty($columns))
                                <div class="ai-table-wrap">
                                    <table class="ai-table" dir="ltr">
                                        <thead>
                                            <tr>
                                                @foreach ($columns as $column)
                                                    <th><span class="ai-column-label">{{ $formatAiColumn($column) }}</span></th>
                                                @endforeach
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @foreach (array_slice($rows, 0, 50) as $row)
                                                <tr>
                                                    @foreach ($columns as $column)
                                                        @php
                                                            $value = is_array($row)
                                                                ? ($row[$column] ?? null)
                                                                : data_get($row, $column);

                                                            $displayValue = $formatAiValue($value);
                                                        @endphp

                                                        <td>
                                                            @if ($isAiNumeric($value))
                                                                <span class="ai-number">{{ $displayValue }}</span>
                                                            @else
                                                                <bdi class="ai-cell-text" dir="auto">{{ $displayValue }}</bdi>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div wire:loading wire:target="send" class="ai-turn ai-turn-assistant">
                    <div class="ai-avatar" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 3.75L13.85 9.3L19.5 11.25L13.85 13.2L12 18.75L10.15 13.2L4.5 11.25L10.15 9.3L12 3.75Z" fill="currentColor"/>
                        </svg>
                    </div>

                    <div class="ai-bubble ai-bubble-assistant">
                        Thinking…
                    </div>
                </div>
            </div>

            <div class="ai-composer">
                <input
                    type="text"
                    dir="auto"
                    wire:model="question"
                    wire:keydown.enter.prevent="send"
                    maxlength="500"
                    placeholder="Ask a pharmacy data question..."
                    class="ai-input"
                    autocomplete="off"
                />

                <x-filament::button
                    type="button"
                    icon="heroicon-o-paper-airplane"
                    wire:click="send"
                    wire:loading.attr="disabled"
                    wire:target="send"
                >
                    Ask
                </x-filament::button>
            </div>

            <div class="ai-footnote">
                <span>⚠</span>
                <span>
                    No SQL is generated from user input. The LLM can only classify the question and optionally summarize
                    rows returned by fixed Eloquent handlers.
                </span>
            </div>
        </div>
    </div>
</x-filament-panels::page>
