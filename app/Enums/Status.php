<?php

namespace App\Enums;

enum Status: string {
    case PENDING = 'Pending';
    case APPROVED = 'Approved';
    case REJECTED = 'Rejected';
}