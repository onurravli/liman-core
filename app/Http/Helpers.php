<?php

use App\Models\AdminNotification;
use App\Models\Certificate;
use App\Models\Extension;
use App\Models\Liman;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Server;
use App\Models\SystemSettings;
use App\System\Command;
use App\System\Helper;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Jackiedo\DotenvEditor\Facades\DotenvEditor;
use Beebmx\Blade\Blade;
use mervick\aesEverywhere\AES256;

if (! function_exists('validate')) {
    function validate($rules, $messages = [])
    {
        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            $errors = $validator->errors();
            abort(400, $errors->first());
        }
    }
}

if (! function_exists('updateSystemSettings')) {
    function updateSystemSettings()
    {
        SystemSettings::updateOrCreate(
            ['key' => 'APP_KEY'],
            ['data' => env('APP_KEY')]
        );

        SystemSettings::updateOrCreate(
            ['key' => 'SSL_PUBLIC_KEY'],
            ['data' => file_get_contents('/liman/certs/liman.crt')]
        );

        SystemSettings::updateOrCreate(
            ['key' => 'SSL_PRIVATE_KEY'],
            ['data' => file_get_contents('/liman/certs/liman.key')]
        );
        $sshPublic = SystemSettings::where([
            'key' => 'SSH_PUBLIC',
        ])->first();
        if (! $sshPublic) {
            $privatekey = \phpseclib3\Crypt\RSA::createKey();
            $publickey = $privatekey->getPublicKey();
            `mkdir -p /home/liman/.ssh`;
            file_put_contents('/home/liman/.ssh/authorized_keys', $publickey);
            file_put_contents('/home/liman/.ssh/liman_pub', $publickey);
            file_put_contents('/home/liman/.ssh/liman_priv', $privatekey);

            chmod('/home/liman/.ssh/liman_pub', 0600);
            chmod('/home/liman/.ssh/liman_priv', 0600);

            SystemSettings::create([
                'key' => 'SSH_PUBLIC',
                'data' => $publickey,
            ]);

            SystemSettings::updateOrCreate(
                ['key' => 'SSH_PRIVATE_KEY'],
                ['data' => $privatekey]
            );
        }
    }
}

if (! function_exists('receiveSystemSettings')) {
    function receiveSystemSettings()
    {
        $app_key = SystemSettings::where([
            'key' => 'APP_KEY',
        ])->first();

        if ($app_key) {
            setEnv([
                'APP_KEY' => $app_key->data,
            ]);
        }
        
        $public_key = SystemSettings::where([
            'key' => 'SSL_PUBLIC_KEY',
        ])->first();

        if ($public_key) {
            file_put_contents('/liman/certs/liman.crt', $public_key->data);
        }

        $private_key = SystemSettings::where([
            'key' => 'SSL_PRIVATE_KEY',
        ])->first();

        if ($private_key) {
            file_put_contents('/liman/certs/liman.key', $private_key->data);
        }

        $sshPublic = SystemSettings::where([
            'key' => 'SSH_PUBLIC',
        ])->first();

        if ($sshPublic) {
            `mkdir -p /home/liman/.ssh`;
            file_put_contents(
                '/home/liman/.ssh/authorized_keys',
                $sshPublic->data
            );
            file_put_contents('/home/liman/.ssh/liman_pub', $sshPublic->data);
            chmod('/home/liman/.ssh/liman_pub', 0600);
        }

        $sshPrivate = SystemSettings::where([
            'key' => 'SSH_PRIVATE_KEY',
        ])->first();

        if ($sshPrivate) {
            `mkdir -p /home/liman/.ssh`;
            file_put_contents('/home/liman/.ssh/liman_priv', $sshPrivate->data);
            chmod('/home/liman/.ssh/liman_priv', 0600);
        }
    }
}

if (! function_exists('respond')) {
    /**
     * @param $message
     * @param  int  $status
     */
    function respond($message, $status = 200)
    {
        if (request()->wantsJson()) {
            return response()->json(
                [
                    'message' => is_array($message) ? $message : __($message),
                    'status' => $status,
                ],
                $status
            );
        } else {
            return response()->view(
                'general.error',
                [
                    'message' => __($message),
                    'status' => $status,
                ],
                $status
            );
        }
    }
}

