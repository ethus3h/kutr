<?php

function parseEnv()
{
    $lines = preg_split ('/$\R?^/m', file_get_contents('.env'));
    $keyVal = array_filter($lines, function($line) { return strlen($line) && $line[0] != '#'; });

    $out = [];
    foreach($keyVal as $key)
    {
        preg_match_all("/([^= ]+)=\"*([^\"]+)\"*/", $key, $cfgKey);
        if (count($cfgKey) > 2 && count($cfgKey[1])) $out[$cfgKey[1][0]] = count($cfgKey[2]) ? trim($cfgKey[2][0]) : "";
    }
    return $out;
}

// Set the secret you've used in your CMS code in .env file
$env = parseEnv();
$redir = $env["REMOTE_AUTH_LOGOUT_URL"] ?: "/";
$secret = $env["REMOTE_AUTH_SECRET"];

$token = isset($_GET["token"]) ? $_GET["token"] : "";
function isSecure() { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443; }
$redir = substr($redir, 4) == "http" ?: (isSecure() ? "https://$redir" : "http://$redir");


function unmakeToken($token)
{
   global $secret;
   $token = base64_decode($token);
   $n = sscanf($token, "%08x%s %d|", $rand, $login, $time);
   $hash = substr(strstr($token, "|"), 1);
   $check = hash('sha256', $rand.$login.$time.$secret, FALSE);
   if ($check == $hash && (time() - $time) < 30) return $login; // Valid for no more than 30s
   return FALSE;
}

$username = unmakeToken($token);
if ($username === FALSE) {
    // Fallback to the default login screen
    header("Location: $redir");
    exit(0);
}

// Ok, user is authentified in our CMS, let's authenticate here too
require __DIR__.'/bootstrap/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$app->make('Illuminate\Contracts\Http\Kernel')
    ->handle(Illuminate\Http\Request::capture());

// An instance of the Laravel app should be now at your fingertip ;-)
$isAuthorized = Auth::check();
if (!$isAuthorized)
{
    // Find user with the given login
    $users = DB::table('users')->get();
    foreach ($users as $user)
    {
        if (strcasecmp($user->name, $username) == 0)
        $auth = Auth::loginUsingId($user->id, true);
        Auth::setUser($auth);
        $jwToken = JWTAuth::fromUser($auth);
        break;
    }
}

$isAuthorized = Auth::check();
if ($isAuthorized)
{   // Need to store the authentication token in the browser's localStorage, so yes, Javascript is required here.
    echo "<html><body></body><script>";
    echo "localStorage.setItem('jwt-token', JSON.stringify('".$jwToken."'));"; // Key is "jwt-token"
    echo "window.location.replace('/');";
    echo "</script></html>";
} else header("Location: $redir");

