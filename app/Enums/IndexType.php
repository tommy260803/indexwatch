<?php

namespace App\Enums;

enum IndexType: string
{
    case Clustered = 'CLUSTERED';
    case Nonclustered = 'NONCLUSTERED';
    case ClusteredColumnstore = 'CLUSTERED COLUMNSTORE';
    case NonclusteredColumnstore = 'NONCLUSTERED COLUMNSTORE';
}
