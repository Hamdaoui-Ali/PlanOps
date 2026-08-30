# PlanOps hero preview design QA

- source visual truth: `C:/Users/aliha/.codex/generated_images/01a05241-5ef7-74e2-a03e-ee016ebbce83/exec-6a00dd27-1832-4eaa-98dc-32c489ee2f1d.png`
- implementation screenshot: unavailable; the in-app browser could not connect and no local browser executable or Playwright dependency is installed
- viewport: intended desktop 1536 x 1024; responsive rules also reviewed for <=700px
- state: public landing page, dark theme, hero preview visible
- source and implementation dimensions: source 1536 x 1024; implementation capture unavailable; CSS preview is 16:13 on desktop and 16:14 on mobile with `width: 100%` and `overflow: hidden`
- density normalization: not applicable; no implementation pixels captured

## Full-view comparison

The source image was opened and used as the visual target. The implementation was not available as a screenshot, so a pixel-level comparison cannot be completed.

## Focused region comparison

The hero product-preview region is the only changed region. The previous dense board was replaced with a dedicated fixed-ratio mockup component and the implementation test now guards its layout structure.

## Findings and fixes

- [P1 fixed] Dense production-like board collapsed inside the hero. Replaced with `resources/views/components/dashboard-preview.blade.php`.
- [P1 fixed] Preview had incompatible class contracts and inner overflow. New preview owns its fixed aspect ratio, hides overflow at the outer frame, and scales the whole mockup as one unit.
- [P2 fixed] The mockup was visually too short and lacked useful product density. Added a Blocked column, additional task states, and KPI coverage for blocked work.
- [P2 fixed] Remaining unused vertical space under the KPI row. Added a compact Recent Activity strip with three readable events.
- [P2 blocked] Browser-rendered visual comparison and console check could not run because no browser connection or local browser executable was available.

## Verification

- `npm.cmd run build` passed.
- `php artisan test tests/Feature/PublicSurfaceTest.php --no-ansi` passed: 3 tests, 36 assertions.
- `git diff --check` passed.
- final result: blocked
