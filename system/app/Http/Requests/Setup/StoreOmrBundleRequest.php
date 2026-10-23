<?php

namespace App\Http\Requests\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

class StoreOmrBundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(CapabilityAuthorizer::class)->allows($user, CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template_code' => ['required', 'string', 'regex:/^[A-Z0-9-]{3,32}$/'],
            'template' => ['required', 'file', 'mimes:json', 'max:1024'],
            'config' => ['required', 'file', 'mimes:json', 'max:1024'],
            'manifest' => ['required', 'file', 'mimes:json', 'max:1024'],
            'references' => ['required', 'array', 'min:1', 'max:8'],
            'references.*' => ['file', 'mimes:svg', 'max:2048'],
        ];
    }
}
