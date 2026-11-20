<?php

use App\Models\Area;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

it('lets the uploader download a private attachment', function () {
    $uploader = User::factory()->colaborador()->create();
    $area = Area::factory()->create(['manager_id' => null]);
    $uploader->update(['area_id' => $area->id]);
    $task = Task::factory()->create(['area_id' => $area->id, 'assignee_id' => $uploader->id]);

    Storage::disk('local')->put('evidencia.pdf', 'contenido de la evidencia');

    $attachment = Attachment::factory()->create([
        'attachable_type' => Task::class,
        'attachable_id' => $task->id,
        'disk' => 'local',
        'path' => 'evidencia.pdf',
        'original_name' => 'evidencia.pdf',
        'mime_type' => 'application/pdf',
        'uploaded_by' => $uploader->id,
    ]);

    $this->actingAs($uploader)
        ->get(route('attachments.download', $attachment))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('refuses downloads to users without access to the attachment', function () {
    $outsider = User::factory()->create();
    $uploader = User::factory()->colaborador()->create();
    $task = Task::factory()->create();

    $attachment = Attachment::factory()->create([
        'attachable_type' => Task::class,
        'attachable_id' => $task->id,
        'disk' => 'local',
        'path' => 'evidencia.pdf',
        'uploaded_by' => $uploader->id,
    ]);

    $this->actingAs($outsider)
        ->get(route('attachments.download', $attachment))
        ->assertForbidden();
});
