<?php
namespace App\Enums;

enum UserRole: string
{
    case ADMIN        = 'admin';
    case COMMERCIAL   = 'commercial';
    case MEDIAPLANNER = 'mediaplanner';
    case TECHNIQUE    = 'technique';
}
