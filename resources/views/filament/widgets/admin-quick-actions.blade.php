<x-filament-widgets::widget>
    <div class="pharmacy-quick-shell">
        <div class="pharmacy-quick-heading">
            <div>
                <h2>Admin shortcuts</h2>
                <p>Common pharmacy workflows and monitoring pages.</p>
            </div>
            <span>Admin only</span>
        </div>

        <div class="pharmacy-quick-grid">
            @foreach ($actions as $action)
                <a href="{{ $action['url'] }}" class="pharmacy-quick-card">
                    <div class="pharmacy-quick-symbol" aria-hidden="true">{{ $action['symbol'] }}</div>
                    <div class="pharmacy-quick-copy">
                        <strong>{{ $action['label'] }}</strong>
                        <small>{{ $action['description'] }}</small>
                    </div>
                    <div class="pharmacy-quick-arrow" aria-hidden="true">→</div>
                </a>
            @endforeach
        </div>
    </div>

    <style>
        .pharmacy-quick-shell { border: 1px solid rgba(148,163,184,.25); border-radius: 18px; padding: 18px; background: linear-gradient(135deg, rgba(255,255,255,.98), rgba(248,250,252,.9)); box-shadow: 0 8px 28px rgba(15,23,42,.05); }
        .pharmacy-quick-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:14px; }
        .pharmacy-quick-heading h2 { margin:0; font-size:1.05rem; font-weight:750; color:#0f172a; }
        .pharmacy-quick-heading p { margin:.3rem 0 0; font-size:.84rem; color:#64748b; }
        .pharmacy-quick-heading span { border-radius:999px; padding:5px 9px; font-size:.72rem; font-weight:700; color:#92400e; background:#fffbeb; border:1px solid #fde68a; white-space:nowrap; }
        .pharmacy-quick-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; }
        .pharmacy-quick-card { display:flex; align-items:center; gap:11px; min-width:0; padding:13px 14px; border-radius:14px; border:1px solid rgba(148,163,184,.22); background:#fff; text-decoration:none; transition:transform .12s ease, border-color .12s ease, box-shadow .12s ease; }
        .pharmacy-quick-card:hover { transform:translateY(-1px); border-color:rgba(245,158,11,.55); box-shadow:0 8px 22px rgba(15,23,42,.07); }
        .pharmacy-quick-symbol { display:grid; place-items:center; width:34px; height:34px; flex:0 0 34px; border-radius:10px; background:#fff7ed; color:#b45309; font-weight:800; }
        .pharmacy-quick-copy { display:grid; min-width:0; gap:2px; }
        .pharmacy-quick-copy strong { color:#1e293b; font-size:.88rem; }
        .pharmacy-quick-copy small { color:#64748b; font-size:.76rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .pharmacy-quick-arrow { margin-left:auto; color:#94a3b8; font-size:1rem; }
        .dark .pharmacy-quick-shell { background:linear-gradient(135deg,#18181b,#111827); border-color:#3f3f46; }
        .dark .pharmacy-quick-heading h2, .dark .pharmacy-quick-copy strong { color:#f8fafc; }
        .dark .pharmacy-quick-heading p, .dark .pharmacy-quick-copy small { color:#a1a1aa; }
        .dark .pharmacy-quick-card { background:#18181b; border-color:#3f3f46; }
        .dark .pharmacy-quick-symbol { background:#292524; color:#fbbf24; }
        @media (max-width: 1024px) { .pharmacy-quick-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width: 640px) { .pharmacy-quick-grid { grid-template-columns:1fr; } .pharmacy-quick-heading { flex-direction:column; } }
    </style>
</x-filament-widgets::widget>
