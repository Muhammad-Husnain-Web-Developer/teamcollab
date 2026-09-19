<?php

namespace App\Http\Requests\Message;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendMessageRequest extends FormRequest
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
            // A plain text message needs a body unless it carries files; voice
            // and gif messages carry their content in files/metadata instead
            // (enforced in withValidator() below, since it depends on `type`).
            'body'       => ['nullable', 'string', 'max:10000'],
            'files'      => ['nullable', 'array', 'max:10'],
            'files.*'    => ['integer', 'exists:files,id'],
            'parent_id'  => ['nullable', 'integer', 'exists:messages,id'],
            'channel_id' => ['sometimes', 'integer'],
            // Deliberately narrow, type-specific fields rather than a generic
            // freeform `metadata` field, which would let a client spoof
            // server-controlled metadata like `forwarded_from`.
            'type'                   => ['sometimes', 'string', 'in:text,voice,gif'],
            'voice_duration_seconds' => ['sometimes', 'numeric', 'min:0', 'max:3600'],
            // A relative app path (e.g. a workspace sticker's /emojis/{id}/image
            // route) is valid here too, not just an absolute Giphy URL —
            // Laravel's built-in `url` rule rejects relative paths outright.
            'gif_url'                => ['required_if:type,gif', 'nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)/i'],
            'gif_preview_url'        => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)/i'],
            'gif_provider'           => ['nullable', 'string', 'in:giphy,tenor,workspace'],
            'gif_width'              => ['nullable', 'integer', 'min:0', 'max:5000'],
            'gif_height'             => ['nullable', 'integer', 'min:0', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('type', 'text');
            $hasFiles = ! empty($this->input('files'));

            if ($type === 'text' && ! $hasFiles && blank($this->input('body'))) {
                $validator->errors()->add('body', 'A message body is required when no files are attached.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'body.max'             => 'Message may not exceed 10,000 characters.',
            'files.max'            => 'You may not attach more than 10 files per message.',
            'files.*.exists'       => 'One or more of the selected files could not be found.',
            'gif_url.required_if'  => 'A GIF message requires a gif_url.',
            'gif_url.regex'        => 'The gif url must be a valid URL or app path.',
            'gif_preview_url.regex' => 'The gif preview url must be a valid URL or app path.',
        ];
    }
}
