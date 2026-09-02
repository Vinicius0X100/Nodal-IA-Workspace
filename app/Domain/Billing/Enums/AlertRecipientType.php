<?php

namespace App\Domain\Billing\Enums;

enum AlertRecipientType: string
{
    case USER  = 'user';
    case GROUP = 'group';
}
