<?php

namespace framework\Pipeline;

class FingersCrossedProcessor implements ProcessorInterface
{
    public function process($payload, callable ...$stages)
    {
        foreach ($stages as $stage) {
            $payload = $stage($payload);
        }

        return $payload;
    }
}
