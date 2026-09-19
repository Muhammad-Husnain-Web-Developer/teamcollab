<?php

namespace App\Http\Requests\Message;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ForwardMessageRequest extends FormRequest
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
            'channel_ids'   => ['nullable', 'array', 'max:10'],
            'channel_ids.*' => ['integer', 'exists:channels,id'],
            'user_ids'      => ['nullable', 'array', 'max:10'],
            'user_ids.*'    => ['integer', 'exists:mysql.users,id'],
            'comment'       => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (empty($this->input('channel_ids')) && empty($this->input('user_ids'))) {
                $validator->errors()->add('targets', 'Choose at least one channel or person to forward to.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'channel_ids.max' => 'You may not forward to more than 10 channels at once.',
            'user_ids.max'    => 'You may not forward to more than 10 people at once.',
            'comment.max'     => 'Comment may not exceed 2,000 characters.',
        ];
    }
}
