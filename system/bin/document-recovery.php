#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\DocumentRecoveryFixtures;

const RECOVERY_ROOT = '/var/www/html';
const RECOVERY_EVIDENCE = '/evidence';

function recoveryFail(string $message, int $status = 1): never
{
    fwrite(STDERR, "document-recovery: {$message}\n");
    exit($status);
}

function recoveryObserveConfiguration(array $identity): void
{
    if (! is_file(RECOVERY_ROOT.'/vendor/autoload.php') || ! is_file(RECOVERY_ROOT.'/bootstrap/app.php')) {
        recoveryFail('Laravel application files are unavailable.');
    }
    chdir(RECOVERY_ROOT);
    putenv('LARAVEL_STORAGE_PATH='.RECOVERY_ROOT.'/storage');
    $_ENV['LARAVEL_STORAGE_PATH'] = RECOVERY_ROOT.'/storage';
    $_SERVER['LARAVEL_STORAGE_PATH'] = RECOVERY_ROOT.'/storage';
    require_once RECOVERY_ROOT.'/vendor/autoload.php';
    $app = require RECOVERY_ROOT.'/bootstrap/app.php';
    $configCache = getenv('APP_CONFIG_CACHE');
    if (is_string($configCache) && is_file($configCache)) {
        recoveryFail('A cached Laravel configuration is not permitted during observational preflight.');
    }
    $app->bootstrapWith([
        LoadEnvironmentVariables::class,
        LoadConfiguration::class,
    ]);
    recoveryAssertEffectiveConfig($identity, false);
}

function recoveryBootstrap(): void
{
    if (! is_file(RECOVERY_ROOT.'/vendor/autoload.php') || ! is_file(RECOVERY_ROOT.'/bootstrap/app.php')) {
        recoveryFail('Laravel application files are unavailable.');
    }
    chdir(RECOVERY_ROOT);
    putenv('LARAVEL_STORAGE_PATH='.RECOVERY_ROOT.'/storage');
    $_ENV['LARAVEL_STORAGE_PATH'] = RECOVERY_ROOT.'/storage';
    $_SERVER['LARAVEL_STORAGE_PATH'] = RECOVERY_ROOT.'/storage';
    require_once RECOVERY_ROOT.'/vendor/autoload.php';
    $app = require RECOVERY_ROOT.'/bootstrap/app.php';
    $app->make(ConsoleKernel::class)->bootstrap();
}

function recoveryRequiredEnv(string $name): string
{
    $value = getenv($name);
    if (! is_string($value) || trim($value) === '') {
        recoveryFail("{$name} is required.");
    }

    return trim($value);
}

