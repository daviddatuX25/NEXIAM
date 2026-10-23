<?php

namespace App\Application\Capabilities\Handlers\Admission;

use App\Admission\Queries\ApplicantOfficeInquiryQuery;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class OfficeInquiryOptionsHandler
{
    private const CAPABILITY_ID = 'admission.office_inquiries.options@1';

    public function __construct(private readonly ApplicantOfficeInquiryQuery $query) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?User $actor = null): array
    {
        if ($input !== []) {
            throw ValidationException::withMessages(['input' => 'This tool accepts no input fields.']);
        }
        if ($actor === null) {
            throw new RuntimeException('office_inquiry_not_found');
        }

        $options = $this->query->options($actor);
        $list = $this->query->listFor($actor);
        $inquiries = [];
        foreach ($list['inquiries'] as $inquiry) {
            $inquiries[] = [
                'id' => $inquiry['id'],
                'office' => $inquiry['office'],
                'office_label' => $inquiry['office_label'],
                'status' => $inquiry['status'],
            ];
        }

        return [
            'mode' => 'read',
            'capability_id' => self::CAPABILITY_ID,
            'destinations' => $options['destinations'],
            'synthetic_only' => true,
            'inquiries' => $inquiries,
        ];
    }
}
