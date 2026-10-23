<?php

namespace App\Http\Requests\Setup;

use App\Assessment\Omr\BookletAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

final class SetBookletSetsModeRequest extends FormRequest
{
    public function authorize(BookletAuthorization $authorization): bool
    {
        $actor = $this->user();
        if ($actor === null) {
            return false;
        }

        try {
            $authorization->assertManage($actor);
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'allow_multiple_sets' => ['required', 'boolean'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'confirm_discard_alternates' => ['required', 'boolean'],
        ];
    }
}
