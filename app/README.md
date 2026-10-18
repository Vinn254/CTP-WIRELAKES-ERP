# CTP-WireLakes member app (Flutter)

The member portal in the founder's pocket. Every **CTP** and every **E-Fundi grade** gets the
same pages they already have on the website, built natively in Flutter. Staff and the super
admin do not use this app — the office stays on the website.

## One backend, not a second system

The app talks to the same Laravel + MySQL installation in `../web` over the JSON API in
`web/routes/api.php` (`/api/*`, Sanctum bearer token). It has no database of its own and no
rules of its own:

- the member is resolved from the token, never passed in a url;
- every endpoint calls the same model validators and support classes the Blade portal calls
  (`MemberPortal`, `Marketplace`, `Execution`, `Construction`, `Technology`, `TrainingTrack`, `Finance`);
- a screen the web sidebar hides for a role returns 403 here too, so the app cannot show a page
  the website would not show.

## What each member sees

| Role | Pages |
| --- | --- |
| CTP (trainee) | Overview · My training · My mobilisation · My communities · E-Waste collection · My attachment work · Items to put right · My projects · My applications · My profile |
| E-Fundi (all grades) | Overview · My E-Fundi record · Invited to you · E-Waste collection · My work orders · Items to put right · My repair jobs · Purchase request · My earnings · My projects · My applications · My profile |
| Senior fundi approved to train | the E-Fundi list **plus** Training CTPs |

The list is built in `lib/screens/member_pages.dart`, which mirrors the `$nav` in
`web/resources/views/layouts/member.blade.php` page for page, in the same order, with the same
badges.

Photos go both ways through JSON: an upload is sent as `<field>_b64` and re-attached by the
backend's `UploadsFromJson` trait, so the existing `storePhoto()` / `storeProof()` writers run
unchanged; a private picture comes back as a `data:` uri and opens in the zoomable lightbox.

## Running it

```bash
cd app
flutter pub get
flutter run                       # Android emulator reaches the backend at 10.0.2.2:8000
```

Start the backend first (`cd web && php artisan serve --port=8000`). Anywhere else, point the
app at the machine serving the API:

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000
```

Sign in with the same member email and password used on the web portal — there is no separate
app account.

## Layout

```
lib/
  config/api_config.dart      base url
  services/api_client.dart    every endpoint, typed, plus the token store
  models/                     the JSON shapes, one file per area
  widgets/ui.dart             palette, cards, tags, photo picker, the shared form sheet
  screens/                    one screen per portal page + the drawer shell
```
