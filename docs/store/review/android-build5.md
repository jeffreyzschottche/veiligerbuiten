# Android 1.0 (5): startup crash in the background engine

The tester video `/Users/jeffreyzschottche/Desktop/fiverrelende.mp4` shows the app
returning to the launcher immediately after its splash screen.

Google Play Vitals confirmed two foreground crashes in release 4 on Android 15
and 16, on Infinix X6858 and TECNO KN3 devices. Its native stack reports:

```
terminating due to uncaught exception of type std::runtime_error:
java.lang.ClassNotFoundException: io.ionic.android_js_engine.NativeWebAPI
Java_io_ionic_android_1js_1engine_Runner_initRunner
io.ionic.backgroundrunner.plugin.BackgroundRunnerPlugin.load
nl.veiligerbuiten.app.MainActivity.onCreate
```

Crash report:
https://play.google.com/console/u/0/developers/5489700749851720516/app/4975702237827842044/vitals/crashes/acc4c672179953357994fc707b45db61/details?days=28&isUserPerceived=true

R8 removed classes used only through JNI and renamed argument types and fields.
The JS engine AAR ships an empty `proguard.txt`. This happens before location or
account handling; it is unrelated to tester country availability.

Build 5 adds narrowly scoped keep rules for `io.ionic.android_js_engine.**`,
including members, and Capacitor's runtime annotation types. Release minification
remains enabled. Android versionCode is now 5; versionName remains 1.0. No new iOS
submission is needed for these Android-only build rules.

Validation:

- Gradle `:app:assembleRelease :app:bundleRelease` passed with release signing.
- `apksigner verify --print-certs` passed with the VeiligerBuiten certificate.
- `aapt dump badging` confirms `nl.veiligerbuiten.app`, versionCode 5, target SDK 36.
- `node docs/store/verify-android-release.mjs APK` checks class definitions in the
  actual minified DEX against the original engine JAR and permission annotations.
  It fails on the published build 4 APK (19 missing/renamed definitions, including
  NativeWebAPI) and passes on build 5 (all 23 checked definitions present).
- An attempted local emulator check could not be completed reliably because of
  insufficient host disk/memory resources. The disposable emulator was stopped
  and removed. Runtime verification on the tester's phone is still needed.

Artifact: `frontend/android/app/build/outputs/bundle/release/app-release.aab`

SHA-256: `53c7e95ca4b2a1952cfdcfc23bdb26f2ce691bf345dfac24e36a8dd151717548`

Build 5 was uploaded to the existing closed Alpha track, with Dutch and English
release notes and a 100% rollout to its selected testers. Submission was confirmed
in Play Console: **Wijzigingen worden beoordeeld**. Automated checks were still
running (the console estimated up to 14 minutes); the change is sent onwards when
those checks finish. Managed publishing is off, so approval permits publication.
This does not mean the tester can already download the update.

Publishing overview:
https://play.google.com/console/u/0/developers/5489700749851720516/app/4975702237827842044/publishing