if (! function_exists('ip_in_range')) {
    function ip_in_range($ip, $range)
    {
        if (strpos((string) $range, '/') == false) {
            $range .= '/32';
        }
        // $range is in IP/CIDR format eg 127.0.0.1/24
        [$range, $netmask] = explode('/', (string) $range, 2);
        $range_decimal = ip2long($range);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, 32 - $netmask) - 1;
        $netmask_decimal = ~$wildcard_decimal;

        return ($ip_decimal & $netmask_decimal) ==
            ($range_decimal & $netmask_decimal);
    }
}

if (! function_exists('strposX')) {
    function strposX($haystack, $needle, $number)
    {
        if ($number == '1') {
            return strpos((string) $haystack, (string) $needle);
        } elseif ($number > '1') {
            return strpos(
                (string) $haystack,
                (string) $needle,
                strposX($haystack, $needle, $number - 1) + strlen((string) $needle)
            );
        } else {
            return error_log(
                'Error: Value for parameter $number is out of range'
            );
        }
    }
}

if (! function_exists('rootSystem')) {
    function rootSystem()
    {
        return new Helper();
    }
}

if (! function_exists('users')) {
    function users()
    {
        return User::all();
    }
}

if (! function_exists('registerModuleRoutes')) {
    function registerModuleRoutes()
    {
        $files = searchModuleFiles('routes.php');
        foreach ($files as $file) {
            require_once $file.'/routes.php';
        }
    }
}

if (! function_exists('registerModuleListeners')) {
    function registerModuleListeners()
    {
        $files = searchModuleFiles('listeners.php');
        foreach ($files as $file) {
            require_once $file.'/listeners.php';
        }
    }
}

if (! function_exists('searchModuleFiles')) {
    function searchModuleFiles($type)
    {
        $command = 'find /liman/modules/ -name @{:type}';

        $output = Command::runLiman($command, [
            'type' => $type,
        ]);
        if ($output == '') {
            return [];
        }

        $data = explode("\n", (string) $output);
        $arr = [];
        foreach ($data as $file) {
            array_push($arr, dirname($file));
        }

        return $arr;
    }
}

if (! function_exists('getLimanPermissions')) {
    /**
     * @return mixed
     */
    function getLimanPermissions($user_id)
    {
        $map = [
            'view_logs' => 'Sunucu Günlük Kayıtlarını Görüntüleme',
            'add_server' => 'Sunucu Ekleme',
            'server_services' => 'Sunucu Servislerini Görüntüleme',
            'server_details' => 'Sunucu Detaylarını Görüntüleme',
            'update_server' => 'Sunucu Detaylarını Güncelleme',
        ];
        $permissions = Permission::where([
            'morph_id' => $user_id ? $user_id : user()->id,
            'type' => 'liman',
            'key' => 'id',
        ])->get();
        $permissions = $permissions->map(function ($permission) use (&$map) {
            $permission->name = __($map[$permission->value]);
            $permission->id = $permission->value;

            return $permission;
        });

        return $permissions;
    }
}

if (! function_exists('settingsModuleViews')) {
    /**
     * @return mixed
     */
    function settingsModuleViews()
    {
        $str = '';
        foreach (searchModuleFiles('settings.blade.php') as $file) {
            $blade = new Blade(
                [realpath(base_path('resources/views/components')), $file],
                '/tmp'
            );
            $str .= $blade->render('settings');
        }

        return $str;
    }
}

if (! function_exists('renderModuleView')) {
    /**
     * @return mixed
     */
    function renderModuleView($module, $page, $params = [])
    {
        $blade = new Blade('/liman/modules/'.$module.'/views/', '/tmp');
        $str = $blade->render($page, $params);

        return view('modules.layout', [
            'name' => $module,
            'html' => $str,
        ]);
    }
}

$sidebarModuleLinks = null;
if (! function_exists('sidebarModuleLinks')) {
    /**
     * @return mixed
     */
    function sidebarModuleLinks()
    {
        global $sidebarModuleLinks;
        if ($sidebarModuleLinks != null) {
            return $sidebarModuleLinks;
        }
        $array = [];
        foreach (searchModuleFiles('sidebar.json') as $file) {
            $filePath = $file.'/sidebar.json';
            $data = file_get_contents($filePath);
            $json = json_decode($data, true);
            if (json_last_error() != JSON_ERROR_NONE) {
                continue;
            }
            foreach ($json as $a) {
                array_push($array, $a);
            }
        }
        $sidebarModuleLinks = $array;

        return $array;
    }
}

