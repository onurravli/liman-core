<?php

namespace App\Models;

use App\Support\Database\CacheQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Extension extends Model
{
    use UsesUuid, CacheQueryBuilder;

    /**
     * @var array
     */
    protected $fillable = [
        'display_name',
        'name',
        'version',
        'icon',
        'service',
        'sslPorts',
        'issuer',
        'language',
        'support',
        'displays',
        'require_key',
        'status',
    ];

    protected $casts = [
        'displays' => 'array',
    ];

    /**
     * @param $id
     * @return mixed
     */
    public static function one($id)
    {
        // Find Object
        $extension = Extension::find($id);

        // If object is not found, abort
        if ($extension == null) {
            abort(504, 'Eklenti Bulunamadı');
        }

        return $extension;
    }

    /**
     * @return mixed
     */
    public function servers()
    {
        return Server::getAll()->filter(function ($value) {
            return DB::table('server_extensions')
                ->where([
                    'server_id' => $value->id,
                    'extension_id' => request('extension_id'),
                ])
                ->exists();
        });
    }

    public static function getAll()
    {
        return user()->extensions();
    }

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('extensions', function (
            \Illuminate\Database\Eloquent\Builder $builder
        ) {
            $builder->orderBy('order');
        });
    }

    public function getDisplayNameAttribute($value)
    {
        if (empty($this->attributes['display_name'])) {
            return Str::title(str_replace('-', ' ', (string) $this->name));
        }

        return $this->attributes['display_name'];
    }

    public function getUpdatedAtAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->isoFormat('LLLL');
    }

    public function setNameAttribute($value)
    {
        if ($this->name) {
            return;
        }

        $this->attributes['name'] = $value;
    }
}