function recoveryRawSafety(): array
{
    $runId = recoveryRequiredEnv('DOCUMENT_RECOVERY_RUN_ID');
    if (! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{7,63}\z/', $runId)) {
        recoveryFail('DOCUMENT_RECOVERY_RUN_ID has an invalid format.');
    }
    $port = filter_var(recoveryRequiredEnv('DOCUMENT_RECOVERY_PUBLIC_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
    if ($port === false) {
        recoveryFail('DOCUMENT_RECOVERY_PUBLIC_PORT must be a valid unprivileged port.');
    }
    if (getenv('DOCUMENT_RECOVERY_SYNTHETIC_ENV') !== '1' || getenv('DOCUMENT_RECOVERY_MODE') !== '1') {
        recoveryFail('Synthetic recovery environment is not enabled.');
    }
    $syntheticEnv = @file_get_contents(RECOVERY_ROOT.'/docker/recovery-synthetic.env');
    if (! is_string($syntheticEnv) || $syntheticEnv !== "DOCUMENT_RECOVERY_SYNTHETIC_ENV=1\n") {
        recoveryFail('The synthetic recovery environment overlay is unavailable or has unexpected contents.');
    }
    $required = [
        'APP_ENV' => 'testing',
        'APP_DEBUG' => 'false',
        'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'APP_URL' => 'http://127.0.0.1:'.(string) $port,
        'APP_CONFIG_CACHE' => '/tmp/document-recovery/cache/config.php',
        'APP_EVENTS_CACHE' => '/tmp/document-recovery/cache/events.php',
        'APP_ROUTES_CACHE' => '/tmp/document-recovery/cache/routes.php',
        'APP_SERVICES_CACHE' => '/tmp/document-recovery/cache/services.php',
        'APP_PACKAGES_CACHE' => '/tmp/document-recovery/cache/packages.php',
        'VIEW_COMPILED_PATH' => '/tmp/document-recovery/views',
        'DOCUMENT_RECOVERY_PUBLIC_PORT' => (string) $port,
        'DOCUMENT_RECOVERY_EVIDENCE_PATH' => RECOVERY_EVIDENCE,
        'LARAVEL_STORAGE_PATH' => RECOVERY_ROOT.'/storage',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => RECOVERY_ROOT.'/storage/recovery.sqlite',
        'SESSION_DRIVER' => 'file',
        'SESSION_COOKIE' => 'nexiam_document_recovery_'.$runId,
        'CACHE_STORE' => 'file',
        'QUEUE_CONNECTION' => 'database',
        'QUEUE_AFTER_COMMIT' => 'true',
        'FILESYSTEM_DISK' => 'local',
        'MAIL_MAILER' => 'array',
        'BROADCAST_CONNECTION' => 'log',
        'XIAM_AI_GATEWAY' => 'fake',
        'XIAM_AI_PROVIDER' => 'fake',
        'OMR_WORKER_ENABLED' => 'false',
        'OMR_WORKER_ENDPOINT' => '',
        'OMR_WORKER_SIGNING_SECRET' => '',
        'AWS_ACCESS_KEY_ID' => '',
        'AWS_SECRET_ACCESS_KEY' => '',
        'AWS_DEFAULT_REGION' => '',
        'AWS_BUCKET' => '',
        'AWS_URL' => '',
        'AWS_ENDPOINT' => '',
        'HOME' => '/tmp/document-recovery-home',
        'XDG_CACHE_HOME' => '/tmp/document-recovery-home/cache',
        'XDG_CONFIG_HOME' => '/tmp/document-recovery-home/config',
        'XDG_DATA_HOME' => '/tmp/document-recovery-home/data',
        'TMPDIR' => '/tmp',
        'TMP' => '/tmp',
        'TEMP' => '/tmp',
    ];
    foreach ($required as $key => $expected) {
        if (getenv($key) !== $expected) {
            recoveryFail("Unsafe recovery environment value for {$key}.");
        }
    }
    if (! in_array(getenv('DB_URL'), [false, ''], true)) {
        recoveryFail('A SQLite URL may not override the private database path.');
    }
    if (getenv('DB_HOST') !== '' || getenv('DB_PORT') !== '' || getenv('DB_USERNAME') !== '' || getenv('DB_PASSWORD') !== '') {
        recoveryFail('External database credentials must be empty.');
    }
    if (is_link(RECOVERY_ROOT.'/storage/recovery.sqlite')) {
        recoveryFail('The private recovery SQLite path must not be a symlink.');
    }
    $evidenceMount = realpath(RECOVERY_EVIDENCE);
    if ($evidenceMount === false || ! is_dir($evidenceMount) || ! is_writable($evidenceMount)) {
        recoveryFail('/evidence must be an existing writable run-owned mount.');
    }
    $buildDigest = recoveryRequiredEnv('DOCUMENT_RECOVERY_BUILD_DIGEST');
    if (! preg_match('/\A[0-9a-f]{64}\z/', $buildDigest)) {
        recoveryFail('DOCUMENT_RECOVERY_BUILD_DIGEST must be a SHA-256 digest.');
    }
    $buildPath = RECOVERY_ROOT.'/public/build';
    if (! is_dir($buildPath) || is_link($buildPath) || ! is_file($buildPath.'/manifest.json')) {
        recoveryFail('The immutable run-owned public/build mount or its manifest.json is unavailable.');
    }
    $computedBuildDigest = recoveryTreeDigest($buildPath);
    if (! hash_equals($buildDigest, $computedBuildDigest)) {
        recoveryFail('The mounted public/build digest does not match DOCUMENT_RECOVERY_BUILD_DIGEST.');
    }
    $mounts = file_get_contents('/proc/self/mountinfo');
    if (! is_string($mounts)) {
        recoveryFail('Container mount boundaries cannot be verified.');
    }
    recoveryAssertMountMode($mounts, RECOVERY_ROOT, true);
    recoveryAssertMountMode($mounts, $buildPath, true);
    recoveryAssertMountMode($mounts, RECOVERY_EVIDENCE, false);
    recoveryAssertMountMode($mounts, RECOVERY_ROOT.'/storage', false);
    recoveryAssertMountMode($mounts, RECOVERY_ROOT.'/bootstrap/cache', false);
    recoveryAssertMountMode($mounts, '/tmp', false);
    $interfaces = @scandir('/sys/class/net');
    if (! is_array($interfaces) || array_values(array_diff($interfaces, ['.', '..'])) !== ['lo']) {
        recoveryFail('The recovery container is not isolated to loopback networking.');
    }
    $loVersion = @shell_exec('soffice --version 2>/dev/null');
    $popplerVersion = @shell_exec('pdftoppm -v 2>&1');
    if (! is_string($loVersion) || trim($loVersion) === '' || ! is_string($popplerVersion) || trim($popplerVersion) === '') {
        recoveryFail('LibreOffice and Poppler must both be available before initialization.');
    }

    return [
        'run_id' => $runId,
        'public_port' => $port,
        'build_digest' => $computedBuildDigest,
        'source_digest' => recoveryTreeDigest(RECOVERY_ROOT, ['storage', 'bootstrap/cache', 'node_modules', 'public/build', '.git', 'docker/jitsi/config', 'vendor/pestphp/pest/.temp'], RECOVERY_ROOT.'/docker/recovery-synthetic.env'),
        'libreoffice_version' => trim($loVersion),
        'poppler_version' => trim($popplerVersion),
    ];
}

function recoveryAssertMountMode(string $mountInfo, string $mountpoint, bool $readOnly): void
{
    $encoded = str_replace(['\\', ' '], ['\\134', '\\040'], $mountpoint);
    foreach (explode("\n", $mountInfo) as $line) {
        $fields = explode(' ', $line);
        if (($fields[4] ?? null) !== $encoded) {
            continue;
        }
        $options = explode(',', $fields[5] ?? '');
        if (in_array('ro', $options, true) === $readOnly) {
            return;
        }
        recoveryFail("Mount mode is unsafe at {$mountpoint}.");
    }
    recoveryFail("Mount boundary is missing at {$mountpoint}.");
}

function recoveryTreeDigest(string $root, array $excluded = [], ?string $environmentOverlay = null): string
{
    if (! is_dir($root) || ! is_readable($root)) {
        recoveryFail('The tree for recovery identity is unavailable or unreadable.');
    }

    $rootPath = rtrim($root, '/');
    $files = [];
    $directory = new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS);
    $sourceIdentity = $environmentOverlay !== null;
    $filter = new RecursiveCallbackFilterIterator($directory, static function (SplFileInfo $entry) use ($rootPath, $excluded, $sourceIdentity): bool {
        $relative = substr($entry->getPathname(), strlen($rootPath) + 1);
        foreach ($excluded as $prefix) {
            if ($relative === $prefix || str_starts_with($relative, $prefix.'/')) {
                return false;
            }
        }
        if ($sourceIdentity && ! $entry->isDir()) {
            $basename = basename($relative);
            $isPrivateEnvironment = ($basename === '.env' || str_starts_with($basename, '.env.'))
                && ! in_array($basename, ['.env.example', '.env.example.write'], true);
            $isDatabaseState = preg_match('/\Adatabase\/[^\/]+\.sqlite[^\/]*\z/', $relative) === 1;
            if ($isPrivateEnvironment
                || $isDatabaseState
                || $basename === 'auth.json'
                || $relative === 'docker/jitsi/.env-provision.lock') {
                return false;
            }
        }
        if ($entry->isLink()) {
            return false;
        }
        if (! is_readable($entry->getPathname())) {
            recoveryFail('An included path in the tree for recovery identity is unreadable: '.$relative);
        }

        return true;
    });
    $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::LEAVES_ONLY);

    try {
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            if (! $file->isFile()) {
                recoveryFail('A non-file path is present in the tree for recovery identity: '.$file->getPathname());
            }
            $relative = substr($file->getPathname(), strlen($rootPath) + 1);
            $digest = hash_file('sha256', $file->getPathname());
            if (! is_string($digest)) {
                recoveryFail('An included file in the tree for recovery identity could not be hashed: '.$relative);
            }
            $files[$relative] = $digest;
        }
    } catch (UnexpectedValueException $exception) {
        recoveryFail('The tree for recovery identity could not be traversed: '.$exception->getMessage());
    }
    if ($environmentOverlay !== null) {
        $overlay = @file_get_contents($environmentOverlay);
        if (! is_string($overlay)) {
            recoveryFail('The synthetic environment overlay is unavailable for source identity.');
        }
        $files['.env'] = hash('sha256', $overlay);
    }
    ksort($files, SORT_STRING);

    return hash('sha256', json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function recoveryAssertEffectiveConfig(array $identity, bool $assertConnection = true): void
{
    $expected = [
        'app.env' => 'testing',
        'app.debug' => false,
        'app.url' => 'http://127.0.0.1:'.$identity['public_port'],
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => RECOVERY_ROOT.'/storage/recovery.sqlite',
        'session.driver' => 'file',
        'session.files' => RECOVERY_ROOT.'/storage/framework/sessions',
        'session.cookie' => 'nexiam_document_recovery_'.$identity['run_id'],
        'cache.default' => 'file',
        'queue.default' => 'database',
        'queue.connections.database.after_commit' => true,
        'filesystems.default' => 'local',
        'filesystems.disks.local.root' => RECOVERY_ROOT.'/storage/app/private',
        'mail.default' => 'array',
        'xiam.ai.gateway' => 'fake',
        'xiam.ai.provider' => 'fake',
    ];
    foreach ($expected as $key => $value) {
        if (config($key) !== $value) {
            recoveryFail("Effective Laravel configuration is unsafe at {$key}.");
        }
    }
    if (! in_array(config('database.connections.sqlite.url'), [null, ''], true)) {
        recoveryFail('Effective SQLite URL must be null or empty.');
    }
    if ($assertConnection) {
        $connection = DB::connection('sqlite');
        if ($connection->getDatabaseName() !== RECOVERY_ROOT.'/storage/recovery.sqlite') {
            recoveryFail('The active SQLite connection does not use the private recovery file.');
        }
    }
}

function recoveryManifestPath(): string
{
    return RECOVERY_EVIDENCE.'/fixture.json';
}

function recoveryReadVerifiedManifest(array $identity): array
{
    $path = recoveryManifestPath();
    if (! is_file($path) || is_link($path)) {
        recoveryFail('A fixture manifest from this recovery run is required.');
    }
    try {
        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        recoveryFail('The recovery fixture manifest is invalid JSON.');
    }
    foreach (['schema_version' => 2, 'service' => 'document-recovery', 'run_id' => $identity['run_id'], 'public_port' => $identity['public_port'], 'build_digest' => $identity['build_digest'], 'source_digest' => $identity['source_digest'], 'initial_build_digest' => $identity['build_digest'], 'initial_source_digest' => $identity['source_digest']] as $key => $expected) {
        if (($manifest[$key] ?? null) !== $expected) {
            recoveryFail("Fixture manifest identity mismatch at {$key}.");
        }
    }
    if (! is_array($manifest['cases'] ?? null) || $manifest['cases'] === []) {
        recoveryFail('The fixture manifest contains no initialized cases.');
    }

    return $manifest;
}

function recoveryAssertNoticeManifestDatabase(array $manifest): void
{
    foreach (['supplemental_notice_actors', 'notice_case_urls', 'notice_state_hashes'] as $key) {
        if (! is_array($manifest[$key] ?? null) || $manifest[$key] === []) {
            recoveryFail("Notice fixture manifest is missing {$key}.");
        }
    }

    $starting = $manifest['notice_state_hashes']['starting_state'] ?? null;
    $actors = $manifest['supplemental_notice_actors'];
    if (! is_array($starting) || (int) ($starting['canonical_family_count'] ?? 0) !== 19
        || (int) ($starting['institution_id'] ?? 0) < 1 || (int) ($starting['cycle_id'] ?? 0) < 1
        || ! is_array($starting['family_ids'] ?? null) || count($starting['family_ids']) !== 19) {
        recoveryFail('Notice fixture manifest starting-state identity is incomplete.');
    }

    $institution = DB::table('institutions')->where('id', $starting['institution_id'])->value('code');
    $cycle = DB::table('academic_cycles')->where('id', $starting['cycle_id'])
        ->where('institution_id', $starting['institution_id'])->value('code');
    if ($institution !== 'SYN-INST-001' || $cycle !== $starting['cycle_code']) {
        recoveryFail('Notice fixture institution/cycle identity does not match the private database.');
    }

    $familyIds = array_values($starting['family_ids']);
    if (count(array_unique($familyIds)) !== 19) {
        recoveryFail('Notice family ids are not unique.');
    }
    foreach ($familyIds as $familyId) {
        if (! DB::table('communication_templates')->where('id', $familyId)
            ->where('institution_id', $starting['institution_id'])->exists()) {
            recoveryFail('A manifest notice family does not belong to the synthetic institution.');
        }
    }

    foreach ([
        'guidance_author' => ['guidance', 'guidance.exam.schedule'],
        'assessment_only_reader' => ['guidance', 'guidance.assessment.catalog'],
        'registrar' => ['registrar', 'registrar.setup.manage'],
        'wrong_office_guidance_capability' => ['registrar', 'guidance.exam.schedule'],
    ] as $actorKey => [$office, $capability]) {
        $actor = $actors[$actorKey] ?? null;
        if (! is_array($actor) || ! isset($actor['id'], $actor['email'], $actor['password'])) {
            recoveryFail("Notice fixture actor {$actorKey} is incomplete.");
        }
        $user = DB::table('users')->where('id', $actor['id'])->where('email', $actor['email'])->first();
        $profile = DB::table('staff_profiles')->where('user_id', $actor['id'])
            ->where('institution_id', $starting['institution_id'])->where('active_cycle_code', $starting['cycle_code'])
            ->where('office', $office)->where('status', 'active')->whereNull('deactivated_at')->first();
        $auth = DB::table('staff_authentication_states')->where('user_id', $actor['id'])
            ->whereNotNull('activation_completed_at')->whereNotNull('password_set_at')
            ->where('must_change_password', false)->first();
        $permissionId = DB::table('permissions')->where('name', $capability)->where('guard_name', 'web')->value('id');
        $hasCapability = $permissionId !== null && DB::table('model_has_permissions')->where('permission_id', $permissionId)
            ->where('model_type', User::class)->where('model_id', $actor['id'])->exists();
        if ($user === null || $profile === null || $auth === null || ! $hasCapability) {
            recoveryFail("Notice fixture actor {$actorKey} does not match its scoped active authentication contract.");
        }
    }

    foreach (['cancellation', 'permit'] as $case) {
        $initial = $manifest['notice_state_hashes'][$case] ?? null;
        if (! is_array($initial) || ($initial['schema'] ?? null) !== 'notice-content-3'
            || ($initial['renderer'] ?? null) !== 'notice-message-email-2'
            || (int) ($initial['version'] ?? 0) !== 2
            || ! preg_match('/\A[a-f0-9]{64}\z/D', (string) ($initial['content_sha256'] ?? ''))
            || ! preg_match('/\A[a-f0-9]{64}\z/D', (string) ($initial['email_sha256'] ?? ''))
            || ! preg_match('/\A[a-f0-9]{64}\z/D', (string) ($initial['portal_sha256'] ?? ''))) {
            recoveryFail("Initial notice message {$case} is not a canonical content3/renderer2 version.");
        }
        $family = DB::table('communication_templates')->where('id', $initial['family_id'])
            ->where('institution_id', $starting['institution_id'])->first();
        $version = DB::table('communication_template_versions')->where('id', $initial['version_id'])
            ->where('communication_template_id', $initial['family_id'])->where('version', 2)->first();
        if ($family === null || (int) $family->active_version < 2 || $version === null
            || $version->content_schema_version !== 'notice-content-3'
            || $version->email_renderer_version !== 'notice-message-email-2'
            || ! hash_equals((string) $version->content_sha256, $initial['content_sha256'])
            || (string) $version->document_revision_id !== (string) ($initial['document_version_id'] ?? null)) {
            recoveryFail("Initial notice message {$case} differs from its exact immutable database version.");
        }
        $emailBlueprint = json_decode((string) $version->email_blueprint, true);
        if (! is_array($emailBlueprint)) {
            recoveryFail("Initial notice message {$case} is missing its semantic email blueprint.");
        }
        $imageIds = [];
        foreach ($emailBlueprint['blocks'] ?? [] as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'image' && is_string($block['asset_id'] ?? null)) {
                $imageIds[] = $block['asset_id'];
            }
        }
        $image = $imageIds === [] ? null : DB::table('communication_template_documents')->where('id', $imageIds[0])
            ->where('communication_template_id', $initial['family_id'])->where('institution_id', $starting['institution_id'])->first();
        $privateRoot = realpath(RECOVERY_ROOT.'/storage/app/private');
        $assetPath = is_object($image) ? realpath(RECOVERY_ROOT.'/storage/app/private/'.$image->storage_path) : false;
        if ($image === null || ! is_string($privateRoot) || $assetPath === false || ! str_starts_with($assetPath, $privateRoot.'/')
            || ! hash_equals((string) $image->checksum, hash_file('sha256', $assetPath))
            || ! in_array($image->mime_type, ['image/png', 'image/jpeg'], true)) {
            recoveryFail("Initial notice message {$case} does not retain its genuine private raster asset.");
        }
    }

    $startingState = $manifest['notice_state_hashes']['starting_state'] ?? [];
    $permitDocumentId = (int) ($startingState['notice_document_version_id'] ?? 0);
    $document = DB::table('notice_document_versions')->where('id', $permitDocumentId)
        ->where('communication_template_id', $manifest['notice_state_hashes']['permit']['family_id'] ?? '')->first();
    $documentState = is_object($document) ? json_decode((string) $document->state, true) : null;
    $sourceAssetId = is_array($documentState) ? ($documentState['document_asset_id'] ?? null) : null;
    $source = is_string($sourceAssetId) ? DB::table('communication_template_documents')->where('id', $sourceAssetId)
        ->where('communication_template_id', $manifest['notice_state_hashes']['permit']['family_id'])->first() : null;
    $privateRoot = realpath(RECOVERY_ROOT.'/storage/app/private');
    $sourcePath = is_object($source) ? realpath(RECOVERY_ROOT.'/storage/app/private/'.$source->storage_path) : false;
    if ($document === null || $document->version !== 1
        || ! hash_equals((string) $document->definition_sha256, (string) ($startingState['notice_document_definition_sha256'] ?? ''))
        || ! hash_equals((string) $document->render_sha256, (string) ($startingState['notice_document_render_sha256'] ?? ''))
        || ! hash_equals((string) $source?->checksum, (string) ($startingState['word_asset_sha256'] ?? ''))
        || ! is_string($privateRoot) || $sourcePath === false || ! str_starts_with($sourcePath, $privateRoot.'/')
        || ! hash_equals((string) $source->checksum, hash_file('sha256', $sourcePath))) {
        recoveryFail('The permit document version/source starting-state hashes differ from the private database.');
    }
    $docPin = DB::table('communication_template_versions')->where('id', $manifest['notice_state_hashes']['permit']['version_id'] ?? '')
        ->value('document_revision_id');
    if ((int) $docPin !== $permitDocumentId) {
        recoveryFail('Initial permit message no longer pins the exact ready document version.');
    }
    if (DB::table('notification_deliveries')->exists()) {
        recoveryFail('Notice fixture preparation unexpectedly created occurrences or deliveries.');
    }
}

