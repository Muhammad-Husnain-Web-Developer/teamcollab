<?php

namespace App\Http\Requests\Emoji;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadEmojiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shortcode' => ['required', 'string', 'regex:/^[a-z0-9_]{2,32}$/', 'unique:workspace_emojis,shortcode'],
            // Deliberately excludes svg — everything is re-encoded to PNG on
            // upload anyway, so there's no reason to let an SVG (which can
            // carry embedded scripts) anywhere near the image processor.
            'file'      => ['required', 'file', 'max:512', 'mimes:jpeg,jpg,png,gif,webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'shortcode.regex'  => 'Shortcode may only contain lowercase letters, numbers, and underscores (2-32 characters).',
            'shortcode.unique' => 'This shortcode is already used by another emoji.',
            'file.max'         => 'Emoji image may not be larger than 512KB.',
            'file.mimes'       => 'Emoji must be a JPG, PNG, GIF, or WEBP image.',
        ];
    }
}
