<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum VideoOrientation: string
{
    use HasValues;

    case Horizontal = 'horizontal';

    case Vertical = 'vertical';
}
