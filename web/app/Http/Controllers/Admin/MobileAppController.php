<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\MobileApp;
use App\Support\SafeUrl;
use App\Support\Uploads;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * The one place the office decides what a phone installs: the package visitors download from the public site,
 * the version it is, what is new in it, and the Play Store link the desk would rather point at.
 */
class MobileAppController extends Controller
{
    public function index()
    {
        return view('admin.mobile-app.index', [
            'file' => MobileApp::package(),
            'download' => MobileApp::url(),
            'size' => MobileApp::sizeMb(),
            'version' => MobileApp::version(),
            'notes' => MobileApp::notes(),
            'publishedAt' => MobileApp::publishedAt(),
            'playUrl' => MobileApp::playUrl(),
            'downloads' => MobileApp::downloads(),
            'pageUrl' => route('get-the-app'),
        ]);
    }

    /** One save for the whole desk. A package is optional — the link and the words can change on their own. */
    public function save(Request $request)
    {
        $data = $request->validate([
            'package' => ['nullable', 'file', 'max:'.MobileApp::MAX_KB, function (string $attribute, $value, Closure $fail) {
                if ($value instanceof UploadedFile && ! Uploads::isApk($value)) {
                    $fail('That file is not an Android package (.apk). Upload the package the build produced.');
                }
            }],
            'version' => 'nullable|string|max:40',
            'notes' => 'nullable|string|max:600',
            'play_url' => SafeUrl::rule(),
        ], [], [
            'package' => 'app package',
            'version' => 'version',
            'notes' => 'what is new',
            'play_url' => 'Play Store link',
        ]);

        if ($request->hasFile('package')) {
            MobileApp::putPackage($request->file('package'));
        }

        foreach (['app_android_version' => 'version', 'app_android_notes' => 'notes', 'app_play_url' => 'play_url'] as $key => $field) {
            Setting::set($key, trim((string) ($data[$field] ?? '')));
        }

        return back()->with('status', $request->hasFile('package')
            ? 'The new version is up — the public page now offers it to every visitor.'
            : 'The download page details are saved.');
    }

    /** Taking the app down pulls the file off the server and stops the public Download button. */
    public function remove()
    {
        MobileApp::removePackage();

        return back()->with('status', 'The package has been taken down. The public page no longer offers a download.');
    }
}
