<?php

namespace App\Reports\Services;

use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\DocumentTemplate\Rendering\ComponentRendererRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\DocumentTemplate\Rendering\ReportHtmlContextFiller;
use App\DocumentTemplate\Services\DynamicAssetResolutionService;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Foundation\Institution;
use App\Models\Reports\ReportTemplate;
use App\Models\User;
use App\Printing\PrintRenderer;
use App\Reports\Surfaces\StaffVoucherSlipSurface;
use App\ResultSheet\Rendering\ResultSheetCssService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VoucherSlipPrintService
{
    public function __construct(
        private readonly DynamicAssetResolutionService $dynamicAssets,
        private readonly ReportHtmlContextFiller $htmlFiller,
        private readonly ResultSheetCssService $cssService,
        private readonly PrintRenderer $printRenderer,
    ) {}

    public function renderResponse(
        User $targetUser,
        StaffActivationVoucher $voucher,
        string $token,
        Request $request,
        ?string $fallbackDisplay = null
    ): Response {
        $targetUser->loadMissing(['staffProfile.department', 'roles']);

        $surface = DocumentSurfaceRegistry::resolve('staff_voucher_slip');
        abort_if($surface === null, 404);

        $institutionId = $targetUser->staffProfile?->institution_id;
        $template = ReportTemplate::query()
            ->where('surface_key', 'staff_voucher_slip')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('institution_id')->when($institutionId !== null, fn ($query) => $query->orWhere('institution_id', $institutionId)))
            ->when($institutionId !== null, fn ($query) => $query->orderByRaw('CASE WHEN institution_id = ? THEN 0 ELSE 1 END', [$institutionId]))
            ->latest('id')
            ->first();

        $paperSize = (string) ($template?->paper_size ?? 'a4');
        $orientation = (string) ($template?->orientation ?? 'portrait');
        $compiler = new DocumentBlueprintCompiler(
            ComponentRendererRegistry::withDefaults()
        );
        $blueprint = $surface->defaultBlueprint($paperSize, $orientation);
        $defaultPage = $blueprint['page'];
        $marginMm = (float) data_get($template?->builder_json, 'page.margin_mm', $defaultPage['margin_mm']);
        $compiledBlueprint = $compiler->compile($blueprint);

        if ($template === null) {
            $rawHtml = $compiledBlueprint;
        } elseif ($template->source_kind === ReportTemplate::SOURCE_BUILDER
            && is_array($template->builder_json)
            && ! empty($template->builder_json['components'])) {
            $templateBlueprint = $template->builder_json;
            $storedPage = is_array($templateBlueprint['page'] ?? null) ? $templateBlueprint['page'] : [];
            $templateBlueprint['page'] = [
                ...$defaultPage,
                ...$storedPage,
                'paper_size' => $paperSize,
                'orientation' => $orientation,
                'width_mm' => $defaultPage['width_mm'],
                'height_mm' => $defaultPage['height_mm'],
                'margin_mm' => $marginMm,
            ];
            $rawHtml = $compiler->compile($templateBlueprint);
        } elseif ($template->source_kind === ReportTemplate::SOURCE_HTML) {
            $rawHtml = is_string($template->compiled_html) && trim($template->compiled_html) !== ''
                ? $template->compiled_html
                : (string) $template->content;
        } elseif ($template->source_kind === ReportTemplate::SOURCE_BUILDER
            && is_string($template->compiled_html) && trim($template->compiled_html) !== '') {
            $rawHtml = $template->compiled_html;
        } else {
            $rawHtml = $compiledBlueprint;
        }

        abort_if(trim($rawHtml) === '', 422, 'The staff voucher slip template is empty.');

        $qrUrl = url('/staff/activate').'#v='.$voucher->public_reference.'&t='.$token;
        $qrPng = $this->dynamicAssets->renderVerificationQr($qrUrl, 400, 4);
        $qrDataUri = 'data:image/png;base64,'.base64_encode($qrPng);

        $issuer = User::query()->find($voucher->issued_by_user_id);
        $officeName = match ($targetUser->staffProfile?->office) {
            'registrar' => 'Registrar Office',
            'guidance' => 'Guidance Office',
            'program' => 'Academic Department / Program',
            'admin' => 'Administrative & HR Office',
            default => 'University Office',
        };

        $purposeLabel = match ($voucher->purpose) {
            'mfa_reset' => 'MFA Device Reset',
            'password_reset' => 'Password Reset',
            default => 'Account Activation',
        };

        $timezone = (string) config('app.timezone', 'UTC');
        $validityBadge = $voucher->expires_at === null
            ? 'No calendar expiry · Single-use · Revocable'
            : 'Single-use · Expires: '.$voucher->expires_at->setTimezone($timezone)->format('M j, Y g:i A T');

        $fallback = $fallbackDisplay ?? 'Available via QR';

        $institution = $targetUser->staffProfile?->institution
            ?? ($institutionId !== null ? Institution::query()->find($institutionId) : null);
        $institutionName = (string) ($institution?->name ?? config('app.name', 'Ilocos Sur Polytechnic State College'));
        $campusName = (string) ($institution?->campus_name ?? '');
        $institutionBadge = trim(($institution?->short_name ?? $institutionName).($campusName !== '' ? ' · '.$campusName : ''));

        $context = [
            'institution_name' => $institutionName,
            'institution_badge' => $institutionBadge,
            'office_label' => $officeName.($targetUser->staffProfile?->department?->name ? ' · '.$targetUser->staffProfile->department->name : ''),
            'staff_name' => (string) $targetUser->name,
            'staff_email' => $targetUser->email ? (string) $targetUser->email : 'Email to be supplied during setup',
            'staff_role' => $targetUser->roles->pluck('name')->map(fn ($r) => ucwords(str_replace('_', ' ', $r)))->join(', ') ?: 'Staff Member',
            'public_reference' => (string) $voucher->public_reference,
            'fallback_code' => $fallback,
            'validity_badge' => $validityBadge,
            'purpose_label' => $purposeLabel,
            'activation_url' => url('/staff/activate'),
            'issued_at' => $voucher->created_at?->format('F j, Y g:i A') ?? now()->format('F j, Y g:i A'),
            'issued_by_name' => $issuer?->name ?? 'Campus Administrator',
            'voucher_qr' => $qrDataUri,
            'page_i' => '1',
            'page_all' => '1',
        ];

        $filledHtml = $this->htmlFiller->fill($rawHtml, $context);
        $printDocument = '<style>.voucher-qr-image{display:block;width:100%;height:100%;aspect-ratio:1/1;object-fit:contain}</style><main data-staff-voucher-print="true">'.$filledHtml.'</main>';

        if ($request->input('format') !== 'pdf') {
            $embedded = $request->boolean('embedded');
            $actions = $embedded
                ? ''
                : '<nav class="print-actions" aria-label="Print actions">'
                    .'<button type="button" onclick="window.print()">Print Slip</button>'
                    .'<button type="button" onclick="window.close()">Close</button>'
                    .'</nav>';
            $bodyClass = $embedded ? ' class="embedded-print"' : '';
            $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
                .'<meta name="viewport" content="width=device-width, initial-scale=1">'
                .'<title>Staff Access Voucher Slip</title><style>'
                .'@page{size:'.$paperSize.' '.$orientation.';margin:'.$marginMm.'mm}*{box-sizing:border-box}'
                .'html,body{margin:0;padding:0;background:#eef2f6;color:#142536;font-family:Arial,Helvetica,sans-serif}'
                .'body{padding:76px 16px 24px}.embedded-print{padding:0;background:#fff}'
                .'.print-actions{position:sticky;z-index:10;top:0;display:flex;justify-content:center;gap:10px;padding:14px;background:#173f63;color:#fff;box-shadow:0 2px 10px #14253633}'
                .'.print-actions button,.print-actions a{border:1px solid #ffffff66;border-radius:6px;background:#fff;color:#173f63;padding:10px 14px;font:600 14px Arial,Helvetica,sans-serif;text-decoration:none;cursor:pointer}'
                .'.print-document{width:'.$defaultPage['width_mm'].'mm;max-width:100%;margin:0 auto;background:#fff;box-shadow:0 8px 30px #14253622;padding:12mm;overflow:visible}'
                .'@media print{html,body{background:#fff}.print-actions{display:none!important}body{padding:0}.print-document{width:auto;max-width:none;margin:0;padding:0;box-shadow:none;overflow:visible;zoom:1!important}}'
                .'@media screen and (max-width:480px){body{padding:0 8px 16px}.print-document{width:'.$defaultPage['width_mm'].'mm;max-width:none;padding:12mm;overflow:visible}.print-actions{gap:6px;padding:10px 6px}.print-actions button,.print-actions a{padding:9px 8px;font-size:12px}}'
                .'</style></head><body'.$bodyClass.'>'.$actions
                .'<div class="print-document">'.$printDocument.'</div>'
                .'<script>const sheet=document.querySelector(".print-document");const fitSheet=()=>{sheet.style.zoom=matchMedia("(max-width:480px)").matches?String(Math.max(.1,Math.min(1,(document.documentElement.clientWidth-16)/sheet.offsetWidth))):"1"};fitSheet();addEventListener("resize",fitSheet);</script></body></html>';

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        $wrappedHtml = $this->cssService->wrapForPdf($printDocument, $paperSize, $orientation, $marginMm);

        $pdfBytes = $this->printRenderer->render(
            $wrappedHtml,
            ['format' => $paperSize, 'orientation' => $orientation]
        );

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="NEXIAM-Staff-Access-Voucher-'.$voucher->public_reference.'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @param  list<array{user: User, voucher: StaffActivationVoucher, token: string, fallback: string}>  $cards
     */
    public function renderBatchResponse(array $cards, Request $request, string $format = 'html', string $layout = 'grouped'): Response
    {
        $surface = DocumentSurfaceRegistry::resolve('staff_voucher_slip');
        abort_unless($surface instanceof StaffVoucherSlipSurface, 404);
        if ($layout === 'individual') {
            return $this->renderIndividualBatchResponse($cards, $format);
        }
        usort($cards, static function (array $left, array $right): int {
            $leftProfile = $left['user']->staffProfile;
            $rightProfile = $right['user']->staffProfile;

            return [
                $leftProfile?->office ?? '',
                $leftProfile?->department_id ?? 0,
                implode(',', $leftProfile?->program_codes ?? []),
                $left['user']->name,
            ] <=> [
                $rightProfile?->office ?? '',
                $rightProfile?->department_id ?? 0,
                implode(',', $rightProfile?->program_codes ?? []),
                $right['user']->name,
            ];
        });

        $institutionId = $cards[0]['user']->staffProfile?->institution_id;
        $template = ReportTemplate::query()
            ->where('surface_key', 'staff_voucher_slip')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('institution_id')->when($institutionId !== null, fn ($query) => $query->orWhere('institution_id', $institutionId)))
            ->when($institutionId !== null, fn ($query) => $query->orderByRaw('CASE WHEN institution_id = ? THEN 0 ELSE 1 END', [$institutionId]))
            ->latest('id')
            ->first();
        $paperSize = (string) ($template?->paper_size ?? 'a4');
        $orientation = (string) ($template?->orientation ?? 'portrait');
        $defaultPage = $surface->groupedGridBlueprint($paperSize, $orientation)['page'];
        $marginMm = (float) data_get($template?->builder_json, 'page.margin_mm', $defaultPage['margin_mm']);
        $compiler = new DocumentBlueprintCompiler(ComponentRendererRegistry::withDefaults());
        $groups = [];
        foreach ($cards as $card) {
            $profile = $card['user']->staffProfile;
            $key = implode('|', [
                $profile?->office ?? '',
                (string) ($profile?->department_id ?? ''),
                implode(',', $profile?->program_codes ?? []),
            ]);
            $groups[$key]['label'] = implode(' · ', array_filter([
                match ($profile?->office) {
                    'registrar' => 'Registrar Office',
                    'guidance' => 'Guidance Office',
                    'program' => 'Academic Department / Program',
                    'admin' => 'Administrative & HR Office',
                    default => 'University Office',
                },
                $profile?->department?->name,
                implode(', ', $profile?->program_codes ?? []),
            ]));
            $groups[$key]['cards'][] = $card;
        }

        $timezone = (string) config('app.timezone', 'UTC');
        $markup = '';
        foreach ($groups as $group) {
            foreach (array_chunk($group['cards'], 4) as $pageCards) {
                $blueprint = $surface->groupedGridBlueprint($paperSize, $orientation, count($pageCards), $marginMm);
                $blueprint['page']['margin_mm'] = $marginMm;
                $blueprint['components'][0]['w'] = (float) $blueprint['page']['width_mm'] - ($marginMm * 2);
                $compiled = $compiler->compile($blueprint);
                $compiled = preg_replace_callback(
                    '/<span class="voucher-qr" data-qr="(\{\{card_\d+_qr\}\})"><\/span>/',
                    static fn (array $matches): string => '<img class="voucher-qr" alt="Staff voucher QR code" src="'.$matches[1].'">',
                    $compiled,
                ) ?? $compiled;
                $context = [];
                foreach ($pageCards as $index => $card) {
                    $user = $card['user'];
                    $voucher = $card['voucher'];
                    $profile = $user->staffProfile;
                    $qrUrl = url('/staff/activate').'#v='.$voucher->public_reference.'&t='.$card['token'];
                    $qr = $this->dynamicAssets->renderVerificationQr($qrUrl, 320, 4);
                    $roles = $user->roles->pluck('name')->map(fn (string $role) => ucwords(str_replace('_', ' ', $role)))->join(', ');
                    $institution = $card['user']->staffProfile?->institution
                        ?? ($institutionId !== null ? Institution::query()->find($institutionId) : null);
                    $institutionName = (string) ($institution?->name ?? config('app.name', 'Ilocos Sur Polytechnic State College'));
                    $campusName = (string) ($institution?->campus_name ?? '');
                    $institutionBadge = trim(($institution?->short_name ?? $institutionName).($campusName !== '' ? ' · '.$campusName : ''));
                    $context += [
                        'card_'.$index.'_institution' => $institutionBadge,
                        'card_'.$index.'_institution_name' => $institutionName,
                        'card_'.$index.'_purpose' => match ($voucher->purpose) {
                            'password_reset' => 'Password recovery',
                            'mfa_reset' => 'MFA recovery',
                            default => 'Account activation',
                        },
                        'card_'.$index.'_name' => (string) $user->name,
                        'card_'.$index.'_email' => $user->email ?: 'Email to be supplied during setup',
                        'card_'.$index.'_office' => trim(($profile?->office ?? 'staff').' '.($profile?->department?->name ?? '')),
                        'card_'.$index.'_role' => $roles ?: 'Staff member',
                        'card_'.$index.'_qr' => 'data:image/png;base64,'.base64_encode($qr),
                        'card_'.$index.'_reference' => (string) $voucher->public_reference,
                        'card_'.$index.'_fallback' => $card['fallback'],
                        'card_'.$index.'_expiry' => $voucher->expires_at === null
                            ? 'No calendar expiry'
                            : $voucher->expires_at->setTimezone($timezone)->format('M j, Y g:i A T (e)'),
                    ];
                }
                $markup .= '<section class="voucher-group"><h1>'.e($group['label']).'</h1>'.$this->htmlFiller->fill($compiled, $context).'</section>';
            }
        }

        $printActions = $format === 'pdf'
            ? ''
            : '<nav class="print-actions"><button type="button" onclick="window.print()">Print grouped slips</button></nav>';
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Staff Access Voucher Grid</title><style>'
            .'@page{size:'.$paperSize.' '.$orientation.';margin:'.$marginMm.'mm}*{box-sizing:border-box}html,body{margin:0;padding:0;background:#fff;color:#142536;font-family:Arial,Helvetica,sans-serif}'
            .'.voucher-group{page-break-inside:avoid}.voucher-group + .voucher-group{page-break-before:always}.voucher-group h1{font-size:10pt;margin:0 0 2mm;padding-bottom:1mm;border-bottom:1px solid #94a3b8}'
            .'.voucher-grid{width:100%;border-collapse:collapse;table-layout:fixed;color:#182a3a}'
            .'.voucher-grid tr{page-break-inside:avoid}.voucher-grid td{width:50%;padding:2mm;vertical-align:top}'
            .'.voucher-card{min-height:57mm;overflow:hidden;border:1px dashed #64748b;padding:3mm}'
            .'.voucher-heading,.voucher-footer{font-size:6.5pt;line-height:1.25}.voucher-heading{border-bottom:1px solid #173f63;padding-bottom:1mm}'
            .'.card-main{width:100%;margin-top:2mm}.identity{display:inline-block;width:62%;vertical-align:top;font-size:7pt;overflow-wrap:anywhere}.identity span{display:block;margin-top:1mm}.identity strong{font-size:9pt}'
            .'.voucher-qr{display:inline-block;width:27mm;height:27mm;margin-left:2mm;padding:2mm;background:#fff;vertical-align:top}'
            .'.credentials{width:100%;margin-top:2mm;font-size:6.5pt;font-family:monospace}.voucher-footer{margin-top:1.5mm;border-top:1px solid #cbd5e1;padding-top:1mm}'
            .'@media screen{body{padding:16px}.voucher-group{margin:0 auto 24px;max-width:210mm}.voucher-group h1{font-size:14pt}.print-actions{position:sticky;top:0;padding:10px;text-align:right;background:#fff}.print-actions button{padding:9px 14px}}@media print{.print-actions{display:none}}'
            .'</style></head><body>'.$printActions.$markup.'</body></html>';
        if ($format === 'pdf') {
            $pdf = $this->printRenderer->render(
                $html,
                ['format' => $paperSize, 'orientation' => $orientation],
            );

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="NEXIAM-Staff-Access-Voucher-Grid.pdf"',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @param  list<array{user: User, voucher: StaffActivationVoucher, token: string, fallback: string}>  $cards
     */
    private function renderIndividualBatchResponse(array $cards, string $format): Response
    {
        $institutionId = $cards[0]['user']->staffProfile?->institution_id;
        $template = ReportTemplate::query()
            ->where('surface_key', 'staff_voucher_slip')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('institution_id')->when($institutionId !== null, fn ($query) => $query->orWhere('institution_id', $institutionId)))
            ->when($institutionId !== null, fn ($query) => $query->orderByRaw('CASE WHEN institution_id = ? THEN 0 ELSE 1 END', [$institutionId]))
            ->latest('id')
            ->first();
        $surface = DocumentSurfaceRegistry::resolve('staff_voucher_slip');
        abort_unless($surface instanceof StaffVoucherSlipSurface, 404);
        $page = $surface->defaultBlueprint((string) ($template?->paper_size ?? 'a4'), (string) ($template?->orientation ?? 'portrait'))['page'];
        $paperSize = (string) ($template?->paper_size ?? 'a4');
        $orientation = (string) ($template?->orientation ?? 'portrait');
        $marginMm = (float) data_get($template?->builder_json, 'page.margin_mm', $page['margin_mm']);
        $markup = '';
        foreach ($cards as $card) {
            $singleRequest = Request::create('/', 'POST', ['embedded' => true, 'format' => 'html']);
            $single = $this->renderResponse($card['user'], $card['voucher'], $card['token'], $singleRequest, $card['fallback']);
            preg_match('/<main data-staff-voucher-print="true">.*?<\/main>/s', (string) $single->getContent(), $match);
            abort_if(! isset($match[0]), 422, 'An individual voucher template could not be rendered.');
            $markup .= '<section class="individual-voucher">'.$match[0].'</section>';
        }

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Individual Staff Vouchers</title><style>'
            .'@page{size:'.$paperSize.' '.$orientation.';margin:'.$marginMm.'mm}*{box-sizing:border-box}html,body{margin:0;padding:0;background:#fff;color:#142536;font-family:Arial,Helvetica,sans-serif}'
            .'.individual-voucher{break-after:page;page-break-after:always}.print-actions{position:sticky;top:0;padding:10px;text-align:right;background:#fff}.print-actions button{padding:9px 14px}@media print{.print-actions{display:none}}'
            .'</style></head><body><nav class="print-actions"><button type="button" onclick="window.print()">Print individual slips</button></nav>'.$markup.'</body></html>';
        if ($format === 'pdf') {
            $pdf = $this->printRenderer->render(
                $this->cssService->wrapForPdf($html, $paperSize, $orientation, $marginMm),
                ['format' => $paperSize, 'orientation' => $orientation],
            );

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="NEXIAM-Staff-Individual-Vouchers.pdf"',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
