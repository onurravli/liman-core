<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Notification Model
 *
 * @extends Model
 */
class Notification extends Model
{
    use UsesUuid;

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'message',
        'server_id',
        'extension_id',
        'level',
        'read',
        'mail'
    ];

    /**
     * Creates new notification
     *
     * @param $title
     * @param $type "notify | certificate | health"
     * @param $message
     * @param $server_id
     * @param $extension_id
     * @param $level "0 | 2"
     * @return mixed
     */
    public static function new(
        $title,
        $type,
        $message,
        $server_id = null,
        $extension_id = null,
        $level = 0
    )
    {
        // Create a notification object and fill values.
        // Before we return the notification, check if it's urgent. If so, send an email.
        return Notification::create([
            'user_id' => auth()->id(),
            'title' => json_encode([
                'tr' => __($title, [], 'tr'),
                'en' => __($title, [], 'en'),
            ]),
            'type' => $type,
            'message' => $message,
            'server_id' => $server_id,
            'extension_id' => $extension_id,
            'level' => $level,
            'read' => false,
        ]);
    }

    /**
     * Sends new notification
     *
     * @param $title
     * @param $type "notify | certificate | health"
     * @param $message
     * @param $user_id
     * @param $server_id
     * @param $extension_id
     * @param $level "0 | 2"
     * @return mixed
     */
    public static function send(
        $title,
        $type,
        $message,
        $user_id,
        $server_id = null,
        $extension_id = null,
        $level = 0
    )
    {
        // Create a notification object and fill values.
        return Notification::create([
            'user_id' => $user_id,
            'title' => $title,
            'type' => $type,
            'message' => $message,
            'server_id' => $server_id,
            'extension_id' => $extension_id,
            'level' => $level,
            'read' => false,
        ]);
    }
}
