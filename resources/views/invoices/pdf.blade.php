@use('App\Support\Bangla')
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <title>ইনভয়েস {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: {{ $fontFamily }}; font-size: 10.5pt; color: #1c1917; line-height: 1.65; }
        p { margin: 0; }
        .muted { color: #78716c; font-size: 9pt; }
        .header { width: 100%; border-bottom: 2px solid #b45309; padding-bottom: 8px; }
        .brand { font-size: 16pt; font-weight: bold; color: #1c1917; }
        .doc-title { font-size: 13pt; font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }
        h2 { font-size: 9.5pt; color: #78716c; margin: 14px 0 3px; letter-spacing: 0.4px; }
        table.items { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin-top: 4px; }
        table.items th { background-color: #f5f5f4; border-bottom: 1px solid #d6d3d1; padding: 6px; text-align: left; }
        table.items td { border-bottom: 1px solid #e7e5e4; padding: 6px; vertical-align: top; }
        table.totals { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        table.totals td { padding: 3px 6px; }
        table.totals td.label { color: #57534e; width: 45%; }
        table.totals td.value { text-align: right; width: 55%; white-space: nowrap; }
        table.totals tr.grand td { border-top: 1.5px solid #1c1917; font-weight: bold; font-size: 11pt; padding-top: 5px; }
        .badge { border: 1px solid #d6d3d1; padding: 1px 6px; font-size: 8.5pt; }
        .footer { margin-top: 18px; border-top: 1px solid #e7e5e4; padding-top: 6px; color: #78716c; font-size: 8.5pt; }
    </style>
</head>
<body>
    <table class="header" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <p class="brand">{{ $business['name_bn'] ?? '' }}</p>
                @if (! empty($business['name']))
                    <p class="muted">{{ $business['name'] }}</p>
                @endif
                @if (! empty($business['phone']))
                    <p class="muted">ফোন: {{ Bangla::digits($business['phone']) }}</p>
                @endif
                @if (! empty($business['address']))
                    <p class="muted">{{ $business['address'] }}</p>
                @endif
            </td>
            <td class="right" style="width: 45%; vertical-align: top;">
                <p class="doc-title">ইনভয়েস</p>
                <p class="muted">ইনভয়েস নম্বর: {{ $invoice->invoice_number }}</p>
                <p class="muted">ইস্যু তারিখ: {{ Bangla::date($invoice->issued_at) }}</p>
                <p class="muted">অর্ডার কোড: {{ $order->code }}</p>
            </td>
        </tr>
    </table>

    <h2>গ্রাহক তথ্য</h2>
    <p style="font-weight: bold;">{{ $order->user?->name }}</p>
    @if ($order->user?->email)
        <p class="muted">{{ $order->user->email }}</p>
    @endif
    <p class="muted">{{ $order->delivery_address }}</p>

    <h2>পণ্যের বিবরণ</h2>
    <table class="items" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 37%;">পণ্য</th>
                <th style="width: 22%;">ভ্যারিয়েন্ট</th>
                <th class="center" style="width: 10%;">পরিমাণ</th>
                <th class="right" style="width: 13%;">একক দর</th>
                <th class="right" style="width: 13%;">মোট</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ Bangla::digits($loop->iteration) }}</td>
                    <td>
                        {{ $item->product_title }}
                        @if ($item->weight_kg !== null)
                            <span class="muted">({{ Bangla::number($item->weight_kg, 3) }} {{ $item->unit === 'kg' ? 'কেজি' : $item->unit }})</span>
                        @endif
                    </td>
                    <td>{{ $item->variant_label }}</td>
                    <td class="center">{{ Bangla::digits((int) $item->quantity) }}</td>
                    <td class="right">{{ Bangla::money($item->unit_price) }}</td>
                    <td class="right">{{ Bangla::money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 10px;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <p class="muted">পেমেন্ট পদ্ধতি: ক্যাশ অন ডেলিভারি (COD)</p>
                <p class="muted">অর্ডার স্ট্যাটাস: {{ $order->status }}</p>
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table class="totals" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="label">সাবটোটাল</td>
                        <td class="value" nowrap="nowrap">{{ Bangla::money($invoice->subtotal) }}</td>
                    </tr>
                    <tr>
                        <td class="label">ডেলিভারি চার্জ</td>
                        <td class="value" nowrap="nowrap">{{ Bangla::money($invoice->delivery_fee) }}</td>
                    </tr>
                    <tr class="grand">
                        <td class="label">সর্বমোট</td>
                        <td class="value" nowrap="nowrap">{{ Bangla::money($invoice->total) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        <p>এই ইনভয়েসটি সিস্টেম দ্বারা স্বয়ংক্রিয়ভাবে তৈরি করা হয়েছে। ইনভয়েস নম্বর: {{ $invoice->invoice_number }}।</p>
        <p>মুদ্রণ বা ডিজিটাল কপি শুধুমাত্র অর্ডার যাচাইয়ের জন্য ব্যবহারযোগ্য।</p>
    </div>
</body>
</html>
