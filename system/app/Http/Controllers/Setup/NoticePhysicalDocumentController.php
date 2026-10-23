<?php

declare(strict_types=1);

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\DiscardNoticeDocumentDraftRequest;
use App\Http\Requests\Notifications\PreviewNoticeDocumentRequest;
use App\Http\Requests\Notifications\SaveNoticeDocumentDraftRequest;
use App\Http\Requests\Notifications\SaveNoticeDocumentVersionRequest;
use App\Http\Requests\Notifications\StoreNoticeDocumentImageRequest;
use App\Http\Requests\Notifications\UploadNoticeDocumentRequest;
use App\Models\Notifications\NoticeDocumentDraft;
use App\Models\Notifications\NoticeDocumentVersion;
use App\Notifications\Templates\Actions\NoticeDocumentWorkflow;
use App\Notifications\Templates\Queries\NoticeDocumentWorkspaceQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class NoticePhysicalDocumentController extends Controller
{
    public function __construct(private readonly NoticeDocumentWorkspaceQuery $query, private readonly NoticeDocumentWorkflow $workflow) {}

    public function edit(Request $request, string $domain, string $slug): InertiaResponse
    {
        $props = $this->query->editor($request->user(), $domain, $slug);

        return Inertia::render('Setup/Notifications/Templates/PhysicalDocument', $props);
    }

    public function state(Request $request, string $domain, string $slug): JsonResponse
    {
        return response()->json($this->query->editor($request->user(), $domain, $slug));
    }

    public function store(SaveNoticeDocumentDraftRequest $request, string $domain, string $slug): JsonResponse
    {
        return $this->operation($this->workflow->save($this->query->family($request->user(), $domain, $slug), $request->validated(), $request->user()));
    }

    public function update(SaveNoticeDocumentDraftRequest $request, string $domain, string $slug, int $draft): JsonResponse
    {
        return $this->operation($this->workflow->save($this->query->family($request->user(), $domain, $slug),
            $request->validated(), $request->user(), $draft));
    }

    public function destroy(DiscardNoticeDocumentDraftRequest $request, string $domain, string $slug, int $draft): JsonResponse
    {
        return $this->operation($this->workflow->discard($this->query->family($request->user(), $domain, $slug),
            $request->validated(), $request->user(), $draft));
    }

    public function upload(UploadNoticeDocumentRequest $request, string $domain, string $slug): JsonResponse
    {
        $data = $request->validated();
        $file = $data['document'];
        unset($data['document']);

        return $this->operation($this->workflow->upload($this->query->family($request->user(), $domain, $slug), $file,
            $data, $request->user()));
    }

    public function storeImage(StoreNoticeDocumentImageRequest $request, string $domain, string $slug): JsonResponse
    {
        $data = $request->validated();
        $file = $data['image'];
        unset($data['image']);

        return $this->operation($this->workflow->storeImage(
            $this->query->family($request->user(), $domain, $slug),
            $file,
            $data,
            $request->user()
        ));
    }

    public function image(Request $request, string $domain, string $slug, string $asset): Response
    {
        $family = $this->query->family($request->user(), $domain, $slug);
        $bytes = $this->workflow->imageBytes($family, $asset, $request->user());
        $mime = str_starts_with($bytes, "\x89PNG") ? 'image/png' : 'image/jpeg';

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(PreviewNoticeDocumentRequest $request, string $domain, string $slug): JsonResponse
    {
        return response()->json($this->workflow->preview($this->query->family($request->user(), $domain, $slug),
            $request->validated(), $request->user()));
    }

    public function version(SaveNoticeDocumentVersionRequest $request, string $domain, string $slug): JsonResponse
    {
        return $this->operation($this->workflow->createVersion($this->query->family($request->user(), $domain, $slug),
            $request->validated(), $request->user()));
    }

    public function pdf(Request $request, string $domain, string $slug, int $version): Response
    {
        $family = $this->query->family($request->user(), $domain, $slug);
        $artifact = NoticeDocumentVersion::query()->whereKey($version)->where('communication_template_id', $family->id)->firstOrFail();
        $path = $this->workflow->pdf($family, $artifact, $request->user());
        try {
            $bytes = file_get_contents($path);
            abort_unless(is_string($bytes) && str_starts_with($bytes, '%PDF-'), 404);

            return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'inline; filename="notice-document.pdf"']);
        } finally {
            @unlink($path);
            @rmdir(dirname($path));
        }
    }

    public function thumbnail(Request $request, string $domain, string $slug, int $version): Response
    {
        $family = $this->query->family($request->user(), $domain, $slug);
        $artifact = NoticeDocumentVersion::query()->whereKey($version)->where('communication_template_id', $family->id)->firstOrFail();
        $bytes = $this->workflow->thumbnail($family, $artifact, $request->user());
        abort_if($bytes === null, 404, 'thumbnail_unavailable');

        return response($bytes, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function draftThumbnail(Request $request, string $domain, string $slug, int $draft): Response
    {
        $family = $this->query->family($request->user(), $domain, $slug);
        $artifact = NoticeDocumentDraft::query()->whereKey($draft)->where('communication_template_id', $family->id)->firstOrFail();
        $bytes = $this->workflow->draftThumbnail($family, $artifact, $request->user());
        abort_if($bytes === null, 404, 'thumbnail_unavailable');

        return response($bytes, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function operation(array $result): JsonResponse
    {
        $status = (int) ($result['_status'] ?? 200);
        unset($result['_status']);

        return response()->json($result, $status);
    }
}
