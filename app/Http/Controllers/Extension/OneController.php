<?php

namespace App\Http\Controllers\Extension;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Token;
use App\System\Command;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MimeType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use mervick\aesEverywhere\AES256;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use function request;

/**
 * Extension One Controller
 *
 * @extends Controller
 */
class OneController extends Controller
{
    /**
     * Get extension settings
     *
     * @return RedirectResponse
     * @throws GuzzleException
     */
    public function serverSettings(): \Illuminate\Http\RedirectResponse
    {
        $extension = json_decode(
            file_get_contents(
                '/liman/extensions/' .
                strtolower((string) extension()->name) .
                DIRECTORY_SEPARATOR .
                'db.json'
            ),
            true
        );
        foreach ($extension['database'] as $key) {
            if (
                $key['type'] == 'password' &&
                request($key['variable']) !=
                request($key['variable'] . '_confirmation')
            ) {
                return redirect(
                    route('extension_server_settings_page', [
                        'extension_id' => extension()->id,
                        'server_id' => server()->id,
                    ])
                )
                    ->withInput()
                    ->withErrors([
                        'message' => __('Parola alanları uyuşmuyor!'),
                    ]);
            }
        }

        foreach ($extension['database'] as $key) {
            $opts = [
                'server_id' => server()->id,
                'name' => $key['variable'],
            ];

            if (!isset($key['global']) || $key['global'] === false) {
                $opts['user_id'] = user()->id;
            }

            $row = DB::table('user_settings')->where($opts);
            $variable = request($key['variable']);
            if ($variable) {
                if ($row->exists()) {
                    $encKey = env('APP_KEY') . user()->id . server()->id;
                    if ($row->first()->user_id != user()->id && !user()->isAdmin()) {
                        return redirect(
                            route('extension_server_settings_page', [
                                'extension_id' => extension()->id,
                                'server_id' => server()->id,
                            ])
                        )
                            ->withInput()
                            ->withErrors([
                                'message' => __('Bu ayar sadece eklentiyi kuran kişi tarafından değiştirilebilir.'),
                            ]);
                    }
                    $row->update([
                        'user_id' => user()->id,
                        'server_id' => server()->id,
                        'value' => AES256::encrypt($variable, $encKey),
                        'updated_at' => Carbon::now(),
                    ]);
                } else {
                    $encKey = env('APP_KEY') . user()->id . server()->id;
                    DB::table('user_settings')->insert([
                        'id' => Str::uuid(),
                        'server_id' => server()->id,
                        'user_id' => user()->id,
                        'name' => $key['variable'],
                        'value' => AES256::encrypt($variable, $encKey),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }
        }

        //Check Verification
        if (
            array_key_exists('verification', $extension) &&
            $extension['verification'] != null &&
            $extension['verification'] != ''
        ) {
            $client = new Client(['verify' => false]);
            $result = '';
            try {
                $res = $client->request('POST', env('RENDER_ENGINE_ADDRESS', 'https://127.0.0.1:2806'), [
                    'form_params' => [
                        'lmntargetFunction' => $extension['verification'],
                        'extension_id' => extension()->id,
                        'server_id' => server()->id,
                        'token' => Token::create(user()->id),
                    ],
                    'timeout' => 5,
                ]);
                $output = (string) $res->getBody();
                if (isJson($output)) {
                    $message = json_decode($output);
                    if (isset($message->message)) {
                        $result = $message->message;
                    }
                } else {
                    $result = $output;
                }
            } catch (\Exception) {
                $result = __('Doğrulama başarısız, girdiğiniz bilgileri kontrol edin.');
            }
            if (trim((string) $result) != 'ok') {
                return redirect(
                    route('extension_server_settings_page', [
                        'extension_id' => extension()->id,
                        'server_id' => server()->id,
                    ])
                )
                    ->withInput()
                    ->withErrors([
                        'message' => $result,
                    ]);
            }
        }
        system_log(7, 'EXTENSION_SETTINGS_UPDATE', [
            'extension_id' => extension()->id,
            'server_id' => server()->id,
        ]);

        return redirect(
            route('extension_server', [
                'extension_id' => extension()->id,
                'server_id' => server()->id
            ])
        );
    }

    /**
     * Return extension settings page
     *
     * @return JsonResponse|Response
     * @throws \Exception
     */
    public function serverSettingsPage()
    {
        $extension = json_decode(
            file_get_contents(
                '/liman/extensions/' .
                strtolower((string) extension()->name) .
                DIRECTORY_SEPARATOR .
                'db.json'
            ),
            true
        );
        system_log(7, 'EXTENSION_SETTINGS_PAGE', [
            'extension_id' => extension()->id,
        ]);
        $similar = [];
        $globalVars = [];
        $flag = server()->key();
        foreach ($extension['database'] as $key => $item) {
            if (
                ($flag != null && $item['variable'] == 'clientUsername') ||
                ($flag != null && $item['variable'] == 'clientPassword')
            ) {
                unset($extension['database'][$key]);
            }

            $opts = [
                'server_id' => server()->id,
                'name' => $item['variable'],
            ];

            if (!isset($item['global']) || $item['global'] === false) {
                $opts['user_id'] = user()->id;
            }

            $obj = DB::table('user_settings')
                ->where($opts)
                ->first();
            if ($obj) {
                if (array_key_exists('user_id', $opts)) {
                    $key = env('APP_KEY') . user()->id . server()->id;
                } else {
                    $key = env('APP_KEY') . $obj->user_id . server()->id;
                    if ($obj->user_id != user()->id) {
                        array_push($globalVars, $item['variable']);
                    }
                }

                $similar[$item['variable']] = AES256::decrypt(
                    $obj->value,
                    $key
                );
            }
        }

        return magicView('extension_pages.setup', [
            'extension' => $extension,
            'similar' => $similar,
            'extensionDb' => extensionDb(),
            'globalVars' => $globalVars,
        ]);
    }

    /**
     * Force enable extension
     *
     * @return JsonResponse|Response
     */
    public function forceEnableExtension()
    {
        $flag = extension()->update([
            'status' => '1',
        ]);

        if ($flag) {
            return respond('Eklenti başarıyla aktifleştirildi!');
        } else {
            return respond('Eklenti aktifleştirilirken bir hata oluştu!', 201);
        }
    }

    /**
     * Force dependency install
     *
     * @return JsonResponse|Response
     * @throws GuzzleException
     */
    public function forceDepInstall()
    {
        $file = file_get_contents('/liman/extensions/' . strtolower((string) extension()->name) . '/db.json');
        $json = json_decode($file, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            return respond('Eklenti dosyası okunurken bir hata oluştu!', 201);
        }

        if (array_key_exists('dependencies', $json) && $json['dependencies'] != '') {
            rootSystem()->installPackages($json['dependencies']);

            return respond('İşlem başlatıldı!');
        } else {
            return respond('Bu eklentinin hiçbir bağımlılığı yok!', 201);
        }
    }

    /**
     * Delete extension from file system
     *
     * @return JsonResponse|Response
     * @throws GuzzleException
     */
    public function remove()
    {
        $ext_name = extension()->name;
        hook('extension_delete_attempt', extension());
        try {
            Command::runLiman(
                "rm -rf '/liman/extensions/{:extension}'",
                [
                    'extension' => strtolower((string) extension()->name),
                ]
            );
        } catch (\Exception) {
        }

        try {
            rootSystem()->userRemove(extension()->id);
            extension()->delete();
        } catch (\Exception) {
        }

        hook('extension_delete_successful', [
            'request' => request()->all(),
        ]);

        if (is_file(storage_path('extension_updates'))) {
            $json = json_decode(file_get_contents(storage_path('extension_updates')), true);
            for ($i = 0; $i < count($json); $i++) {
                if ($json[$i]['name'] == $ext_name) {
                    unset($json[$i]);
                }
            }
            file_put_contents(storage_path('extension_updates'), json_encode($json));
        }

        try {
            $query = Permission::where('value', $ext_name)
                ->where('type', 'function')
                ->where('key', 'name')
                ->delete();
        } catch (\Exception) {
        }

        system_log(3, 'EXTENSION_REMOVE');

        return respond('Eklenti Başarıyla Silindi');
    }

    /**
     * Get files from extensions public folder
     *
     * @return BinaryFileResponse|void
     */
    public function publicFolder()
    {
        $basePath =
            '/liman/extensions/' . strtolower((string) extension()->name) . '/public/';

        $targetPath = $basePath . explode('public/', (string) url()->current(), 2)[1];

        if (realpath($targetPath) != $targetPath) {
            abort(404);
        }

        if (is_file($targetPath)) {
            return response()->download($targetPath, null, [
                'Content-Type' => MimeType::fromExtension(pathinfo($targetPath, PATHINFO_EXTENSION)),
            ]);
        } else {
            abort(404);
        }
    }
}
