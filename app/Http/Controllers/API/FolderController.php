<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;

use App\Application;
use App\Models\Song;
use App\Models\Setting;
use App\Models\User;

use App\Http\Requests;
use App\Http\Controllers\Controller;

class FolderController extends Controller
{
    /**
     * Split the path in the associative array in a hierarchical structure
     */
    public function makeTree($array, $delimiter = DIRECTORY_SEPARATOR)
    {
        $ret = [];
        $split = '/' . preg_quote($delimiter, '/') . '/';
        if (!is_array($array))
            return $ret;

        foreach ($array as $path => $id)
        {
            $pieces = preg_split($split, $path, -1, PREG_SPLIT_NO_EMPTY);
            $lastEl = array_pop($pieces);

            $parent = &$ret;
            foreach ($pieces as $piece) {
                if (!isset($parent[$piece]))
                    $parent[$piece] = [];
                elseif (!is_array($parent[$piece]))
                    $parent[$piece] = [];
                $parent = &$parent[$piece];
            }

            if (empty($parrent[$lastEl]))
                $parent[$lastEl] = $id;
        }
        return $ret;
    }

    /**
     * Map the tree structure to an array usable for JSON
     *
     * @param $key      The current array's key
     * @param $value    The current array's value
     * @return array    The mapped array
     */
    public function mapForJSON($key, $value) {
        if (!is_array($value))
            return array('name' => $key, 'songId' => $value, 'children' => array());

        return array('name' => $key, 'songId' => 0, 'children' => array_map(array($this, __FUNCTION__), array_keys($value), $value));
    }

    /**
     * Get the complete folder hierarchy.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function fullHierarchy()
    {
        $songs = Song::getHierarchy(auth()->user())->toArray();
        $mediaPath = Setting::get('media_path');
        if (!$mediaPath)
            return response('Media path not defined', 404);

        $mediaPathLen = strlen($mediaPath);
        // Convert array of db records to an associative array we'll explode in a tree
        $songPaths = [];
        foreach ($songs as $song)
            $songPaths[substr($song['path'], $mediaPathLen)] = $song['id'];

        // This is the tree from the song paths only. Only element whose value are not array are songs, so let's convert them now
        $tree = $this->makeTree($songPaths);
        $out = array_map(array($this, 'mapForJSON'), array_keys($tree), $tree);

/*
        $out = array('name'=>'Media Library', 'songId' => 0, 'children' => array()); $dir = [];
        $ref = array(''=>&$out['children']);
        $lastPath = '';
        foreach ($songs as $song) {
            $path = substr($song['path'], $mediaPathLen);
            $baseName = pathinfo($path, PATHINFO_DIRNAME);
            $fileName = pathinfo($path, PATHINFO_FILENAME);
            if ($baseName != $lastPath) {
                // Walk down the hierarcy in output (creating missing path on the fly)
                $this->walkDown($out, $baseName)['children'] = $dir;
                $dir = [];
                $lastPath = $baseName;
            }
            $dir[] = array('name' => $fileName, 'songId' => $song['id'], 'children' => array());
        }
        if (count($dir))
            $this->walkDown($out, $lastPath)['children'] = $dir;
*/
        return response()->json(array('name'=>'Media Library', 'songId'=>0, 'children' => $out));
    }
}