if (! function_exists('settingsModuleButtons')) {
    /**
     * @return mixed
     */
    function settingsModuleButtons()
    {
        $str = '';
        foreach (searchModuleFiles('settings.blade.php') as $file) {
            $foo = substr((string) $file, 15);
            $name = substr($foo, 0, strpos($foo, '/'));
            $hrefName = $name;
            if (is_numeric($name[0])) {
                $hrefName = 'l-'.$name;
            }

            $str .=
                '<li class="nav-item">
               <a id="'.
                $name.
                "tab\" class=\"nav-link\" data-toggle=\"tab\" href=\"#$hrefName\">$name</a>
            </li>";
        }

        return $str;
    }
}

if (! function_exists('getLimanHostname')) {
    function getLimanHostname(): string
    {
        return trim((string) `hostname`);
    }
}

if (! function_exists('serverModuleViews')) {
    /**
     * @return mixed
     */
    function serverModuleViews()
    {
        $str = '';
        foreach (searchModuleFiles('server.blade.php') as $file) {
            $blade = new Blade(
                [realpath(base_path('resources/views/components')), $file],
                '/tmp'
            );
            $str .= $blade->render('server');
        }

        return $str;
    }
}

if (! function_exists('serverModuleButtons')) {
    /**
     * @return mixed
     */
    function serverModuleButtons()
    {
        $str = '';
        foreach (searchModuleFiles('server.blade.php') as $file) {
            $foo = substr((string) $file, 15);
            $name = substr($foo, 0, strpos($foo, '/'));
            $str .=
                '<li class="nav-item">
               <a id="'.
                $name.
                "tab\"class=\"nav-link\" data-toggle=\"tab\" href=\"#$name\">$name</a>
            </li>";
        }

        return $str;
    }
}

if (! function_exists('getVersion')) {
    /**
     * @return mixed
     */
    function getVersion(): string|bool
    {
        return file_get_contents(storage_path('VERSION'));
    }
}

if (! function_exists('getVersionCode')) {
    /**
     * @return mixed
     */
    function getVersionCode(): int
    {
        return intval(file_get_contents(storage_path('VERSION_CODE')));
    }
}

