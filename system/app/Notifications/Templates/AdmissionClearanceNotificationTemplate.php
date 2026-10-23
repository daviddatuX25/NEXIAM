<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

/**
 * Official admission clearance notification template.
 *
 * Truthful boundary notice: confirms that the applicant's admission clearance
 * and prerequisite document verification have been confirmed by the Registrar,
 * and instructs the applicant to receive their physical enrollment sheet.
 *
 * Explicitly disclaims that admission clearance constitutes university
 * matriculation, course registration, or fee payment, which are completed
 * directly with the university outside the NEXIAM admission system.
 */
final class AdmissionClearanceNotificationTemplate
{
    public const EVENT_KEY = 'admission.clearance.confirmed.v1';

    public const TEMPLATE_KEY = 'admission.clearance_confirmed';

    public const SLUG = 'clearance-confirmed';

    public const TITLE = 'Admission Clearance Confirmed';

    public const CONFIRMATION_STATEMENT = 'Your admission clearance has been confirmed by the Registrar. Please proceed to receive your physical enrollment sheet for university matriculation.';

    public const ENROLLMENT_DISCLAIMER = 'Final subject registration and enrollment fees are completed directly with the university.';

    /**
     * Get the truthful notification message combining confirmation and the university enrollment boundary note.
     */
    public static function message(): string
    {
        return self::CONFIRMATION_STATEMENT.' '.self::ENROLLMENT_DISCLAIMER;
    }

    /**
     * Get the in-app notification title.
     */
    public static function inAppTitle(): string
    {
        return self::TITLE;
    }

    /**
     * Get the in-app notification body.
     */
    public static function inAppBody(): string
    {
        return self::message();
    }

    /**
     * Get the default email subject line.
     */
    public static function defaultEmailSubject(?string $applicationNo = null): string
    {
        if ($applicationNo !== null && trim($applicationNo) !== '') {
            return "Admission Clearance Confirmed — {$applicationNo}";
        }

        return 'Admission Clearance Confirmed — {{application_no}}';
    }

    /**
     * Get the default HTML email body with tokens or substituted values.
     *
     * @param  array<string, string>  $tokens
     */
    public static function defaultEmailBody(array $tokens = []): string
    {
        $name = $tokens['applicant_name'] ?? '{{applicant_name}}';
        $program = $tokens['preferred_program'] ?? '{{preferred_program}}';
        $institution = $tokens['institution_name'] ?? '{{institution_name}}';

        return sprintf(
            '<p>Dear <strong>%s</strong>,</p>'
            .'<p>%s</p>'
            .'<p><strong>Cleared Program:</strong> %s<br><strong>Institution:</strong> %s</p>'
            .'<p style="color: #4b5563; font-size: 0.875rem;"><em>%s</em></p>',
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(self::CONFIRMATION_STATEMENT, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($program, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($institution, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(self::ENROLLMENT_DISCLAIMER, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Definition array compatible with CommunicationTemplateRegistry slots.
     *
     * @return array{
     *     event_key: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     default_email_subject: string,
     *     default_email_body: string,
     *     default_in_app_title: string,
     *     default_in_app_body: string,
     *     default_action_label: string,
     *     default_action_route: string,
     *     channels: list<string>,
     *     tokens: list<array{token: string, label: string, sample: string}>
     * }
     */
    public static function slotDefinition(): array
    {
        return [
            'event_key' => self::EVENT_KEY,
            'slug' => self::SLUG,
            'name' => self::TITLE,
            'description' => 'Sent to applicants when their admission clearance and intake documents are confirmed by the Registrar.',
            'default_email_subject' => self::defaultEmailSubject(),
            'default_email_body' => self::defaultEmailBody(),
            'default_in_app_title' => self::inAppTitle(),
            'default_in_app_body' => self::inAppBody(),
            'default_action_label' => 'View Clearance Details',
            'default_action_route' => 'applicant.admission-clearance',
            'channels' => ['in_app', 'mail'],
            'tokens' => [
                ['token' => '{{applicant_name}}', 'label' => 'Applicant Name', 'sample' => 'Maria Santos'],
                ['token' => '{{application_no}}', 'label' => 'Application Reference Code', 'sample' => 'AY26-00812'],
                ['token' => '{{preferred_program}}', 'label' => 'Cleared Program', 'sample' => 'BS Computer Science'],
                ['token' => '{{institution_name}}', 'label' => 'Institution Name', 'sample' => 'Central State University'],
                ['token' => '{{portal_url}}', 'label' => 'Portal URL', 'sample' => 'http://localhost:18000/applicant/login'],
            ],
        ];
    }

    /**
     * Render template contents given context.
     *
     * @param  array<string, mixed>  $context
     * @return array{
     *     title: string,
     *     statement: string,
     *     disclaimer: string,
     *     body: string,
     *     email_subject: string,
     *     email_body: string
     * }
     */
    public static function render(array $context = []): array
    {
        $applicationNo = isset($context['application_no']) && is_string($context['application_no'])
            ? $context['application_no']
            : null;

        $tokens = [];
        foreach (['applicant_name', 'preferred_program', 'institution_name'] as $key) {
            if (isset($context[$key]) && is_string($context[$key])) {
                $tokens[$key] = $context[$key];
            }
        }

        return [
            'title' => self::inAppTitle(),
            'statement' => self::CONFIRMATION_STATEMENT,
            'disclaimer' => self::ENROLLMENT_DISCLAIMER,
            'body' => self::message(),
            'email_subject' => self::defaultEmailSubject($applicationNo),
            'email_body' => self::defaultEmailBody($tokens),
        ];
    }
}
