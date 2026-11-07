# iOS 1.0 (4) — privacy review fixes

Apple rejected build 3 on 5 November 2026 (submission
`28d13adc-b5a8-4756-a445-5c6d8edf5367`):

- 5.1.2(i): App Privacy declares tracking, but the reviewer did not encounter ATT.
  ATT previously ran only when an ad became eligible after three app opens.
- 5.1.1(iv): the location explanation used “Toestaan” and allowed skipping with
  “Later” before Apple's system permission request.

Build 4 requests ATT at the first app mount, independently of login, subscription,
ad frequency and UMP geography. Concurrent callers share the request; requests
wait for the app to be active. Advertising waits for a determined tracking status.
Denied/restricted ATT uses non-personalized banner requests. Existing system
decisions remain respected.

The iOS setup explanation has one neutral “Verder” / “Continue” button. It waits
for ATT, then requests location and notifications sequentially through iOS. Denial
still permits entry into the app. Android's setup and disclosure remain unchanged.
The iOS location purpose string now describes approximate area refreshes and
location validation for reports.

Validation on 6 November 2026:

- All 32 Node tests passed, including 10 tracking permission cases.
- Nuxt mobile generation and Capacitor iOS sync passed.
- Chrome preview of the generated bundle with mocked iOS APIs verified a single
  neutral setup button, no ad/location request before ATT resolved, sequential
  location/notification requests and continued navigation after denial. This is
  browser validation, not a physical iPhone test.
- Xcode Release archive and App Store distribution export succeeded for
  `nl.veiligerbuiten.app`, version 1.0, build 4, team `322XX6NN87`.
- App Store Connect upload succeeded at 01:05 CET. GoogleMobileAds and
  UserMessagingPlatform emitted missing vendor dSYM warnings; upload succeeded.
- Review notes updated in App Store Connect with both fixes and reproduction steps.

Archive and exported IPA are under `/private/tmp/veiligerbuiten-ios-review/`.
Apple finished processing build 4. It replaced build 3 in version 1.0, and the
existing submission was resubmitted on 6 November 2026 at 01:16 CET. App Store
Connect confirmed **Waiting for Review** for the app version, Premium maandelijks
subscription and VeiligerBuiten Premium subscription group. This is submission
confirmation, not an approval from Apple.

Submission: https://appstoreconnect.apple.com/apps/6826950469/distribution/reviewsubmissions/details/28d13adc-b5a8-4756-a445-5c6d8edf5367

# Android tester access

Verified in Play Console on 6 November 2026: Alpha release 4 (1.0) is active and
available to selected testers in 178 countries/regions, including Pakistan and the
rest-of-world selection. The selected VeiligerBuiten tester mailing list has 17
addresses. Console dashboard showed zero opted-in testers at the time of checking.

Web opt-in: https://play.google.com/apps/testing/nl.veiligerbuiten.app

Store/install: https://play.google.com/store/apps/details?id=nl.veiligerbuiten.app

Suggested message for Zubair:

> Hi Zubair, the Android test is now live and country availability has been approved.
> Please open https://play.google.com/apps/testing/nl.veiligerbuiten.app using the
> Google account you provided for the tester list, choose “Become a tester”, then
> install the app from Google Play. Please use that same Google account in the
> Play Store. Let me know if you still get “App not available”.
