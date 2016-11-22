<?php

namespace App\Services;

use App\Console\Commands\SyncMedia;
use App\Events\LibraryChanged;
use App\Libraries\WatchRecord\WatchRecordInterface;
use App\Models\Album;
use App\Models\Artist;
use App\Models\File;
use App\Models\Setting;
use App\Models\Song;
use App\Models\Genre;
use getID3;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Finder\Finder;

class Media
{
    /**
     * All applicable tags in a media file that we cater for.
     * Note that each isn't necessarily a valid ID3 tag name.
     *
     * @var array
     */
    protected $allTags = [
        'artist',
        'album',
        'title',
        'length',
        'track',
        'disc',
        'genre',
        'year',
        'lyrics',
        'cover',
        'mtime',
        'compilation',
    ];

    /**
     * Tags to be synced.
     *
     * @var array
     */
    protected $tags = [];

    public function __construct()
    {
    }

    /**
     * Sync the media. Oh sync the media.
     *
     * @param string|null $path
     * @param array       $tags        The tags to sync.
     *                                 Only taken into account for existing records.
     *                                 New records will have all tags synced in regardless.
     * @param bool        $force       Whether to force syncing even unchanged files
     * @param SyncMedia   $syncCommand The SyncMedia command object, to log to console if executed by artisan.
     */
    public function sync($path = null, $tags = [], $force = false, SyncMedia $syncCommand = null)
    {
        if (!app()->runningInConsole()) {
            set_time_limit(config('koel.sync.timeout'));
        }

        $path = $path ?: Setting::get('media_path');
        $this->setTags($tags);

        $results = [
            'good' => [], // Updated or added files
            'bad' => [], // Bad files
            'ugly' => [], // Unmodified files
        ];

        $getID3 = new getID3();

        $files = $this->gatherFiles($path);

        if ($syncCommand) {
            $syncCommand->createProgressBar(count($files));
        }

        foreach ($files as $file) {
            $file = new File($file, $getID3);

            $song = $file->sync($this->tags, $force);

            if ($song === true) {
                $results['ugly'][] = $file;
            } elseif ($song === false) {
                $results['bad'][] = $file;
            } else {
                $results['good'][] = $file;
            }

            if ($syncCommand) {
                $syncCommand->updateProgressBar();
                $syncCommand->logToConsole($file->getPath(), $song, $file->getSyncError());
            }
        }

        // Delete non-existing songs.
        $hashes = array_map(function ($f) {
            return self::getHash($f->getPath());
        }, array_merge($results['ugly'], $results['good']));

        Song::deleteWhereIDsNotIn($hashes);

        // Trigger LibraryChanged, so that TidyLibrary handler is fired to, erm, tidy our library.
        event(new LibraryChanged());
    }


    /**
     * Sync the media (step by step).
     *
     * @param Number      $doneAlready The number of files to skip before starting synchronizing
     * @param bool        $force       Whether to force syncing even unchanged files (if set to true, it drops the library)
     * @param Number      $amount      If not zero, compute as many file as this amount before returning
     * @return array  The last sync state
     */
    public function syncStep($force = false, $doneAlready = 0, $amount = 0)
    {
        if (app()->runningInConsole()) {
            return []; // Should not be called from the console (how can it be called ?)
        }

        if ($force) {
            Song::whereNotIn('id', [])->delete();
            $amount = 1; // Force fast reply for the first request
        }

        // Disable the PHP builtin time limit (if run from nginx, it's not this limit that's used anyway)
        set_time_limit(0);
        $totalTime = config('koel.sync.timeout');
        $startTime = microtime(true);

        $path = Setting::get('media_path');
        $this->setTags([]);

        $getID3 = new getID3();

        $files = $this->gatherFiles($path);

        // No need to store the files anymore, only the count
        $results = [
            'change' => 0,   // Updated or added files
            'bad' => [],     // Bad files
            'nochange' => 0, // Unmodified files
            'count' => count($files),
            'lastSong' => 'Starting...',
        ];

        foreach ($files as $file) {
            if ($doneAlready) {
                $results['nochange'] = $results['nochange'] + 1;
                $doneAlready--;
                continue;
            }
            $file = new File($file, $getID3);

            try {
                $song = $file->sync($this->tags, false);
            } catch(\Exception $e) {
                $song = false;
                $file->setSyncError($e->getMessage());
            }

            if ($song === true) {
                $results['nochange'] = $results['nochange'] + 1;
            } elseif ($song === false) {
                $results['bad'][] = $file->getPath().' : '. $file->getSyncError();
            } else {
                $results['change'] = $results['change'] + 1;
                $results['lastSong'] = $song->title . ' <em>by</em> ' . $song->getArtistAttribute()->name;
            }
            if ($amount !== 0 && $results['change'] > $amount) {
                break;
            }
            // Then check if we've spent half our budget time, and in this case, let's estimate the remaining time
            $currentTime = microtime(true);
            if ($amount === 0 && ($currentTime - $startTime) > $totalTime / 2) {
                $amount = (int)($results['change'] * 1.5);
            }
        }

        if ($results['nochange'] + $results['change'] + count($results['bad']) == $results['count']) {
            // Trigger LibraryChanged, so that TidyLibrary handler is fired to, erm, tidy our library.
            event(new LibraryChanged());
        }

        return $results;
    }

