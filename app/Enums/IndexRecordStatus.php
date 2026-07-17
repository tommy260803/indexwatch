<?php

namespace App\Enums;

enum IndexRecordStatus: string
{
    case Active = 'active';
    // Se marca así cuando un escaneo posterior ya no encuentra el índice en sys.indexes,
    // en vez de borrar la fila y perder su historial de snapshots.
    case Dropped = 'dropped';
}
