<?php

namespace App\Enums;

enum AlertType: string
{
    case Fragmentation = 'fragmentation';
    case Inactive = 'inactive';
    case MissingIndex = 'missing_index';
    case DuplicateIndex = 'duplicate_index';
    case Heap = 'heap';
    case StaleStatistics = 'stale_statistics';
    case PageSplits = 'page_splits';
    case FillFactor = 'fill_factor';
}