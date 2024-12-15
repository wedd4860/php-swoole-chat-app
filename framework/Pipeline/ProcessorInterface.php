<?php

namespace framework\Pipeline;

interface ProcessorInterface
{
    /**
     * Process the payload using multiple stages.
     *
     * @param mixed $payload
     *
     * @return mixed
     */
    public function process($payload, callable ...$stages);
}
