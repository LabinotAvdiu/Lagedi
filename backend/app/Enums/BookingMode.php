<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingMode: string
{
    case EmployeeBased  = 'employee_based';
    case CapacityBased  = 'capacity_based';
}
