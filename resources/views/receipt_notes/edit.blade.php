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
                                
                                <div class="col-12">
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

                        <div class="mt-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">Update Receipt Note</h6>
                                    <p class="small text-muted">Save changes to this receipt note without converting it.</p>
                                    <button type="submit" class="btn btn-primary btn-lg w-100" id="submit-btn">Update Receipt Note</button>
                                </div>
                                @if(!$receiptNote->is_converted)
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">Convert to Purchase Entry</h6>
                                    <p class="small text-muted">Update and convert this receipt note to a purchase entry (requires invoice details).</p>
                                    <button type="submit" class="btn btn-success btn-lg w-100" id="update-and-convert-btn" name="convert_to_purchase_entry" value="1">Update & Convert to Purchase Entry</button>
                                </div>
                                @else
                                <div class="col-md-6">
                                    <h6 class="text-success mb-2">Already Converted</h6>
                                    <p class="small text-muted">This receipt note has been converted to a purchase entry.</p>
                                    <button type="button" class="btn btn-secondary btn-lg w-100" disabled>Already Converted</button>
                                </div>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            const productsList = $('#products-list');
            const productsHeader = $('.products-header');
            let productIndex = {
                {
                    $receiptNote - > items - > count()
                }
            };

            // Show products header if items exist
            if (productsList.children().length > 0) {
                productsHeader.css('display', 'grid');
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
                    </div>`;
                productsList.append(rowHtml);
                productsHeader.css('display', 'grid');
                productIndex++;
                $(this).val(''); // Reset select
                calculateTotals();
            });

            // Remove product row
            productsList.on('click', '.remove-item-btn', function() {
                $(this).closest('.product-item-row').remove();
                if (productsList.children().length === 0) {
                    productsList.html('<p class="text-muted text-center p-4 border rounded">No products added.</p>');
                    productsHeader.hide();
                }
                calculateTotals();
            });

            // Validate quantity input
            productsList.on('input', '.quantity-input', function() {
                const $input = $(this);
                const maxQty = parseFloat($input.attr('max')) || 9999;
                const currentQty = parseFloat($input.val());

                if (currentQty > maxQty) {
                    $input.val(maxQty);
                    const $warning = $('<small class="text-danger d-block mt-1">Max qty exceeded.</small>');
                    $input.parent().append($warning);
                    setTimeout(() => $warning.remove(), 2000);
                }
                calculateTotals();
            });

            // Calculate totals
            function calculateTotals() {
                let grandSubtotal = 0;
                let grandTotalDiscount = 0;
                let grandTotalCgst = 0;
                let grandTotalSgst = 0;
                let grandTotalIgst = 0;

                const discountRate = parseFloat($('#discount').val()) || 0;

                $('.product-item-row').each(function() {
                    const quantity = parseFloat($(this).find('.quantity-input').val()) || 0;
                    const unitPrice = parseFloat($(this).find('.unit-price').val()) || 0;
                    const cgstRate = parseFloat($(this).find('.cgst-rate').val()) || 0;
                    const sgstRate = parseFloat($(this).find('.sgst-rate').val()) || 0;
                    const igstRate = parseFloat($(this).find('.igst-rate').val()) || 0;

                    if (quantity > 0 && unitPrice >= 0) {
                        const basePrice = quantity * unitPrice;
                        const discountAmount = basePrice * (discountRate / 100);
                        const priceAfterDiscount = basePrice - discountAmount;

                        const cgstAmount = priceAfterDiscount * (cgstRate / 100);
                        const sgstAmount = priceAfterDiscount * (sgstRate / 100);
                        const igstAmount = priceAfterDiscount * (igstRate / 100);

                        grandSubtotal += priceAfterDiscount;
                        grandTotalDiscount += discountAmount;
                        grandTotalCgst += cgstAmount;
                        grandTotalSgst += sgstAmount;
                        grandTotalIgst += igstAmount;
                    }
                });

                $('#subtotal').text('₹' + grandSubtotal.toFixed(2));
                $('#total_discount').text('₹' + grandTotalDiscount.toFixed(2));
                $('#total_cgst').text('₹' + grandTotalCgst.toFixed(2));
                $('#total_sgst').text('₹' + grandTotalSgst.toFixed(2));
                $('#total_igst').text('₹' + grandTotalIgst.toFixed(2));
                $('#grand_total').text('₹' + (grandSubtotal + grandTotalCgst + grandTotalSgst + grandTotalIgst).toFixed(2));
            }

            // Add validation for the update and convert button
            $('#update-and-convert-btn').on('click', function(e) {
                const invoiceNumber = $('#invoice_number').val().trim();
                const invoiceDate = $('#invoice_date').val().trim();

                if (!invoiceNumber || !invoiceDate) {
                    e.preventDefault();
                    alert('Please fill in Invoice Number and Invoice Date before converting to a purchase entry.');
                    if (!invoiceNumber) {
                        $('#invoice_number').focus();
                    } else if (!invoiceDate) {
                        $('#invoice_date').focus();
                    }
                    return false;
                }

                // Check if all products have unit prices
                let missingPriceProducts = [];
                $('.product-item-row').each(function() {
                    const quantity = parseFloat($(this).find('.quantity-input').val()) || 0;
                    const unitPrice = parseFloat($(this).find('.unit-price').val()) || 0;
                    const productName = $(this).find('select[name$="[product_id]"] option:selected').text();
                    
                    if (quantity > 0 && unitPrice <= 0) {
                        missingPriceProducts.push(productName);
                    }
                });

                if (missingPriceProducts.length > 0) {
                    e.preventDefault();
                    alert('Please add unit prices for the following products: ' + missingPriceProducts.join(', '));
                    return false;
                }

                return confirm('Are you sure you want to update and convert this receipt note to a purchase entry? This action cannot be undone.');
            });

            // Trigger initial updates
            calculateTotals();

            // Update totals on input change
            $(document).on('input', '.quantity-input, .unit-price, .discount-input, .cgst-rate, .sgst-rate, .igst-rate, #discount', calculateTotals);
        });
    </script>
</body>
@include('layout.footer')