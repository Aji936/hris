<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Models\User;
use App\Shared\Database\AuditColumns;
use App\Shared\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DummyAuditableRecord extends Model
{
    use HasAuditColumns;

    protected $table = 'dummy_auditable_records';

    protected $fillable = [
        'title',
        'created_by',
        'updated_by',
    ];
}

class HasAuditColumnsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('dummy_auditable_records', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            AuditColumns::apply($table);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('dummy_auditable_records');

        parent::tearDown();
    }

    public function test_it_populates_audit_columns_and_integrates_soft_deletes(): void
    {
        $creator = User::factory()->create();
        $editor = User::factory()->create();

        $this->actingAs($creator);
        $record = DummyAuditableRecord::query()->create(['title' => 'Initial HR Policy']);

        $this->assertSame($creator->id, $record->created_by);
        $this->assertSame($creator->id, $record->updated_by);
        $this->assertNull($record->deleted_at);
        $this->assertTrue($record->creator->is($creator));

        $this->actingAs($editor);
        $record->update(['title' => 'Updated HR Policy']);
        $record->refresh();

        $this->assertSame($creator->id, $record->created_by);
        $this->assertSame($editor->id, $record->updated_by);
        $this->assertTrue($record->updater->is($editor));

        $record->delete();

        $this->assertNull(DummyAuditableRecord::query()->find($record->id));
        $trashedRecord = DummyAuditableRecord::withTrashed()->find($record->id);
        $this->assertNotNull($trashedRecord);
        $this->assertNotNull($trashedRecord->deleted_at);

        $trashedRecord->restore();
        $this->assertNull($trashedRecord->fresh()->deleted_at);
    }
}