if (! function_exists('notifications')) {
    /**
     * @return mixed
     */
    function notifications()
    {
        return Notification::where([
            'user_id' => auth()->id(),
            'read' => false,
        ])
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}

if (! function_exists('knownPorts')) {
    /**
     * @return mixed
     */
    function knownPorts()
    {
        $ports = ['5986', '443'];
        if(! env('LDAP_IGNORE_CERT', false)) {
            array_push($ports, '636');
        }
        return $ports;
    }
}

if (! function_exists('retrieveCertificate')) {
    /**
     * @return mixed
     */
    function retrieveCertificate($hostname, $port)
    {
        $get = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'allow_self_signed' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $flag = false;
        try {
            $read = stream_socket_client(
                'ssl://'.$hostname.':'.$port,
                $errno,
                $errstr,
                intval(config('liman.server_connection_timeout')),
                STREAM_CLIENT_CONNECT,
                $get
            );
            $flag = true;
        } catch (\Exception) {
        }

        if (! $flag) {
            try {
                $read = stream_socket_client(
                    'tlsv1.1://'.$hostname.':'.$port,
                    $errno,
                    $errstr,
                    intval(config('liman.server_connection_timeout')),
                    STREAM_CLIENT_CONNECT,
                    $get
                );
                $flag = true;
            } catch (\Exception) {
                return [false, __('Sertifika alınamıyor!')];
            }
        }

        $cert = stream_context_get_params($read);
        $certinfo = openssl_x509_parse(
            $cert['options']['ssl']['peer_certificate']
        );
        openssl_x509_export(
            $cert['options']['ssl']['peer_certificate'],
            $publicKey
        );
        $certinfo['subjectKeyIdentifier'] = array_key_exists(
            'subjectKeyIdentifier',
            $certinfo['extensions']
        )
            ? $certinfo['extensions']['subjectKeyIdentifier']
            : '';
        $certinfo['authorityKeyIdentifier'] = array_key_exists(
            'authorityKeyIdentifier',
            $certinfo['extensions']
        )
            ? substr((string) $certinfo['extensions']['authorityKeyIdentifier'], 6)
            : '';
        $certinfo['validFrom_time_t'] = Carbon::createFromTimestamp(
            $certinfo['validFrom_time_t']
        )->format('H:i d/m/Y');
        $certinfo['validTo_time_t'] = Carbon::createFromTimestamp(
            $certinfo['validTo_time_t']
        )->format('H:i d/m/Y');
        unset($certinfo['extensions']);
        $path = Str::random(10);
        $certinfo['path'] = $path;
        file_put_contents('/tmp/'.$path, $publicKey);

        return [true, $certinfo];
    }
}

if (! function_exists('adminNotifications')) {
    /**
     * @return mixed
     */
    function adminNotifications()
    {
        return AdminNotification::where([
            'read' => false,
        ])
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}

if (! function_exists('addCertificate')) {
    /**
     * @return mixed
     */
    function addCertificate($hostname, $port, $path)
    {
        rootSystem()->addCertificate(
            '/tmp/'.$path,
            'liman-'.$hostname.'_'.$port
        );

        // Create Certificate Object.
        return Certificate::create([
            'server_hostname' => strtolower((string) $hostname),
            'origin' => $port,
        ]);
    }
}

if (! function_exists('getLimanId')) {
    /**
     * @return mixed
     */
    function getLimanId()
    {
        return md5(
            'l1m@ns3cur1ty'.trim(shell_exec('ls /dev/disk/by-uuid -1'))
        ).PHP_EOL;
    }
}

if (! function_exists('system_log')) {
    /**
     * @param $level
     * @param $message
     * @param  array  $array
     */
    function system_log($level, $message, $array = [])
    {
        $array['user_id'] = user() ? user()->id : '';
        $array['ip_address'] = request()->ip();

        match ($level) {
            1 => Log::emergency($message, $array),
            2 => Log::alert($message, $array),
            3 => Log::critical($message, $array),
            4 => Log::error($message, $array),
            5 => Log::warning($message, $array),
            6 => Log::notice($message, $array),
            7 => Log::info($message, $array),
            default => Log::debug($message, $array),
        };
    }
}

if (! function_exists('server')) {
    /**
     * @return \App\Models\Server
     */
    function server()
    {
        if (! request()->request->get('server')) {
            abort(501, 'Sunucu Bulunamadı');
        }
        $serverObj = json_decode((string) request()->request->get('server'));
        $server = Server::find($serverObj->id);

        return $server;
    }
}

if (! function_exists('servers')) {
    function servers()
    {
        return auth()
            ->user()
            ->servers();
    }
}

if (! function_exists('extensions')) {
    /**
     * @param  array  $filter
     * @return array
     */
    function extensions($filter = [])
    {
        return Extension::getAll($filter);
    }
}

if (! function_exists('extension')) {
    /**
     * @param  null  $id
     * @return Extension
     */
    function extension($id = null)
    {
        if ($id == null) {
            $id = request('extension_id');
        }

        return Extension::one($id);
    }
}

if (! function_exists('user')) {
    /**
     * @param  null  $id
     * @return User
     */
    function user()
    {
        return auth()->user();
    }
}

if (! function_exists('sandbox')) {
    /**
     * @param  null  $id
     * @return App\Sandboxes\Sandbox
     */
    function sandbox($language = null)
    {
        return new App\Sandboxes\PHPSandbox();
    }
}

if (! function_exists('hook')) {
    /**
     * @param $name
     * @param  array  $data
     * @return void
     */
    function hook($name, $data = [])
    {
        // Will be implemented
    }
}

if (! function_exists('magicView')) {
    /**
     * @param $extension
     * @return array
     */
    function magicView($view, $data = [])
    {
        if (
            request()->wantsJson() &&
            ! request()->has('partialRequest') &&
            ! request()->has('limanJSRequest')
        ) {
            return response()->json($data);
        } else {
            return response()->view($view, $data);
        }
    }
}

if (! function_exists('getExtensionJson')) {
    /**
     * @param $extension
     * @return array
     */
    function getExtensionJson($extension_name)
    {
        $extension_json = '/liman/extensions/'.
            strtolower((string) $extension_name).
            DIRECTORY_SEPARATOR.
            'db.json';

        if (file_exists($extension_json)) {
            $json = json_decode(
                file_get_contents(
                    $extension_json
                ),
                true
            );
            if (empty($json['display_name'])) {
                $json['display_name'] = Str::title(str_replace('-', ' ', (string) $json['name']));
            }

            return $json;
        } else {
            abort(404, $extension_name.__(' eklentisi sistemde bulunamadı, yeniden yüklemeyi deneyin.'));
        }
    }
}

if (! function_exists('redirect_now')) {
    function redirect_now($url, $code = 302)
    {
        try {
            \App::abort($code, '', ['Location' => $url]);
        } catch (\Exception $exception) {
            $previousErrorHandler = set_exception_handler(function () {
            });
            restore_error_handler();
            call_user_func($previousErrorHandler, $exception);
            exit();
        }
    }
}
if (! function_exists('extensionDb')) {
    /**
     * @param $key
     * @return string
     */
    function extensionDb($key = '*')
    {
        $target = DB::table('user_settings')
            ->where([
                'user_id' => auth()->user()->id,
                'server_id' => server()->id,
                'name' => $key,
            ])
            ->first();
        if ($key == 'clientPassword' || $key == 'clientUsername') {
            $serverKey = server()->key();
            if ($serverKey == null) {
                return null;
            }
            $data = json_decode((string) $serverKey->data, true);
            $encKey = env('APP_KEY').auth()->user()->id.server()->id;

            return AES256::decrypt($data[$key], $encKey);
        }
        if ($target) {
            $key = env('APP_KEY').auth()->user()->id.server()->id;

            return AES256::decrypt($target->value, $key);
        }

        return null;
    }
}

if (! function_exists('sudo')) {
    function sudo()
    {
        if (server()->key()->type == 'ssh_certificate') {
            return 'sudo ';
        }

        return 'sudo -p "liman-pass-sudo" ';
    }
}

if (! function_exists('getObject')) {
    /**
     * @param $type
     * @param  null  $id
     */
    function getObject($type, $id = null)
    {
        // Check for type
        switch ($type) {
            case 'Extension':
            case 'extension':
                try {
                    return Extension::find($id);
                } catch (\Throwable) {
                    abort(404, __('Eklenti bulunamadı.'));
                }
                break;
            case 'Server':
            case 'server':
                try {
                    return Server::find($id);
                } catch (\Throwable) {
                    abort(404, __('Sunucu bulunamadı.'));
                }
                break;
            default:
                return false;
        }
    }
}

if (! function_exists('objectToArray')) {
    /**
     * @param $array
     * @param $key
     * @param $value
     * @return array
     */
    function objectToArray($array, $key, $value)
    {
        $combined_array = [];
        foreach ($array as $item) {
            if (is_array($item)) {
                $combined_array[$item[$key]] = $item[$value];
            } else {
                $combined_array[$item->__get($key)] = $item->__get($value);
            }
        }

        return $combined_array;
    }
}

if (! function_exists('cleanArray')) {
    function cleanArray($array)
    {
        $newArray = [];
        foreach ($array as $row) {
            $newArray[] = $row;
        }

        return $newArray;
    }
}

if (! function_exists('cleanDash')) {
    /**
     * @return array|Request|string
     */
    function cleanDash($text): string
    {
        return str_replace('-', '', (string) $text);
    }
}
if (! function_exists('isJson')) {
    function isJson($string, $return_data = false)
    {
        $data = json_decode((string) $string);

        return json_last_error() == JSON_ERROR_NONE
            ? ($return_data
                ? $data
                : true)
            : false;
    }
}

if (! function_exists('getPermissions')) {
    function getPermissions($path): string
    {
        return substr(sprintf('%o', fileperms($path)), -4);
    }
}

if (! function_exists('getExtensionFunctions')) {
    function getExtensionFunctions(string $extension_name)
    {
        $file = '/liman/extensions/'.
            strtolower($extension_name).
            DIRECTORY_SEPARATOR.
            'db.json';

        if (is_file($file)) {
            $extension = json_decode(
                file_get_contents(
                    $file
                ),
                true
            );
        } else {
            $extension = [];
        }

        return isset($extension['functions'])
            ? collect($extension['functions'])
            : [];
    }
}

if (! function_exists('extensionTranslate')) {
    function extensionTranslate(string $text, string $extension_name)
    {
        $lang = session('locale');
        $file =
            '/liman/extensions/'.
            strtolower($extension_name).
            '/lang/'.
            $lang.
            '.json';
        if (is_file($file)) {
            $lang = json_decode(file_get_contents($file), true);

            return isset($lang[$text]) ? $lang[$text] : $text;
        }

        return $text;
    }
}

if (! function_exists('setEnv')) {
    function setEnv(array $values): bool
    {
        $editor = DotenvEditor::load(base_path('.env'));
        $editor->setKeys($values);
        try {
            $editor->save();
        } catch (\Exception) {
            return false;
        }
        shell_exec('php /liman/server/artisan config:clear');

        return true;
    }
}
if (! function_exists('checkHealth')) {
    function checkHealth()
    {
        $allowed = [
            'certs' => '0700',
            'database' => '0700',
            'extensions' => '0755',
            'keys' => '0755',
            'logs' => '0700',
            'sandbox' => '0755',
            'server' => '0700',
            'webssh' => '0700',
            'modules' => '0700',
            'packages' => '0700',
            'hashes' => '0700',
            'sql-backups' => '0750',
        ];
        $messages = [];

        // Check Permissions and Owners
        foreach ($allowed as $name => $permission) {
            // Permission Check
            $file = '/liman/'.$name;
            if (! file_exists($file)) {
                array_push($messages, [
                    'type' => 'danger',
                    'message' => "'/liman/$name' isimli sistem dosyası bulunamadı",
                ]);

                continue;
            }

            if (getPermissions('/liman/'.$name) != $permission) {
                array_push($messages, [
                    'type' => 'danger',
                    'message' => "'/liman/$name' izni hatalı (".
                        getPermissions('/liman/'.$name).
                        ').',
                ]);
            }

            // Owners Check
            $owner = posix_getpwuid(fileowner($file))['name'];
            $group = posix_getgrgid(filegroup($file))['name'];
            if ($owner != 'liman' || $group != 'liman') {
                array_push($messages, [
                    'type' => 'danger',
                    'message' => "'/liman/$name' dosyasının sahibi hatalı ($owner : $group).",
                ]);
            }
        }

        // Check Extra Files
        $extra = array_diff(
            array_diff(scandir('/liman'), ['..', '.']),
            array_keys($allowed)
        );
        foreach ($extra as $item) {
            array_push($messages, [
                'type' => 'warning',
                'message' => "'/liman/$item' dosyasina izin verilmiyor.",
            ]);
        }
        if (empty($messages)) {
            array_push($messages, [
                'type' => 'success',
                'message' => __('Her şey Yolunda, sıkıntı yok!'),
            ]);
        }

        return $messages;
    }
}

if (! function_exists('lDecrypt')) {
    function lDecrypt($data)
    {
        $key = env('APP_KEY').user()->id.server()->id;

        return AES256::decrypt($data, $key);
    }
}

if (! function_exists('setBaseDn')) {
    function setBaseDn($ldap_host = null)
    {
        $ldap_host = $ldap_host ? $ldap_host : config('ldap.ldap_host');
        $flag = false;
        $connection = ldap_connect($ldap_host, 389);
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 10);
        ldap_set_option($connection, LDAP_OPT_TIMELIMIT, 10);
        $flag = ldap_bind($connection);
        $outputs = ldap_read($connection, '', 'objectclass=*');
        $entries = ldap_get_entries($connection, $outputs)[0];
        $domain = str_replace(
            'dc=',
            '',
            strtolower((string) $entries['rootdomainnamingcontext'][0])
        );
        $domain = str_replace(',', '.', $domain);
        setEnv([
            'LDAP_BASE_DN' => $entries['rootdomainnamingcontext'][0],
            'LDAP_DOMAIN' => $domain,
        ]);

        return $flag;
    }
}

if (! function_exists('checkPort')) {
    function checkPort($ip, $port)
    {
        if ($port == -1) {
            return true;
        }
        $fp = @fsockopen($ip, $port, $errno, $errstr, 0.1);
        if (! $fp) {
            return false;
        } else {
            fclose($fp);

            return true;
        }
    }
}
if (! function_exists('endsWith')) {
    function endsWith($string, $endString)
    {
        $len = strlen((string) $endString);
        if ($len == 0) {
            return true;
        }

        return substr((string) $string, -$len) === $endString;
    }
}

if (! function_exists('fetchExtensionTemplates')) {
    function fetchExtensionTemplates()
    {
        $path = storage_path('extension_templates/templates.json');

        return json_decode(file_get_contents($path));
    }
}

if (! function_exists('scanTranslations')) {
    function scanTranslations($directory)
    {
        $pattern =
            '[^\w]'.
            '(?<!->)'.
            '('.
            implode('|', ['__']).
            ')'.
            "\(".
            "[\'\"]".
            '('.
            '.+'.
            ')'.
            "[\'\"]".
            "[\),]";
        $allMatches = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            if (endsWith($file->getPathname(), '.php')) {
                $content = file_get_contents($file->getPathname());
                if (preg_match_all("/$pattern/siU", $content, $matches)) {
                    foreach ($matches[2] as $row) {
                        $allMatches[$row] = $row;
                    }
                }
            }
        }

        return $allMatches;
    }
}
