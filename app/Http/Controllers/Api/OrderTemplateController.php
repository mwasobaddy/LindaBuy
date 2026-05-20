<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\Api\CreatedResponse;
use App\Http\Responses\Api\DeletedResponse;
use App\Http\Responses\Api\ForbiddenResponse;
use App\Http\Responses\Api\SuccessResponse;
use App\Models\OrderTemplate;
use Illuminate\Http\Request;

class OrderTemplateController extends Controller
{
    public function index(Request $request)
    {
        $seller = $request->user()->sellers()->first();

        if (! $seller) {
            return app(SuccessResponse::class, ['data' => []]);
        }

        $templates = OrderTemplate::where('seller_id', $seller->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return app(SuccessResponse::class, ['data' => $templates]);
    }

    public function store(Request $request)
    {
        $seller = $request->user()->sellers()->first();

        if (! $seller) {
            return app(ForbiddenResponse::class, ['message' => 'You must be a seller to create templates.']);
        }

        $validated = $request->validate([
            'template_name' => 'required|string|max:255',
            'item_description' => 'required|string|max:5000',
            'price' => 'required|integer|min:1000',
            'delivery_type' => 'required|in:shop_delivery,g4s',
            'delivery_location' => 'nullable|string|max:1000',
        ]);

        $template = OrderTemplate::create([
            'seller_id' => $seller->id,
            'template_name' => $validated['template_name'],
            'item_description' => $validated['item_description'],
            'price' => $validated['price'],
            'delivery_type' => $validated['delivery_type'],
            'delivery_location' => $validated['delivery_location'] ?? null,
        ]);

        return app(CreatedResponse::class, [
            'data' => $template,
            'message' => 'Template created successfully',
        ]);
    }

    public function show(Request $request, OrderTemplate $orderTemplate)
    {
        $seller = $request->user()->sellers()->first();

        if (! $seller || $orderTemplate->seller_id !== $seller->id) {
            return app(ForbiddenResponse::class, ['message' => 'This template does not belong to you.']);
        }

        return app(SuccessResponse::class, ['data' => $orderTemplate]);
    }

    public function update(Request $request, OrderTemplate $orderTemplate)
    {
        $seller = $request->user()->sellers()->first();

        if (! $seller || $orderTemplate->seller_id !== $seller->id) {
            return app(ForbiddenResponse::class, ['message' => 'This template does not belong to you.']);
        }

        $validated = $request->validate([
            'template_name' => 'sometimes|string|max:255',
            'item_description' => 'sometimes|string|max:5000',
            'price' => 'sometimes|integer|min:1000',
            'delivery_type' => 'sometimes|in:shop_delivery,g4s',
            'delivery_location' => 'nullable|string|max:1000',
        ]);

        $orderTemplate->update($validated);

        return app(SuccessResponse::class, [
            'data' => $orderTemplate,
            'message' => 'Template updated successfully',
        ]);
    }

    public function destroy(Request $request, OrderTemplate $orderTemplate)
    {
        $seller = $request->user()->sellers()->first();

        if (! $seller || $orderTemplate->seller_id !== $seller->id) {
            return app(ForbiddenResponse::class, ['message' => 'This template does not belong to you.']);
        }

        $orderTemplate->delete();

        return app(DeletedResponse::class, ['message' => 'Template deleted successfully']);
    }
}
