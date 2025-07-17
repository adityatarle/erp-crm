<!DOCTYPE html>
<html lang="en">

<head>
    @include('layout.header')
    <style>
        body {
            background-color: #f4f7f9;
        }

        .main-content-area {
            min-height: 100vh;
        }

        .card-header h1 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
        }

        .card.form-section {
            border: 1px solid #dee2e6;
            box-shadow: none;
        }

        .products-header,
        .product-item-row {
            display: grid;
            grid-template-columns: 3fr 1fr 1.5fr 1fr 1fr 1fr 1fr 1fr 0.5fr;
            gap: 1rem;
            align-items: start;
            padding: 0.75rem 1rem;
        }

        .products-header {
            background-color: #e9ecef;
            border-radius: .375rem;
            font-weight: 600;
            font-size: 0.85rem;
            color: #495057;
            text-transform: uppercase;
        }

        .product-item-row {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: .375rem;
            margin-bottom: 0.75rem;
        }

        .product-item-row .form-control[readonly] {
            background-color: #e9ecef;
            pointer-events: none;
        }

        .totals-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: .375rem;
            padding: 1.5rem;
        }

        .totals-card .row {
            font-size: 1.1rem;
        }

        .totals-card .grand-total {
            font-size: 1.4rem;
            font-weight: bold;
        }

        @media (max-width: 1200px) {
            .products-header {
                display: none;
            }

            .product-item-row {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>

<body class="act-receiptnotes">
    <div class="main-content-area">
        <div class="container p-3 p-md-4 mx-auto">
            @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card shadow-sm w-100 border-0">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center text-white">
                    <h1 class="mb-0 h5">Edit Receipt Note</h1>
                    <a href="{{ route('receipt_notes.index') }}" class="btn btn-light btn-sm">Back to List</a>
                </div>
                <div class="card-body p-4">
                    <!-- Update Form -->
                    <form action="{{ route('receipt_notes.update', $receiptNote->id) }}" method="POST" id="edit-receipt-note-form">
                        @csrf
                        @method('PUT')
                        <div class="card form-section p-3 mb-4">
                            <div class="row g-3">
                                
                                <div class="col-md-6">
                                    <label for="purchase_order_id" class="form-label">Purchase Order (Optional)</label>
                                    <input type="text"name="purchase_order_id" id="purchase_order_id" class="form-control" value="{{ $receiptNote->purchase_order_number }}" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label for="party_name" class="form-label">Party</label>
                                    <input type="text" id="party_name" class="form-control" value="{{ $receiptNote->party->name }}" readonly>
                                    <input type="hidden" name="party_id" id="party_id" value="{{ $receiptNote->party_id }}">
                                    @error('party_id')
                                    <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="receipt_number" class="form-label">Receipt Number</label>
                                    <input type="text" name="receipt_number" id="receipt_number" class="form-control" value="{{ old('receipt_number', $receiptNote->receipt_number) }}" required>
                                    @error('receipt_number')
                                    <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="receipt_date" class="form-label">Receipt Date</label>
                                    <input type="date" name="receipt_date" id="receipt_date" class="form-control" value="{{ old('receipt_date', $receiptNote->receipt_date) }}" required>
                                    @error('receipt_date')
                                    <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="invoice_number" class="form-label">Invoice Number</label>
                                    <input type="text" name="invoice_number" id="invoice_number" class="form-control" value="{{ old('invoice_number', $receiptNote->invoice_number) }}">
                                    @error('invoice_number')
                                    <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="invoice_date" class="form-label">Invoice Date</label>
                                    <input type="date" name="invoice_date" id="invoice_date" class="form-control" value="{{ old('invoice_date', $receiptNote->invoice_date) }}">
                                    @error('invoice_date')
                                    <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="discount" class="form-label">Global Discount (%)</label>
                                    <input type="number" name="discount" id="discount" class="form-control" value="{{ old('discount', $receiptNote->discount ?? 0) }}" step="0.01" min="0" max="100" placeholder="0.00">
                                    @error('discount')
                                    <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="note" class="form-label">Note (Optional)</label>
                                    <textarea name="note" id="note" class="form-control" rows="2" placeholder="Add any additional notes">{{ old('note', $receiptNote->note) }}</textarea>
                                    @error('note')
                                    <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <h4 class="mb-3 text-primary">Products Received</h4>
                        <div id="products-list-container">
                            <div class="products-header">
                                <div>Product</div>
                                <div>Qty Rcvd.</div>
                                <div>Price</div>
                                <div>Disc %</div>
                                <div>CGST %</div>
                                <div>SGST %</div>
                                <div>IGST %</div>
                                <div>Status</div>
                                <div>Action</div>
                            </div>
                            <div id="products-list">
                                @foreach($receiptNote->items as $index => $item)
                                <div class="product-item-row" id="row-{{ $index }}">
                                    <input type="hidden" name="products[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                                    <div class="fw-bold d-flex align-items-center">{{ $item->product->name }}</div>
                                    <div>
                                        <input type="number" name="products[{{ $index }}][quantity]" class="form-control quantity-input" min="0" max="{{ $item->quantity_available }}" value="{{ old('products.' . $index . '.quantity', $item->quantity) }}" required>
                                        <small class="text-muted">Max: {{ $item->quantity_available }}</small>
                                    </div>
                                    <div><input type="number" name="products[{{ $index }}][unit_price]" class="form-control unit-price" value="{{ old('products.' . $index . '.unit_price', $item->unit_price) }}" step="0.01" required></div>
                                    <div><input type="number" name="products[{{ $index }}][discount]" class="form-control discount-input" value="{{ old('products.' . $index . '.discount', $item->discount ?? $receiptNote->discount) }}" step="0.01" min="0" max="100"></div>
                                    <div><input type="number" name="products[{{ $index }}][cgst_rate]" class="form-control cgst-rate" value="{{ old('products.' . $index . '.cgst_rate', $item->cgst_rate) }}" step="0.01" min="0" max="100"></div>
                                    <div><input type="number" name="products[{{ $index }}][sgst_rate]" class="form-control sgst-rate" value="{{ old('products.' . $index . '.sgst_rate', $item->sgst_rate) }}" step="0.01" min="0" max="100"></div>
                                    <div><input type="number" name="products[{{ $index }}][igst_rate]" class="form-control igst-rate" value="{{ old('products.' . $index . '.igst_rate', $item->igst_rate) }}" step="0.01" min="0" max="100"></div>
                                    <div>
                                        <select name="products[{{ $index }}][status]" class="form-select status-select">
                                            <option value="received" {{ $item->status == 'received' ? 'selected' : '' }}>Received</option>
                                            <option value="pending" {{ $item->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                        </select>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn" title="Remove from this receipt"><i class="fa fa-trash"></i></button>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <div class="mt-3">
                                <label for="add_product" class="form-label">Add Product</label>
                                <select id="add_product" class="form-select select2">
                                    <option value="" selected disabled>Select a product...</option>
                                    @foreach($products as $product)
                                    <option value="{{ $product->id }}" data-name="{{ $product->name }}">{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row mt-4 g-4">
                            <div class="col-lg-7"></div>
                            <div class="col-lg-5">
                                <div class="totals-card">
                                    <div class="row mb-2">
                                        <div class="col-7">Subtotal</div>
                                        <div class="col-5 text-end" id="subtotal">₹0.00</div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-7">Total Discount</div>
                                        <div class="col-5 text-end" id="total_discount">₹0.00</div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-7">Total CGST</div>
                                        <div class="col-5 text-end" id="total_cgst">₹0.00</div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-7">Total SGST</div>
                                        <div class="col-5 text-end" id="total_sgst">₹0.00</div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-7">Total IGST</div>
                                        <div class="col-5 text-end" id="total_igst">₹0.00</div>
                                    </div>
                                    <hr>
                                    <div class="row grand-total">
                                        <div class="col-7 text-dark">Grand Total</div>
                                        <div class="col-5 text-end text-primary" id="grand_total">₹0.00</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-lg" id="submit-btn">Update Receipt Note</button>
                        </div>
                    </form>

                    <!-- Conversion Form -->
                    <form action="{{ route('receipt_notes.convert', $receiptNote->id) }}" method="POST" id="convert-receipt-note-form" class="d-inline">
                        @csrf
                        @method('POST')
                        <input type="hidden" name="purchase_order_id" id="convert_purchase_order_id" value="{{ $receiptNote->purchase_order_id }}">
                        <input type="hidden" name="party_id" value="{{ $receiptNote->party_id }}">
                        <input type="hidden" name="invoice_number" value="{{ $receiptNote->invoice_number ?? '' }}">
                        <input type="hidden" name="invoice_date" value="{{ $receiptNote->invoice_date ?? '' }}">
                        <input type="hidden" name="receipt_number" value="{{ old('receipt_number', $receiptNote->receipt_number) }}">
                        <input type="hidden" name="receipt_date" value="{{ old('receipt_date', $receiptNote->receipt_date) }}">
                        <input type="hidden" name="note" value="{{ old('note', $receiptNote->note) }}">

                        @foreach($receiptNote->items as $index => $item)
                        <input type="hidden" name="products[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                        <input type="hidden" name="products[{{ $index }}][quantity]" class="convert-quantity-input" value="{{ old('products.' . $index . '.quantity', $item->quantity) }}">
                        <input type="hidden" name="products[{{ $index }}][unit_price]" class="convert-unit-price" value="{{ old('products.' . $index . '.unit_price', $item->unit_price) }}">
                        <input type="hidden" name="products[{{ $index }}][discount]" class="convert-discount-input" value="{{ old('products.' . $index . '.discount', $item->discount ?? $receiptNote->discount) }}">
                        <input type="hidden" name="products[{{ $index }}][cgst_rate]" class="convert-cgst-rate" value="{{ old('products.' . $index . '.cgst_rate', $item->cgst_rate) }}">
                        <input type="hidden" name="products[{{ $index }}][sgst_rate]" class="convert-sgst-rate" value="{{ old('products.' . $index . '.sgst_rate', $item->sgst_rate) }}">
                        <input type="hidden" name="products[{{ $index }}][igst_rate]" class="convert-igst-rate" value="{{ old('products.' . $index . '.igst_rate', $item->igst_rate) }}">
                        <input type="hidden" name="products[{{ $index }}][status]" class="convert-status" value="{{ $item->status }}">
                        @endforeach
                        <button type="submit" class="btn btn-success btn-lg" id="convert-btn">Convert to Purchase Entry</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productsList = document.getElementById('products-list');
            const productsHeader = document.querySelector('.products-header');
            let productIndex = {{ $receiptNote->items->count() }};

            // Show products header if items exist
            if (productsList && productsList.children.length > 0) {
                if (productsHeader) {
                    productsHeader.style.display = 'grid';
                }
            }

            // Add new product row
            const addProductSelect = document.getElementById('add_product');
            if (addProductSelect) {
                addProductSelect.addEventListener('change', function() {
                    const productId = this.value;
                    const productName = this.options[this.selectedIndex].dataset.name;
                    if (!productId) return;

                    const newRow = document.createElement('div');
                    newRow.className = 'product-item-row';
                    newRow.id = `row-${productIndex}`;
                    newRow.innerHTML = `
                        <input type="hidden" name="products[${productIndex}][product_id]" value="${productId}">
                        <div class="fw-bold d-flex align-items-center">${productName}</div>
                        <div>
                            <input type="number" name="products[${productIndex}][quantity]" class="form-control quantity-input" min="0" max="9999" required placeholder="0">
                            <small class="text-muted">Max: 9999</small>
                        </div>
                        <div><input type="number" name="products[${productIndex}][unit_price]" class="form-control unit-price" step="0.01" required></div>
                        <div><input type="number" name="products[${productIndex}][discount]" class="form-control discount-input" step="0.01" min="0" max="100"></div>
                        <div><input type="number" name="products[${productIndex}][cgst_rate]" class="form-control cgst-rate" step="0.01" min="0" max="100"></div>
                        <div><input type="number" name="products[${productIndex}][sgst_rate]" class="form-control sgst-rate" step="0.01" min="0" max="100"></div>
                        <div><input type="number" name="products[${productIndex}][igst_rate]" class="form-control igst-rate" step="0.01" min="0" max="100"></div>
                        <div>
                            <select name="products[${productIndex}][status]" class="form-select status-select">
                                <option value="received">Received</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-center justify-content-center">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn" title="Remove from this receipt"><i class="fa fa-trash"></i></button>
                        </div>
                    `;
                    productsList.appendChild(newRow);
                    productsHeader.style.display = 'grid';
                    productIndex++;
                    this.value = ''; // Reset select
                });
            }

            // Remove product row
            productsList.addEventListener('click', function(e) {
                if (e.target.closest('.remove-item-btn')) {
                    e.target.closest('.product-item-row').remove();
                    if (productsList.children.length === 0) {
                        productsList.innerHTML = '<p class="text-muted text-center p-4 border rounded">No products added.</p>';
                        productsHeader.style.display = 'none';
                    }
                    updateConversionForm();
                    calculateTotals();
                }
            });

            // Quantity validation
            productsList.addEventListener('input', function(e) {
                if (e.target.classList.contains('quantity-input')) {
                    const input = e.target;
                    const maxQty = parseFloat(input.getAttribute('max')) || 9999;
                    const currentQty = parseFloat(input.value);

                    if (currentQty > maxQty) {
                        input.value = maxQty;
                        const warning = document.createElement('small');
                        warning.className = 'text-danger d-block mt-1';
                        warning.textContent = 'Max qty exceeded.';
                        input.parentElement.appendChild(warning);
                        setTimeout(() => warning.remove(), 2000);
                    }
                }
            });

            // Update conversion form inputs
            function updateConversionForm() {
                const convertForm = document.getElementById('convert-receipt-note-form');
                if (!convertForm) return;
                
                // Clear existing product inputs
                convertForm.querySelectorAll('input[name^="products"]').forEach(input => input.remove());

                document.querySelectorAll('.product-item-row').forEach((row, index) => {
                    const productId = row.querySelector('input[name$="[product_id]"]').value;
                    const quantity = row.querySelector('.quantity-input').value;
                    const unitPrice = row.querySelector('.unit-price').value;
                    const discount = row.querySelector('.discount-input').value;
                    const cgstRate = row.querySelector('.cgst-rate').value;
                    const sgstRate = row.querySelector('.sgst-rate').value;
                    const igstRate = row.querySelector('.igst-rate').value;
                    const status = row.querySelector('.status-select').value;

                    // Create hidden inputs for conversion form
                    const hiddenInputs = [
                        {name: `products[${index}][product_id]`, value: productId},
                        {name: `products[${index}][quantity]`, value: quantity, class: 'convert-quantity-input'},
                        {name: `products[${index}][unit_price]`, value: unitPrice, class: 'convert-unit-price'},
                        {name: `products[${index}][discount]`, value: discount, class: 'convert-discount-input'},
                        {name: `products[${index}][cgst_rate]`, value: cgstRate, class: 'convert-cgst-rate'},
                        {name: `products[${index}][sgst_rate]`, value: sgstRate, class: 'convert-sgst-rate'},
                        {name: `products[${index}][igst_rate]`, value: igstRate, class: 'convert-igst-rate'},
                        {name: `products[${index}][status]`, value: status, class: 'convert-status'}
                    ];

                    hiddenInputs.forEach(({name, value, class: className}) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = value;
                        if (className) input.className = className;
                        convertForm.appendChild(input);
                    });
                });

                // Update top-level fields
                const updateField = (name, sourceId) => {
                    const targetInput = convertForm.querySelector(`input[name="${name}"]`);
                    const sourceInput = document.getElementById(sourceId);
                    if (targetInput && sourceInput) {
                        targetInput.value = sourceInput.value;
                    }
                };

                updateField('purchase_order_id', 'purchase_order_id');
                updateField('receipt_number', 'receipt_number');
                updateField('receipt_date', 'receipt_date');
                updateField('invoice_number', 'invoice_number');
                updateField('invoice_date', 'invoice_date');
                updateField('discount', 'discount');
                updateField('note', 'note');
            }

            // Real-time calculation function - like invoice page
            function calculateTotals() {
                let subtotal = 0;
                let totalDiscount = 0;
                let totalCgst = 0;
                let totalSgst = 0;
                let totalIgst = 0;

                // Get global discount rate
                const globalDiscountRate = parseFloat(document.getElementById('discount')?.value) || 0;

                // Process each product row
                document.querySelectorAll('.product-item-row').forEach(row => {
                    const quantity = parseFloat(row.querySelector('.quantity-input')?.value) || 0;
                    const unitPrice = parseFloat(row.querySelector('.unit-price')?.value) || 0;
                    const itemDiscountRate = parseFloat(row.querySelector('.discount-input')?.value) || globalDiscountRate;
                    const cgstRate = parseFloat(row.querySelector('.cgst-rate')?.value) || 0;
                    const sgstRate = parseFloat(row.querySelector('.sgst-rate')?.value) || 0;
                    const igstRate = parseFloat(row.querySelector('.igst-rate')?.value) || 0;

                    if (quantity > 0 && unitPrice >= 0) {
                        // Calculate base price
                        const basePrice = quantity * unitPrice;
                        
                        // Calculate discount
                        const discountAmount = basePrice * (itemDiscountRate / 100);
                        const priceAfterDiscount = basePrice - discountAmount;

                        // Calculate GST
                        const cgstAmount = priceAfterDiscount * (cgstRate / 100);
                        const sgstAmount = priceAfterDiscount * (sgstRate / 100);
                        const igstAmount = priceAfterDiscount * (igstRate / 100);

                        // Add to grand totals
                        subtotal += priceAfterDiscount;
                        totalDiscount += discountAmount;
                        totalCgst += cgstAmount;
                        totalSgst += sgstAmount;
                        totalIgst += igstAmount;
                    }
                });

                // Calculate grand total
                const grandTotal = subtotal + totalCgst + totalSgst + totalIgst;

                // Update display elements
                document.getElementById('subtotal').textContent = `₹${subtotal.toFixed(2)}`;
                document.getElementById('total_discount').textContent = `₹${totalDiscount.toFixed(2)}`;
                document.getElementById('total_cgst').textContent = `₹${totalCgst.toFixed(2)}`;
                document.getElementById('total_sgst').textContent = `₹${totalSgst.toFixed(2)}`;
                document.getElementById('total_igst').textContent = `₹${totalIgst.toFixed(2)}`;
                document.getElementById('grand_total').textContent = `₹${grandTotal.toFixed(2)}`;
            }

            // Make calculateTotals globally accessible
            window.calculateTotals = calculateTotals;

            // Validate conversion form before submission
            const convertBtn = document.getElementById('convert-btn');
            if (convertBtn) {
                convertBtn.addEventListener('click', function(e) {
                    let hasInvalidPrices = false;
                    let invalidRows = [];
                    
                    // Check each product row for valid unit prices
                    document.querySelectorAll('.product-item-row').forEach((row, index) => {
                        const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
                        const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
                        
                        if (quantity > 0 && unitPrice <= 0) {
                            hasInvalidPrices = true;
                            invalidRows.push(index + 1);
                        }
                    });
                    
                    if (hasInvalidPrices) {
                        e.preventDefault();
                        alert(`Cannot convert to Purchase Entry!\n\nProducts at row(s) ${invalidRows.join(', ')} have quantity > 0 but unit price is 0 or empty.\n\nPlease enter valid unit prices for all products before conversion.`);
                        return;
                    }
                    
                    // Update the conversion form and proceed
                    updateConversionForm();
                });
            }

            // Initialize Select2 for the add product dropdown
            const addProductSelect = document.getElementById('add_product');
            if (addProductSelect && window.jQuery) {
                window.jQuery(addProductSelect).select2({
                    placeholder: "Select a product...",
                    allowClear: true,
                    width: '100%'
                });
            }

            // Real-time event binding - like invoice page
            document.addEventListener('input', function(e) {
                if (e.target.closest('#products-list') || e.target.id === 'discount') {
                    calculateTotals();
                    updateConversionForm();
                }
            });

            // Calculate totals on page load
            calculateTotals();
        });
    </script>
</body>
@include('layout.footer')