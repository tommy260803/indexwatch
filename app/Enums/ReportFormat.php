<?php

namespace App\Enums;

enum ReportFormat: string
{
    case Html = 'html';
    case Csv = 'csv';
    case Pdf = 'pdf';
    case Xlsx = 'xlsx';
}