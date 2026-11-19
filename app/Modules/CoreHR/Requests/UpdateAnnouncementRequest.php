<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Requests;

use App\Modules\CoreHR\Enums\AnnouncementCategory;
use App\Modules\CoreHR\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Announcement|null $announcement */
        $announcement = $this->route('announcement');

        return $announcement instanceof Announcement
            && (bool) $this->user()?->can('update', $announcement);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(AnnouncementCategory::class)],
            'content' => ['required', 'string', 'max:10000'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
