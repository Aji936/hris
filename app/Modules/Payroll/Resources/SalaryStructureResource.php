<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources;

use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\Payroll\Models\SalaryStructure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalaryStructure
 */
class SalaryStructureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'components' => $this->whenLoaded('components', fn () => PayrollComponentResource::collection($this->components)->resolve()),
            'default_allowance_total' => $this->whenLoaded(
                'components',
                fn () => $this->calculateDefaultComponentTotal(PayrollComponentType::ALLOWANCE),
            ),
            'default_deduction_total' => $this->whenLoaded(
                'components',
                fn () => $this->calculateDefaultComponentTotal(PayrollComponentType::DEDUCTION),
            ),
        ];
    }
}
