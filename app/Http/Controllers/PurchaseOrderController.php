<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PDF;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
     public function index()
    {
        // THE FIX: Eager-load the two correct relationships for the status accessor.
        $purchaseOrders = PurchaseOrder::with(['party', 'items', 'receiptNoteItems', 'purchaseEntryItems'])
            ->latest()
            ->paginate(15);

        return view('purchase_orders.index', compact('purchaseOrders'));
    }

    public function create()
    {
        // Fetch parties with their state information
        $parties = Party::select('id', 'name')->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => htmlspecialchars($p->name, ENT_QUOTES, 'UTF-8'),

            ];
        });

        $products = Product::select('id', 'name', 'price')->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => htmlspecialchars($p->name, ENT_QUOTES, 'UTF-8'),
                'price' => $p->price,
            ];
        });

        $categories = Product::distinct()->pluck('category')->filter()->values();
        $subcategories = Product::distinct()->pluck('subcategory')->filter()->values();

        // Get your company's state from the .env file, with a fallback
        $companyState = env('COMPANY_STATE', 'Maharashtra');

        return view('purchase_orders.create', compact('parties', 'products', 'categories', 'subcategories', 'companyState'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'party_id' => 'required|exists:parties,id',
            'order_date' => 'required|date',
            'customer_name' => 'nullable|string|max:255', // New validation rule
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0|max:100',
            'products.*.cgst' => 'nullable|numeric|min:0|max:28',
            'products.*.sgst' => 'nullable|numeric|min:0|max:28',
            'products.*.igst' => 'nullable|numeric|min:0|max:28',
        ]);

        try {
            DB::transaction(function () use ($validated, &$purchaseOrder) {
                $purchaseOrder = PurchaseOrder::create([
                    'purchase_order_number' => 'PO-' . Str::random(8),
                    'party_id' => $validated['party_id'],
                    'order_date' => $validated['order_date'],
                    'customer_name' => $validated['customer_name'] ?? null, // Add this lin
                    'status' => 'pending',
                ]);

                foreach ($validated['products'] as $item) {
                    $discount = isset($item['discount']) ? floatval($item['discount']) : 0;
                    $cgst = isset($item['cgst']) ? floatval($item['cgst']) : 0;
                    $sgst = isset($item['sgst']) ? floatval($item['sgst']) : 0;
                    $igst = isset($item['igst']) ? floatval($item['igst']) : 0;
                    $unitPrice = floatval($item['unit_price']);
                    $quantity = intval($item['quantity']);
                    $discountedPrice = $unitPrice * (1 - ($discount / 100));
                    $totalTaxRate = ($cgst + $sgst + $igst) / 100;
                    $totalPrice = $quantity * $discountedPrice * (1 + $totalTaxRate);

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount' => $discount,
                        'cgst' => $cgst,
                        'sgst' => $sgst,
                        'igst' => $igst,
                        'total_price' => $totalPrice,
                    ]);
                }
            });

            return response()->json(['success' => true, 'message' => 'Purchase order created.']);
        } catch (\Exception $e) {
            \Log::error('Error creating purchase order: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create purchase order.'], 500);
        }
    }


    public function getLastPurchasePrice(Request $request)
    {
        $productId = $request->input('product_id');
        $lastPurchase = PurchaseOrderItem::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'last_price' => $lastPurchase ? $lastPurchase->unit_price : null,
        ]);
    }



  public function show($id)
    {
        // Eager-load all necessary relationships
        $purchaseOrder = PurchaseOrder::with([
            'party', 
            'items.product', 
            'receiptNoteItems', 
            'purchaseEntryItems'
        ])->findOrFail($id);

        // Calculate received quantities from BOTH sources for the view
        // FIXED: Use 'quantity' instead of 'quantity_received'
        $receivedViaNote = $purchaseOrder->receiptNoteItems
            ->where('status', 'received')
            ->groupBy('product_id')
            ->map(fn($items) => $items->sum('quantity')); // Fixed column name
        
        $receivedViaEntry = $purchaseOrder->purchaseEntryItems
            ->where('status', 'received')
            ->groupBy('product_id')
            ->map(fn($items) => $items->sum('quantity'));

        // Combine both into a single collection with the correct variable name
        $totalReceivedQuantities = $purchaseOrder->items->mapWithKeys(function ($item) use ($receivedViaNote, $receivedViaEntry) {
            $fromNote = $receivedViaNote->get($item->product_id, 0);
            $fromEntry = $receivedViaEntry->get($item->product_id, 0);
            return [$item->product_id => $fromNote + $fromEntry];
        });

        // Pass the correct variable to the view
        return view('purchase_orders.show', compact('purchaseOrder', 'totalReceivedQuantities'));
    }

    /**
     * API endpoint to get details for a new entry.
     */
    public function getDetailsForReceipt($id)
    {
        $po = PurchaseOrder::with(['party', 'items.product', 'receiptNoteItems', 'purchaseEntryItems'])->findOrFail($id);

        // FIXED: Use 'quantity' instead of 'quantity_received'
        $receivedViaNote = $po->receiptNoteItems->groupBy('product_id')->map(fn($g) => $g->sum('quantity')); // Fixed column name
        $receivedViaEntry = $po->purchaseEntryItems->groupBy('product_id')->map(fn($g) => $g->sum('quantity'));
        
        $remainingItems = $po->items->map(function ($item) use ($receivedViaNote, $receivedViaEntry) {
            $fromNote = $receivedViaNote->get($item->product_id, 0);
            $fromEntry = $receivedViaEntry->get($item->product_id, 0);
            $totalReceived = $fromNote + $fromEntry;
            
            $item->quantity_remaining = $item->quantity - $totalReceived;
            return $item;
        })->filter(fn($item) => $item->quantity_remaining > 0)->values();

        return response()->json([
            'party' => $po->party,
            'items' => $remainingItems,
        ]);
    }





    public function approve(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (auth()->user()->role !== 'superadmin') {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        $purchaseOrder->update(['status' => 'approved']);
        return redirect()->back()->with('success', 'Purchase order approved.');
    }

    public function downloadPDF($id)
    {
        $purchaseOrder = PurchaseOrder::with('party', 'items.product')->findOrFail($id);

        // Your company's details are fine
        $company = [
            'name' => 'MAULI SOLUTIONS',
            'address' => 'Gat No-627, Pune-Nashik Highway, IN Front Off Gabriel, Vitthal-Muktai Complex, Kuruli Chakan, Pune-410501',
            'contact' => 'Mob-9284716150/9158506948',
            'gstin' => '27ABIFM9220D1ZC',
            'state' => 'Maharashtra, Code: 27',
            'email' => 'maulisolutions18@gmail.com',
            'pan' => 'ABIFM9220D'
        ];

        // --- KEY FIX: Corrected Calculations ---
        $subtotal = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;
        $totalQuantity = 0;

        foreach ($purchaseOrder->items as $item) {
            // Calculate the base amount for the line item (Price * Qty)
            $baseItemAmount = $item->quantity * $item->unit_price;
            // Calculate the discounted amount for this item
            $itemAmountAfterDiscount = $baseItemAmount * (1 - (($item->discount ?? 0) / 100));

            // Add the discounted amount to the subtotal
            $subtotal += $itemAmountAfterDiscount;

            // Calculate taxes based on the discounted amount
            $totalCgst += $itemAmountAfterDiscount * (($item->cgst ?? 0) / 100);
            $totalSgst += $itemAmountAfterDiscount * (($item->sgst ?? 0) / 100);
            $totalIgst += $itemAmountAfterDiscount * (($item->igst ?? 0) / 100);

            $totalQuantity += $item->quantity;
        }

        // Grand total is the subtotal (which is already discounted) plus all calculated taxes
        $grandTotal = $subtotal + $totalCgst + $totalSgst + $totalIgst;

        $amountInWords = function_exists('numberToWords') ? numberToWords(round($grandTotal)) : 'Error';

        $data = [
            'purchaseOrder' => $purchaseOrder,
            'company' => $company,
            'subtotal' => $subtotal,
            'totalCgst' => $totalCgst,
            'totalSgst' => $totalSgst,
            'totalIgst' => $totalIgst,
            'grandTotal' => $grandTotal,
            'totalQuantity' => $totalQuantity,
            'amountInWords' => $amountInWords
        ];

        $pdf = PDF::loadView('purchase_orders.pdf', $data);

        return $pdf->download('PO_' . $purchaseOrder->purchase_order_number . '.pdf');
    }

    // In app/Http/Controllers/PurchaseOrderController.php

     public function getDetails($id)
    {
        $po = PurchaseOrder::with(['party', 'items.product', 'receiptNoteItems', 'purchaseEntryItems'])->findOrFail($id);

        // Get quantities received via Receipt Notes - FIXED: Use 'quantity' instead of 'quantity_received'
        $receivedViaNote = $po->receiptNoteItems->groupBy('product_id')->map(fn($g) => $g->sum('quantity')); // Fixed column name
        
        // Get quantities received via direct Purchase Entries
        $receivedViaEntry = $po->purchaseEntryItems->groupBy('product_id')->map(fn($g) => $g->sum('quantity'));
        
        $remainingItems = $po->items->map(function ($item) use ($receivedViaNote, $receivedViaEntry) {
            $fromNote = $receivedViaNote->get($item->product_id, 0);
            $fromEntry = $receivedViaEntry->get($item->product_id, 0);
            $totalReceived = $fromNote + $fromEntry;
            
            $item->quantity_remaining = $item->quantity - $totalReceived;
            return $item;
        })->filter(fn($item) => $item->quantity_remaining > 0)->values(); // Keep only items with a remaining quantity

        return response()->json([
            'party' => $po->party,
            'items' => $remainingItems,
        ]);
    }
}
