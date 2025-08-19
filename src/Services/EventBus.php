<?php
namespace App\Services;
interface EventBus { public function publish(string $event, array $payload): void; }
class NullEventBus implements EventBus { public function publish(string $event, array $payload): void {} }
