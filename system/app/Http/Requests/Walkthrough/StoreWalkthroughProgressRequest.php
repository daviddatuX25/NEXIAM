<?php

namespace App\Http\Requests\Walkthrough;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWalkthroughProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $surface = $this->session()->get('auth.surface');

        if (! $user instanceof User) {
            return false;
        }

        return match ($surface) {
            'applicant' => $user->applicantAccount()->exists(),
            'staff' => $user->staffProfile()->exists(),
            default => false,
        };
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scope_key' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'page_key' => ['required', 'string', 'max:160', 'regex:/^(?:[a-z][a-z0-9]*(?:-[a-z0-9]+)*|page:[A-Za-z][A-Za-z0-9]*(?:\/[A-Za-z][A-Za-z0-9]*)*|tour:[a-z][a-z0-9]*(?:-[a-z0-9]+)*)$/D'],
            'revision' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'cursor' => ['present', 'nullable', 'string', 'max:160', 'regex:/^[a-z]+(?:-[a-z]+)*$/D'],
            'disposition' => ['required', 'in:in_progress,completed,skipped'],
        ];
    }
}
