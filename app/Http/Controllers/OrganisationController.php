<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrganisationController extends Controller
{
    /**
     * Display a listing of the organisations.
     */
    public function index(): JsonResponse
    {
        // Fetch all organisations and eager-load their types and country
        $organisations = Organisation::with(['types', 'country'])->get();
        
        return response()->json(['organisations' => $organisations]);
    }

    /**
     * Store a newly created organisation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateOrganisation($request);

        // Extract the types array before saving the organisation
        $types = $request->input('types', []);
        unset($validated['types']);

        $organisation = Organisation::create($validated);

        // Attach the types to the pivot table
        if (!empty($types)) {
            $organisation->types()->attach($types);
        }

        // Load the relationships to return complete data to React
        $organisation->load(['types', 'country']);

        return response()->json([
            'message' => 'Organisation created successfully.',
            'organisation' => $organisation
        ], 201);
    }

    /**
     * Display the specified organisation.
     */
    public function show(Organisation $organisation): JsonResponse
    {
        $organisation->load(['types', 'country', 'users']);
        
        return response()->json(['organisation' => $organisation]);
    }

    /**
     * Update the specified organisation.
     */
    public function update(Request $request, Organisation $organisation): JsonResponse
    {
        $validated = $this->validateOrganisation($request);

        $types = $request->input('types');
        unset($validated['types']);

        $organisation->update($validated);

        // 'sync' cleanly removes old types and adds the newly selected ones
        if ($types !== null) {
            $organisation->types()->sync($types);
        }

        $organisation->load(['types', 'country']);

        return response()->json([
            'message' => 'Organisation updated successfully.',
            'organisation' => $organisation
        ]);
    }

    /**
     * Remove the specified organisation.
     */
    public function destroy(Organisation $organisation): JsonResponse
    {
        $organisation->delete();

        return response()->json(['message' => 'Organisation deleted successfully.']);
    }

    /**
     * Helper method to keep validation rules perfectly DRY.
     */
    private function validateOrganisation(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            
            // Validate the array of type IDs
            'types' => 'nullable|array',
            'types.*' => 'exists:organisation_types,id',

            'billing_address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:20',
            'country_id' => 'nullable|exists:countries,id',
            'currency_id' => 'nullable|exists:countries,id',
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'credit_terms' => 'nullable|string|max:255',
            
            'accounts_payable_contact' => 'nullable|string|max:255',
            'accounts_payable_emails' => 'nullable|array',
            'accounts_payable_emails.*' => 'email',
            
            'pay_email' => 'nullable|email|max:255',
            'rec_email' => 'nullable|email|max:255',
            'copy_email' => 'nullable|email|max:255',
            'phone_number' => 'nullable|string|max:50',
            'fax_number' => 'nullable|string|max:50',
            'bank_account_number' => 'nullable|string',
            'routing_number' => 'nullable|string',
            'rec_name' => 'nullable|string|max:255',
            'rec_tel' => 'nullable|string|max:50',
        ]);
    }
}