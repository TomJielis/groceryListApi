<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Any subset of the push settings, e.g. {"items_added": false}; the others keep their value. The messages are
 * Laravel's own (not the user's language), as the app has always received them.
 */
class NotificationSettingsRequest extends FormRequest
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
        return collect(User::NOTIFICATION_SETTINGS)
            ->mapWithKeys(fn ($types, string $setting) => [$setting => ['sometimes', 'boolean']])
            ->all();
    }

    /**
     * The settings that were sent, as true/false ("0", 1 ... pass the boolean rule)
     *
     * @return array<string, bool>
     */
    public function settings(): array
    {
        return array_map(fn ($value) => (bool) $value, $this->validated());
    }
}
