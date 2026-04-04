<!DOCTYPE html>
<html>
<head>
<style>
    body { font-family: Arial, sans-serif; background: #f9fafb; margin: 0; padding: 20px; }
    .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; }
    .header { background: #7c3aed; padding: 30px; text-align: center; }
    .header h1 { color: white; margin: 0; font-size: 24px; }
    .header p { color: #ddd6fe; margin: 6px 0 0; }
    .body { padding: 30px; }
    .label { font-size: 12px; color: #888; }
    .value { font-size: 15px; color: #111; font-weight: bold; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th { background: #f3f4f6; padding: 8px 12px; text-align: left; font-size: 12px; color: #555; }
    td { padding: 8px 12px; border-bottom: 1px solid #f3f4f6; font-size: 13px; }
    .total { text-align: right; font-size: 18px; font-weight: bold; color: #7c3aed; margin-top: 10px; }
    .footer { text-align: center; padding: 20px; color: #aaa; font-size: 11px; border-top: 1px solid #f3f4f6; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Pro_BMS</h1>
        <p>Purchase Order</p>
    </div>
    <div class="body">
        <p>Hello {{ $purchaseOrder->supplier?->name ?? 'Supplier' }},</p>
        <p>A new purchase order has been created for your review.</p>

        <div class="label">PO Number</div>
        <div class="value">{{ $purchaseOrder->po_number }}</div>

        <div class="label">Expected Date</div>
        <div class="value">{{ $purchaseOrder->expected_date ?? 'TBD' }}</div>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Unit Cost</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchaseOrder->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->unit_cost, 2) }}</td>
                    <td>${{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total">Total: ${{ number_format($purchaseOrder->total, 2) }}</div>
    </div>
    <div class="footer">Pro_BMS Business Management System</div>
</div>
</body>
</html>