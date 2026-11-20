<?php

namespace Vayu\Database\Migrations;

/**
 * Base class for migrations. Migration files return an anonymous subclass:
 *
 *   return new class extends Migration {
 *       public function up(): void { ... }
 *       public function down(): void { ... }
 *   };
 */
abstract class Migration
{
    abstract public function up(): void;

    public function down(): void
    {
    }
}
