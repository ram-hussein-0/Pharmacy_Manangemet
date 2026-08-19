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
            grid-template-columns: minmax(0, 1fr) auto auto;
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


        .ai-entity-trigger {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border: 1px solid rgba(148, 163, 184, 0.38);
            border-radius: 14px;
            padding: 0 13px;
            background: white;
            color: rgb(71, 85, 105);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }

        .ai-entity-trigger:hover,
        .ai-entity-trigger.is-open {
            border-color: rgba(245, 158, 11, 0.7);
            color: rgb(146, 64, 14);
            background: rgb(255, 251, 235);
        }

        .dark .ai-entity-trigger {
            background: rgb(24, 24, 27);
            border-color: rgb(63, 63, 70);
            color: rgb(212, 212, 216);
        }

        .dark .ai-entity-trigger:hover,
        .dark .ai-entity-trigger.is-open {
            background: rgb(69, 26, 3);
            border-color: rgb(180, 83, 9);
            color: rgb(253, 230, 138);
        }

        .ai-entity-popover {
            width: min(100%, 560px);
            margin: 14px 0 0 auto;
            padding: 14px;
            border: 1px solid rgba(148, 163, 184, 0.28);
            border-radius: 16px;
            background: white;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.10);
        }

        .dark .ai-entity-popover {
            background: rgb(24, 24, 27);
            border-color: rgb(63, 63, 70);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.24);
        }

        .ai-entity-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 11px;
        }

        .ai-entity-heading strong { color: rgb(30, 41, 59); font-size: 13px; }
        .ai-entity-heading span { color: rgb(100, 116, 139); font-size: 11px; line-height: 1.45; }
        .dark .ai-entity-heading strong { color: rgb(244, 244, 245); }
        .dark .ai-entity-heading span { color: rgb(161, 161, 170); }

        .ai-entity-close {
            border: 0;
            border-radius: 9px;
            width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            background: rgba(148, 163, 184, 0.12);
            color: rgb(71, 85, 105);
            cursor: pointer;
            font-size: 18px;
        }

        .ai-entity-controls { display: grid; grid-template-columns: 180px 1fr; gap: 8px; }
        .ai-entity-select,
        .ai-entity-search { width: 100%; border: 1px solid rgba(148,163,184,.38); border-radius: 12px; padding: 8px 11px; font-size: 13px; background: white; color: rgb(15,23,42); outline: none; }
        .ai-entity-select:focus,
        .ai-entity-search:focus { border-color: rgba(245,158,11,.75); box-shadow: 0 0 0 3px rgba(245,158,11,.1); }
        .dark .ai-entity-select,
        .dark .ai-entity-search { background: rgb(39,39,42); border-color: rgb(82,82,91); color: rgb(250,250,250); }

        .ai-entity-list { display: grid; gap: 6px; max-height: 280px; overflow-y: auto; margin-top: 10px; padding-right: 2px; scrollbar-width: thin; }
        .ai-entity-chip { width: 100%; display: grid; gap: 2px; text-align: left; border: 1px solid rgba(148,163,184,.22); border-radius: 11px; padding: 9px 10px; background: rgba(248,250,252,.78); cursor: pointer; }
        .ai-entity-chip:hover { border-color: rgba(245,158,11,.65); background: rgb(255,251,235); }
        .ai-entity-chip strong { color: rgb(30,41,59); font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ai-entity-chip small { color: rgb(100,116,139); font-size: 10px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dark .ai-entity-chip { background: rgb(39,39,42); border-color: rgb(63,63,70); }
        .dark .ai-entity-chip:hover { background: rgb(41,37,36); border-color: rgb(180,83,9); }
        .dark .ai-entity-chip strong { color: rgb(244,244,245); }
        .dark .ai-entity-chip small { color: rgb(161,161,170); }
        .ai-entity-empty { padding: 14px 4px 5px; color: rgb(100,116,139); font-size: 12px; text-align: center; }

        @media (max-width: 720px) {
            .ai-entity-controls { grid-template-columns: 1fr; }
            .ai-entity-popover { width: 100%; }
        }



        /* Production answer typography: safe Markdown + mixed Arabic/English direction. */
        .ai-markdown {
            display: grid;
            gap: 0;
            width: 100%;
            color: inherit;
            line-height: 1.72;
            overflow-wrap: anywhere;
        }

        .ai-markdown > :first-child { margin-top: 0 !important; }
        .ai-markdown > :last-child { margin-bottom: 0 !important; }
        .ai-markdown p { margin: 0 0 .72rem; }
        .ai-markdown h1,
        .ai-markdown h2,
        .ai-markdown h3,
        .ai-markdown h4 {
            margin: .9rem 0 .45rem;
            color: rgb(30, 41, 59);
            font-weight: 780;
            letter-spacing: -.015em;
        }
        .ai-markdown h1 { font-size: 1.18rem; }
        .ai-markdown h2 { font-size: 1.08rem; }
        .ai-markdown h3,
        .ai-markdown h4 { font-size: 1rem; }
        .ai-markdown strong { color: rgb(146, 64, 14); font-weight: 780; }
        .ai-markdown em { color: rgb(71, 85, 105); }
        .ai-markdown ul,
        .ai-markdown ol { margin: .3rem 0 .8rem; }
        .ai-markdown ul[dir="rtl"],
        .ai-markdown ol[dir="rtl"] { padding-right: 1.45rem; padding-left: 0; }
        .ai-markdown ul[dir="ltr"],
        .ai-markdown ol[dir="ltr"] { padding-left: 1.45rem; padding-right: 0; }
        .ai-markdown li { margin: .22rem 0; }
        .ai-markdown [dir="rtl"] { text-align: right; unicode-bidi: isolate; }
        .ai-markdown [dir="ltr"] { text-align: left; unicode-bidi: isolate; }
        .ai-markdown [dir="auto"] { text-align: start; unicode-bidi: plaintext; }
        .ai-markdown blockquote {
            margin: .7rem 0;
            padding: .55rem .85rem;
            border-inline-start: 3px solid rgba(245, 158, 11, .62);
            border-radius: .6rem;
            background: rgba(255, 247, 237, .75);
            color: rgb(71, 85, 105);
        }
        .ai-markdown code {
            direction: ltr;
            unicode-bidi: isolate;
            border-radius: .42rem;
            padding: .1rem .34rem;
            background: rgba(120, 53, 15, .08);
            color: rgb(124, 45, 18);
            font-size: .9em;
        }
        .ai-markdown pre {
            max-width: 100%;
            overflow-x: auto;
            margin: .75rem 0;
            border: 1px solid rgba(245, 158, 11, .16);
            border-radius: .85rem;
            padding: .8rem;
            background: rgb(255, 251, 235);
            text-align: left;
        }
        .ai-markdown pre code { padding: 0; background: transparent; }
        .ai-markdown table {
            width: 100%;
            margin: .8rem 0;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border: 1px solid rgba(245, 158, 11, .18);
            border-radius: .85rem;
        }
        .ai-markdown th,
        .ai-markdown td { padding: .55rem .68rem; border-bottom: 1px solid rgba(245, 158, 11, .13); }
        .ai-markdown th { background: rgba(255, 247, 237, .86); font-weight: 750; }
        .ai-markdown tr:last-child td { border-bottom: 0; }
        .ai-markdown a { color: rgb(180, 83, 9); text-decoration: underline; text-underline-offset: 2px; }

        .ai-stream-text {
            min-width: 9rem;
            white-space: normal;
            overflow-wrap: anywhere;
            unicode-bidi: plaintext;
            line-height: 1.65;
        }

        /* Warm pharmacy surface treatment for the assistant itself. */
        .ai-hero {
            border-color: rgba(245, 158, 11, .24) !important;
            background:
                radial-gradient(circle at top left, rgba(245, 158, 11, .20), transparent 31%),
                radial-gradient(circle at top right, rgba(251, 191, 36, .10), transparent 30%),
                linear-gradient(135deg, rgba(255, 253, 247, .99), rgba(255, 247, 237, .96)) !important;
        }
        .ai-thread,
        .ai-bubble-assistant,
        .ai-entity-popover,
        .ai-input,
        .ai-entity-trigger,
        .ai-entity-select,
        .ai-entity-search {
            background-color: rgb(255, 253, 247) !important;
            border-color: rgba(245, 158, 11, .18) !important;
        }
        .ai-entity-chip { background: rgba(255, 251, 235, .72) !important; }

        .dark .ai-markdown h1,
        .dark .ai-markdown h2,
        .dark .ai-markdown h3,
        .dark .ai-markdown h4 { color: rgb(250, 250, 250); }
        .dark .ai-markdown strong { color: rgb(253, 186, 116); }
        .dark .ai-markdown em { color: rgb(212, 212, 216); }
        .dark .ai-markdown blockquote,
        .dark .ai-markdown pre,
        .dark .ai-markdown th { background: rgba(69, 26, 3, .28); }
        .dark .ai-markdown code { color: rgb(254, 215, 170); background: rgba(120, 53, 15, .26); }
        .dark .ai-markdown table,
        .dark .ai-markdown th,
        .dark .ai-markdown td { border-color: rgba(180, 83, 9, .28); }

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
                        <h2 class="ai-title">Pharmacy AI Assistant</h2>
                        <div class="ai-subtitle">
                            Ask naturally about inventory, sales, purchases, suppliers, expenses, staff activity, and other pharmacy data.
                            Answers are based on verified read-only information from the system.
                        </div>

                        <div class="ai-examples">
                            <span class="ai-example">low stock products</span>
                            <span class="ai-example">expiring batches</span>
                            <span class="ai-example">today sales</span>
                            <span class="ai-example">profit this month</span>
                            <span class="ai-example">product: "Panadol"</span>
                            <span class="ai-example">supplier: "Exact supplier"</span>
                        </div>
                    </div>
                </div>

                <div class="ai-badge">
                    ● Connected · Read-only
                </div>
            </div>

            @if (blank(config('llm.api_key')))
                <div class="ai-warning">
                    <strong>AI service is currently unavailable.</strong>
                    Verified fallback answers remain available for supported requests while the AI connection is restored.
                </div>
            @endif


            <div class="ai-thread">
                @foreach ($messages as $message)
                    @php
                        $isUser = ($message['role'] ?? 'assistant') === 'user';
                        $content = trim((string) ($message['content'] ?? ''));
                        $rows = $message['rows'] ?? [];
                        $columns = $message['columns'] ?? [];
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

                        <div class="ai-bubble {{ $isUser ? 'ai-bubble-user' : 'ai-bubble-assistant' }}" dir="auto">
                            @if ($isUser)
                                <div class="ai-message-text" dir="auto">{{ $content }}</div>
                            @else
                                <div class="ai-markdown">
                                    {!! app(\App\Services\Ai\AiMarkdownRenderer::class)->render($content) !!}
                                </div>
                            @endif

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

                    <div class="ai-bubble ai-bubble-assistant ai-stream-bubble">
                        <div class="ai-stream-text ai-markdown" wire:stream="ai-answer-stream" dir="auto">Analyzing verified data…</div>
                    </div>
                </div>
            </div>

            @if ($entityPickerOpen)
                <div class="ai-entity-popover" wire:key="ai-entity-popover">
                    <div class="ai-entity-heading">
                        <div>
                            <strong>Insert database entity</strong><br>
                            <span>Search only when needed. Selecting a result inserts the canonical database name into the question.</span>
                        </div>
                        <button type="button" wire:click="closeEntityPicker" class="ai-entity-close" aria-label="Close entity picker">×</button>
                    </div>

                    <div class="ai-entity-controls">
                        <select wire:model.live="entityType" class="ai-entity-select" aria-label="Entity type">
                            @foreach ($this->entityTypes() as $type => $label)
                                <option value="{{ $type }}">{{ $label }}</option>
                            @endforeach
                        </select>

                        <input
                            type="search"
                            wire:model.live.debounce.300ms="entitySearch"
                            placeholder="Search the selected entity..."
                            class="ai-entity-search"
                            autocomplete="off"
                        />
                    </div>

                    <div class="ai-entity-list">
                        @forelse ($this->getEntityOptions() as $entity)
                            <button
                                type="button"
                                wire:key="ai-entity-{{ $entityType }}-{{ $entity['id'] }}"
                                wire:click="insertEntity('{{ $entityType }}', {{ $entity['id'] }})"
                                class="ai-entity-chip"
                                title="Insert {{ $entity['name'] }}"
                            >
                                <strong dir="auto">{{ $entity['name'] }}</strong>
                                <small dir="auto">{{ $entity['meta'] }}</small>
                            </button>
                        @empty
                            <div class="ai-entity-empty">No matching entities.</div>
                        @endforelse
                    </div>
                </div>
            @endif

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

                <button
                    type="button"
                    wire:click="toggleEntityPicker"
                    class="ai-entity-trigger {{ $entityPickerOpen ? 'is-open' : '' }}"
                    aria-expanded="{{ $entityPickerOpen ? 'true' : 'false' }}"
                >
                    <span aria-hidden="true">＋</span>
                    Insert entity
                </button>

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
                    Answers use verified read-only pharmacy data. The assistant cannot modify inventory, sales, users, or other records.
                </span>
            </div>
        </div>
    </div>
</x-filament-panels::page>
