<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMenuItem;
use App\Models\OrderItemBroadcastSpecification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class OrderItemController extends Controller
{
    /**
     * Handles adding a polymorphic broadcast item row to a parent order.
     * Route: POST /api/orders/{order}/items
     */
    public function store(Order $order, Request $request): JsonResponse
    {
        $baseValidator = Validator::make($request->all(), [
            'order_menu_item_id' => 'required|integer|exists:order_menu_items,id',
            'due_date'           => 'required|date_format:Y-m-d',
            'specifications'     => 'required|array',
        ]);

        if ($baseValidator->fails()) {
            return response()->json(['errors' => $baseValidator->errors()], 422);
        }

        $menuItem = OrderMenuItem::findOrFail($request->input('order_menu_item_id'));
        if ((int)$menuItem->order_menu_category_id !== 1) {
            return response()->json([
                'errors' => ['order_menu_item_id' => ['The selected menu item does not belong to Category 1.']]
            ], 422);
        }

        $specs = $request->input('specifications');
        $customErrors = $this->validateVideoSpecifications($menuItem, $specs);
        if (count($customErrors) > 0) {
            return response()->json(['errors' => $customErrors], 422);
        }

        $item = DB::transaction(function () use ($order, $menuItem, $request, $specs) {
            // Create concrete table details record
            $broadcastSpec = OrderItemBroadcastSpecification::create([
                'type'             => $specs['type'],
                'cut'              => $specs['cut'],
                'duration_seconds' => (int) $specs['duration_seconds'],
                'language'         => $specs['language'],
                'encoding'         => $specs['encoding'] ?? null,
                'encoding_custom'  => $specs['encoding_custom'] ?? null,
                'isci'             => 'ISCI-' . strtoupper(Str::random(8)),
            ]);

            $itemTags = (array) ($menuItem->tags ?? []);

            // Track into structural core order_items table log
            return OrderItem::create([
                'order_id'             => $order->id,
                'order_menu_item_id'   => $menuItem->id,
                'locked_price'         => $menuItem->default_price ?? '0.00',
                'order_item_status_id' => 1, 
                'due_date'             => $request->input('due_date'),
                'specifiable_id'       => $broadcastSpec->id,
                'specifiable_type'     => OrderItemBroadcastSpecification::class,
                'audio_received'       => in_array('Audio', $itemTags, true) ? false : null,
                'voice_over_received'  => in_array('Voice Over', $itemTags, true) ? false : null,
                'art_received'         => in_array('Art', $itemTags, true) ? false : null,
            ]);
        });

        return response()->json([
            'message' => 'Video delivery item successfully authorized and appended to cart.',
            'data'    => $item->fresh(['specifiable', 'statusLookup'])
        ], 201);
    }

    /**
     * Executes an in-place update on the specification entity tables while auto-incrementing the R-counter.
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

        $incomingSpecs = $request->input('specifications', []);
        if ((int)$menuItem->order_menu_category_id === 1) {
            $customErrors = $this->validateVideoSpecifications($menuItem, $incomingSpecs);
            if (count($customErrors) > 0) {
                return response()->json(['errors' => $customErrors], 422);
            }
        }

        $orderItem = DB::transaction(function () use ($orderItem, $request, $incomingSpecs) {
            $specification = $orderItem->specifiable;
            $nextRevision = ((int) ($orderItem->revision_number ?? 0)) + 1;

            if ($specification) {
                $newIsci = 'ISCI-' . strtoupper(Str::random(8)) . 'R' . $nextRevision;

                $specification->update([
                    'type'             => $incomingSpecs['type'] ?? $specification->type,
                    'cut'              => $incomingSpecs['cut'] ?? $specification->cut,
                    'duration_seconds' => isset($incomingSpecs['duration_seconds']) ? (int)$incomingSpecs['duration_seconds'] : $specification->duration_seconds,
                    'language'         => $incomingSpecs['language'] ?? $specification->language,
                    'encoding'         => array_key_exists('encoding', $incomingSpecs) ? $incomingSpecs['encoding'] : $specification->encoding,
                    'encoding_custom'  => array_key_exists('encoding_custom', $incomingSpecs) ? $incomingSpecs['encoding_custom'] : $specification->encoding_custom,
                    'isci'             => $newIsci,
                ]);
            }

            $orderItem->update([
                'due_date'        => $request->input('due_date', $orderItem->due_date),
                'revision_number' => $nextRevision,
            ]);

            return $orderItem;
        });

        return response()->json([
            'message' => "Line item specifications updated successfully to Revision {$orderItem->revision_number}.",
            'data'    => $orderItem->fresh(['specifiable', 'statusLookup'])
        ], 200);
    }

    /**
     * Shared Validation Logic Engine
     */
    private function validateVideoSpecifications(OrderMenuItem $menuItem, ?array $specs): array
    {
        $customErrors = [];
        $blueprint = (array) $menuItem->form_blueprint;

        if (blank($blueprint) || !isset($blueprint['types'])) {
            $customErrors['specifications'] = ['The underlying menu item form blueprint config is invalid.'];
            return $customErrors;
        }

        $type = Arr::get($specs, 'type');
        if (!is_string($type) || !array_key_exists($type, $blueprint['types'])) {
            $customErrors['specifications.type'] = ['The selected type is invalid.'];
            return $customErrors;
        }

        $typeConfig = $blueprint['types'][$type];
        $cut = Arr::get($specs, 'cut');
        $durationSeconds = Arr::get($specs, 'duration_seconds');
        $language = Arr::get($specs, 'language');

        if ($cut === 'International TV Package' || $type === 'International') {
            if ($durationSeconds !== 30) {
                $customErrors['specifications.duration_seconds'] = ['International spots are locked to 30 seconds.'];
            }
            if (!is_string($language) || !Str::startsWith($language, 'English')) {
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

        $encoding = Arr::get($specs, 'encoding');
        $encodingCustom = trim((string) Arr::get($specs, 'encoding_custom'));
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
     * Soft cancels an active row from a cart checkout view container node index.
     * Route: DELETE /api/order-items/{orderItem}
     */
    public function destroy(OrderItem $orderItem): JsonResponse
    {
        $statusModelClass = $orderItem->statusLookup()->getRelated();
        $cancelledStatus = $statusModelClass::where('name', 'Cancelled')->first();
        $statusId = $cancelledStatus ? $cancelledStatus->id : 5; 

        $orderItem->update([
            'order_item_status_id' => $statusId
        ]);

        return response()->json([
            'message' => 'Line item successfully transitioned to Cancelled state.',
            'data'    => $orderItem->fresh(['specifiable', 'statusLookup'])
        ], 200);
    }
}