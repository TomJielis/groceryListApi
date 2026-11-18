<?php

namespace App\Http\Requests\Card;

use Illuminate\Foundation\Http\FormRequest;

// A loyalty card: a title and a photo of it. Its messages are Laravel's own, but for the photo being too big.
class CardStoreRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            // A data URL (photo or barcode) or a link; ~10 MB of text is a photo of about 7.5 MB
            'attachment' => 'required|string|max:10000000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachment.max' => 'De afbeelding is te groot. Kies een kleinere foto.',
        ];
    }

    public function title(): string
    {
        return $this->validated('title');
    }

    public function attachment(): string
    {
        return $this->validated('attachment');
    }
}