function recoveryAssertManifestDatabase(array $manifest): void
{
    $caseIds = array_column($manifest['cases'], 'id');
    sort($caseIds);
    if ($caseIds !== ['course-quota-builder', 'passing-report-word', 'result-builder', 'result-word']) {
        recoveryFail('Fixture manifest case identities are not the required four-case set.');
    }
    foreach ($manifest['cases'] as $case) {
        if (! is_array($case) || ! isset($case['id'], $case['actor']['id'], $case['actor']['email'], $case['template']['id'], $case['template']['name'], $case['surface'], $case['source_kind'], $case['source_sha256'])) {
            recoveryFail('Fixture manifest contains an incomplete database identity.');
        }
        $actor = DB::table('users')->where('id', $case['actor']['id'])->value('email');
        $templateTable = $case['surface'] === 'result' ? 'result_sheet_templates' : 'report_templates';
        if ($case['surface'] !== 'result' && ! in_array($case['surface'], ['passing_report', 'course_quota_qualifiers'], true)) {
            recoveryFail('Fixture manifest contains an unrecognized report surface.');
        }
        $template = DB::table($templateTable)->where('id', $case['template']['id'])->first();
        if ($actor !== $case['actor']['email'] || $template === null || $template->name !== $case['template']['name'] || (bool) $template->is_active !== false) {
            recoveryFail('Fixture manifest actor/template identities do not match the private database.');
        }
        if ($case['source_kind'] === 'docx') {
            $privateRoot = realpath(RECOVERY_ROOT.'/storage/app/private');
            $assetPath = $template->document_path ?? '';
            $asset = realpath(RECOVERY_ROOT.'/storage/app/private/'.$assetPath);
            if (! is_string($privateRoot) || ! is_string($assetPath) || $assetPath === '' || $asset === false || ! str_starts_with($asset, $privateRoot.'/') || ! hash_equals($case['source_sha256'], hash_file('sha256', $asset)) || ! hash_equals($case['source_sha256'], (string) ($template->document_checksum ?? ''))) {
                recoveryFail('Fixture manifest document source digest does not match the private database asset.');
            }
        } elseif ($case['source_kind'] === 'builder') {
            $blueprint = json_decode((string) ($template->builder_json ?? ''), true);
            if (! is_array($blueprint) || ! isset($case['builder_sha256']) || ! hash_equals($case['builder_sha256'], hash('sha256', json_encode($blueprint, JSON_THROW_ON_ERROR)))) {
                recoveryFail('Fixture manifest builder digest does not match the private database blueprint.');
            }
        } else {
            recoveryFail('Fixture manifest source kind is unsupported.');
        }
    }
    if (isset($manifest['operational_reports'])) {
        $reportClass = 'Tests\\Support\\DocumentRecoveryReportFixtures';
        if (! class_exists($reportClass)) {
            recoveryFail('Operational reports are present in the manifest, but the report fixture class is unavailable.');
        }
        app($reportClass)->assertManifestBlock($manifest['operational_reports'], $manifest['run_id'], $manifest['cases']);
    }

    recoveryAssertNoticeManifestDatabase($manifest);
}

