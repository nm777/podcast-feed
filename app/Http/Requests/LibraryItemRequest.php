<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LibraryItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'file' => 'required_without:url|prohibits:url|file|mimes:mp3,mp4,m4a,wav,ogg|max:512000',
            'url' => [
                'required_without:file',
                'prohibits:file',
                'url',
                'max:2048',
                'regex:/\.(mp3|mp4|m4a|wav|ogg)(\?.*)?$/i',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'file.required_without' => 'Please provide either a file or a URL.',
            'url.required_without' => 'Please provide either a URL or a file.',
            'file.prohibits' => 'You cannot provide both a file and a URL.',
            'url.prohibits' => 'You cannot provide both a URL and a file.',
            'url.regex' => 'The URL must point to a direct audio or video file (MP3, MP4, M4A, WAV, OGG).',
            'file.mimes' => 'The file must be an audio or video file (MP3, MP4, M4A, WAV, OGG).',
        ];
    }
}
