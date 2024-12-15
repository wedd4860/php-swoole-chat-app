<?php

namespace framework\Socket\Actions;

use framework\Socket\Actions\Interfaces\ActionInterface;
use framework\Socket\Models\Interfaces\GenericPersistenceInterface;
use framework\Pipeline\PipelineBuilder;
use framework\Pipeline\PipelineInterface;
use Exception;

class ActionManager
{
    protected array $handlerMap = [];
    protected array $pipelineMap = [];

    protected array $actions = [
        AddListenerAction::class,
        BaseAction::class,
        BroadcastAction::class,
        ChannelConnectAction::class,
        ChannelDisconnectAction::class,
        FanoutAction::class,
        ClosedConnectionAction::class,
    ];

    public function __construct(
        array $extraActions = [],
    ) {
        $this->actions = array_merge($this->actions, $extraActions);
    }

    /**
     * @throws Exception
     */
    public static function make(array $actions = [], bool $fresh = false): static
    {
        $manager = new static($actions);
        return $manager->startActions($fresh);
    }

    /**
     * @param bool $fresh
     *
     * @return static
     *
     * @throws Exception
     */
    public function startActions(bool $fresh = false): static
    {
        foreach ($this->actions as $action) {
            if (is_string($action)) {
                $this->add(new $action);
                continue;
            } else if (is_array($action)) {
                $this->startActionWithMiddlewares($action);
                continue;
            }
            throw new Exception('Not valid action: ' . json_encode($action));
        }

        $this->applyFreshToActions($fresh);

        return $this;
    }

    /**
     * Add a step for the current's action middleware.
     *
     * @param string $action
     * @param Callable $middleware
     *
     * @return static
     */
    public function middleware(string $action, callable $middleware): static
    {
        if (!isset($this->pipelineMap[$action])) {
            $this->pipelineMap[$action] = [];
        }

        $this->pipelineMap[$action][] = $middleware;

        return $this;
    }

    /**
     * Check if actions already exists added.
     *
     * @param string $name
     * @return bool
     */
    public function hasAction(string $name): bool
    {
        return isset($this->handlerMap[$name]);
    }

    /**
     * Add an action to be handled. It returns a pipeline for
     * eventual middlewares to be added for each.
     *
     * @param ActionInterface $actionHandler
     *
     * @return static
     */
    public function add(ActionInterface $actionHandler): static
    {
        $actionName = $actionHandler->getName();
        $this->handlerMap[$actionName] = $actionHandler;

        return $this;
    }

    /**
     * It removes an action from the Router.
     *
     * @param ActionInterface|string $action
     * @return static
     */
    public function remove(ActionInterface|string $action): static
    {
        $actionName = is_string($action) ? $action : $action->getName();
        unset($this->handlerMap[$actionName]);

        return $this;
    }

    /**
     * Get an Action by name
     *
     * @param string $name
     * @return ActionInterface|null
     */
    public function getAction(string $name)
    {
        return $this->handlerMap[$name];
    }

    /**
     * 현재 준비된 핸들러를 기반으로 파이프라인을 준비
     *
     * @param string $action
     *
     * @return PipelineInterface
     */
    public function getPipeline(string $action): PipelineInterface
    {
        $pipelineBuilder = new PipelineBuilder;
        if (!isset($this->pipelineMap[$action])) {
            return $pipelineBuilder->build();
        }

        foreach ($this->pipelineMap[$action] as $middleware) {
            $pipelineBuilder->add($middleware);
        }

        return $pipelineBuilder->build();
    }

    /**
     * @param array $action
     * @return void
     *
     * @throws Exception
     */
    protected function startActionWithMiddlewares(array $action): void
    {
        if ($this->hasAction($action[0]::ACTION_NAME)) {
            throw new Exception('Action already added!');
        }

        $actionInstance = new $action[0];
        $this->add($actionInstance);
        for ($i = 1; $i < count($action); $i++) {
            $this->middleware($actionInstance->getName(), $action[$i]);
        }
    }

    /**
     * @param bool $fresh
     *
     * @return void
     */
    protected function applyFreshToActions(bool $fresh = false): void
    {
        array_map(function ($action) use ($fresh) {
            $action->setFresh($fresh);
        }, $this->handlerMap);
    }

    /**
     * Set persistence to the action instance.
     *
     * @param ActionInterface $action
     * @param GenericPersistenceInterface $persistence
     * @return void
     */
    public function setActionPersistence(ActionInterface $action, GenericPersistenceInterface $persistence): void
    {
        if (method_exists($action, 'setPersistence')) {
            $action->setPersistence($persistence);
        }
    }
}
