<?php

namespace App\Http\Controllers\Api;

use App\Models\OdkTarget;
use App\Support\OdkWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OdkTargetController
{
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => collect(OdkTarget::types())->map(fn (string $type): array => [
                'key' => $type,
                'label' => str($type)->replace('_', ' ')->title()->toString(),
                'requires_record' => $type !== OdkTarget::TYPE_NONE,
            ])->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->workspace();
        $targets = $workspace->targets()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.trim((string) $request->input('q')).'%'))
            ->where('active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $targets]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertManager($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_values(array_diff(OdkTarget::types(), [OdkTarget::TYPE_NONE])))],
            'external_id' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $target = $this->workspace()->targets()->create([
            'uuid' => (string) Str::uuid(),
            ...$data,
            'active' => true,
        ]);

        return response()->json(['success' => true, 'data' => $target], 201);
    }

    public function update(Request $request, OdkTarget $target): JsonResponse
    {
        $this->assertManager($request);
        abort_unless((int) $target->workspace_id === (int) $this->workspace()->id, 404);
        $data = $request->validate([
            'external_id' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'active' => ['boolean'],
        ]);
        $target->update($data);

        return response()->json(['success' => true, 'data' => $target->fresh()]);
    }

    public function destroy(Request $request, OdkTarget $target): JsonResponse
    {
        $this->assertManager($request);
        abort_unless((int) $target->workspace_id === (int) $this->workspace()->id, 404);
        $target->update(['active' => false]);

        return response()->json(['success' => true]);
    }

    private function workspace()
    {
        $workspace = app(OdkWorkspaceContext::class)->current();
        abort_unless($workspace, 409, 'No active workspace is selected.');

        return $workspace;
    }

    private function assertManager(Request $request): void
    {
        abort_unless(app(OdkWorkspaceContext::class)->userCanManage($request->user()), 403, 'Only workspace owners and admins can manage targets.');
    }
}
