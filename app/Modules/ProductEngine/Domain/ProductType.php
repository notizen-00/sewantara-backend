<?php

namespace App\Modules\ProductEngine\Domain;

enum ProductType: string
{
    case Vehicle = 'vehicle';
    case Equipment = 'equipment';
    case Accommodation = 'accommodation';
    case Space = 'space';
    case Service = 'service';
    case Membership = 'membership';
    case Package = 'package';
    case Goods = 'goods';
}
