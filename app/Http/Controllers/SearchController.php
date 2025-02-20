<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Search Controller
 *
 * Manages the search input on sidebar
 *
 * @extends Controller
 */
class SearchController extends Controller
{
    /**
     * Search retrieved query and return results
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request)
    {
        $searchable = [];

        // Get constant searchables
        if (user()->isAdmin()) {
            foreach (config('liman.admin_searchable') as $constant) {
                $constant['name'] = __($constant['name']);
                array_push($searchable, $constant);
            }
        }

        foreach (config('liman.user_searchable') as $constant) {
            $constant['name'] = __($constant['name']);
            array_push($searchable, $constant);
        }

        // Server searching
        $servers = Server::select('id', 'name')->get()
            ->filter(function ($server) {
                return Permission::can(user()->id, 'server', 'id', $server->id);
            });
        foreach ($servers as $server) {
            if (Permission::can(user()->id, 'liman', 'id', 'server_details')) {
                array_push($searchable, [
                    'name' => $server->name,
                    'url' => route('server_one', $server->id),
                ]);
            }

            // Extension searching
            $extensions = $server->extensions();

            foreach ($extensions as $extension) {
                if (! empty($extension->display_name)) {
                    array_push($searchable, [
                        'name' => $server->name . ' / ' . $extension->display_name,
                        'url' => route('extension_server', [$extension->id, $server->id]),
                    ]);
                    continue;
                }
                array_push($searchable, [
                    'name' => $server->name . ' / ' . $extension->name,
                    'url' => route('extension_server', [$extension->id, $server->id]),
                ]);
            }
        }

        $results = [];
        $search_query = $request->search_query;

        // Searching inside the searchable array
        foreach ($searchable as $search_item) {
            if (str_contains(strtolower((string) $search_item['name']), strtolower((string) $search_query))) {
                array_push($results, $search_item);
            }
        }

        return response()->json($results);
    }
}
