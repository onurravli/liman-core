<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification Sent Event
 *
 * @extends Notification
 */
class NotificationSent extends Notification
{
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(private $notification)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array
     */
    public function via(mixed $notifiable)
    {
        return ['broadcast'];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @return BroadcastMessage
     */
    public function toBroadcast(mixed $notifiable)
    {
        return (new BroadcastMessage(
            (array) $this->notification
        ))->onConnection('sync');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array
     */
    public function toArray(mixed $notifiable)
    {
        return (array) $this->notification;
    }
}
