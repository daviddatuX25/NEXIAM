<?php

namespace App\Admission\Support;

final class DocumentCorrectionInstructionSuggestions
{
    /** @return list<array{code:string,label:string,template:string}> */
    public static function all(): array
    {
        return [
            [
                'code' => 'present-for-inspection',
                'label' => 'Present for Registrar inspection',
                'template' => 'Please present {document_label} to the Registrar for inspection.',
            ],
            [
                'code' => 'provide-missing-document',
                'label' => 'Provide missing document',
                'template' => 'Please submit or present the required {document_label}.',
            ],
            [
                'code' => 'provide-clearer-copy',
                'label' => 'Provide clearer copy',
                'template' => 'Please provide a clearer, legible copy of {document_label}.',
            ],
            [
                'code' => 'present-original-with-copy',
                'label' => 'Bring original + photocopy',
                'template' => 'Please bring the original {document_label} along with a photocopy for Registrar verification.',
            ],
        ];
    }
}
