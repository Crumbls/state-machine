<?php

namespace Crumbls\StateMachine\Pipeline;

use Crumbls\StateMachine\Http\StateTransitionRequest;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Container\Container;

class StateMachinePipeline
{
    protected Pipeline $pipeline;

    public function __construct()
    {
        $this->pipeline = new Pipeline(Container::getInstance());
    }

    public function send(StateTransitionRequest $request): self
    {
        $this->pipeline->send($request);
        return $this;
    }

    /**
     * @param array<string> $middleware
     */
    public function through(array $middleware): self
    {
        $this->pipeline->through($middleware);
        return $this;
    }

    public function then(\Closure $destination): mixed
    {
        return $this->pipeline->then($destination);
    }
}