function recoveryVerifyRuntime(array $identity, bool $requireManifest = true): ?array
{
    $manifest = null;
    if ($requireManifest) {
        if (is_link(RECOVERY_ROOT.'/storage/recovery.sqlite')) {
            recoveryFail('The private recovery SQLite file must not be a symlink.');
        }
        if (! is_file(RECOVERY_ROOT.'/storage/recovery.sqlite')) {
            recoveryFail('The initialized private recovery database is missing.');
        }
        $manifest = recoveryReadVerifiedManifest($identity);
    }
    recoveryBootstrap();
    recoveryAssertEffectiveConfig($identity);
    if ($manifest !== null) {
        recoveryAssertManifestDatabase($manifest);
    }

    return $manifest;
}

function recoveryPreflight(array $identity): void
{
    $database = file_exists(RECOVERY_ROOT.'/storage/recovery.sqlite');
    $manifestExists = file_exists(recoveryManifestPath());
    if ($database !== $manifestExists) {
        recoveryFail('Database and fixture manifest ownership are inconsistent.');
    }
    if ($database) {
        $manifest = recoveryReadVerifiedManifest($identity);
        $state = 'initialized';
    } else {
        $entries = array_values(array_diff(scandir(RECOVERY_EVIDENCE) ?: [], ['.', '..']));
        if ($entries !== []) {
            recoveryFail('New-run evidence directory is not empty; refusing to reuse prior evidence.');
        }
        $manifest = null;
        $state = 'safe-to-initialize';
    }
    fwrite(STDOUT, json_encode([
        'status' => $state,
        'run_id' => $identity['run_id'],
        'public_port' => $identity['public_port'],
        'source_digest' => $identity['source_digest'],
        'build_digest' => $identity['build_digest'],
        'database_exists' => $database,
        'manifest_exists' => $manifestExists,
        'case_identities' => $manifest === null ? [] : array_map(fn (array $case): array => ['case_id' => $case['id'], 'actor_id' => $case['actor']['id'], 'template_id' => $case['template']['id']], $manifest['cases']),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
}

function recoveryAtomicManifest(array $manifest): void
{
    $path = recoveryManifestPath();
    if (file_exists($path) || is_link($path)) {
        recoveryFail('Refusing to overwrite an existing fixture manifest.');
    }
    $temporary = RECOVERY_EVIDENCE.'/.fixture.'.bin2hex(random_bytes(8)).'.tmp';
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    if (file_put_contents($temporary, $json, LOCK_EX) === false || ! link($temporary, $path)) {
        @unlink($temporary);
        recoveryFail('Could not atomically write /evidence/fixture.json.');
    }
    @unlink($temporary);
}

function recoveryOption(string $name, string $default): string
{
    foreach (array_slice($GLOBALS['argv'], 2) as $argument) {
        if (str_starts_with($argument, '--'.$name.'=')) {
            return substr($argument, strlen($name) + 3);
        }
    }

    return $default;
}

$command = $argv[1] ?? '';
if (! in_array($command, ['preflight', 'initialize', 'serve', 'drain', 'inspect'], true)) {
    recoveryFail('Usage: document-recovery.php {preflight|initialize|serve|drain|inspect} [options]', 2);
}
$identity = recoveryRawSafety();

if ($command === 'preflight') {
    recoveryObserveConfiguration($identity);
    recoveryPreflight($identity);
    exit(0);
}
if ($command === 'initialize') {
    $freshIdentity = recoveryRawSafety();
    foreach (['run_id', 'public_port', 'build_digest', 'source_digest'] as $key) {
        if ($freshIdentity[$key] !== $identity[$key]) {
            recoveryFail('Recovery identity changed after the initial safety check.');
        }
    }
    recoveryObserveConfiguration($freshIdentity);
    recoveryPreflight($freshIdentity);
    if (file_exists(RECOVERY_ROOT.'/storage/recovery.sqlite') || file_exists(recoveryManifestPath())) {
        recoveryFail('Initialization is permitted only for a new run with no database or manifest.');
    }
    if (array_values(array_diff(scandir(RECOVERY_EVIDENCE) ?: [], ['.', '..'])) !== []) {
        recoveryFail('Initialization requires an empty new-run evidence directory.');
    }
    $dirs = [
        '/tmp/document-recovery/cache', '/tmp/document-recovery/views',
        '/tmp/document-recovery-home/cache', '/tmp/document-recovery-home/config',
        '/tmp/document-recovery-home/data', RECOVERY_ROOT.'/storage/app/private',
        RECOVERY_ROOT.'/storage/app/public', RECOVERY_ROOT.'/storage/app/sensitive',
        RECOVERY_ROOT.'/storage/framework/cache/data', RECOVERY_ROOT.'/storage/framework/sessions',
        RECOVERY_ROOT.'/storage/framework/views', RECOVERY_ROOT.'/storage/logs',
    ];
    foreach ($dirs as $dir) {
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            recoveryFail("Could not create private runtime directory {$dir}.");
        }
    }
    $databaseHandle = @fopen(RECOVERY_ROOT.'/storage/recovery.sqlite', 'x+b');
    if ($databaseHandle === false) {
        recoveryFail('Could not exclusively create the new private SQLite database.');
    }
    fclose($databaseHandle);
    recoveryBootstrap();
    recoveryAssertEffectiveConfig($identity);
    $status = Artisan::call('migrate', ['--force' => true]);
    if ($status !== 0) {
        recoveryFail('Additive migrations failed: '.trim(Artisan::output()));
    }
    $recoveryFixtures = app(DocumentRecoveryFixtures::class);
    $cases = $recoveryFixtures->prepare($identity['run_id'], true);
    $noticeSupplement = $recoveryFixtures->prepareNoticeSupplement($identity['run_id']);

    $reportSourceOption = recoveryOption('report-source', '');
    $operationalReports = null;
    if ($reportSourceOption !== '') {
        if (! in_array($reportSourceOption, ['docx', 'builder'], true)) {
            recoveryFail('The optional --report-source must be "docx" or "builder".', 2);
        }
        $reportClass = 'Tests\\Support\\DocumentRecoveryReportFixtures';
        if (! class_exists($reportClass)) {
            recoveryFail('The operational report fixture class is not available.');
        }
        $operationalReports = app($reportClass)->prepare($identity['run_id'], $reportSourceOption, $cases);
    }

    $manifest = [
        'schema_version' => 2,
        'service' => 'document-recovery',
        'run_id' => $identity['run_id'],
        'public_port' => $identity['public_port'],
        'generated_at' => date('c'),
        'source_digest' => $identity['source_digest'],
        'build_digest' => $identity['build_digest'],
        'initial_source_digest' => $identity['source_digest'],
        'initial_build_digest' => $identity['build_digest'],
        'libreoffice_version' => $identity['libreoffice_version'],
        'poppler_version' => $identity['poppler_version'],
        'cases' => $cases,
        'supplemental_notice_actors' => $noticeSupplement['supplemental_notice_actors'],
        'notice_case_urls' => $noticeSupplement['notice_case_urls'],
        'notice_state_hashes' => $noticeSupplement['notice_state_hashes'],
    ];
    if ($operationalReports !== null) {
        $manifest['operational_reports'] = $operationalReports;
    }
    recoveryAtomicManifest($manifest);
    fwrite(STDOUT, "Document recovery initialized (run {$identity['run_id']}).\n");
    exit(0);
}

$manifest = recoveryVerifyRuntime($identity);
if ($command === 'serve') {
    chdir(RECOVERY_ROOT.'/public');
    $router = RECOVERY_ROOT.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
    passthru('php -S 127.0.0.1:8000 -t . '.escapeshellarg($router), $status);
    exit($status);
}
if ($command === 'drain') {
    $maxJobs = filter_var(recoveryOption('max-jobs', '20'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
    $maxSeconds = filter_var(recoveryOption('max-seconds', '120'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 600]]);
    if ($maxJobs === false || $maxSeconds === false) {
        recoveryFail('max-jobs must be 0..100 and max-seconds must be 1..600.', 2);
    }
    $startedAt = microtime(true);
    $deadline = $startedAt + $maxSeconds;
    $processed = 0;
    $failed = [];
    while ($processed < $maxJobs && microtime(true) < $deadline && DB::table('jobs')->exists()) {
        $jobId = DB::table('jobs')->orderBy('id')->value('id');
        $remaining = max(1, (int) floor($deadline - microtime(true)));
        $commandLine = 'timeout --signal=KILL '.escapeshellarg((string) $remaining).'s php '.escapeshellarg(RECOVERY_ROOT.'/artisan').' queue:work --once --tries=1 --timeout='.max(1, min(30, $remaining)).' 2>&1';
        $output = [];
        exec($commandLine, $output, $status);
        if ($status !== 0) {
            $failed[] = ['job_id' => $jobId, 'status' => $status, 'message' => substr(trim(implode("\n", $output)), 0, 2000)];
            break;
        }
        $processed++;
    }
    $pending = DB::table('jobs')->orderBy('id')->limit(100)->pluck('id')->all();
    $failedJobs = DB::getSchemaBuilder()->hasTable('failed_jobs') ? DB::table('failed_jobs')->orderByDesc('id')->limit(20)->get()->map(fn ($row): array => ['id' => $row->id, 'uuid' => $row->uuid, 'failed_at' => $row->failed_at])->all() : [];
    $outcome = ['run_id' => $manifest['run_id'], 'processed' => $processed, 'failed' => $failed, 'failed_jobs' => $failedJobs, 'pending_count' => DB::table('jobs')->count(), 'pending_job_ids' => $pending, 'completed' => $failed === [] && $failedJobs === [] && $pending === [] && microtime(true) < $deadline, 'elapsed_seconds' => round(microtime(true) - $startedAt, 3), 'recorded_at' => date('c')];
    $json = json_encode($outcome, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    $tmp = RECOVERY_EVIDENCE.'/.drain.'.bin2hex(random_bytes(8)).'.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false || ! rename($tmp, RECOVERY_EVIDENCE.'/drain.json')) {
        @unlink($tmp);
        recoveryFail('Could not record bounded drain outcome.');
    }
    fwrite(STDOUT, $json);
    exit($outcome['completed'] ? 0 : 1);
}
if ($command === 'inspect') {
    $evidence = recoveryOption('evidence', RECOVERY_EVIDENCE);
    if ($evidence !== RECOVERY_EVIDENCE || ! is_dir($evidence) || ! is_writable($evidence)) {
        recoveryFail('Inspection is restricted to the exact writable /evidence mount.', 2);
    }
    $tables = array_map(static fn (string $table): string => str_contains($table, '.') ? explode('.', $table, 2)[1] : $table, DB::getSchemaBuilder()->getTableListing());
    $tableRows = static fn (string $table): array => in_array($table, $tables, true)
        ? DB::table($table)->get()->map(fn ($row): array => (array) $row)->all()
        : [];
    $summary = [
        'run_id' => $manifest['run_id'],
        'generated_at' => date('c'),
        'print_jobs' => $tableRows('print_jobs'),
        'result_sheet_templates' => $tableRows('result_sheet_templates'),
        'report_templates' => $tableRows('report_templates'),
        'jobs' => $tableRows('jobs'),
        'communication_templates' => $tableRows('communication_templates'),
        'communication_template_versions' => $tableRows('communication_template_versions'),
        'communication_template_drafts' => $tableRows('communication_template_drafts'),
        'communication_template_documents' => $tableRows('communication_template_documents'),
        'notice_document_versions' => $tableRows('notice_document_versions'),
        'notice_document_drafts' => $tableRows('notice_document_drafts'),
        'communication_template_receipts' => $tableRows('communication_template_receipts'),
        'notice_document_receipts' => $tableRows('notice_document_receipts'),
        'notification_deliveries' => $tableRows('notification_deliveries'),
        'audit_events' => $tableRows('audit_events'),
    ];
    if (isset($manifest['operational_reports'])) {
        $reportClass = 'Tests\\Support\\DocumentRecoveryReportFixtures';
        if (class_exists($reportClass) && method_exists($reportClass, 'inspection')) {
            $summary['operational_reports'] = app($reportClass)->inspection($manifest['operational_reports'], $manifest['cases']);
        }
    }
    $json = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    if (file_put_contents(RECOVERY_EVIDENCE.'/inspection.json', $json, LOCK_EX) === false) {
        recoveryFail('Could not write inspection.json.');
    }
    fwrite(STDOUT, "Document recovery inspection written to /evidence/inspection.json\n");
    exit(0);
}
recoveryFail('Unknown recovery command.', 2);
