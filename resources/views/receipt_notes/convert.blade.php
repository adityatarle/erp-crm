@include('layout.header')

<body class="act-receiptnotes-convert">
    <div class="main-content-area">
        <div class="container p-3 p-md-4 mx-auto">
            <div class="card shadow-sm w-100 border-0">
                <div class="card-header bg-success d-flex justify-content-between align-items-center text-white">
                    <h1 class="mb-0 h5">Convert Receipt Note to Purchase Entry</h1>
                    <a href="{{ route('receipt_notes.index') }}" class="btn btn-light btn-sm">Cancel</a>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('receipt_notes.convert', $receiptNote->id) }}" method="POST">
                        @csrf
                        {{-- Top section: now requires Invoice details --}}
                        <div class="card p-3 mb-4">
                             <div class="row g-3">
                                <div class="col-md-4"><p><strong>Party:</strong> {{ $receiptNote->party->name }}</p></div>
                                <div class="col-md-4"><p><strong>Receipt Date:</strong> {{ $receiptNote->receipt_date->format('d-m-Y') }}</p></div>
                                <div class="col-md-4">
                                    <label for="purchase_order_id" class="form-label">Link to Purchase Order</label>
                                    <select name="purchase_order_id" id="purchase_order_id" class="form-select" required>
                                        <option value="">Select PO</option>
                                        @foreach($purchaseOrders as $po)
                                            <option value="{{ $po->id }}" {{ $receiptNote->purchase_order_id == $po->id ? 'selected' : '' }}>
                                                {{ $po->purchase_order_number }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="invoice_number" class="form-label">Invoice Number (from supplier)</label>
                                    <input type="text" name="invoice_number" id="invoice_number" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="invoice_date" class="form-label">Invoice Date (from supplier)</label>
                                    <input type="date" name="invoice_date" id="invoice_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                </div>
                             </div>
                        </div>
                        
                        {{-- Items table pre-filled from Receipt Note --}}
                        <h5 class="mt-4">Items to be entered</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Discount %</th>
                                        <th>CGST %</th>
                                        <th>SGST %</th>
                                        <th>IGST %</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($receiptNote->items as $index => $item)
                                        <tr>
                                            <td>
                                                {{ $item->product->name }}
                                                <input type="hidden" name="products[{{$index}}][product_id]" value="{{ $item->product_id }}">
                                            </td>
                                            <td><input type="number" name="products[{{$index}}][quantity]" class="form-control" value="{{ $item->quantity }}" required></td>
                                            <td><input type="number" name="products[{{$index}}][unit_price]" class="form-control" value="{{ $item->unit_price ?? '0.00' }}" step="0.01" required></td>
                                            <td><input type="number" name="products[{{$index}}][discount]" class="form-control" value="{{ $item->discount ?? '0' }}" step="0.01"></td>
                                            <td><input type="number" name="products[{{$index}}][cgst_rate]" class="form-control" value="{{ $item->cgst_rate ?? '0' }}" step="0.01"></td>
                                            <td><input type="number" name="products[{{$index}}][sgst_rate]" class="form-control" value="{{ $item->sgst_rate ?? '0' }}" step="0.01"></td>
                                            <td><input type="number" name="products[{{$index}}][igst_rate]" class="form-control" value="{{ $item->igst_rate ?? '0' }}" step="0.01"></td>
                                            <td>
                                                <select name="products[{{$index}}][status]" class="form-select" required>
                                                    <option value="received" {{ ($item->status ?? 'received') == 'received' ? 'selected' : '' }}>Received</option>
                                                    <option value="pending" {{ ($item->status ?? '') == 'pending' ? 'selected' : '' }}>Pending</option>
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-success btn-lg">Confirm & Create Purchase Entry</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
@include('layout.footer')