<?php

namespace App\Http\Requests\Message;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ScheduleMessageRequest extends FormRequest
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
            'body'          => ['nullable', 'string', 'max:10000'],
            'files'         => ['nullable', 'array', 'max:10'],
            'files.*'       => ['integer', 'exists:files,id'],
            'type'          => ['sometimes', 'string', 'in:text,voice,gif'],
            'scheduled_for' => ['required', 'date', 'after:now'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (blank($this->input('body')) && empty($this->input('files'))) {
                $validator->errors()->add('body', 'A message body or file is required.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'scheduled_for.required' => 'Pick a date and time to send this message.',
            'scheduled_for.after'    => 'Scheduled time must be in the future.',
        ];
    }
}
