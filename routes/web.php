<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseEntryController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReceiptNoteController;
use App\Http\Controllers\QuantraController;
use App\Http\Controllers\EnquiryController;

// Home page (login form for guests, redirects to dashboard for authenticated users)
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return view('home');
})->name('home');

Route::get('/login', [HomeController::class, 'index'])->name('login');


// Dashboard (requires authentication)
Route::get('/dashboard', function () {
    $user = Auth::user(); // Get the authenticated user

    // Initialize financial variables
    $totalRevenue = null;
    $totalCOGS = null;
    $grossProfit = null;
    $totalQuantraExpenses = null;
    $netProfit = null;

    $totalRevenue = \App\Models\Sale::sum('total_price');
    // Perform financial calculations ONLY for superadmin
    if ($user && $user->isSuperAdmin()) { // Use the method from User model
        // 1. Calculate Total Revenue


        // 2. Calculate Total Cost of Goods Sold (COGS)
        $totalCOGS = 0;
        $soldItems = \App\Models\SaleItem::with('product:id') // Also get stock_method
            ->select('product_id', 'quantity')
            ->get();
        $averageProductNetCosts = [];

        foreach ($soldItems as $soldItem) {
            if (!$soldItem->product_id || !$soldItem->product) { // Check product exists
                \Log::warning("SaleItem ID {$soldItem->id} missing product_id or product relationship.");
                continue;
            }

            $productId = $soldItem->product_id;

            // Check if average cost for this product isn't calculated yet
            if (!array_key_exists($productId, $averageProductNetCosts)) {
                // NOTE: The COGS calculation here uses weighted average cost of *all received purchases*.
                // For strict FIFO/LIFO, the logic would be significantly more complex.
                // This approach is a common simplification (Average Cost Method).

                $purchaseItemsForProduct = \App\Models\PurchaseEntryItem::where('product_id', $productId)
                    ->where('status', 'received')
                    ->select('unit_price', 'quantity', 'discount')
                    ->get();

                if ($purchaseItemsForProduct->isNotEmpty()) {
                    $totalNetCostForThisProduct = 0;
                    $totalQuantityPurchasedForThisProduct = 0;

                    foreach ($purchaseItemsForProduct as $pi) {
                        $netUnitPurchasePrice = $pi->unit_price * (1 - (($pi->discount ?? 0) / 100));
                        $totalNetCostForThisProduct += $netUnitPurchasePrice * $pi->quantity;
                        $totalQuantityPurchasedForThisProduct += $pi->quantity;
                    }

                    if ($totalQuantityPurchasedForThisProduct > 0) {
                        $averageProductNetCosts[$productId] = $totalNetCostForThisProduct / $totalQuantityPurchasedForThisProduct;
                    } else {
                        $averageProductNetCosts[$productId] = 0; // No received quantity, cost is 0
                    }
                } else {
                    $averageProductNetCosts[$productId] = 0; // No purchase history, cost is 0
                }
            }
            $totalCOGS += $soldItem->quantity * ($averageProductNetCosts[$productId] ?? 0);
        }

        // 3. Calculate Gross Profit
        $grossProfit = $totalRevenue - $totalCOGS;

        // 4. Calculate Total Quantra Expenses
        $totalQuantraExpenses = \App\Models\Quantra::sum('amount'); // Sum of all 'amount' in Quantra table

        // 5. Calculate Net Profit
        $netProfit = $grossProfit - $totalQuantraExpenses;
    }

    // --- Data for all users ---
    $lowStockProducts = \App\Models\Product::where('stock', '<', 10)
        ->orderBy('stock', 'asc')
        ->take(10)
        ->get();
    $recentSales = \App\Models\Sale::with(['customer', 'saleItems.product'])
        ->latest()
        ->take(5)
        ->get();
    // --- End of data for all users ---

    return view('dashboard', compact(
        'totalRevenue',
        'totalCOGS',
        'grossProfit',
        'totalQuantraExpenses', // Pass to view
        'netProfit',          // Pass to view
        'lowStockProducts',
        'recentSales'
        // Pass the user if you need to check role again in view for some reason,
        // but it's better to rely on data existence
        // 'user' => $user
    ));
})->middleware('auth')->name('dashboard');



// Authentication routes (login, register, logout, etc.)
Auth::routes(['verify' => false]); // Email verification disabled


