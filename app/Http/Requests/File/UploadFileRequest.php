<?php

namespace App\Http\Requests\File;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'max:51200', // 50 MB
                // webm/ogg/m4a added for voice messages — real-world MediaRecorder
                // output varies by browser (kept in sync with FileService::$allowedMimes).
                'mimes:jpeg,jpg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,mp4,mp3,wav,mov,avi,webm,ogg,m4a',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.file'     => 'The uploaded item must be a valid file.',
            'file.max'      => 'The file may not be larger than 50MB.',
            'file.mimes'    => 'This file type is not allowed. Supported types: images, documents, spreadsheets, presentations, text files, archives, and media files.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Allow overriding max size based on file type if needed
    }
}
