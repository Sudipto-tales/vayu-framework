<?php

namespace Vayu\Database;

/**
 * Base seeder. Seeder files return an anonymous subclass:
 *
 *   return new class extends Seeder {
 *       public function run(): void { ... }
 *   };
 */
abstract class Seeder
{
    abstract public function run(): void;
}
