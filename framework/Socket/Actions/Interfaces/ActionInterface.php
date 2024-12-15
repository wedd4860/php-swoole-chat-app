<?php

namespace framework\Socket\Actions\Interfaces;

interface ActionInterface
{
    public function execute(array $data): mixed;
    public function send(string $data, ?int $fd = null, bool $toChannel = false): void;
    public function refreshMember(string $cId, ?array $listeners = null): bool;
    public function getName(): string;
    public function setPersistence($param): void;
    public function setFd(int $fd): void;
    public function setServer(mixed $server): void;
    public function __invoke(array $data): mixed;
}
