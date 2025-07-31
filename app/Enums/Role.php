<?php

namespace App\Enums;

enum Role : string {
    case MEMBER = 'Member';
    case MODERATOR = 'Moderator';
    case ADMIN = 'Admin';
}