<?php

use App\Models\TamEvaluation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    TamEvaluation::ensureTableExists();
});

test('public TAM evaluation instrument renders with YouTube video and evaluation questions', function (): void {
    $response = $this->get(route('tam.first-years'));

    $response->assertSuccessful();
    $response->assertSee('EVALUATION INSTRUMENT: TECHNOLOGY ACCEPTANCE OF NEXIAM');
    $response->assertSee('First-Year College Student');
    $response->assertSee('PART I: EVALUATOR PROFILE AND INFORMED CONSENT');
    $response->assertSee('Participant Informed Consent');
    $response->assertSee('Republic Act No. 10173');
    $response->assertSee('College of Arts and Sciences');
    $response->assertSee('College of Business Management');
    $response->assertSee('College of Teacher Education');
    $response->assertSee('PART II: EVALUATION INSTRUCTIONS AND RATING BENCHMARK');
    $response->assertSee('Neutral means neither agreement nor disagreement');
    $response->assertSee('https://www.youtube-nocookie.com/embed/6P1jCN4LWnM');
    $response->assertSee('https://youtu.be/6P1jCN4LWnM');
    $response->assertSee('STU-PU-01');
    $response->assertSee('STU-PEOU-01');
    $response->assertSee('Download Backup Copy');
    $response->assertSee('Submit Evaluation to NEXIAM');
    $response->assertSee('nexiamispsctagudin@gmail.com');
    $response->assertSee('Need Help or Have Inquiries?');
    $response->assertSee('How to operate parts of the system');
    $response->assertSee('System Operation: How to Navigate', false);
});
test('initial screen context renders researchers, xiam greeting, and institutional documents', function (): void {
    $response = $this->get(route('tam.first-years'));

    $response->assertSuccessful();
    // Mascot & Welcome context
    $response->assertSee('Meet Xiam');
    $response->assertSee('Welcome, ISPSC Aspirants');
    $response->assertSee('NEXIAM was designed specifically to elevate, simplify, and dignify');
    $response->assertSee('/assets/xiam/emotions/v2/hi-smooth.webp');

    // Three Researchers
    $response->assertSee('Meet the Capstone Researchers');
    $response->assertSee('David Datu N. Sarmiento');
    $response->assertSee('Jaypee G. Pagaduan');
    $response->assertSee('Christine M. Lopez');
    $response->assertSee('/assets/researchers/daviddatusarmiento.png');
    $response->assertSee('/assets/researchers/jaypeepagaduan.png');
    $response->assertSee('/assets/researchers/christinelopez.png');

    // Supervision
    $response->assertSee('Dr. Zeus Vincent Mendoza');
    $response->assertSee('Imelda Binay-an, PhD');
    $response->assertSee('Dr. Daniel Juan B. Ramirez');

    // Three Institutional Documents
    $response->assertSee('Permit to Conduct Research');
    $response->assertSee('Official Deployment Letter');
    $response->assertSee('English Critique Certification');
    $response->assertSee('/assets/docs/letter-of-conduct.png');
    $response->assertSee('/assets/docs/deployment-letter.jpg');
    $response->assertSee('/assets/docs/english-critique.jpg');

    // Video gating & YouTube API
    $response->assertSee('https://www.youtube.com/iframe_api');
    $response->assertSee('video-watch-progress-wrap');
    $response->assertSee('Attended live classroom presentation / already watched beforehand?');

    // Open Graph & Social Share Preview (Facebook / Messenger)
    $response->assertSee('og:image', false);
    $response->assertSee('nexiam-tam-og-share.png', false);
    $response->assertSee('nexiam-xiam-icon-512.png', false);
    $response->assertSee('twitter:card', false);
    $response->assertSee('summary_large_image', false);

    // Unified Department Traction & Share Card in Footer
    $response->assertSee('Help your College reach its Milestone');
    $response->assertSee('/assets/xiam/emotions/v2/sad-smooth.webp');
    $response->assertSee('Share to Messenger');
    $response->assertSee('Copy Ready GC Message');
    $response->assertSee('Copy Link for GCs');
    $response->assertSee('Target Respondents: First-Year College Students Only');
    $response->assertSee('Overall Milestone Progress');
    $response->assertSee('Target: 50%');
    $response->assertSee('Target: 25%');
});

test('submitted TAM view renders post-submission celebration and pleading star-eyes Xiam card', function (): void {
    $response = $this->get(route('tam.first-years', ['submitted' => 1]));

    $response->assertSuccessful();
    $response->assertSee('Submission Received Successfully');
    $response->assertSee('Help us further by sharing this to someone first year you know');
    $response->assertSee('/assets/xiam/emotions/v2/star-eyes-smooth.webp');
    $response->assertSee('Share on Facebook');
});

