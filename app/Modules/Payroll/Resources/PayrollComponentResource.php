<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources;

use App\Modules\Payroll\Models\PayrollComponent;
use App\Modules\Payroll\Models\SalaryStructureComponent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayrollComponent
 */
class PayrollComponentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SalaryStructureComponent|null $pivot */
        $pivot = $this->pivot instanceof SalaryStructureComponent ? $this->pivot : null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'is_taxable' => $this->is_taxable,
            'is_fixed' => $this->is_fixed,
            'formula' => $this->formula,
            'is_active' => $this->is_active,
            'default_amount' => $this->when($pivot !== null, fn () => (string) $pivot?->default_amount),
        ];
    }
}
