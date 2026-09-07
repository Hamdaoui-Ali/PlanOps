<?php

use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('project creation establishes the creator as the owner and active owner member', function (): void {
    $user = User::factory()->create();

    $project = (new CreateProject)->handle($user, [
        'name' => 'Collaboration foundation',
        'key' => 'COLLAB',
    ]);

    $membership = ProjectMembership::query()
        ->where('project_id', $project->id)
        ->whereNull('removed_at')
        ->sole();

    expect($project->owner_id)->toBe($user->id)
        ->and($project->user_id)->toBe($user->id)
        ->and($membership->user_id)->toBe($user->id)
        ->and($membership->role)->toBe(ProjectRole::OWNER)
        ->and($membership->joined_at)->toEqual($project->created_at);
});

test('project creation rolls back when the owner membership cannot be written', function (): void {
    $user = User::factory()->create();
    $connection = DB::connection();
    $driver = $connection->getDriverName();

    if ($driver === 'sqlite') {
        $connection->unprepared(<<<'SQL'
            CREATE TRIGGER fail_project_owner_membership
            BEFORE INSERT ON project_memberships
            WHEN NEW.role = 'OWNER'
            BEGIN
                SELECT RAISE(ABORT, 'owner membership write failed');
            END;
        SQL);
    } elseif ($driver === 'pgsql') {
        $connection->unprepared(<<<'SQL'
            CREATE FUNCTION fail_project_owner_membership() RETURNS trigger AS $$
            BEGIN
                IF NEW.role = 'OWNER' THEN
                    RAISE EXCEPTION 'owner membership write failed';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER fail_project_owner_membership
            BEFORE INSERT ON project_memberships
            FOR EACH ROW EXECUTE FUNCTION fail_project_owner_membership();
        SQL);
    } else {
        $this->markTestSkipped("Project-identity rollback contract requires SQLite or PostgreSQL; {$driver} is configured.");
    }

    try {
        expect(fn (): Project => (new CreateProject)->handle($user, [
            'name' => 'Must roll back',
            'key' => 'ROLLBACK',
        ]))->toThrow(QueryException::class);
    } finally {
        if ($driver === 'sqlite') {
            $connection->unprepared('DROP TRIGGER IF EXISTS fail_project_owner_membership');
        }

        if ($driver === 'pgsql') {
            $connection->unprepared('DROP TRIGGER IF EXISTS fail_project_owner_membership ON project_memberships');
            $connection->unprepared('DROP FUNCTION IF EXISTS fail_project_owner_membership()');
        }
    }

    expect(Project::query()->count())->toBe(0)
        ->and(ProjectMembership::query()->count())->toBe(0);
});
