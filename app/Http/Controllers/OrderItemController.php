<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMenuItem;
use App\Models\StatusLookup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class OrderItemController extends Controller
{
    /**
     * Appends a validated Category 1 video line item to an order.
     * Route: POST /api/orders/{order}/items
     */
    public function store(Order $order, Request $request): JsonResponse
    {
        // Step 1: Structural Baseline Payload Verification
        $baseValidator = Validator::make($request->all(), [
            'order_menu_item_id' => 'required|integer|exists:order_menu_items,id',
            'due_date'           => 'required|date_format:Y-m-d',
            'specifications'     => 'required|array',
        ]);

        if ($baseValidator->fails()) {
            return response()->json(['errors' => $baseValidator->errors()], 422);
        }

        // Step 2: Validate Menu Item and Category Association
        $menuItem = OrderMenuItem::findOrFail($request->input('order_menu_item_id'));
        
        if ((int)$menuItem->order_menu_category_id !== 1) {
            return response()->json([
                'errors' => ['order_menu_item_id' => ['The selected menu item does not belong to Category 1 (Broadcast & Streaming Video).']]
            ], 422);
        }

        // Step 3: Deconstruct & Verify the Master form_blueprint JSON Column
        $blueprint = $menuItem->form_blueprint;
        if (blank($blueprint) || !is_array($blueprint) || !isset($blueprint['types'])) {
            return response()->json([
                'errors' => ['specifications' => ['The underlying menu item form blueprint config is invalid or missing types configuration.']]
            ], 422);
        }

        // Gather specs targeted for fine-grained validation
        $specs = $request->input('specifications');
        
        // Manual validation container to map exact custom 422 error keys
        $customErrors = [];

        // Validate Type
        $type = Arr::get($specs, 'type');
        if (!is_string($type) || !array_key_exists($type, $blueprint['types'])) {
            $customErrors['specifications.type'] = ['The selected type is invalid or not supported by this menu item.'];
            return response()->json(['errors' => $customErrors], 422);
        }

        $typeConfig = $blueprint['types'][$type];
        $cut = Arr::get($specs, 'cut');
        $durationSeconds = Arr::get($specs, 'duration_seconds');
        $language = Arr::get($specs, 'language');

        // 🚀 LEGACY UI OVERRIDE GATEWAY: Match broadcast-encoding-matrix.ts
        $isInternationalCut = ($cut === 'International TV Package' || $type === 'International');
        if ($isInternationalCut) {
            // Force duration_seconds to be strictly 30
            if ($durationSeconds !== 30) {
                $customErrors['specifications.duration_seconds'] = ['International TV Packages are strictly locked to 30 seconds.'];
            }
            // Force language to start with English or match blueprint entry
            if (!is_string($language) || !Str::startsWith($language, 'English')) {
                $customErrors['specifications.language'] = ['International TV Packages are locked to English deliverables.'];
            }
        } else {
            // Standard Blueprint Rules Engine Execution (Case-Sensitive & Strict Type Matching)
            if (!is_string($cut) || !in_array($cut, $typeConfig['cuts'] ?? [], true)) {
                $customErrors['specifications.cut'] = ['The selected cut variant is not valid for this item type.'];
            }

            if (!is_int($durationSeconds) || !in_array($durationSeconds, $typeConfig['durations'] ?? [], true)) {
                $customErrors['specifications.duration_seconds'] = ['The duration must be an integer matching allowed parameters: ' . implode(', ', $typeConfig['durations'] ?? [])];
            }

            if (!is_string($language) || !in_array($language, $typeConfig['languages'] ?? [], true)) {
                $customErrors['specifications.language'] = ['The selected language delivery target is invalid.'];
            }
        }

        // 🚀 ENCODING XOR LOGIC CONSTRAINT: Exactly one parameter can exist
        $encoding = Arr::get($specs, 'encoding');
        $encodingCustom = trim((string) Arr::get($specs, 'encoding_custom'));

        $hasCatalog = !blank($encoding);
        $hasCustom = !blank($encodingCustom);

        if (($hasCatalog && $hasCustom) || (!$hasCatalog && !$hasCustom)) {
            $customErrors['specifications.encoding'] = ['Exactly one of encoding (catalog) or encoding_custom must be provided.'];
            $customErrors['specifications.encoding_custom'] = ['Exactly one of encoding (catalog) or encoding_custom must be provided.'];
        } else {
            if ($hasCatalog) {
                if (!in_array($encoding, $blueprint['encodings'] ?? [], true)) {
                    $customErrors['specifications.encoding'] = ['The selected catalog profile is invalid for this delivery platform.'];
                }
            }
        }

        // Short-circuit execution if any custom business rules failed validation
        if (count($customErrors) > 0) {
            return response()->json(['errors' => $customErrors], 422);
        }

        // Step 4: Inject Default Values & Persist to the Database
        // Automatically calculate and inject systemic internal trackers
        $finalSpecs = array_merge($specs, [
            'isci' => 'ISCI-' . strtoupper(Str::random(8)), // Inject platform unique clock identifier 
        ]);

        $item = OrderItem::create([
            'order_id'             => $order->id,
            'order_menu_item_id'   => $menuItem->id,
            'locked_price'         => $menuItem->default_price ?? '0.00', // Snapshot historical financial data
            'order_item_status_id' => 1, // Defaults cleanly to "Still In Cart" state index
            'due_date'             => $request->input('due_date'),
            'specifications'       => $finalSpecs,
        ]);

        return response()->json([
            'message' => 'Video delivery item successfully authorized and appended to cart.',
            'data'    => $item
        ], 201);
    }

    /**
     * Updates an existing line item's specifications.
     * Route: PATCH /api/order-items/{orderItem}
     */
    public function update(OrderItem $orderItem, Request $request): JsonResponse
    {
        $menuItem = $orderItem->orderMenuItem;

        if ($orderItem->statusLookup?->name === 'Cancelled' || (int)$orderItem->order_item_status_id === 5) {
            return response()->json([
                'errors' => ['specifications' => ['This line item has been cancelled and can no longer be modified.']]
            ], 422);
        }

        // Route processing only for Category 1 elements
        if ((int)$menuItem->order_menu_category_id === 1) {
            $customErrors = $this->validateVideoSpecifications($menuItem, $request->input('specifications', []));
            if (count($customErrors) > 0) {
                return response()->json(['errors' => $customErrors], 422);
            }
        }

        // Keep existing due date if not provided in update payload
        $orderItem->update([
            'due_date'       => $request->input('due_date', $orderItem->due_date),
            'specifications' => $request->input('specifications', $orderItem->specifications),
        ]);

        return response()->json([
            'message' => 'Line item specifications updated successfully.',
            'data'    => $orderItem
        ], 200);
    }

    /**
     * DRY Shared Validator Routine for Category 1 Video Assets
     */
    private function validateVideoSpecifications(OrderMenuItem $menuItem, ?array $specs): array
    {
        $customErrors = [];
        $blueprint = (array) $menuItem->form_blueprint;

        if (blank($blueprint) || !isset($blueprint['types'])) {
            $customErrors['specifications'] = ['The underlying menu item form blueprint config is invalid.'];
            return $customErrors;
        }

        $type = \Illuminate\Support\Arr::get($specs, 'type');
        if (!is_string($type) || !array_key_exists($type, $blueprint['types'])) {
            $customErrors['specifications.type'] = ['The selected type is invalid.'];
            return $customErrors;
        }

        $typeConfig = $blueprint['types'][$type];
        $cut = \Illuminate\Support\Arr::get($specs, 'cut');
        $durationSeconds = \Illuminate\Support\Arr::get($specs, 'duration_seconds');
        $language = \Illuminate\Support\Arr::get($specs, 'language');

        // Legacy Overrides Check
        if ($cut === 'International TV Package' || $type === 'International') {
            if ($durationSeconds !== 30) {
                $customErrors['specifications.duration_seconds'] = ['International spots are locked to 30 seconds.'];
            }
            if (!is_string($language) || !\Illuminate\Support\Str::startsWith($language, 'English')) {
                $customErrors['specifications.language'] = ['International spots are locked to English.'];
            }
        } else {
            if (!is_string($cut) || !in_array($cut, $typeConfig['cuts'] ?? [], true)) {
                $customErrors['specifications.cut'] = ['The selected cut variant is not valid.'];
            }
            if (!is_int($durationSeconds) || !in_array($durationSeconds, $typeConfig['durations'] ?? [], true)) {
                $customErrors['specifications.duration_seconds'] = ['The selected duration is invalid.'];
            }
            if (!is_string($language) || !in_array($language, $typeConfig['languages'] ?? [], true)) {
                $customErrors['specifications.language'] = ['The selected language is invalid.'];
            }
        }

        // XOR Encoding Verification
        $encoding = \Illuminate\Support\Arr::get($specs, 'encoding');
        $encodingCustom = trim((string) \Illuminate\Support\Arr::get($specs, 'encoding_custom'));
        $hasCatalog = !blank($encoding);
        $hasCustom = !blank($encodingCustom);

        if (($hasCatalog && $hasCustom) || (!$hasCatalog && !$hasCustom)) {
            $customErrors['specifications.encoding'] = ['Provide exactly one of encoding or encoding_custom.'];
        } elseif ($hasCatalog && !in_array($encoding, $blueprint['encodings'] ?? [], true)) {
            $customErrors['specifications.encoding'] = ['The selected catalog profile is invalid.'];
        }

        return $customErrors;
    }

    /**
     * Prunes a single line item row from an order container.
     * Route: DELETE /api/order-items/{orderItem}
     */
    public function destroy(OrderItem $orderItem): JsonResponse
    {
        $statusModelClass = $orderItem->statusLookup()->getRelated();
        
        // Query that model to find the 'Cancelled' row
        $cancelledStatus = $statusModelClass::where('name', 'Cancelled')->first();
        
        // Safe fallback to ID 5 if your database seeder hasn't run yet
        $statusId = $cancelledStatus ? $cancelledStatus->id : 5; 

        // Update the item state
        $orderItem->update([
            'order_item_status_id' => $statusId
        ]);

        return response()->json([
            'message' => 'Line item successfully transitioned to Cancelled state.',
            'data'    => $orderItem->fresh([
                'statusLookup:id,name,order_status_id',
                'statusLookup.orderStatus:id,name'
            ])
        ], 200);
    }
}