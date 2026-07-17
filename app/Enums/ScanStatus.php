<?php

namespace App\Enums;

enum ScanStatus: string
{
    case Success = 'success';
    case Error = 'error';
    case Running = 'running';
}