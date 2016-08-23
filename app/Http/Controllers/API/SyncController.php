<?php

namespace App\Http\Controllers\API;

use App\Facades\Media;
use App\Http\Requests\API\SyncRequest;

class SyncController extends Controller
{
    /**
     * Synchronize the library.
     *
     * @param Request $request
     * @param bool    $force
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sync(SyncRequest $request, $force = false)
    {
         // In a next version we should opt for a echo system,
         // but let's just do this async now.
        $results = Media::syncStep($force, $request->input('doneAlready'));
        if (count($results) === 0)
            return response('Not supported on command line', 500);

        return response()->json([ 'lastSong' => $results['lastSong'], 'songsDone' => $results['change'] + $results['nochange'], 'songsFailed' => $results['bad'], 'songsNoChange'=> $results['nochange'], 'songsTotal' => $results['count'] ]);
    }
}
