<?php

namespace App\Notifications;

use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GroupInviteNotification extends Notification
{
    public function __construct(
        public GroupInvite $invite,
        public Group $group,
        public User $inviter,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    // The code cannot ride in the URL (GroupTimeline has no ?code=), so the
    // link and the code are given separately.
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->inviter->name} invited you to {$this->group->name} on Family Timeline")
            ->line("{$this->inviter->name} has invited you to join the {$this->group->name} timeline.")
            ->line("Your invite code is: {$this->invite->code}")
            ->action('Open the group', url("/g/{$this->group->slug}"))
            ->line('Sign in or create an account, then enter the code on the group page to join.');
    }
}
