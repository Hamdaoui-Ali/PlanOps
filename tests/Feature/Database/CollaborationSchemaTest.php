<?php

use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Enums\ProjectRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function collaborationIndex(string $table, string $name): array
{
    return collect(Schema::getIndexes($table))->firstWhere('name', $name) ?? [];
}

function collaborationColumn(string $table, string $name): array
{
    return collect(Schema::getColumns($table))->firstWhere('name', $name) ?? [];
}

function collaborationUsesPostgres(): bool
{
    return Schema::getConnection()->getDriverName() === 'pgsql';
}

test('collaboration roles expose only the authority values', function (): void {
    expect(ProjectRole::cases())->toHaveCount(3)
        ->and(array_column(ProjectRole::cases(), 'value'))->toBe(['OWNER', 'ADMIN', 'MEMBER']);
});

test('project event types expose the authority values', function (): void {
    expect(array_column(ProjectEventType::cases(), 'value'))->toBe([
        'INVITATION_CREATED',
        'INVITATION_ACCEPTED',
        'INVITATION_REVOKED',
        'INVITATION_RESENT',
        'MEMBER_REMOVED',
        'MEMBER_ROLE_CHANGED',
        'OWNERSHIP_TRANSFERRED',
    ]);
});

test('collaboration tables expose the lifecycle columns', function (): void {
    foreach ([
        'project_memberships' => [
            'project_id', 'user_id', 'role', 'joined_at', 'removed_at',
            'removed_by_user_id', 'created_at', 'updated_at',
        ],
        'project_invitations' => [
            'project_id', 'email', 'normalized_email', 'role', 'invited_by_user_id',
            'token_hash', 'expires_at', 'accepted_at', 'revoked_at', 'last_sent_at',
            'created_at', 'updated_at',
        ],
        'project_events' => [
            'project_id', 'actor_user_id', 'subject_user_id', 'event_type',
            'metadata', 'created_at',
        ],
    ] as $table => $columns) {
        expect(Schema::hasTable($table))->toBeTrue("{$table} is required");

        foreach ($columns as $column) {
            expect(Schema::hasColumn($table, $column))->toBeTrue("{$table}.{$column} is required");
        }
    }
});

test('collaboration indexes protect active lifecycle records', function (): void {
    expect(collaborationIndex('project_memberships', 'project_memberships_project_id_user_id_unique')['unique'] ?? false)->toBeTrue()
        ->and(collaborationIndex('project_memberships', 'project_memberships_project_id_role_index'))->not->toBeEmpty()
        ->and(collaborationIndex('project_memberships', 'project_memberships_project_id_removed_at_index'))->not->toBeEmpty()
        ->and(collaborationIndex('project_invitations', 'project_invitations_token_hash_unique')['unique'] ?? false)->toBeTrue()
        ->and(collaborationIndex('project_events', 'project_events_project_id_created_at_index'))->not->toBeEmpty();

    if (collaborationUsesPostgres()) {
        expect(collaborationIndex('project_memberships', 'project_memberships_one_active_owner_unique')['unique'] ?? false)->toBeTrue()
            ->and(collaborationIndex('project_invitations', 'project_invitations_pending_email_unique')['unique'] ?? false)->toBeTrue();
    }
});

test('historical collaboration fields are nullable and use the documented types', function (): void {
    foreach ([
        ['project_memberships', 'removed_at'],
        ['project_memberships', 'removed_by_user_id'],
        ['project_invitations', 'accepted_at'],
        ['project_invitations', 'revoked_at'],
        ['project_invitations', 'last_sent_at'],
        ['project_events', 'actor_user_id'],
        ['project_events', 'subject_user_id'],
        ['project_events', 'metadata'],
    ] as [$table, $column]) {
        expect(collaborationColumn($table, $column)['nullable'] ?? null)->toBeTrue("{$table}.{$column} must be nullable");
    }

    if (collaborationUsesPostgres()) {
        expect(collaborationColumn('project_events', 'metadata')['type'] ?? null)->toBe('jsonb')
            ->and(collaborationColumn('project_events', 'created_at')['type'] ?? null)->toBe('timestamptz');
    }
});
