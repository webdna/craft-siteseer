---
spec: Siteseer — visits from the command line
slug: console-visits
status: draft
version: 0.1
date: 2026-10-20
author: Claude (with Sam Birch)
client: webdna (Craft plugin)
approver: Sam Birch
related: [webdna/craft-siteseer#1]
---

# Siteseer — visits from the command line

> **Status:** draft - **Version:** 0.1
> Everything the Siteseer utility can be told to visit can be told to `./craft siteseer/visit`
> instead, and the command either visits the pages there and then or queues the same batched
> jobs the utility's "Run a scan" button does.

---

## 1. How it works

The utility builds an itinerary from five choices: which sites, which entry sections, which
category groups, which Commerce product types, and which extra URIs (typed in, and optionally
the static URIs from `config/siteseer.php`). It can also take snapshots. "Run a scan" turns the
itinerary into queue jobs.

`./craft siteseer/visit` takes the same five choices as options, by handle or by id, so a
command line written once works on every environment. Leaving a choice out means all of it;
the console default is the utility's default (every box ticked). By default it visits each URL
in turn and prints the result, as `siteseer/default/visit` already does. With `--queue` it
builds exactly the jobs "Run a scan" builds and returns at once.

A real-time run ends with a summary line and fails (exit 1) when any page did not answer 200,
so a deploy script or a scheduled job can act on a broken page. A queued run reports how many
URLs it queued; the errors land in the utility's error table as before.

## 2. Scope

**In:** issue #1 — a command that runs the utility's visits, real-time and queued; handles as
well as ids; `*` working as "all"; an exit code for a real-time run; the two itinerary defects
that stop manual URIs being visited (they are what the command's `--manual` relies on).

**Out:** saving a utility configuration to replay later (the issue asks for the visits to be
configured *through* the command, and a command line is already a saved configuration); a
schedule built into the plugin (schedule-it or cron runs the command); any change to the
snapshot feature; Craft 4 support (the code already calls Craft 5 APIs); posting on or closing
the GitHub issue.

## 3. User journeys

1. A developer runs `./craft siteseer/visit` after a deploy. Every page on every site is
   visited in turn, each line shows its status, and the run ends `Visited 412 URLs on 2 sites,
   3 failed` with exit 1.
2. The same site schedules `siteseer/visit --sections=news,blog --queue` nightly. The command
   queues the batched jobs and exits 0; the queue worker visits them and errors appear in the
   utility.
3. A developer warms two static pages and the config URIs: `./craft siteseer/visit
   --sections= --groups= --types= --manual=contact,about --include-config=1`.

## 4. Data model

None. No settings, tables or migrations change.

## 5. Rules

- **BR-1** `siteseer/visit` (and its existing long form `siteseer/default/visit`) builds its
  itinerary with the same service call the utility's controller makes, so a choice gives the
  same URLs from both.
- **BR-2** `--sites`, `--sections`, `--groups` and `--types` each take a comma-separated list of
  handles or ids, or `*`. Left out (or `*`), the choice is all of them. Given as empty
  (`--sections=`), it is none. A numeric value is an id; anything else is a handle.
- **BR-3** An unknown handle or id is refused before anything is visited or queued: the command
  names it and exits 2 (usage error). `--types` given on a site without Commerce is refused the
  same way, unless empty.
- **BR-4** `--manual` takes comma-separated URIs; `--include-config=1` adds the config file's
  `manualUris`. Both are visited on every chosen site. Neither is included by default.
- **BR-5** Without `--queue`, the visits run in the command's process, one line per URL as now,
  then one summary line: URLs visited, sites, failures. Exit 1 when any URL failed (not 200, or
  no response), else 0. Each failure is also saved to the error table, as now.
- **BR-6** With `--queue`, the command pushes one batched `SiteseerJob` per site — what "Run a
  scan" pushes — prints how many URLs on how many sites it queued, and exits 0. A site with no
  URLs pushes no job.
- **BR-7** `--snapshot=1` deletes the existing snapshots and takes new ones, queued or not, as
  the utility's checkbox does.
- **BR-8** An itinerary with no URLs prints that nothing matched and exits 0.
- **BR-9** Manual URIs reach the itinerary from both the command (strings) and the utility
  (editable-table rows): each is visited once per chosen site.

## 6. Interfaces

| Surface | Change |
|---|---|
| `./craft siteseer/visit` | New short route to the existing action. |
| `--queue` | New option. |
| `--sites`, `--sections`, `--groups`, `--types` | Accept handles and `*` as well as ids. |
| `--include-config`, `--snapshot`, `--manual` | Unchanged (`--includeconfig` still accepted). |
| Exit code | 1 when a real-time visit failed; 2 for an unknown handle or id. |
| `VisitService::visit()` | Returns the number of failed URLs (was `void`). |
| `ItineraryService::resolveIds()` | New: handles/ids/`*` to ids for one kind, or throws naming the unknown one. |
| README, CHANGELOG | Command section rewritten; entry added. |

## 7. Test plan

All in the craft5 sandbox, with the plugin mounted from `~/Projects/plugins/craft-siteseer`.

| Id | Scenario | Expect |
|---|---|---|
| TS-1 | `siteseer/visit` with no options | Every section's, group's and product type's URLs on every site, one line each, summary line; exit 0 when all 200 |
| TS-2 | `--sections=<handle> --groups= --types=` | Only that section's entries |
| TS-3 | Same with the section's id, and with `--sections="*"` | Id gives TS-2's list; `*` gives TS-1's sections |
| TS-4 | `--manual=no-such-page` plus a section | The 404 printed red, saved to the error table; summary counts 1 failed; exit 1 |
| TS-5 | `--queue` with TS-2's options | One job per site in the queue, "Queued N URLs on M sites"; exit 0; running the queue visits the same N URLs |
| TS-6 | `--include-config=1 --sections= --groups= --types=` | The config's `manualUris` visited on each site |
| TS-7 | Utility "Run a scan" with a manual URI typed in | Job queued and the URI visited (was: error on the row array) |
| TN-1 | `--sections=nope` | "Unknown section: nope", exit 2, nothing visited or queued |
| TN-2 | Everything empty (`--sections= --groups= --types=`) | "Nothing to visit", exit 0 |

## 8. Build order

### Tasks

- [ ] **1.1 Itinerary fixes** — `src/services/ItineraryService.php`
      Rules: BR-4, BR-9 · Verify: TS-6, TS-7
- [ ] **1.2 Command: selection, queue, exit code** — `src/console/controllers/DefaultController.php`,
      `src/services/ItineraryService.php`, `src/services/VisitService.php`, `src/Siteseer.php`
      Rules: BR-1–BR-8 · Verify: TS-1–TS-5, TN-1, TN-2
- [ ] **1.3 Docs** — `README.md`, `CHANGELOG.md` · Verify: README examples run as written

## Appendix B — build context

- **Preconditions:** craft5 running (`ddev` from lowercase `~/projects/craft5`); the plugin
  bind-mounted at `plugins/craft-siteseer` and installed.
- **Traps:** Yii splits a comma string into an array when the option's default is `[]`, so `*`
  arrives as `['*']`, and `--sections=` arrives as `['']`. The plugin has no test suite; craft5
  is the proof. Never commit in craft5.
- **Done:** every TS/TN row observed in craft5; `php -l` clean on every changed file.
