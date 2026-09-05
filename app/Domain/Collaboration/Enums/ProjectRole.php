<?php

namespace App\Domain\Collaboration\Enums;

enum ProjectRole: string
{
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case MEMBER = 'MEMBER';
}
