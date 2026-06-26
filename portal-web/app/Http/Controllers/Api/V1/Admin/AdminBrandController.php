<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminBrandController
{
    public function index(Request $request): JsonResponse
    {
        $query = Brand::query();

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('domain', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        $perPage = (int) $request->input('per_page', 50);
        $page = (int) $request->input('page', 1);

        $brands = $query->orderBy('alexa_rank')->orderBy('name')->paginate($perPage, ['*'], 'page', $page);
        $arr = $brands->toArray();

        return response()->json([
            'data' => $arr['data'],
            'meta' => [
                'total' => $arr['total'],
                'per_page' => $arr['per_page'],
                'current_page' => $arr['current_page'],
                'last_page' => $arr['last_page'],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'domain' => 'required|string|max:255|unique:brands,domain',
            'name' => 'required|string|max:100',
            'category' => 'nullable|string|max:50',
            'alexa_rank' => 'nullable|integer|min:0',
            'enabled' => 'boolean',
        ]);

        $brand = Brand::create($validated);

        AdminAuditLog::record('brand.create', 'brand', $brand->id, $this->present($brand), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->present($brand)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $brand = Brand::findOrFail($id);

        $validated = $request->validate([
            'domain' => 'string|max:255|unique:brands,domain,' . $id,
            'name' => 'string|max:100',
            'category' => 'nullable|string|max:50',
            'alexa_rank' => 'nullable|integer|min:0',
            'enabled' => 'boolean',
        ]);

        $brand->update($validated);

        AdminAuditLog::record('brand.update', 'brand', $id, $this->present($brand->fresh()), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->present($brand->fresh())]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $brand = Brand::findOrFail($id);
        $brand->delete();

        AdminAuditLog::record('brand.delete', 'brand', $id, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    public function import(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'brands' => 'required|array|min:1',
            'brands.*.domain' => 'required|string|max:255',
            'brands.*.name' => 'required|string|max:100',
            'brands.*.category' => 'nullable|string|max:50',
            'brands.*.alexa_rank' => 'nullable|integer|min:0',
        ]);

        $created = 0;
        $updated = 0;
        $now = now();
        foreach ($validated['brands'] as $row) {
            $existing = Brand::where('domain', $row['domain'])->first();
            if ($existing) {
                $existing->update([
                    'name' => $row['name'],
                    'category' => $row['category'] ?? $existing->category,
                    'alexa_rank' => $row['alexa_rank'] ?? $existing->alexa_rank,
                ]);
                $updated++;
            } else {
                Brand::create(array_merge($row, ['enabled' => true]));
                $created++;
            }
        }

        AdminAuditLog::record('brand.import', 'brand', null, ['created' => $created, 'updated' => $updated], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['created' => $created, 'updated' => $updated, 'total' => $created + $updated]]);
    }

    public function export(): JsonResponse
    {
        $rows = Brand::query()
            ->orderBy('alexa_rank')
            ->get()
            ->map(fn (Brand $b): array => [
                'domain' => $b->domain,
                'name' => $b->name,
                'category' => $b->category,
                'alexa_rank' => $b->alexa_rank,
                'enabled' => (bool) $b->enabled,
            ])->all();

        return response()->json(['data' => $rows, 'meta' => ['total' => count($rows)]]);
    }

    private function present(Brand $b): array
    {
        return $b->toArray();
    }
}
