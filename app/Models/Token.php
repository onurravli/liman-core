<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Token Model
 *
 * This model utilizes tokens between Go Sandbox and Laravel instance
 *
 * @extends Model
 */
class Token extends Model
{
    use UsesUuid;

    protected $fillable = ['token', 'user_id'];

    /**
     * Create a new token or retrieve old one
     *
     * @param $user_id
     * @return string
     */
    public static function create($user_id = null)
    {
        $user = $user_id ? $user_id : auth()->id();
        $exists = Token::where(['user_id' => $user])->first();
        if ($exists) {
            if (Carbon::now()->diffInHours($exists->created_at) > 23) {
                $exists->delete();

                return self::generate($user);
            }

            return $exists['token'];
        }

        return self::generate($user);
    }

    /**
     * Generate a new token
     *
     * @param $user_id
     * @return string
     */
    public static function generate($user_id = null)
    {
        $token = Str::random(32);
        while (Token::where('token', $token)->exists()) {
            $token = Str::random(32);
        }

        Token::firstOrCreate([
            'token' => $token,
            'user_id' => $user_id,
        ]);

        return $token;
    }
}
