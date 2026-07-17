<?php

namespace App\Enums;

enum MissingIndexStatus: string
{
    case Candidate = 'candidate';
    case Reviewed = 'reviewed';
    case Created = 'created';
    case Dismissed = 'dismissed';
    case Stale = 'stale';
}