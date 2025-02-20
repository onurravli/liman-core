<?php

namespace App\Http\Controllers\HASync;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\Module;
use App\System\Command;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * High Availability Sync Controller
 *
 * This class utilizes needed private functions for high availability sync between Limans
 *
 * @extends Controller
 */
class MainController extends Controller
{
    /**
     * Returns extension list on system
     *
     * @return JsonResponse
     * @throws GuzzleException
     * @throws GuzzleException
     */
    public function extensionList()
    {
        $extensions = Extension::all();

        $list = [];
        foreach ($extensions as $extension) {
            $list[] = [
                "id" => $extension->id,
                "name" => strtolower($extension->name),
                "version_code" => (int) str_replace('.', '', $extension->version),
                "download_path" => route("ha_download_ext", [
                    "extension_name" => $extension->name
                ]),
                "key_content" => Command::runSystem('cat ' . "/liman/keys/" . $extension->id)
            ];
        }

        return response()->json($list);
    }

    /**
     * Returns extension zip
     *
     * @return BinaryFileResponse
     */
    public function downloadExtension()
    {
        // Generate Extension Folder Path
        $path = '/liman/extensions/' . strtolower((string) request('extension_name'));
        $tempPath = '/tmp/' . Str::random() . '.zip';

        // Zip the current extension
        Command::runLiman('cd @{:path} && zip -r @{:tempPath} .', [
            'path' => $path,
            'tempPath' => $tempPath,
        ]);

        // Return zip as download and delete it after sent.
        return response()
            ->download(
                $tempPath,
                Str::uuid() . '.zip'
            )
            ->deleteFileAfterSend();
    }

    /**
     * Module list existing on system
     *
     * @return JsonResponse
     */
    public function moduleList()
    {
        $modules = Module::all();

        $list = [];
        foreach ($modules as $module) {
            $list[] = [
                "id" => $module->id,
                "name" => $module->name,
                "updated_at" => $module->updated_at->toDateTimeString(),
                "download_path" => route("ha_download_module", [
                    "module_name" => $module->name
                ]),
            ];
        }

        return response()->json($list);
    }

    /**
     * Returns module zip file
     *
     * @return BinaryFileResponse
     */
    public function downloadModule()
    {
        // Generate Module Folder Path
        $path = '/liman/modules/' . (string) request('module_name');
        $tempPath = '/tmp/' . Str::random() . '.zip';

        // Zip the current module
        Command::runLiman('cd @{:path} && zip -r @{:tempPath} .', [
            'path' => $path,
            'tempPath' => $tempPath,
        ]);

        // Return zip as download and delete it after sent.
        return response()
            ->download(
                $tempPath,
                Str::uuid() . '.zip'
            )
            ->deleteFileAfterSend();
    }
}
