<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;

class AdminHeroSlideController extends Controller
{
    private const IMAGE_RULES = ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'];

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        return response()->json(['data' => HeroSlide::ordered()->get()]);
    }

    // ── Store: both images are required ───────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateSlide($request, creating: true);

        $slide = HeroSlide::create([
            'desktop_image' => $request->file('desktop_image')->store('hero-slides', 'public'),
            'mobile_image'  => $request->file('mobile_image')->store('hero-slides', 'public'),
            'alt_ar'        => $validated['alt_ar'] ?? null,
            'alt_en'        => $validated['alt_en'] ?? null,
            'link'          => $validated['link'] ?? null,
            'is_active'     => $validated['is_active'] ?? true,
            'sort_order'    => (int) HeroSlide::max('sort_order') + 1,
        ]);

        return response()->json(['message' => 'Slide created successfully.', 'data' => $slide], 201);
    }

    // ── Update: images optional (replace either one) ──────────────────────────
    // POST (not PUT) so multipart file uploads work

    public function update(Request $request, int $id): JsonResponse
    {
        $slide = HeroSlide::findOrFail($id);
        $validated = $this->validateSlide($request, creating: false);

        foreach (['desktop_image', 'mobile_image'] as $field) {
            if ($request->hasFile($field)) {
                $old = $slide->{$field};
                $slide->{$field} = $request->file($field)->store('hero-slides', 'public');
                Storage::disk('public')->delete($old);
            }
        }

        $slide->fill([
            'alt_ar'    => $validated['alt_ar'] ?? null,
            'alt_en'    => $validated['alt_en'] ?? null,
            'link'      => $validated['link'] ?? null,
            'is_active' => $validated['is_active'] ?? $slide->is_active,
        ])->save();

        return response()->json(['message' => 'Slide updated successfully.', 'data' => $slide->fresh()]);
    }

    // ── Destroy (image files are deleted by the model) ────────────────────────

    public function destroy(int $id): JsonResponse
    {
        HeroSlide::findOrFail($id)->delete();

        return response()->json(['message' => 'Slide deleted successfully.']);
    }

    // ── Toggle / Reorder ──────────────────────────────────────────────────────

    public function toggleActive(int $id): JsonResponse
    {
        $slide = HeroSlide::findOrFail($id);
        $slide->update(['is_active' => !$slide->is_active]);

        return response()->json(['data' => $slide]);
    }

    /** ids[0] shows first */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:hero_slides,id'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['ids'] as $index => $id) {
                HeroSlide::whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['message' => 'Slides order saved.', 'data' => HeroSlide::ordered()->get()]);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    private function validateSlide(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'nullable';

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'desktop_image' => [$required, ...self::IMAGE_RULES],
            'mobile_image'  => [$required, ...self::IMAGE_RULES],
            'alt_ar'        => ['nullable', 'string', 'max:150'],
            'alt_en'        => ['nullable', 'string', 'max:150'],
            // Internal path (/shop) or a full https link — never javascript: etc.
            'link'          => ['nullable', 'string', 'max:500', 'regex:/^(\/(?!\/)[^\s]*|https?:\/\/[^\s]+)$/'],
            'is_active'     => ['nullable', 'boolean'],
        ], [
            'desktop_image.required' => 'The desktop image is required — a slide needs both a desktop and a mobile image.',
            'mobile_image.required'  => 'The mobile image is required — a slide needs both a desktop and a mobile image.',
            'link.regex'             => 'The link must start with / (e.g. /shop) or https://',
        ]);

        // Catch swapped images: desktop must be landscape, mobile must not be
        $validator->after(function (Validator $v) use ($request) {
            $this->checkOrientation($v, $request->file('desktop_image'), 'desktop_image', landscape: true);
            $this->checkOrientation($v, $request->file('mobile_image'), 'mobile_image', landscape: false);
        });

        return $validator->validate();
    }

    private function checkOrientation(Validator $v, mixed $file, string $field, bool $landscape): void
    {
        if (!$file instanceof UploadedFile || $v->errors()->has($field)) {
            return;
        }
        $size = @getimagesize($file->getRealPath());
        if (!$size) {
            return;
        }
        [$w, $h] = $size;
        if ($landscape && $w <= $h) {
            $v->errors()->add($field, "The desktop image must be wide (landscape). This one is {$w}×{$h}.");
        } elseif (!$landscape && $w > $h) {
            $v->errors()->add($field, "The mobile image must be tall or square (portrait). This one is {$w}×{$h}.");
        }
    }
}