// Authenticated routes (require login)
Route::middleware('auth')->group(function () {

    Route::get('/payables', [PaymentController::class, 'index'])->name('payables');
    Route::get('/payables/export', [PaymentController::class, 'exportPayables'])->name('payables.export');
    Route::get('/payables/create', [PaymentController::class, 'create'])->name('payments.payables.create');
    Route::post('/payables', [PaymentController::class, 'store'])->name('payments.payables.store');
    Route::get('/payments', [PaymentController::class, 'paymentsList'])->name('payments.payables.list');
    Route::get('/payables/get-purchase-entries-by-party', [PaymentController::class, 'getPurchaseEntriesByParty'])->name('payables.getPurchaseEntriesByParty');

    Route::get('/receivables', [PaymentController::class, 'receivables'])->name('receivables');
    Route::get('/receivables/export', [PaymentController::class, 'exportReceivables'])->name('receivables.export');
    Route::get('/receivables/create', [PaymentController::class, 'createReceivable'])->name('receivables.create');
    Route::post('/receivables', [PaymentController::class, 'storeReceivable'])->name('receivables.store');
    Route::get('/receivables/list', [PaymentController::class, 'receivablesPaymentsList'])->name('receivables.paymentsList');
    Route::get('/receivables/get-sales-by-customer', [PaymentController::class, 'getSalesByCustomer'])->name('receivables.getSalesByCustomer');

    Route::get('/purchase-entries', [PurchaseEntryController::class, 'index'])->name('purchase_entries.index');
    Route::get('/purchase-entries/create', [PurchaseEntryController::class, 'create'])->name('purchase_entries.create');
    Route::post('/purchase-entries', [PurchaseEntryController::class, 'store'])->name('purchase_entries.store');
    Route::get('/purchase-entries/{purchaseEntry}/edit', [PurchaseEntryController::class, 'edit'])->name('purchase_entries.edit');
    Route::put('/purchase-entries/{purchaseEntry}', [PurchaseEntryController::class, 'update'])->name('purchase_entries.update');
    Route::post('/purchase-entries/check-invoice', [PurchaseEntryController::class, 'checkInvoiceNumber'])->name('purchase_entries.check_invoice');
    Route::get('/purchase-entries/{id}', [PurchaseEntryController::class, 'show'])->name('purchase_entries.show');


    Route::get('/receipt_notes', [ReceiptNoteController::class, 'index'])->name('receipt_notes.index');
    Route::get('/receipt_notes/create', [ReceiptNoteController::class, 'create'])->name('receipt_notes.create');
    Route::post('/receipt_notes', [ReceiptNoteController::class, 'store'])->name('receipt_notes.store');
    // Receipt Notes Conversion Routes
    Route::get('/receipt-notes/{id}/convert', [ReceiptNoteController::class, 'convert'])->name('receipt_notes.convert_form');
    Route::post('/receipt-notes/{id}/convert', [App\Http\Controllers\ReceiptNoteController::class, 'convertToPurchaseEntry'])->name('receipt_notes.convert');
    Route::get('/receipt-notes/{receiptNote}/edit', [ReceiptNoteController::class, 'edit'])->name('receipt_notes.edit');
    Route::put('/receipt-notes/{receiptNote}', [ReceiptNoteController::class, 'update'])->name('receipt_notes.update');

    // ADD THIS NEW ROUTE
    Route::get('receipt_notes/{id}/pdf', [ReceiptNoteController::class, 'downloadPDF'])->name('receipt_notes.pdf');


    Route::get('/purchase-orders/{id}/details', [PurchaseOrderController::class, 'getDetails'])->name('purchase_orders.details');
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase_orders.index');
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase_orders.create');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase_orders.store');
    Route::get('/purchase-orders/last-price', [PurchaseOrderController::class, 'getLastPurchasePrice'])->name('purchase_orders.last-price');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase_orders.approve');
    Route::get('/purchase-orders/{id}/download-pdf', [PurchaseOrderController::class, 'downloadPDF'])
        ->name('purchase_orders.download_pdf');
    Route::get('/purchase_orders/show/{id}', [PurchaseOrderController::class, 'show'])->name('purchase_orders.show');

    Route::get('/parties/search', [PartyController::class, 'search'])->name('parties.search');

    Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');



    Route::get('/parties', [PartyController::class, 'index'])->name('parties.index');
    Route::post('/parties/import', [PartyController::class, 'import'])->name('parties.import');
    Route::get('/parties/search', [PartyController::class, 'search'])->name('parties.search');
    Route::resource('parties', PartyController::class);

    Route::resource('delivery_notes', DeliveryNoteController::class);
    Route::put('/delivery-notes/{deliveryNote}/update-only', [DeliveryNoteController::class, 'updateOnly'])->name('delivery_notes.update_only');
    Route::put('/delivery-notes/{deliveryNote}/convert-to-invoice', [DeliveryNoteController::class, 'updateAndConvertToInvoice'])->name('delivery_notes.convert_to_invoice');
    // Additional route for fetching sales by customer (used in create/edit views)
    Route::get('/delivery_notes/get-sales-by-customer', [DeliveryNoteController::class, 'getSalesByCustomer'])->name('delivery_notes.getSalesByCustomer');

    // Ensure the route for creating an invoice from a delivery note is defined
    Route::get('/invoices/create-from-delivery-note/{deliveryNote}', [InvoiceController::class, 'createFromDeliveryNote'])->name('invoices.createFromDeliveryNote');
    Route::get('/delivery-notes/{deliveryNote}/pdf', [DeliveryNoteController::class, 'downloadPdf'])->name('delivery_notes.downloadPdf');

    Route::get('/quantra', [QuantraController::class, 'index'])->name('quantra.index');
    Route::get('/quantra/create', [QuantraController::class, 'create'])->name('quantra.create');
    Route::post('/quantra', [QuantraController::class, 'store'])->name('quantra.store');

    // Product routes
    Route::resource('products', ProductController::class)->names([
        'index' => 'products.index',
        'create' => 'products.create',
        'store' => 'products.store',
        'show' => 'products.show',
        'edit' => 'products.edit',
        'update' => 'products.update',
        'destroy' => 'products.destroy',
    ]);
    Route::get('/test-subcategories', function () {
        $subcategories = App\Models\Product::whereNotNull('subcategory')->distinct()->pluck('subcategory')->sort();
        \Log::info('Subcategories: ' . $subcategories->toJson());
        return response()->json($subcategories);
    });
    Route::get('/products/export/excel', [ProductController::class, 'export'])->name('products.export');
    Route::post('/products/import/excel', [ProductController::class, 'import'])->name('products.import');

    // Customer routes
    Route::resource('customers', CustomerController::class)->names([
        'index' => 'customers.index',
        'create' => 'customers.create',
        'store' => 'customers.store',
        'show' => 'customers.show',
        'edit' => 'customers.edit',
        'update' => 'customers.update',
        'destroy' => 'customers.destroy',
    ]);
    Route::get('/customers/export/excel', [CustomerController::class, 'export'])->name('customers.export');
    Route::post('/customers/import/excel', [CustomerController::class, 'import'])->name('customers.import');

    // Sales routes
    Route::resource('sales', SaleController::class)->names([
        'index' => 'sales.index',
        'create' => 'sales.create',
        'store' => 'sales.store',
        'show' => 'sales.show',
        'edit' => 'sales.edit',
        'update' => 'sales.update',
        // 'destroy' => 'sales.destroy',
    ]);
    Route::put('sales/update-status/{id}', [SaleController::class, 'updateStatus'])->name('sales.update-status');

    // Invoice routes
    Route::get('/invoices/pending', [InvoiceController::class, 'pendingInvoices'])->name('invoices.pending')->middleware('superadmin');
    Route::post('/invoices/{invoice}/approve', [InvoiceController::class, 'approve'])->name('invoices.approve')->middleware('superadmin');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'generatePDF'])->name('invoices.pdf');



    Route::post('/invoices/{invoice}/request-unlock', [InvoiceController::class, 'requestUnlock'])->name('invoices.request_unlock');

    Route::middleware('superadmin')->group(function () { // Routes only for superadmin
        Route::get('/invoices/{invoice}/manage-unlock-request', [InvoiceController::class, 'manageUnlockRequestForm'])->name('invoices.manage_unlock_request_form');
        Route::post('/invoices/{invoice}/decide-unlock-request', [InvoiceController::class, 'decideUnlockRequest'])->name('invoices.decide_unlock_request');
        // You might also have a page listing all pending unlock requests
        Route::get('/invoices/pending-unlock-requests', [InvoiceController::class, 'listPendingUnlockRequests'])->name('invoices.pending_unlock_requests');

        Route::get('/reports/profit-loss', [\App\Http\Controllers\ReportController::class, 'salesProfitLoss'])->name('reports.profit_loss');
    });








    Route::resource('invoices', InvoiceController::class)->names([
        'index' => 'invoices.index',
        'create' => 'invoices.create',
        'store' => 'invoices.store',
        'show' => 'invoices.show',
        'edit' => 'invoices.edit',
        'update' => 'invoices.update',
        'destroy' => 'invoices.destroy',
    ]);

    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index')->middleware('auth');
});

Route::resource('enquiry', EnquiryController::class);

// Route for adding a follow-up
Route::post('enquiry/{enquiry}/follow-up', [EnquiryController::class, 'addFollowUp'])->name('enquiry.follow-up');
