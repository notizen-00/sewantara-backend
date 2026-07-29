<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Modules\Customers\Application\ManageCustomers;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request, ManageCustomers $customers)
    {
        $result = $customers->paginate(
            $request->string('status')->toString() ?: null,
            $request->string('search')->toString() ?: null,
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function store(Request $request, ManageCustomers $customers)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'blacklisted'])],
            'notes' => ['nullable', 'string'],
        ]);

        $customer = $customers->create($validated);

        return response()->json(['success' => true, 'message' => 'Customer berhasil dibuat.', 'data' => $customer], 201);
    }

    public function show(Customer $customer)
    {
        return response()->json(['success' => true, 'data' => $customer]);
    }

    public function update(Request $request, Customer $customer, ManageCustomers $customers)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'blacklisted'])],
            'notes' => ['nullable', 'string'],
        ]);

        $customer = $customers->update($customer, $validated);

        return response()->json(['success' => true, 'message' => 'Customer berhasil diperbarui.', 'data' => $customer]);
    }
}
