<?php

namespace framework\Socket\ActionMiddlewares\Interfaces;

interface MiddlewareInterface
{
    /**
     * @param mixed $payload
     *
     * @throws Exception
     */
    public function __invoke($payload);
}
