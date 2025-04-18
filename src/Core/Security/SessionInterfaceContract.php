<?php
interface SessionInterface
   {
       public function start(): void;
       public function regenerate(bool $deleteOldSession = true): void;
       public function destroy(): void;
       public function set(string $key, mixed $value): void;
       public function get(string $key, mixed $default = null): mixed;
       public function remove(string $key): void;
       public function has(string $key): bool;
       // weitere Methoden...
   }