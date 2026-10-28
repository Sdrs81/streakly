<?php

namespace App\Support;

class EmailNormalizer
{
    // SQLite compares emails case-sensitively, so every entry point must use this one canonical form.
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