test('submitting TAM answers via form post saves to isolated SQLite database without touching Postgres', function (): void {
    $initialCount = TamEvaluation::count();

    $response = $this->post(route('tam.first-years.store'), [
        'evaluator_role' => 'First-Year College Student',
        'evaluation_date' => '2026-10-20',
        'evaluator_name' => 'Juan Dela Cruz',
        'department' => 'CAS',
        'academic_program' => 'BS Information Technology',
        'video_watched_confirmed' => 'true',
        'STU-PU-01' => 5,
        'STU-PU-02' => 4,
        'STU-PU-03' => 5,
        'STU-PU-04' => 4,
        'STU-PU-05' => 5,
        'STU-PEOU-01' => 5,
        'STU-PEOU-02' => 4,
        'STU-PEOU-03' => 5,
        'STU-PEOU-04' => 4,
        'useful_features' => 'The real-time result sheet and inquiry system were intuitive.',
        'difficulties_encountered' => 'None encountered.',
        'recommendations' => 'Ready for university rollout.',
        'evaluator_signature' => 'Juan Dela Cruz',
    ]);

    $response->assertRedirect(route('tam.first-years', ['submitted' => 1]));

    expect(TamEvaluation::count())->toBe($initialCount + 1);

    /** @var TamEvaluation $record */
    $record = TamEvaluation::latest('id')->first();
    expect($record->evaluator_name)->toBe('Juan Dela Cruz');
    expect($record->department)->toBe('CAS');
    expect($record->academic_program)->toBe('BS Information Technology');
    expect($record->evaluation_date->format('Y-m-d'))->toBe('2026-10-20');
    expect($record->stu_pu_01)->toBe(5);
    expect($record->averagePu())->toBe(4.6);
    expect($record->averagePeou())->toBe(4.5);
    expect($record->overallScore())->toBe(4.56);
    expect($record->getConnectionName())->toBe('tam');
    // Ensure default application connection does not have tam_evaluations
    if (config('database.default') !== 'tam') {
        expect(Schema::hasTable('tam_evaluations'))->toBeFalse();
    }
});

test('submitting TAM answers via JSON API returns 201 created', function (): void {
    $response = $this->postJson(route('tam.first-years.store'), [
        'evaluator_role' => 'First-Year College Student',
        'video_watched_confirmed' => true,
        'STU-PU-01' => 5,
        'STU-PEOU-01' => 4,
        'useful_features' => 'Fast inquiry replies',
        'evaluator_signature' => 'Maria Santos',
    ]);

    $response->assertCreated();
    $response->assertJson([
        'success' => true,
    ]);

    $record = TamEvaluation::latest('id')->first();
    expect($record->evaluator_signature)->toBe('Maria Santos');
});

test('tam response management interface requires authentication or access key', function (): void {
    // Unauthenticated guest without key -> 403 Forbidden
    $this->get(route('tam.responses.index'))->assertForbidden();

    // Guest with valid key -> 200 OK
    $this->get(route('tam.responses.index', ['key' => 'nexiam-tam-research-2026']))
        ->assertSuccessful()
        ->assertSee('TAM Evaluation Response Management')
        ->assertSee('Department Affiliation Breakdown')
        ->assertSee('College of Arts and Sciences')
        ->assertSee('Perceived Usefulness (STU-PU)')
        ->assertSee('Perceived Ease of Use (STU-PEOU)')
        ->assertSee('276')
        ->assertSee('Surcharge Target');

    // Authenticated user -> 200 OK
    $user = User::factory()->make();
    $this->actingAs($user)
        ->get(route('staff.tam-responses.index'))
        ->assertSuccessful()
        ->assertSee('TAM Evaluation Response Management');
});

test('tam response CSV export downloads properly formatted CSV dataset', function (): void {
    $response = $this->get(route('tam.responses.export', ['key' => 'nexiam-tam-research-2026']));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Evaluation Date');
    expect($response->streamedContent())->toContain('Evaluator Name');
    expect($response->streamedContent())->toContain('Department');
    expect($response->streamedContent())->toContain('Academic Program');
    expect($response->streamedContent())->toContain('STU-PU-01');
    expect($response->streamedContent())->toContain('Mean_PU');
    expect($response->streamedContent())->toContain('Mean_PEOU');
});

test('resetting tam responses clears all records securely', function (): void {
    TamEvaluation::create([
        'evaluator_role' => 'First-Year College Student',
        'stu_pu_01' => 5,
    ]);
    expect(TamEvaluation::count())->toBeGreaterThan(0);

    // Unauthorized reset attempt without key
    $this->post(route('tam.responses.reset'))->assertForbidden();

    // Authorized reset with key
    $response = $this->post(route('tam.responses.reset'), ['key' => 'nexiam-tam-research-2026']);
    $response->assertRedirect(route('tam.responses.index', ['key' => 'nexiam-tam-research-2026']));

    expect(TamEvaluation::count())->toBe(0);
});
