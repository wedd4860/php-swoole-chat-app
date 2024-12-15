<?php

namespace framework\Pipeline;

interface PipelineBuilderInterface
{
    /**
     * Add an stage.
     *
     * @return self
     */
    public function add(callable $stage): PipelineBuilderInterface;

    /**
     * Build a new Pipeline object.
     */
    public function build(ProcessorInterface $processor = null): PipelineInterface;
}
