<?php

namespace framework\Pipeline;

interface PipelineInterface extends StageInterface
{
    /**
     * Create a new pipeline with an appended stage.
     *
     * @return static
     */
    public function pipe(callable $operation): PipelineInterface;
}
