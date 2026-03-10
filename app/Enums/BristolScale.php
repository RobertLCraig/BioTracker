<?php

namespace App\Enums;

enum BristolScale: int
{
    case Type1 = 1;
    case Type2 = 2;
    case Type3 = 3;
    case Type4 = 4;
    case Type5 = 5;
    case Type6 = 6;
    case Type7 = 7;

    public function label(): string
    {
        return match ($this) {
            self::Type1 => 'Separate hard lumps, like nuts (severe constipation)',
            self::Type2 => 'Sausage-shaped but lumpy (mild constipation)',
            self::Type3 => 'Like a sausage but with cracks on the surface (normal)',
            self::Type4 => 'Like a sausage or snake, smooth and soft (ideal)',
            self::Type5 => 'Soft blobs with clear-cut edges (lacking fiber)',
            self::Type6 => 'Fluffy pieces with ragged edges, mushy (mild diarrhea)',
            self::Type7 => 'Watery, no solid pieces, entirely liquid (severe diarrhea)',
        };
    }
}
