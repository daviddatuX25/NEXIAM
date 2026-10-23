<?php

declare(strict_types=1);

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\CheckNoticeMessageAttachmentRequest;
use App\Http\Requests\Notifications\DiscardCommunicationTemplateDraftRequest;
use App\Http\Requests\Notifications\PreviewCommunicationTemplateRequest;
use App\Http\Requests\Notifications\PublishCommunicationTemplateVersionRequest;
use App\Http\Requests\Notifications\SaveCommunicationTemplateDraftRequest;
use App\Http\Requests\Notifications\StoreNoticeMessageImageRequest;
use App\Http\Requests\Notifications\TestCommunicationTemplateMailRequest;
use App\Notifications\Templates\Actions\CheckNoticeMessageAttachment;
use App\Notifications\Templates\Actions\DiscardCommunicationTemplateDraft;
use App\Notifications\Templates\Actions\PublishCommunicationTemplateVersion;
use App\Notifications\Templates\Actions\PublishNoticeMessageVersion;
use App\Notifications\Templates\Actions\RenderCommunicationTemplatePreview;
use App\Notifications\Templates\Actions\RenderNoticeMessagePreview;
use App\Notifications\Templates\Actions\SaveNoticeMessageDraft;
use App\Notifications\Templates\Actions\SendNoticeMessageTestMail;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\Notifications\Templates\Actions\StoreNoticeMessageImage;
use App\Notifications\Templates\Queries\CommunicationTemplateEditorQuery;
use App\Notifications\Templates\Queries\NoticeMessageComposerQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class NoticeDocumentStudioController extends Controller
{
    public function __construct(
        private readonly CommunicationTemplateEditorQuery $query,
        private readonly NoticeMessageComposerQuery $messageQuery,
    ) {}

    public function studio(Request $request, string $domain, string $slug): Response
    {
        abort_unless(NoticeMessageComposerQuery::supports($domain, $slug), 404);

        $candidateRequested = $request->query->has('document_candidate_id')
            || $request->query->has('document_candidate_version');

        return Inertia::render('Setup/Notifications/Templates/Message', $this->messageQuery->editor(
            $request->user(), $domain, $slug, $request->query('version'), $request->query('source_version'),
            $candidateRequested ? [
                'id' => $request->query('document_candidate_id'),
                'version' => $request->query('document_candidate_version'),
            ] : null,
        ));
    }

    public function editorState(Request $request, string $domain, string $slug): JsonResponse
    {
        abort_unless(NoticeMessageComposerQuery::supports($domain, $slug), 404);
        $candidateRequested = $request->query->has('document_candidate_id')
            || $request->query->has('document_candidate_version');

        return response()->json($this->messageQuery->editor(
            $request->user(), $domain, $slug, $request->query('version'), $request->query('source_version'),
            $candidateRequested ? [
                'id' => $request->query('document_candidate_id'),
                'version' => $request->query('document_candidate_version'),
            ] : null,
        ));
    }

    public function storeDraft(
        SaveCommunicationTemplateDraftRequest $request,
        string $domain,
        string $slug,
        SaveNoticeMessageDraft $saveMessage,
    ): JsonResponse {
        abort_unless(NoticeMessageComposerQuery::supports($domain, $slug), 404);

        return $this->operation($saveMessage->save(
            $this->query->template($request->user(), $domain, $slug),
            $request->validated(),
            $request->user(),
        ));
    }

    public function updateDraft(
        SaveCommunicationTemplateDraftRequest $request,
        string $domain,
        string $slug,
        string $draft,
        SaveNoticeMessageDraft $saveMessage,
    ): JsonResponse {
        abort_unless(NoticeMessageComposerQuery::supports($domain, $slug), 404);
        $template = $this->query->template($request->user(), $domain, $slug);
        $this->query->assertDraftScope($template, $draft);

        return $this->operation($saveMessage->save(
            $template,
            [...$request->validated(), 'draft_id' => $draft],
            $request->user(),
        ));
    }

    public function discardDraft(DiscardCommunicationTemplateDraftRequest $request, string $domain, string $slug, string $draft, DiscardCommunicationTemplateDraft $discard): JsonResponse
    {
        abort_unless(NoticeMessageComposerQuery::supports($domain, $slug), 404);
        $template = $this->query->template($request->user(), $domain, $slug);
        $this->query->assertDraftScope($template, $draft);

        return $this->operation($discard->discard($template, [...$request->validated(), 'draft_id' => $draft], $request->user()));
    }

    public function preview(
        PreviewCommunicationTemplateRequest $request,
        string $domain,
        string $slug,
        RenderNoticeMessagePreview $messageRenderer,
        RenderCommunicationTemplatePreview $communicationRenderer,
    ): JsonResponse {
        abort_unless(NoticeMessageComposerQuery::supports($domain, $slug), 404);
        $template = $this->query->template($request->user(), $domain, $slug);
        if ($request->has('content_sha256')) {
            return response()->json($communicationRenderer->execute(
                $template,
                $request->validated(),
                $request->user(),
            ));
        }

        return response()->json($messageRenderer->execute(
            $template,
            $request->validated(),
            $request->user(),
        ));
    }

    public function publish(
        PublishCommunicationTemplateVersionRequest $request,
        string $domain,
        string $slug,
        PublishNoticeMessageVersion $publishMessage,
        PublishCommunicationTemplateVersion $publishCommunication,
    ): JsonResponse {
        abort_unless(NoticeMessageComposerQuery::supports($domain, $slug), 404);
        $template = $this->query->template($request->user(), $domain, $slug);
        $draft = $template->draft()->first();
        if (($draft !== null && $draft->content_schema_version !== 'notice-content-3') || $request->has('preview_receipts.print')) {
            return $this->operation($publishCommunication->publish(
                $template,
                $request->validated(),
                $request->user(),
            ));
        }

        return $this->operation($publishMessage->publish(
            $template,
            $request->validated(),
            $request->user(),
        ));
    }

    public function testMail(
        TestCommunicationTemplateMailRequest $request,
        string $domain,
        string $slug,
        SendNoticeMessageTestMail $sendMessage,
    ): JsonResponse {
        abort_unless(NoticeMessageComposerQuery::supports($domain, $slug), 404);

        return $this->operation($sendMessage->execute(
            $this->query->template($request->user(), $domain, $slug),
            $request->validated(),
            $request->user(),
        ));
    }

    public function attachmentCheck(
        CheckNoticeMessageAttachmentRequest $request,
        string $domain,
        string $slug,
        CheckNoticeMessageAttachment $check,
    ): JsonResponse {
        abort_unless(NoticeMessageComposerQuery::supports($domain, $slug), 404);

        return response()->json($check->execute(
            $this->query->template($request->user(), $domain, $slug),
            $request->validated(),
            $request->user(),
        ));
    }

    public function storeMessageImage(
        StoreNoticeMessageImageRequest $request,
        string $domain,
        string $slug,
        StoreNoticeMessageImage $store,
    ): JsonResponse {
        abort_unless(NoticeMessageComposerQuery::supports($domain, $slug), 404);
        $data = $request->validated();
        $image = $data['image'];
        unset($data['image']);

        return $this->operation($store->store(
            $this->query->template($request->user(), $domain, $slug),
            $image,
            $data,
            $request->user(),
        ));
    }

    public function messageImage(
        Request $request,
        string $domain,
        string $slug,
        string $asset,
        StoreCommunicationTemplateDocument $documents,
    ): \Illuminate\Http\Response {
        abort_unless(NoticeMessageComposerQuery::supports($domain, $slug), 404);
        $template = $this->query->template($request->user(), $domain, $slug);
        $metadata = $documents->metadata($template, $asset);
        abort_unless(in_array($metadata->mime_type, ['image/png', 'image/jpeg'], true), 404);
        $document = $documents->verified($template, $asset, $metadata->mime_type);
        $bytes = Storage::disk('local')->get($document->storage_path);
        abort_unless(is_string($bytes), 404);

        return response($bytes, 200, ['Content-Type' => $document->mime_type,
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @param array<string, mixed> $result */
    private function operation(array $result): JsonResponse
    {
        $status = $result['_status'];
        unset($result['_status']);

        return response()->json($result, $status);
    }
}
