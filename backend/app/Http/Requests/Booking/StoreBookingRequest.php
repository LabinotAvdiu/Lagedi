<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Models\CompanyUser;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth guard enforced by route middleware
    }

    public function rules(): array
    {
        return [
            'company_id'  => ['required', 'integer', 'exists:companies,id'],
            'service_id'  => ['required', 'integer', 'exists:services,id'],
            'employee_id' => ['nullable', 'integer', 'exists:company_user,id'],
            'date_time'   => ['required', 'date_format:Y-m-d\TH:i:s', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_time.after' => 'The booking date must be in the future.',
        ];
    }

    /**
     * Cross-company guard: `exists:*,id` only checks that the row exists,
     * not that it belongs to the target company. Without this, an attacker
     * could pass `company_id=1, service_id=77, employee_id=999` where the
     * service/employee actually belong to company 2 — creating an
     * inconsistent booking that bypasses the target salon's planning.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $companyId  = (int) $this->input('company_id');
            $serviceId  = $this->input('service_id');
            $employeeId = $this->input('employee_id');

            if ($companyId && $serviceId) {
                $serviceBelongs = Service::where('id', (int) $serviceId)
                    ->where('company_id', $companyId)
                    ->exists();
                if (! $serviceBelongs) {
                    $v->errors()->add('service_id', 'This service does not belong to the selected company.');
                }
            }

            if ($companyId && $employeeId !== null && $employeeId !== '') {
                $employeeBelongs = CompanyUser::where('id', (int) $employeeId)
                    ->where('company_id', $companyId)
                    ->exists();
                if (! $employeeBelongs) {
                    $v->errors()->add('employee_id', 'This employee does not belong to the selected company.');
                }
            }
        });
    }
}
