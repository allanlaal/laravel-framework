<?php

namespace Illuminate\Console;

use Symfony\Component\Console\SignalRegistry\SignalRegistry;

class Signals
{
    /**
     * The signal registry instance.
     *
     * @var \Symfony\Component\Console\SignalRegistry\SignalRegistry
     */
    protected $registry;

    /**
     * The signal registry's previous list of handlers.
     *
     * @param array<int, array<int, callable>>|null
     */
    protected $previousHandlers;

    /**
     * Create a new Signals instance.
     *
     * @param  \Symfony\Component\Console\SignalRegistry\SignalRegistry  $registry
     * @return void
     */
    public function __construct($registry)
    {
        $this->registry = $registry;

        $this->previousHandlers = $this->getHandlers();
    }

    /**
     * Registers a new signal handler.
     *
     * @param  int  $signal
     * @param  callable  $callback
     * @return void
     */
    public function register($signal, $callback)
    {
        $this->previousHandlers[$signal] ??= $this->initializeSignal($signal);

        with($this->getHandlers(), function ($handlers) use ($signal) {
            $handlers[$signal] ??= $this->initializeSignal($signal);

            $this->setHandlers($handlers);
        });

        $this->registry->register($signal, $callback);

        with($this->getHandlers(), function ($handlers) use ($signal) {
            $lastHandlerInserted = array_pop($handlers[$signal]);
            array_unshift($handlers[$signal], $lastHandlerInserted);

            $this->setHandlers($handlers);
        });
    }

    /**
     * Unregister the current signals instance, and reverts
     * the signal's registry handlers state.
     *
     * @return array<int, array<int, callable>>
     */
    public function unregister()
    {
        $this->setHandlers($this->previousHandlers);
    }

    /**
     * Executes the given callback if "signals" are supported.
     *
     * @param  callable  $callback
     * @return void
     */
    public static function ifSupported($callback)
    {
        if (defined('SIGINT') && SignalRegistry::isSupported()) {
            $callback();
        }
    }

    /**
     * Set the registry's handlers.
     *
     * @param  array<int, array<int, callable>>  $handlers
     * @return void
     */
    protected function setHandlers($handlers)
    {
        (fn () => $this->signalHandlers = $handlers)
            ->call($this->registry);
    }

    /**
     * Get the registry's handlers.
     *
     * @return array<int, array<int, callable>>
     */
    protected function getHandlers()
    {
        return (fn () => $this->signalHandlers)
            ->call($this->registry);
    }

    /**
     * Sets the signal's default callback.
     *
     * @return void
     */
    protected function initializeSignal($signal)
    {
        $existingHandler = pcntl_signal_get_handler($signal);

        return [is_callable($existingHandler)
            ? $existingHandler
            : function ($signal) {
                if (! in_array($signal, [SIGUSR1, SIGUSR2])) {
                    exit(0);
                }
            }, ];
    }
}
