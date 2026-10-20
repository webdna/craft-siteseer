# Release Notes for Siteseer

## 1.1.0 - 2026-10-20
- Added `./craft siteseer/visit`, short for `siteseer/default/visit`. ([#1](https://github.com/webdna/craft-siteseer/issues/1))
- Added the `--queue` option, which pushes the same batched jobs as the utility's "Run a scan" button.
- `--sites`, `--sections`, `--groups` and `--types` now take handles as well as IDs; `*` now means all, and an empty value means none.
- A real-time run now ends with a summary and exits `1` when any page failed, or `64` for an unknown handle or ID.
- Added `--include-config` (the old `--includeconfig` still works).
- Fixed manual URIs never being visited: the itinerary dropped them, and rows from the utility's table caused an error.
- Fixed failed requests being printed as `UNKNOWN` rather than their status code.
- Added ddev config resources and README instructions for testing a statically cached domain.
- Made the scheme configurable (`useHttps`).
- Siteseer now requires Craft 5.3 or later; it already called Craft 5 APIs.

## 1.0.0
- Initial release
