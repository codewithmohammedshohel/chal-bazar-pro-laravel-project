<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ইনভয়েস {{ $order->invoice->invoice_number }} | Chal Bazar Pro</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; font-family: "Noto Sans Bengali", "Segoe UI", system-ui, sans-serif; color: #1c1917; background: #f5f5f4; }
        .sheet { max-width: 800px; margin: 0 auto; background: #fff; border: 1px solid #e7e5e4; border-radius: 16px; padding: 32px; }
        .top { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #b45309; padding-bottom: 16px; }
        .brand { font-size: 22px; font-weight: 800; }
        .muted { color: #78716c; font-size: 12px; }
        h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .08em; color: #78716c; margin: 24px 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e7e5e4; }
        th:last-child, td:last-child { text-align: right; }
        .totals { margin-top: 16px; margin-left: auto; width: 280px; font-size: 13px; }
        .totals div { display: flex; justify-content: space-between; padding: 4px 0; }
        .totals .grand { border-top: 2px solid #1c1917; margin-top: 8px; padding-top: 8px; font-size: 16px; font-weight: 800; }
        .actions { max-width: 800px; margin: 16px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        .btn { border: 1px solid #d6d3d1; background: #fff; border-radius: 999px; padding: 8px 16px; font-size: 13px; cursor: pointer; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border: 0; border-radius: 0; padding: 0; max-width: none; }
            .actions { display: none; }
            @page { margin: 16mm; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button class="btn" onclick="window.print()">প্রিন্ট / PDF সেভ করুন</button>
        <a class="btn" href="{{ route('orders.show', $order) }}">অর্ডারে ফিরে যান</a>
    </div>

    <div class="sheet">
        <div class="top">
            <div>
                <p class="brand">চাল বাজার প্রো</p>
                <p class="muted">Chal Bazar Pro · চট্টগ্রাম, বাংলাদেশ</p>
                <p class="muted">+880 15581-23567</p>
            </div>
            <div style="text-align:right">
                <p style="font-size:18px;font-weight:800;margin:0">ইনভয়েস</p>
                <p class="muted" style="margin:4px 0 0">{{ $order->invoice->invoice_number }}</p>
                <p class="muted" style="margin:4px 0 0">ইস্যু: {{ $order->invoice->issued_at->format('d M Y') }}</p>
                <p class="muted" style="margin:4px 0 0">অর্ডার: {{ $order->code }}</p>
            </div>
        </div>

        <h2>গ্রাহক</h2>
        <p style="margin:0;font-weight:600">{{ $order->user->name }}</p>
        <p class="muted" style="margin:4px 0 0">{{ $order->user->email }}</p>
        <p class="muted" style="margin:4px 0 0;white-space:pre-line">{{ $order->delivery_address }}</p>

        <h2>পণ্যসমূহ</h2>
        <table>
            <thead>
                <tr>
                    <th>পণ্য</th>
                    <th>ইউনিট</th>
                    <th>পরিমাণ</th>
                    <th>একক দাম</th>
                    <th>মোট</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->product_title }}</td>
                        <td>{{ $item->variant_label }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>৳ {{ number_format((float) $item->unit_price, 2) }}</td>
                        <td>৳ {{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div><span>সাবটোটাল</span><span>৳ {{ number_format((float) $order->invoice->subtotal, 2) }}</span></div>
            <div><span>ডেলিভারি চার্জ</span><span>৳ {{ number_format((float) $order->invoice->delivery_fee, 2) }}</span></div>
            <div class="grand"><span>মোট</span><span>৳ {{ number_format((float) $order->invoice->total, 2) }}</span></div>
        </div>

        <p class="muted" style="margin-top:24px">পেমেন্ট: ক্যাশ অন ডেলিভারি (COD) · স্ট্যাটাস: {{ $order->status }}</p>
        <p class="muted">এই ইনভয়েসটি স্বয়ংক্রিয়ভাবে তৈরি — ডেমো ক্যাটালগের জন্য।</p>
    </div>
</body>
</html>
