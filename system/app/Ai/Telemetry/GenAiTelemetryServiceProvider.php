<?php

namespace App\Ai\Telemetry;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\ToolInvoked;

final class GenAiTelemetryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(PromptingAgent::class, [GenAiTelemetryListener::class, 'handlePromptingAgent']);
        Event::listen(AgentStreamed::class, [GenAiTelemetryListener::class, 'handleAgentStreamed']);
        Event::listen(AgentPrompted::class, [GenAiTelemetryListener::class, 'handleAgentPrompted']);
        Event::listen(InvokingTool::class, [GenAiTelemetryListener::class, 'handleInvokingTool']);
        Event::listen(ToolInvoked::class, [GenAiTelemetryListener::class, 'handleToolInvoked']);
        Event::listen(AgentFailed::class, [GenAiTelemetryListener::class, 'handleAgentFailed']);
    }
}
