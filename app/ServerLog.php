<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(array $array)
 */
class ServerLog extends Model
{
    use UsesUuid;
    protected $fillable = ['command', 'server_id', 'user_id','output'];
    public static $dont_log = [
        "hostname", "df -h" , "whoami", "sudo systemctl is-failed "
    ];
    public static function new($command, $output, $server_id = null,$user_id = null)
    {
        foreach (self::$dont_log as $check){
            if(strpos($command, $check)){
                return false;
            }
        }
        if(in_array($command, self::$dont_log)){
            return false;
        }
        $log = new ServerLog([
           "command" => $command,
            "user_id" => ($user_id == null) ? auth()->user()->id : $user_id,
            "server_id" => ($server_id == null) ? server()->id : $server_id,
            "output" => $output
        ]);
        $log->save();
        return $log;
    }

    public static function retrieve($readable = false,$server_id = null)
    {
        // First, Retrieve Logs.
        $logs = ServerLog::where([
            "server_id" => ($server_id == null) ? server()->id : $server_id
        ])->orderBy('updated_at', 'DESC')->get();

        // If it's not requested as readable, which means id's only without logic.
        if(!$readable){
            return $logs;
        }

        // First, convert user_id's into the user names.
        $users = User::all();

        foreach ($logs as $log){
            $user = $users->where('id', $log->user_id)->first();
            if(!$user){
                continue;
            }
            $log->username = $user->name;
        }
        return $logs;
    }

}
