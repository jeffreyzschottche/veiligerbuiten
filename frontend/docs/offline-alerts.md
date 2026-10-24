# Offline proximity alerts and landing examples

The app synchronizes relevant reports into a local snapshot, then compares the phone's position with that snapshot. A report does not have to become known after connectivity is lost: movement can bring a previously saved report into the user's alert radius.

## Behaviour

- The selected 15, 30 or 60 minute interval governs refreshes. Moving more than 10 km from the last snapshot centre also requests a refresh. The fetched area remains 30 km around that centre.
- While the app is open, GPS watches update the user's position independently of internet connectivity. Local notification evaluation happens before a network refresh, so a stalled request does not hold up the first local check.
- The background runner uses the platform's latest available location. It continues evaluating saved reports if the network is disconnected, a request fails, the server returns an error, or the sync request/body exceeds eight seconds.
- Foreground location events use local-only runner checks. Runner dispatches from the foreground are serialized so sync and check events cannot overwrite each other's saved state.
- Individual report expiry and resolved/expired states are honoured. Snapshots older than six hours and positions outside their saved 30 km area cannot trigger proximity notifications. A locally saved fallback position is usable for at most five minutes when the OS cannot supply a position.
- Existing severity, category, quiet-hours and rate-limit settings still apply. Notifications are marked delivered only after scheduling succeeds. Danger reports excluded by the per-check cap remain eligible for a later check.
- Offline local alerts can cover saved places too, since server push cannot arrive without connectivity. Online checks leave those places to server push. Local notifications from offline data say **Eerder opgeslagen / Saved earlier**.
- Reconnection forces a refresh. The map distinguishes saved/unknown data from a calm area; absent cached data never establishes that an area is safe.
- Signing out clears the runner state. An observed 401/402 disables its configuration. Cached subscription expiry is checked before evaluating reports.

Offline operation cannot discover new reports, confirm that a saved situation still exists, or retrieve changes made after the last sync. Map tiles are a separate online resource; this feature does not download an offline basemap.

## Native execution and permissions

This work uses the existing Capacitor Background Runner and Geolocation plugins. It does not introduce an always-running location service. Foreground movement and the runner's JavaScript logic have been checked; background delivery still needs physical iOS/Android testing with granted location and notification permissions.

The runner exposes the platform's last known location. A fresh background location is not guaranteed. iOS and Android decide when periodic work runs, and battery optimization or location permission restrictions can prevent a check. The requested interval is a preference, not an exact delivery guarantee. See the [official Background Runner documentation](https://github.com/ionic-team/capacitor-background-runner#limitations-of-background-tasks). Existing native permission configuration is preserved.

For a device check:

1. Open the subscribed app online, grant location and notification permissions, and synchronize an active report located outside the initial alert radius.
2. Turn off Wi-Fi and mobile data while retaining location services.
3. Move into that report's radius while the app is open. Check that a local notification says it was saved earlier.
4. Repeat in the background on physical devices, allowing the OS to schedule checks. Record location permission and battery settings.
5. Verify that an expired report, an old snapshot and moving beyond the saved area never produce a proximity alert. Restore connectivity and verify that new data arrives.

Native assets must be rebuilt and synchronized through the existing `npm run cap:build` flow before installing a new device build; this work does not install or publish a native binary.

## Homepage

`OfflineSyncStory.vue` explains fetching, saving, comparing locations and offline limitations in Dutch and English. `AlertExamples.vue` cycles five clearly labelled example reports, with context-specific artwork and a benefit statement:

- People under the influence near a bridge.
- Pickpockets.
- Fire.
- Large group of people.
- Police presence.

The carousel advances every six seconds, pauses on hover/focus or when hidden/offscreen, supports manual selection and previous/next controls, and disables autoplay for reduced motion.

Artwork was generated with the built-in imagegen tool. Final WebP files are in `public/images/alerts/` (`bridge.webp`, `pickpockets.webp`, `fire.webp`, `crowd.webp`, `police.webp`). The full final prompt set is saved in [alert-image-prompts.json](./alert-image-prompts.json); generator originals are preserved. Scenes illustrate examples and do not depict verified live reports.

## Validation

Run `npm test` for the isolated native-runner scenarios. The tests execute the shipped runner in a fresh JS context with persistent key-value storage and simulated native APIs, including the complete sync → disconnect → move → notify sequence.

Production build and Vue TypeScript checks passed. Browser checks verified automatic cycling, pause, all five scenes, Dutch/English, reduced motion and layouts at 320–1440 px. An authenticated browser fixture also verified saved reports and geolocation changes while sync was unavailable, including the unknown-area message after leaving cache coverage. These checks do not replace physical-device notification testing.