    /**
     * Gather all applicable files in a given directory.
     *
     * @param string $path The directory's full path
     *
     * @return array An array of SplFileInfo objects
     */
    public function gatherFiles($path)
    {
        return Finder::create()
            ->ignoreUnreadableDirs()
            ->ignoreDotFiles((bool) config('koel.ignore_dot_files')) // https://github.com/phanan/koel/issues/450
            ->files()
            ->followLinks()
            ->name('/\.(mp3|ogg|m4a|flac)$/i')
            ->in($path);
    }

    /**
     * Sync media using a watch record.
     *
     * @param WatchRecordInterface $record      The watch record.
     * @param SyncMedia|null       $syncCommand The SyncMedia command object, to log to console if executed by artisan.
     */
    public function syncByWatchRecord(WatchRecordInterface $record, SyncMedia $syncCommand = null)
    {
        Log::info("New watch record received: '$record'");
        $path = $record->getPath();

        if ($record->isFile()) {
            Log::info("'$path' is a file.");

            // If the file has been deleted...
            if ($record->isDeleted()) {
                // ...and it has a record in our database, remove it.
                if ($song = Song::byPath($path)) {
                    $song->delete();

                    Log::info("$path deleted.");

                    event(new LibraryChanged());
                } else {
                    Log::info("$path doesn't exist in our database--skipping.");
                }
            }
            // Otherwise, it's a new or changed file. Try to sync it in.
            // File format etc. will be handled by File::sync().
            elseif ($record->isNewOrModified()) {
                $result = (new File($path))->sync($this->tags);
                Log::info($result instanceof Song ? "Synchronized $path" : "Invalid file $path");
            }

            return;
        }

        // Record is a directory.
        Log::info("'$path' is a directory.");

        if ($record->isDeleted()) {
            // The directory is removed. We remove all songs in it.
            if ($count = Song::inDirectory($path)->delete()) {
                Log::info("Deleted $count song(s) under $path");
                event(new LibraryChanged());
            } else {
                Log::info("$path is empty--no action needed.");
            }
        } elseif ($record->isNewOrModified()) {
            foreach ($this->gatherFiles($path) as $file) {
                (new File($file))->sync($this->tags);
            }

            Log::info("Synced all song(s) under $path");
        }
    }

    /**
     * Construct an array of tags to be synced into the database from an input array of tags.
     * If the input array is empty or contains only invalid items, we use all tags.
     * Otherwise, we only use the valid items in it.
     *
     * @param array $tags
     */
    public function setTags($tags = [])
    {
        $this->tags = array_intersect((array) $tags, $this->allTags) ?: $this->allTags;

        // We always keep track of mtime.
        if (!in_array('mtime', $this->tags, true)) {
            $this->tags[] = 'mtime';
        }
    }

    /**
     * Generate a unique hash for a file path.
     *
     * @param $path
     *
     * @return string
     */
    public function getHash($path)
    {
        return File::getHash($path);
    }

    /**
     * Tidy up the library by deleting empty albums, artists and genres.
     */
    public function tidy()
    {
        $inUseAlbums = Song::select('album_id')->groupBy('album_id')->get()->pluck('album_id')->toArray();
        $inUseAlbums[] = Album::UNKNOWN_ID;
        Album::deleteWhereIDsNotIn($inUseAlbums);

        $inUseArtists = Album::select('artist_id')->groupBy('artist_id')->get()->pluck('artist_id')->toArray();

        $contributingArtists = Song::distinct()
            ->select('contributing_artist_id')
            ->groupBy('contributing_artist_id')
            ->get()
            ->pluck('contributing_artist_id')
            ->toArray();

        $inUseArtists = array_merge($inUseArtists, $contributingArtists);
        $inUseArtists[] = Artist::UNKNOWN_ID;
        $inUseArtists[] = Artist::VARIOUS_ID;

        Artist::deleteWhereIDsNotIn(array_filter($inUseArtists));

        $inUseGenres = Song::select('genre_id')->groupBy('genre_id')->get()->pluck('genre_id')->toArray();
        $inUseGenres[] = Genre::UNKNOWN_ID;
        Genre::deleteWhereIDsNotIn($inUseGenres);
    }
}
