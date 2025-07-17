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
                                    <label for="purchase_date" class="form-label">Entry Date</label>
                                    <input type="date" name="purchase_date" id="purchase_date" class="form-control" value="{{ old('purchase_date', $receiptNote->purchase_date ?? $receiptNote->receipt_date) }}">
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
                                    <div><input type="number" name="products[{{ $index }}][discount]" class="form-control discount-input" value="{{ old('products.' . $index . '.discount', $item->discount) }}" step="0.01" min="0" max="100"></div>
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
                            <button type="button" class="btn btn-warning btn-sm me-2" onclick="calculateTotals()" id="recalculate-btn">🔄 Recalculate Totals</button>
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
                        <input type="hidden" name="purchase_date" id="convert_purchase_date" value="{{ old('purchase_date', $receiptNote->purchase_date ?? $receiptNote->receipt_date) }}">

                        @foreach($receiptNote->items as $index => $item)
                        <input type="hidden" name="products[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                        <input type="hidden" name="products[{{ $index }}][quantity]" class="convert-quantity-input" value="{{ old('products.' . $index . '.quantity', $item->quantity) }}">
                        <input type="hidden" name="products[{{ $index }}][unit_price]" class="convert-unit-price" value="{{ old('products.' . $index . '.unit_price', $item->unit_price) }}">
                        <input type="hidden" name="products[{{ $index }}][discount]" class="convert-discount-input" value="{{ old('products.' . $index . '.discount', $item->discount) }}">
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
        $(document).ready(function() {
            console.log('🚀 JavaScript loaded successfully!');
            console.log('📦 Receipt note items count:', {{ $receiptNote->items->count() }});
            
            const productsList = $('#products-list');
            const productsHeader = $('.products-header');
            let productIndex = {{ $receiptNote->items->count() }};
            
            console.log('📋 Found elements:', {
                productsList: productsList.length,
                productsHeader: productsHeader.length,
                productIndex: productIndex
            });

            // Show products header if items exist

            }

            // Add new product row
            $('#add_product').on('change', function() {
                const productId = $(this).val();
                const productName = $(this).find('option:selected').data('name');
                if (!productId) return;

                const rowHtml = `
                    <div class="product-item-row" id="row-${productIndex}">
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

                }
                updateConversionForm();
                calculateTotals();
            });


                }
                updateConversionForm();
                calculateTotals();
            });

            // Update conversion form inputs
            function updateConversionForm() {
                const $convertForm = $('#convert-receipt-note-form');
                $convertForm.find('input[name^="products"]').remove(); // Clear existing product inputs

                $('.product-item-row').each(function(index) {
                    const $row = $(this);
                    const productId = $row.find('input[name$="[product_id]"]').val();
                    const quantity = $row.find('.quantity-input').val();
                    const unitPrice = $row.find('.unit-price').val();
                    const discount = $row.find('.discount-input').val();
                    const cgstRate = $row.find('.cgst-rate').val();
                    const sgstRate = $row.find('.sgst-rate').val();
                    const igstRate = $row.find('.igst-rate').val();
                    const status = $row.find('.status-select').val();

                    $convertForm.append(`
                        <input type="hidden" name="products[${index}][product_id]" value="${productId}">
                        <input type="hidden" name="products[${index}][quantity]" class="convert-quantity-input" value="${quantity}">
                        <input type="hidden" name="products[${index}][unit_price]" class="convert-unit-price" value="${unitPrice}">
                        <input type="hidden" name="products[${index}][discount]" class="convert-discount-input" value="${discount}">
                        <input type="hidden" name="products[${index}][cgst_rate]" class="convert-cgst-rate" value="${cgstRate}">
                        <input type="hidden" name="products[${index}][sgst_rate]" class="convert-sgst-rate" value="${sgstRate}">
                        <input type="hidden" name="products[${index}][igst_rate]" class="convert-igst-rate" value="${igstRate}">
                        <input type="hidden" name="products[${index}][status]" class="convert-status" value="${status}">
                    `);
                });

                // Update top-level fields
                $convertForm.find('input[name="purchase_order_id"]').val($('#purchase_order_id').val());
                $convertForm.find('input[name="receipt_number"]').val($('#receipt_number').val());
                $convertForm.find('input[name="receipt_date"]').val($('#receipt_date').val());
                $convertForm.find('input[name="invoice_number"]').val($('#invoice_number').val());
                $convertForm.find('input[name="invoice_date"]').val($('#invoice_date').val());
                $convertForm.find('input[name="note"]').val($('#note').val());
                $convertForm.find('input[name="purchase_date"]').val($('#purchase_date').val());
            }


                        const cgstAmount = priceAfterDiscount * (cgstRate / 100);
                        const sgstAmount = priceAfterDiscount * (sgstRate / 100);
                        const igstAmount = priceAfterDiscount * (igstRate / 100);


            }

            // Make calculateTotals globally accessible
            window.calculateTotals = calculateTotals;



            // Initialize Select2 for the add product dropdown
            $('#add_product').select2({
                placeholder: "Select a product...",
                allowClear: true,
                width: '100%'
            });

            });

            // Also update conversion form on relevant changes
            $(document).on('input change', '.quantity-input, .unit-price, .discount-input, .cgst-rate, .sgst-rate, .igst-rate, .status-select, #receipt_number, #receipt_date, #invoice_number, #invoice_date, #note, #purchase_order_id, #purchase_date', updateConversionForm);
        });
    </script>
</body>
@include('layout.footer')