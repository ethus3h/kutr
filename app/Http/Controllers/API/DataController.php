<?php

namespace App\Http\Controllers\API;

use App\Application;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Interaction;
use App\Models\Playlist;
use App\Models\Setting;
use App\Models\User;
use Lastfm;
use YouTube;

class DataController extends Controller
{
    /**
     * Get a set of application data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $playlists = Playlist::byCurrentUser()->orderBy('name')->with('songs')->get()->toArray();

        // We don't need full song data, just ID's
        foreach ($playlists as &$playlist) {
            $playlist['songs'] = array_pluck($playlist['songs'], 'id');
        }

        // We don't need songs, the javascript code will figure this out
        $genres = Genre::orderBy('name')->get()->toArray();
        // Don't waste bandwidth sending null values for image
        foreach($genres as &$genre) {
            if (is_null($genre['image'])) unset($genre['image']);
        }

        return response()->json([
            'genres' => $genres,
            'artists' => Artist::orderBy('name')->with('albums', with('albums.songs'))->get(),
            'settings' => auth()->user()->is_admin ? Setting::pluck('value', 'key')->all() : [],
            'playlists' => $playlists,
            'interactions' => Interaction::byCurrentUser()->get(),
            'users' => auth()->user()->is_admin ? User::all() : [],
            'currentUser' => auth()->user(),
            'useLastfm' => Lastfm::used(),
            'useYouTube' => YouTube::enabled(),
            'allowDownload' =>  config('koel.download.allow'),
            'cdnUrl' => app()->staticUrl(),
            'currentVersion' => Application::VERSION,
            'latestVersion' => auth()->user()->is_admin ? app()->getLatestVersion() : Application::VERSION,
        ]);
    }
}
