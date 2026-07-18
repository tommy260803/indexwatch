<?php

namespace App\Enums;

enum ScanRunStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Degraded = 'degraded';
    case Error = 'error';
}
