<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Project;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AuditService::class);
    }

    public function test_logs_event_with_authenticated_user(): void
    {
        $user = User::factory()->create();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->headers->set('User-Agent', 'Mozilla/5.0 TestBrowser');
        $request->headers->set('X-Request-Id', 'req-abc-123');

        $this->service->log(
            request: $request,
            eventType: 'project.viewed',
            entityType: 'Project',
            entityId: 42,
            entityUuid: 'some-uuid-value',
            payload: ['key' => 'value'],
        );

        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event_type' => 'project.viewed',
            'entity_type' => 'Project',
            'entity_id' => 42,
            'entity_uuid' => 'some-uuid-value',
            'ip' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 TestBrowser',
            'request_id' => 'req-abc-123',
        ]);
    }

    public function test_logs_event_without_user(): void
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => null);

        $this->service->log(
            request: $request,
            eventType: 'public.action',
        );

        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => null,
            'event_type' => 'public.action',
        ]);
    }

    public function test_logs_event_with_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_user_id' => $user->id]);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->service->log(
            request: $request,
            eventType: 'project.created',
            project: $project,
        );

        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event_type' => 'project.created',
            'project_id' => $project->id,
        ]);
    }

    public function test_truncates_long_user_agent(): void
    {
        $longUserAgent = str_repeat('A', 600);

        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', $longUserAgent);

        $this->service->log(
            request: $request,
            eventType: 'test.event',
        );

        $event = \App\Models\AuditEvent::query()->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertSame(512, strlen((string) $event->user_agent));
        $this->assertSame(str_repeat('A', 512), $event->user_agent);
    }

    public function test_captures_request_id_header(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Request-Id', 'trace-id-xyz-789');

        $this->service->log(
            request: $request,
            eventType: 'test.event',
        );

        $this->assertDatabaseHas('audit_events', [
            'request_id' => 'trace-id-xyz-789',
        ]);
    }

    public function test_ignores_empty_request_id(): void
    {
        $request = Request::create('/test', 'GET');
        // No X-Request-Id header set

        $this->service->log(
            request: $request,
            eventType: 'test.event',
        );

        $event = \App\Models\AuditEvent::query()->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertNull($event->request_id);
    }

    public function test_stores_payload_as_json(): void
    {
        $payload = [
            'action' => 'upload',
            'file' => 'document.pdf',
            'size' => 12345,
            'tags' => ['irb', 'consent'],
        ];

        $request = Request::create('/test', 'GET');

        $this->service->log(
            request: $request,
            eventType: 'document.uploaded',
            payload: $payload,
        );

        $event = \App\Models\AuditEvent::query()->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertIsArray($event->payload);
        $this->assertSame('upload', $event->payload['action']);
        $this->assertSame('document.pdf', $event->payload['file']);
        $this->assertSame(12345, $event->payload['size']);
        $this->assertSame(['irb', 'consent'], $event->payload['tags']);
    }
}
