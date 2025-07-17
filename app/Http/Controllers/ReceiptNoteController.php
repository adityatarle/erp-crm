<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Product;
use App\Models\ReceiptNote;
use App\Models\ReceiptNoteItem;
use App\Models\PurchaseEntry;
use App\Models\PurchaseEntryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Payable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use PDF; // <-- Make sure to add this use statement at the top

class ReceiptNoteController extends Controller
{
   
    public function index()
    {
        $receiptNotes = ReceiptNote::with('party')->get();
        return view('receipt_notes.index', compact('receiptNotes'));
    }

    public function create()
    {
        // Fetch parties that have at least one purchase order
        $parties = Party::whereHas('purchaseOrders')->orderBy('name')->get();

        // Fetch only pending/approved purchase orders
        $purchaseOrders = PurchaseOrder::whereIn('status', ['pending', 'partial'])->orderBy('purchase_order_number', 'desc')->get();

        // Pass all products for the dynamic "Add Product" functionality
        $products = Product::orderBy('name')->get();

        return view('receipt_notes.create', compact('parties', 'products', 'purchaseOrders'));
    }

    public function store(Request $request)
    {
        Log::info('Starting store method for ReceiptNote', $request->all());

        $request->validate([
            'receipt_number' => 'required|string|unique:receipt_notes,receipt_number',
            'receipt_date' => 'required|date',
            'party_id' => 'required|exists:parties,id',
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'purchase_order_number' => 'nullable|string|max:255',
            'discount' => 'nullable|numeric|min:0|max:100',
            'note' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_price' => 'nullable|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0|max:100',
            'products.*.cgst_rate' => 'nullable|numeric|min:0|max:100',
            'products.*.sgst_rate' => 'nullable|numeric|min:0|max:100',
            'products.*.igst_rate' => 'nullable|numeric|min:0|max:100',
            'products.*.status' => 'required|in:received,pending',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                Log::info('Starting transaction for ReceiptNote store');

                $totalGstAmount = 0;
                $subtotal = 0;
                $totalDiscount = 0;
                $discountRate = floatval($request->discount ?? 0);

                // Fetch purchase_order_number if not provided
                $purchaseOrderNumber = $request->purchase_order_number;
                if (!$purchaseOrderNumber && $request->purchase_order_id) {
                    $purchaseOrder = PurchaseOrder::findOrFail($request->purchase_order_id);
                    $purchaseOrderNumber = $purchaseOrder->purchase_order_number;
                }

                // Validate party_id matches the purchase order's party
                $purchaseOrder = PurchaseOrder::findOrFail($request->purchase_order_id);
                if ($purchaseOrder->party_id != $request->party_id) {
                    throw new \Exception('Selected party does not match the purchase order party.');
                }

                $receiptNote = ReceiptNote::create([
                    'receipt_number' => $request->receipt_number,
                    'receipt_date' => $request->receipt_date,
                    'party_id' => $request->party_id,
                    'purchase_order_id' => $request->purchase_order_id,
                    'purchase_order_number' => $purchaseOrderNumber,
                    'note' => $request->note,
                    'gst_amount' => 0,
                    'discount' => $discountRate,
                ]);
                Log::info('Receipt note created', ['id' => $receiptNote->id]);

                foreach ($request->products as $product) {
                    $unitPrice = floatval($product['unit_price']);
                    $quantity = $product['quantity'];
                    $itemDiscount = floatval($product['discount'] ?? $discountRate);
                    $cgstRate = floatval($product['cgst_rate'] ?? 0);
                    $sgstRate = floatval($product['sgst_rate'] ?? 0);
                    $igstRate = floatval($product['igst_rate'] ?? 0);

                    $basePrice = $quantity * $unitPrice;
                    $discountAmount = $basePrice * ($itemDiscount / 100);
                    $priceAfterDiscount = $basePrice - $discountAmount;

                    $cgstAmount = $priceAfterDiscount * ($cgstRate / 100);
                    $sgstAmount = $priceAfterDiscount * ($sgstRate / 100);
                    $igstAmount = $priceAfterDiscount * ($igstRate / 100);
                    $totalPrice = $priceAfterDiscount + $cgstAmount + $sgstAmount + $igstAmount;

                    ReceiptNoteItem::create([
                        'receipt_note_id' => $receiptNote->id,
                        'product_id' => $product['product_id'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount' => $itemDiscount,
                        'cgst_rate' => $cgstRate,
                        'sgst_rate' => $sgstRate,
                        'igst_rate' => $igstRate,
                        'total_price' => $totalPrice,
                        'status' => $product['status'],
                        'gst_rate' => $cgstRate + $sgstRate + $igstRate,
                        'gst_type' => $igstRate > 0 ? 'IGST' : ($cgstRate > 0 || $sgstRate > 0 ? 'CGST' : null),
                    ]);

                    // Update stock for 'received' items
                    if ($product['status'] === 'received') {
                        $productModel = Product::find($product['product_id']);
                        if (!$productModel) {
                            Log::error("Product not found for ID: {$product['product_id']}");
                            throw new \Exception("Product not found for ID: {$product['product_id']}");
                        }
                        Log::info("Updating stock for product ID: {$product['product_id']}, Quantity: {$quantity}");
                        $productModel->increment('stock', $quantity);
                    }

                    $subtotal += $priceAfterDiscount;
                    $totalDiscount += $discountAmount;
                    $totalGstAmount += ($cgstAmount + $sgstAmount + $igstAmount);
                }

                $receiptNote->update([
                    'gst_amount' => $totalGstAmount,
                    'discount' => $discountRate,
                ]);

                // ✅ Check if all items in the purchase order are received
                $purchaseOrderItems = PurchaseOrderItem::where('purchase_order_id', $request->purchase_order_id)->get();
                $allItemsReceived = true;

                foreach ($purchaseOrderItems as $orderItem) {
                    $orderedQty = $orderItem->quantity;

                    $receivedQty = ReceiptNoteItem::whereHas('receiptNote', function ($q) use ($request) {
                        $q->where('purchase_order_id', $request->purchase_order_id);
                    })->where('product_id', $orderItem->product_id)
                      ->where('status', 'received')
                      ->sum('quantity');

                    if ($receivedQty < $orderedQty) {
                        $allItemsReceived = false;
                        break;
                    }
                }

                if ($allItemsReceived) {
                    PurchaseOrder::where('id', $request->purchase_order_id)->update([
                        'status' => 'received',
                    ]);
                    Log::info('Purchase order status updated to received', ['purchase_order_id' => $request->purchase_order_id]);
                }

                Log::info('Receipt note items created and totals updated', ['receipt_note_id' => $receiptNote->id]);
                return redirect()->route('receipt_notes.index')->with('success', 'Receipt note created successfully.');
            });
        } catch (\Exception $e) {
            Log::error('Transaction failed in store', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->withErrors(['error' => 'An error occurred while creating the receipt note. Check logs for details.']);
        }
    }





    public function convertToPurchaseEntry(Request $request, $id)
    {
        $request->validate([
            'invoice_number' => 'required|string|unique:purchase_entries,invoice_number',
            'invoice_date' => 'required|date',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id', // Made optional
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:0',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0|max:100',
            'products.*.cgst_rate' => 'nullable|numeric|min:0|max:100',
            'products.*.sgst_rate' => 'nullable|numeric|min:0|max:100',
            'products.*.igst_rate' => 'nullable|numeric|min:0|max:100',
            'products.*.status' => 'required|in:pending,received',
        ]);

        try {
            return DB::transaction(function () use ($request, $id) {
                Log::info('Starting convertToPurchaseEntry', ['receipt_note_id' => $id]);

                $receiptNote = ReceiptNote::with('items')->findOrFail($id);
                Log::info('Receipt note loaded', ['receipt_note_id' => $receiptNote->id]);

                $receivedProducts = array_filter($request->products, fn($product) => $product['quantity'] > 0);
                if (empty($receivedProducts)) {
                    Log::error('No valid products to convert', ['receipt_note_id' => $id]);
                    return redirect()->back()->with('error', 'No products with valid quantities to convert.');
                }

                $totalAmount = 0;
                $totalDiscount = 0;
                $totalGstAmount = 0;
                $discountRate = $request->discount ?? $receiptNote->discount ?? 0;

                $purchaseEntry = PurchaseEntry::create([
                    'purchase_number' => 'PE-' . Str::random(8),
                    'purchase_order_id' => $request->purchase_order_id,
                    'purchase_date' => $request->receipt_date ?? $receiptNote->receipt_date,
                    'invoice_number' => $request->invoice_number,
                    'invoice_date' => $request->invoice_date,
                    'party_id' => $receiptNote->party_id,
                    'note' => $request->note ?? $receiptNote->note,
                    'gst_amount' => 0, // Will be updated
                    'discount' => $discountRate,
                    'from_receipt_note' => true, // Added flag
                ]);
                Log::info('Purchase entry created', [
                    'id' => $purchaseEntry->id,
                    'discount' => $discountRate,
                ]);

                foreach ($receivedProducts as $item) {
                    $basePrice = $item['quantity'] * $item['unit_price'];
                    $discountAmount = $basePrice * ($discountRate / 100);
                    $priceAfterDiscount = $basePrice - $discountAmount;

                    $cgstRate = $item['cgst_rate'] ?? 0;
                    $sgstRate = $item['sgst_rate'] ?? 0;
                    $igstRate = $item['igst_rate'] ?? 0;

                    $cgstAmount = $priceAfterDiscount * ($cgstRate / 100);
                    $sgstAmount = $priceAfterDiscount * ($sgstRate / 100);
                    $igstAmount = $priceAfterDiscount * ($igstRate / 100);
                    $totalPrice = $priceAfterDiscount + $cgstAmount + $sgstAmount + $igstAmount;

                    PurchaseEntryItem::create([
                        'purchase_entry_id' => $purchaseEntry->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'discount' => $discountRate,
                        'total_price' => $totalPrice,
                        'cgst_rate' => $cgstRate,
                        'sgst_rate' => $sgstRate,
                        'igst_rate' => $igstRate,
                        'status' => $item['status'],
                    ]);
                    Log::info('Purchase entry item created', [
                        'purchase_entry_id' => $purchaseEntry->id,
                        'product_id' => $item['product_id'],
                        'discount' => $discountRate,
                        'cgst_rate' => $cgstRate,
                        'sgst_rate' => $sgstRate,
                        'igst_rate' => $igstRate,
                        'total_price' => $totalPrice,
                        'status' => $item['status'],
                    ]);

                    $totalAmount += $totalPrice;
                    $totalDiscount += $discountAmount;
                    $totalGstAmount += ($cgstAmount + $sgstAmount + $igstAmount);
                }

                $purchaseEntry->update([
                    'gst_amount' => $totalGstAmount,
                    'discount' => $totalDiscount,
                ]);
                Log::info('Purchase entry updated with totals', [
                    'id' => $purchaseEntry->id,
                    'gst_amount' => $totalGstAmount,
                    'discount' => $totalDiscount,
                ]);

                Payable::create([
                    'purchase_entry_id' => $purchaseEntry->id,
                    'party_id' => $receiptNote->party_id,
                    'amount' => $totalAmount,
                    'is_paid' => false,
                ]);
                Log::info('Payable created', ['purchase_entry_id' => $purchaseEntry->id, 'amount' => $totalAmount]);

                // Update PO status if all items are received
                if ($request->purchase_order_id) {
                    $purchaseOrderItems = PurchaseOrderItem::where('purchase_order_id', $request->purchase_order_id)->get();
                    $allItemsReceived = true;

                    foreach ($purchaseOrderItems as $orderItem) {
                        $orderedQty = $orderItem->quantity;
                        $receivedQty = PurchaseEntryItem::whereHas('purchaseEntry', function ($q) use ($request) {
                            $q->where('purchase_order_id', $request->purchase_order_id);
                        })->where('product_id', $orderItem->product_id)
                            ->where('status', 'received')
                            ->sum('quantity');

                        if ($receivedQty < $orderedQty) {
                            $allItemsReceived = false;
                            break;
                        }
                    }

                    if ($allItemsReceived) {
                        PurchaseOrder::where('id', $request->purchase_order_id)->update(['status' => 'received']);
                    }
                }

                $receiptNote->items()->delete();
                Log::info('Receipt note items deleted', ['receipt_note_id' => $receiptNote->id]);
                $receiptNote->delete();
                Log::info('Receipt note deleted', ['id' => $id]);

                return redirect()->route('purchase_entries.index')->with('success', 'Receipt note converted to purchase entry successfully.');
            });
        } catch (\Exception $e) {
            Log::error('Conversion failed', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->withErrors(['error' => 'An error occurred while converting the receipt note. Check logs for details.']);
        }
    }

    public function convertToPurchaseEntrybk(Request $request, $id)
    {
        $request->validate([
            'invoice_number' => 'required|string|unique:purchase_entries,invoice_number',
            'invoice_date' => 'required|date',
            'purchase_order_id' => 'required|exists:purchase_orders,id',
        ]);

        try {
            DB::transaction(function () use ($request, $id) {
                Log::info('Starting convertToPurchaseEntry', ['receipt_note_id' => $id]);

                $receiptNote = ReceiptNote::with('items')->findOrFail($id);
                if (!$receiptNote) {
                    Log::error('Receipt note not found', ['id' => $id]);
                    throw new \Exception('Receipt note not found.');
                }
                Log::info('Receipt note loaded', ['receipt_note_id' => $receiptNote->id]);

                $totalAmount = 0;
                $totalGstAmount = 0;
                $totalDiscount = 0;
                $discountRate = $receiptNote->discount ?? 0;

                $purchaseEntry = PurchaseEntry::create([
                    'purchase_number' => 'PE-' . Str::random(8),
                    'purchase_order_id' => $request->purchase_order_id,
                    'purchase_date' => $receiptNote->receipt_date,
                    'invoice_number' => $request->invoice_number,
                    'invoice_date' => $request->invoice_date,
                    'party_id' => $receiptNote->party_id,
                    'note' => $receiptNote->note,
                    'gst_amount' => 0, // Will be updated
                    'discount' => $discountRate,
                ]);
                Log::info('Purchase entry created', [
                    'id' => $purchaseEntry->id,
                    'discount' => $discountRate,
                ]);

                foreach ($receiptNote->items as $item) {
                    $basePrice = $item->quantity * $item->unit_price;
                    $discountAmount = $basePrice * ($discountRate / 100);
                    $priceAfterDiscount = $basePrice - $discountAmount;

                    $cgstRate = $item->cgst_rate ?? 0;
                    $sgstRate = $item->sgst_rate ?? 0;
                    $igstRate = $item->igst_rate ?? 0;

                    $cgstAmount = $priceAfterDiscount * ($cgstRate / 100);
                    $sgstAmount = $priceAfterDiscount * ($sgstRate / 100);
                    $igstAmount = $priceAfterDiscount * ($igstRate / 100);
                    $totalPrice = $priceAfterDiscount + $cgstAmount + $sgstAmount + $igstAmount;

                    PurchaseEntryItem::create([
                        'purchase_entry_id' => $purchaseEntry->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'discount' => $discountRate,
                        'total_price' => $totalPrice,
                        'cgst_rate' => $cgstRate,
                        'sgst_rate' => $sgstRate,
                        'igst_rate' => $igstRate,
                        'status' => $item->status,
                    ]);
                    Log::info('Purchase entry item created', [
                        'purchase_entry_id' => $purchaseEntry->id,
                        'product_id' => $item->product_id,
                        'discount' => $discountRate,
                        'cgst_rate' => $cgstRate,
                        'sgst_rate' => $sgstRate,
                        'igst_rate' => $igstRate,
                        'total_price' => $totalPrice,
                        'status' => $item->status,
                    ]);

                    $totalAmount += $totalPrice;
                    $totalDiscount += $discountAmount;
                    $totalGstAmount += ($cgstAmount + $sgstAmount + $igstAmount);

                    if ($item->status === 'received') {
                        $productModel = Product::find($item->product_id);
                        if (!$productModel) {
                            Log::error("Product not found for ID: {$item->product_id}");
                            throw new \Exception("Product not found for ID: {$item->product_id}");
                        }
                        Log::info("Updating stock for product ID: {$item->product_id}, Quantity: {$item->quantity}");
                        $productModel->updateStock($item->quantity);
                    }
                }

                $purchaseEntry->update([
                    'gst_amount' => $totalGstAmount,
                    'discount' => $totalDiscount,
                ]);
                Log::info('Purchase entry updated with totals', [
                    'id' => $purchaseEntry->id,
                    'gst_amount' => $totalGstAmount,
                    'discount' => $totalDiscount,
                ]);

                Payable::create([
                    'purchase_entry_id' => $purchaseEntry->id,
                    'party_id' => $receiptNote->party_id,
                    'amount' => $totalAmount,
                    'is_paid' => false,
                ]);
                Log::info('Payable created', ['purchase_entry_id' => $purchaseEntry->id, 'amount' => $totalAmount]);

                $receiptNote->items()->delete();
                Log::info('Receipt note items deleted', ['receipt_note_id' => $receiptNote->id]);
                $receiptNote->delete();
                Log::info('Receipt note deleted', ['id' => $id]);
            });

            Log::info('Conversion to purchase entry completed successfully', ['receipt_note_id' => $id]);
            return redirect()->route('purchase_entries.index')->with('success', 'Receipt note converted to purchase entry successfully.');
        } catch (\Exception $e) {
            Log::error('Conversion failed', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->withErrors(['error' => 'An error occurred while converting the receipt note. Check logs for details.']);
        }
    }

    public function edit(ReceiptNote $receiptNote)
    {
        // Eager-load the relationships for the receipt note itself
        $receiptNote->load(['party', 'items.product']);

        // Fetch all products for the "Add another product" dropdown
        $products = Product::orderBy('name')->get();

        // This variable will hold the original PO for context
        $purchaseOrder = null;

        // If the receipt note is linked to a purchase order, calculate available quantities
        if ($receiptNote->purchase_order_id) {
            $purchaseOrder = PurchaseOrder::with(['items', 'receiptNoteItems'])
                ->find($receiptNote->purchase_order_id);

            if ($purchaseOrder) {
                // Get quantities already received on OTHER notes for this PO
                // FIXED: Use 'quantity' instead of 'quantity_received'
                $receivedOnOtherNotes = $purchaseOrder->receiptNoteItems
                    ->where('receipt_note_id', '!=', $receiptNote->id)
                    ->groupBy('product_id')
                    ->map(fn($group) => $group->sum('quantity')); // Fixed column name

                // For each item currently on THIS receipt note, calculate its maximum allowable quantity
                foreach ($receiptNote->items as $currentItem) {
                    $poItem = $purchaseOrder->items->firstWhere('product_id', $currentItem->product_id);
                    if ($poItem) {
                        $otherReceived = $receivedOnOtherNotes->get($currentItem->product_id, 0);
                        // The max is what they already entered + what's remaining on the PO
                        // FIXED: Use 'quantity' instead of 'quantity_received'
                        $currentItem->quantity_available = $currentItem->quantity + ($poItem->quantity - $otherReceived - $currentItem->quantity);
                    } else {
                        $currentItem->quantity_available = 9999; // Manually added item
                    }
                }
            }
        } else {
            // If no PO is linked, there's no limit on quantity
            foreach ($receiptNote->items as $currentItem) {
                $currentItem->quantity_available = 9999;
            }
        }

        return view('receipt_notes.edit', compact('receiptNote', 'products'));
    }



    public function update(Request $request, ReceiptNote $receiptNote)
    {
        Log::info('Starting update method for ReceiptNote', $request->all());

        $request->validate([
            'receipt_number' => 'required|string|unique:receipt_notes,receipt_number,' . $receiptNote->id,
            'receipt_date' => 'required|date',
            'party_id' => 'required|exists:parties,id',
            'purchase_order_number' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255|unique:purchase_entries,invoice_number',
            'invoice_date' => 'nullable|date',
            'discount' => 'nullable|numeric|min:0|max:100',
            'note' => 'nullable|string',
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_price' => 'nullable|numeric|min:0',
            'products.*.cgst_rate' => 'nullable|numeric|min:0|max:100',
            'products.*.sgst_rate' => 'nullable|numeric|min:0|max:100',
            'products.*.igst_rate' => 'nullable|numeric|min:0|max:100',
            'products.*.status' => 'required|in:received,pending',
        ]);

        try {
            DB::transaction(function () use ($request, $receiptNote) {
                Log::info('Starting transaction for ReceiptNote update', ['receipt_note_id' => $receiptNote->id]);

                $totalGstAmount = 0;
                $subtotal = 0;
                $totalDiscount = 0;
                $discountRate = isset($request->discount) ? floatval($request->discount) : 0;

                $receiptNote->update([
                    'receipt_number' => $request->receipt_number,
                    'receipt_date' => $request->receipt_date,
                    'party_id' => $request->party_id,
                    'purchase_order_number' => $request->purchase_order_number,
                    'invoice_number' => $request->invoice_number,
                    'invoice_date' => $request->invoice_date,
                    'note' => $request->note,
                    'gst_amount' => 0,
                    'discount' => $discountRate,
                ]);
                Log::info('Receipt note updated', ['id' => $receiptNote->id]);

                $receiptNote->items()->delete();
                Log::info('Existing receipt note items deleted', ['receipt_note_id' => $receiptNote->id]);

                foreach ($request->products as $product) {
                    $unitPrice = floatval($product['unit_price']);
                    $quantity = $product['quantity'];
                    $cgstRate = isset($product['cgst_rate']) ? floatval($product['cgst_rate']) : 0;
                    $sgstRate = isset($product['sgst_rate']) ? floatval($product['sgst_rate']) : 0;
                    $igstRate = isset($product['igst_rate']) ? floatval($product['igst_rate']) : 0;

                    $basePrice = $quantity * $unitPrice;
                    $discountAmount = $basePrice * ($discountRate / 100);
                    $priceAfterDiscount = $basePrice - $discountAmount;

                    $cgstAmount = $priceAfterDiscount * ($cgstRate / 100);
                    $sgstAmount = $priceAfterDiscount * ($sgstRate / 100);
                    $igstAmount = $priceAfterDiscount * ($igstRate / 100);
                    $totalPrice = $priceAfterDiscount + $cgstAmount + $sgstAmount + $igstAmount;

                    ReceiptNoteItem::create([
                        'receipt_note_id' => $receiptNote->id,
                        'product_id' => $product['product_id'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'cgst_rate' => $cgstRate,
                        'sgst_rate' => $sgstRate,
                        'igst_rate' => $igstRate,
                        'total_price' => $totalPrice,
                        'status' => $product['status'],
                        'gst_rate' => $cgstRate + $sgstRate + $igstRate,
                        'gst_type' => $igstRate > 0 ? 'IGST' : ($cgstRate > 0 ? 'CGST' : null),
                    ]);

                    $subtotal += $priceAfterDiscount;
                    $totalDiscount += $discountAmount;
                    $totalGstAmount += ($cgstAmount + $sgstAmount + $igstAmount);
                }

                $receiptNote->update([
                    'gst_amount' => $totalGstAmount,
                    'discount' => $discountRate,
                ]);

                Log::info('Receipt note items updated and totals updated', ['receipt_note_id' => $receiptNote->id]);
            });

            if ($request->input('convert_to_purchase_entry') == '1') {
                Log::info('Convert to purchase entry triggered', ['receipt_note_id' => $receiptNote->id]);
                return $this->convertToPurchaseEntry($request, $receiptNote->id);
            }

            Log::info('Receipt note update completed without conversion', ['receipt_note_id' => $receiptNote->id]);
            return redirect()->route('receipt_notes.index')->with('success', 'Receipt note updated successfully.');
        } catch (\Exception $e) {
            Log::error('Transaction failed in update', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->withErrors(['error' => 'An error occurred while updating the receipt note. Check logs for details.']);
        }
    }

    // ADD THIS NEW METHOD TO THE CONTROLLER
    public function downloadPDF($id)
    {
        // Eager load all necessary relationships
        $receiptNote = ReceiptNote::with(['party', 'items.product'])->findOrFail($id);

        // Your company's details
        $company = [
            'name' => 'MAULI SOLUTIONS',
            'address' => 'Gat No-627, Pune-Nashik Highway, IN Front Off Gabriel, Vitthal-Muktai Complex, Kuruli Chakan, Pune-410501',
            'contact' => 'Mob-9284716150/9158506948',
            'gstin' => '27ABIFM9220D1ZC',
            'state' => 'Maharashtra, Code: 27',
            'email' => 'maulisolutions18@gmail.com',
            'pan' => 'ABIFM9220D'
        ];

        // --- KEY FIX: CALCULATE ALL FINANCIAL TOTALS ---
        $subtotal = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;
        $grandTotal = 0;
        $totalQuantity = 0;
        $discountRate = $receiptNote->discount ?? 0;

        foreach ($receiptNote->items as $item) {
            $totalQuantity += $item->quantity;
            $basePrice = $item->quantity * $item->unit_price;
            $discountAmount = $basePrice * ($discountRate / 100);
            $priceAfterDiscount = $basePrice - $discountAmount;

            $subtotal += $priceAfterDiscount;

            // Calculate GST based on the discounted price
            $cgstAmount = $priceAfterDiscount * (($item->cgst_rate ?? 0) / 100);
            $sgstAmount = $priceAfterDiscount * (($item->sgst_rate ?? 0) / 100);
            $igstAmount = $priceAfterDiscount * (($item->igst_rate ?? 0) / 100);

            $totalCgst += $cgstAmount;
            $totalSgst += $sgstAmount;
            $totalIgst += $igstAmount;
        }

        $grandTotal = $subtotal + $totalCgst + $totalSgst + $totalIgst;

        $amountInWords = function_exists('numberToWords') ? numberToWords(round($grandTotal)) : 'Error: numberToWords helper not found.';

        // Pass all the calculated data to the view
        $data = [
            'receiptNote' => $receiptNote,
            'company' => $company,
            'totalQuantity' => $totalQuantity,
            'subtotal' => $subtotal,
            'totalCgst' => $totalCgst,
            'totalSgst' => $totalSgst,
            'totalIgst' => $totalIgst,
            'grandTotal' => $grandTotal,
            'amountInWords' => $amountInWords,
        ];

        // The view file is 'receipt_notes.pdf'
        $pdf = PDF::loadView('receipt_notes.pdf', $data);

        return $pdf->download('Receipt_Note_' . $receiptNote->receipt_number . '.pdf');
    }
}
