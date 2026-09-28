<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_notes(): void
    {
        $response = $this->get(route('work-notes.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_notes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('work-notes.index'));

        $response->assertOk();
        $response->assertViewIs('work-notes.index');
        $response->assertSee('Operational Notes');
    }

    public function test_user_only_sees_their_own_notes(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $myNote = WorkNote::factory()->create([
            'user_id' => $user->id,
            'title' => 'Shift Handover CV-05 notes',
            'content' => 'Vehicle parked at Gate 3 with key at dispatch desk',
        ]);

        $otherNote = WorkNote::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'Private technician checklist',
            'content' => 'Do not show to other operators',
        ]);

        $response = $this->actingAs($user)->get(route('work-notes.index'));

        $response->assertOk();
        $response->assertSee('Shift Handover CV-05 notes');
        $response->assertSee('Vehicle parked at Gate 3 with key at dispatch desk');
        $response->assertDontSee('Private technician checklist');
    }

    public function test_user_can_create_note_with_only_subject_and_description(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('work-notes.store'), [
            'title' => 'GPS Tech Support Hotline',
            'content' => 'Call Engr. Mark at 0917-555-0199 for tracker pings',
        ]);

        $response->assertRedirect(route('work-notes.index'));
        $this->assertDatabaseHas('work_notes', [
            'user_id' => $user->id,
            'title' => 'GPS Tech Support Hotline',
            'content' => 'Call Engr. Mark at 0917-555-0199 for tracker pings',
            'is_pinned' => false,
            'color' => 'slate',
        ]);
    }

    public function test_subject_is_required_to_create_note(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('work-notes.store'), [
            'title' => '',
            'content' => 'Some description without subject',
        ]);

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('work_notes', 0);
    }

    public function test_user_can_update_note(): void
    {
        $user = User::factory()->create();
        $note = WorkNote::factory()->create([
            'user_id' => $user->id,
            'title' => 'Initial Title',
            'content' => 'Initial Content',
        ]);

        $response = $this->actingAs($user)->patch(route('work-notes.update', $note), [
            'title' => 'Updated Subject Title',
            'content' => 'Updated Description text',
        ]);

        $response->assertRedirect(route('work-notes.index'));
        $note->refresh();

        $this->assertEquals('Updated Subject Title', $note->title);
        $this->assertEquals('Updated Description text', $note->content);
    }

    public function test_user_can_toggle_pin_on_note(): void
    {
        $user = User::factory()->create();
        $note = WorkNote::factory()->create([
            'user_id' => $user->id,
            'is_pinned' => false,
        ]);

        // Toggle to pinned
        $response = $this->actingAs($user)->patch(route('work-notes.toggle-pin', $note));
        $response->assertRedirect();
        $this->assertTrue($note->fresh()->is_pinned);

        // Toggle back to unpinned
        $response = $this->actingAs($user)->patch(route('work-notes.toggle-pin', $note));
        $response->assertRedirect();
        $this->assertFalse($note->fresh()->is_pinned);
    }

    public function test_user_can_delete_note(): void
    {
        $user = User::factory()->create();
        $note = WorkNote::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->delete(route('work-notes.destroy', $note));

        $response->assertRedirect(route('work-notes.index'));
        $this->assertDatabaseMissing('work_notes', ['id' => $note->id]);
    }

    public function test_user_cannot_update_or_delete_another_users_note(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $note = WorkNote::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Owner Note',
        ]);

        $responseUpdate = $this->actingAs($attacker)->patch(route('work-notes.update', $note), [
            'title' => 'Hijacked',
        ]);
        $responseUpdate->assertForbidden();

        $responsePin = $this->actingAs($attacker)->patch(route('work-notes.toggle-pin', $note));
        $responsePin->assertForbidden();

        $responseDelete = $this->actingAs($attacker)->delete(route('work-notes.destroy', $note));
        $responseDelete->assertForbidden();

        $this->assertDatabaseHas('work_notes', ['id' => $note->id, 'title' => 'Owner Note']);
    }

    public function test_user_can_search_notes_by_keyword(): void
    {
        $user = User::factory()->create();

        WorkNote::factory()->create([
            'user_id' => $user->id,
            'title' => 'Fuel Formula benchmark',
            'content' => 'Litres divided by distance multiplied by 100',
        ]);

        WorkNote::factory()->create([
            'user_id' => $user->id,
            'title' => 'Security Gate Codes',
            'content' => 'Gate 2 passcode is 4920',
        ]);

        $response = $this->actingAs($user)->get(route('work-notes.index', ['search' => 'Formula']));

        $response->assertOk();
        $response->assertSee('Fuel Formula benchmark');
        $response->assertDontSee('Security Gate Codes');
    }
}
