<?php
declare(strict_types=1);

namespace HeyNorah\Core;

use HeyNorah\Database\Schema;

class Activator
{
    public static function activate(): void
    {
        Schema::create_tables();
        flush_rewrite_rules();
    }
}