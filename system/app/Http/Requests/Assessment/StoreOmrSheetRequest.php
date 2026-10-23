<?php

namespace App\Http\Requests\Assessment;

use App\Assessment\Omr\OmrClientQuad;
use App\Assessment\Omr\OmrSourceImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

use function is_array;

class StoreOmrSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'capture_mode' => ['required', 'in:manual,auto'],
            'client_capture_id' => ['required', 'uuid'],
            'page_no' => ['sometimes', 'integer', 'min:1', 'max:4'],
            'image' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:25600'],
            'qr_payload' => ['nullable', 'string', 'max:300'],
            'qr_state' => ['nullable', 'string', Rule::in(['absent', 'undecodable', 'present_invalid'])],
            'display_code' => ['nullable', 'string', 'max:24'],
            'observed_set_code' => ['nullable', 'string', 'in:A,B,C,D'],
            'manual_confirmed' => ['nullable', 'boolean'],
            'client_quad' => ['sometimes', 'array:points,width,height,orientation,input_hash,page_no'],
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
                $value = $this->input('client_quad');
                if (! is_array($value) || $validator->errors()->isNotEmpty()) {
                    return;
                }

                try {
                    $quad = OmrClientQuad::fromArray($value);
                    $file = $this->file('image');
                    if ($file === null) {
                        return;
                    }
                    $sourceImage = OmrSourceImage::fromBytes((string) $file->get());
                    $pageNo = (int) ($this->input('page_no') ?: 1);
                    if (! $quad->isBoundTo($sourceImage, $pageNo)) {
                        $validator->errors()->add('client_quad', 'The advisory corners must be bound to the uploaded image and selected page.');
                    }
                } catch (Throwable) {
                    $validator->errors()->add('client_quad', 'The advisory corners are malformed or do not match the uploaded image.');
                }
            },
        ];
    }
}
