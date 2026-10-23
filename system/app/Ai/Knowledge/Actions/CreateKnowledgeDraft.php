<?php

namespace App\Ai\Knowledge\Actions;

use App\Ai\Knowledge\KnowledgeSourceAccess;
use App\Models\Ai\KnowledgeCitationFixture;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class CreateKnowledgeDraft
{
    public function __construct(private readonly KnowledgeSourceAccess $access) {}

    /**
     * @param array{
     *     title: string,
     *     key?: string,
     *     source_class?: string,
     *     audience?: string,
     *     question?: string,
     *     answer?: string,
     *     body_text?: string,
     *     category?: string
     * } $input
     */
    public function handle(User $actor, array $input): KnowledgeSourceVersion
    {
        $this->access->requireManageCatalog($actor);
        $sourceClass = $input['source_class'] ?? KnowledgeSource::SOURCE_CLASS_POLICY;
        $audience = $input['audience'] ?? KnowledgeSource::AUDIENCE_STAFF_ONLY;
        if (! in_array($sourceClass, [KnowledgeSource::SOURCE_CLASS_POLICY, KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ], true)) {
            throw new RuntimeException('unsupported_source_class');
        }
        $allowedAudiences = [KnowledgeSource::AUDIENCE_STAFF_ONLY, KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY];
        if (! in_array($audience, $allowedAudiences, true)) {
            throw new RuntimeException('unsupported_audience');
        }

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('title_required');
        }

        $question = null;
        $answer = null;
        if ($sourceClass === KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ) {
            $question = trim((string) ($input['question'] ?? ''));
            $answer = trim((string) ($input['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                throw new RuntimeException('faq_fields_required');
            }
            $body = $question."\n".$answer;
        } else {
            $body = trim((string) ($input['body_text'] ?? ''));
            if ($body === '') {
                throw new RuntimeException('policy_body_required');
            }
        }

        $scope = $actor->actorScope();

        $classification = in_array($audience, [
            KnowledgeSource::AUDIENCE_STAFF_ONLY,
            KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF,
        ], true)
            ? KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL
            : KnowledgeSource::CLASSIFICATION_PUBLIC_INSTITUTIONAL;

        return DB::transaction(function () use ($actor, $input, $title, $sourceClass, $audience, $classification, $scope, $question, $answer, $body): KnowledgeSourceVersion {
            $key = trim((string) ($input['key'] ?? ''));
            if ($key === '') {
                $key = Str::slug($title).'-'.Str::lower(Str::random(4));
            }
            $source = KnowledgeSource::query()->create([
                'id' => (string) Str::uuid(),
                'key' => $key,
                'title' => $title,
                'source_class' => $sourceClass,
                'audience' => $audience,
                'purpose' => 'policy_inquiry',
                'category' => $input['category'] ?? ($scope->office === 'admin'
                    ? KnowledgeSource::CATEGORY_CAMPUS
                    : KnowledgeSource::CATEGORY_ADMISSIONS),
                'classification' => $classification,
                'institution_id' => $scope->institutionId,
                'owning_office' => $scope->office,
                'owner_user_id' => (string) $actor->getKey(),
            ]);

            $checksum = hash('sha256', $body);
            $path = 'knowledge/quarantine/'.$source->id.'/v1.txt';
            Storage::disk('local')->put($path, $body);

            $version = KnowledgeSourceVersion::query()->create([
                'id' => (string) Str::uuid(),
                'knowledge_source_id' => $source->id,
                'version' => 1,
                'status' => KnowledgeSourceVersion::STATUS_DRAFT,
                'title' => $title,
                'body_text' => $body,
                'question' => $question,
                'answer' => $answer,
                'content_checksum' => $checksum,
                'quarantine_path' => $path,
                'created_by' => (string) $actor->getKey(),
            ]);

            KnowledgeCitationFixture::query()->create([
                'id' => (string) Str::uuid(),
                'knowledge_source_version_id' => $version->id,
                'kind' => 'citation',
                'query_text' => $question ?? 'policy',
                'expected_excerpt' => mb_substr($answer ?? $body, 0, 80),
                'expected_outcome' => 'cite',
            ]);
            KnowledgeCitationFixture::query()->create([
                'id' => (string) Str::uuid(),
                'knowledge_source_version_id' => $version->id,
                'kind' => 'refusal',
                'query_text' => 'unrelated personal grades',
                'expected_excerpt' => null,
                'expected_outcome' => 'refuse',
            ]);

            return $version->fresh(['source', 'fixtures']) ?? $version;
        });
    }
}
