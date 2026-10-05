<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Notifications\GroupInviteNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InviteEmailTest extends TestCase
{
    use RefreshDatabase;

    private function groupWith(string $role): array
    {
        $owner = User::factory()->create(['name' => 'Gran']);
        $group = Group::create(['name' => 'Craigs', 'created_by' => $owner->id]);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role' => 'owner']);

        if ($role === 'owner') {
            return [$owner, $group];
        }

        $user = User::factory()->create();
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => $role]);

        return [$user, $group];
    }

    public function test_an_invite_with_an_email_sends_the_code(): void
    {
        Notification::fake();
        [$admin, $group] = $this->groupWith('admin');

        $res = $this->actingAs($admin)
            ->postJson("/api/groups/{$group->slug}/invites", ['email' => 'cousin@example.com'])
            ->assertCreated()
            ->assertJsonPath('email_sent', true);

        $code = $res->json('invite.code');

        Notification::assertSentOnDemandTimes(GroupInviteNotification::class, 1);
        Notification::assertSentOnDemand(GroupInviteNotification::class,
            function ($notification, $channels, AnonymousNotifiable $notifiable) use ($code, $group) {
                $mail = $notification->toMail($notifiable);
                $text = implode("\n", array_merge($mail->introLines, $mail->outroLines));

                return $notifiable->routes['mail'] === 'cousin@example.com'
                    && str_contains($text, $code)
                    && $mail->actionUrl === url("/g/{$group->slug}");
            });
    }

    public function test_an_invite_without_an_email_sends_nothing(): void
    {
        Notification::fake();
        [$owner, $group] = $this->groupWith('owner');

        $this->actingAs($owner)
            ->postJson("/api/groups/{$group->slug}/invites", ['max_uses' => 3])
            ->assertCreated()
            ->assertJsonPath('invite.max_uses', 3)
            ->assertJsonMissingPath('email_sent');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('group_invites', 1);
    }

    public function test_a_member_cannot_email_an_invite(): void
    {
        Notification::fake();
        [$member, $group] = $this->groupWith('member');

        $this->actingAs($member)
            ->postJson("/api/groups/{$group->slug}/invites", ['email' => 'cousin@example.com'])
            ->assertForbidden();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('group_invites', 0);
    }

    public function test_an_invite_to_a_bad_address_is_rejected(): void
    {
        Notification::fake();
        [$owner, $group] = $this->groupWith('owner');

        $this->actingAs($owner)
            ->postJson("/api/groups/{$group->slug}/invites", ['email' => 'not-an-address'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('group_invites', 0);
    }

    public function test_invite_emails_are_rate_limited(): void
    {
        Notification::fake();
        [$owner, $group] = $this->groupWith('owner');

        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($owner)
                ->postJson("/api/groups/{$group->slug}/invites", ['email' => "kin{$i}@example.com"])
                ->assertCreated();
        }

        $this->actingAs($owner)
            ->postJson("/api/groups/{$group->slug}/invites", ['email' => 'kin11@example.com'])
            ->assertTooManyRequests();

        Notification::assertSentOnDemandTimes(GroupInviteNotification::class, 10);
        $this->assertDatabaseCount('group_invites', 10);

        // Invites without an email are not mail and are not held back by it.
        $this->actingAs($owner)
            ->postJson("/api/groups/{$group->slug}/invites", [])
            ->assertCreated();
    }

    public function test_a_failed_send_still_creates_the_invite(): void
    {
        // A mailer that does not exist throws when the notification is sent.
        config(['mail.default' => 'no-such-mailer']);
        [$owner, $group] = $this->groupWith('owner');

        $this->actingAs($owner)
            ->postJson("/api/groups/{$group->slug}/invites", ['email' => 'cousin@example.com'])
            ->assertCreated()
            ->assertJsonPath('email_sent', false)
            ->assertJsonPath('invite.group_id', $group->id);

        $this->assertDatabaseCount('group_invites', 1);
    }
}
