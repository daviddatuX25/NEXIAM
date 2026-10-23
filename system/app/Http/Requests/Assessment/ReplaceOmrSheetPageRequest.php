<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use App\Assessment\Omr\OmrClientQuad;
use App\Assessment\Omr\OmrSourceImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

final class ReplaceOmrSheetPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:25600'],
            'expected_source_page_id' => ['required', 'integer', 'min:1'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'replacement_reason' => ['required', 'string', 'min:1', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:255'],
            'crop_policy' => ['sometimes', 'string', Rule::in(['auto_document_quad', 'keep_original'])],
            'client_quad' => ['sometimes', 'array:points,width,height,orientation,input_hash,page_no'],
            'client_quad.points' => ['required_with:client_quad', 'array', 'list', 'size:4'],
            'client_quad.points.*' => ['required', 'array', 'list', 'size:2'],
            'client_quad.points.*.*' => ['required', 'numeric', 'between:0,1'],
            'client_quad.width' => ['required_with:client_quad', 'integer', 'min:1'],
            'client_quad.height' => ['required_with:client_quad', 'integer', 'min:1'],
            'client_quad.orientation' => ['required_with:client_quad', 'integer', 'between:1,8'],
            'client_quad.input_hash' => ['required_with:client_quad', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'client_quad.page_no' => ['required_with:client_quad', 'integer', 'between:1,4'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $quad = $this->input('client_quad');
                if (! is_array($quad) || $validator->errors()->isNotEmpty()) {
                    return;
                }

                try {
                    $file = $this->file('image');
                    if ($file === null) {
                        throw new \RuntimeException('The replacement image is missing.');
                    }
                    $bytes = (string) $file->get();
                    $source = OmrSourceImage::fromBytes($bytes);
                    $clientQuad = OmrClientQuad::fromArray($quad);
                    if (! $clientQuad->isBoundTo($source, (int) $this->route('page'))) {
                        $validator->errors()->add('client_quad', 'The advisory corners must be bound to the replacement image and selected page.');
                    }
                } catch (Throwable) {
                    $validator->errors()->add('client_quad', 'The advisory corners are malformed or do not match the replacement image.');
                }
            },
        ];
    }
}
