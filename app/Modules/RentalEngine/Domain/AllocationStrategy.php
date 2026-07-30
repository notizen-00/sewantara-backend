<?php

namespace App\Modules\RentalEngine\Domain;

enum AllocationStrategy: string
{
    case AutoAssign = 'auto_assign';
    case Manual = 'manual';
}
