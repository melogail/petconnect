---
paths:
  - vite.config.ts
---

# Vite

## Vite watcher ignores extend the defaults; count directories, not files
Three settled points about `server.watch.ignored`, from UI port Phase 1.

**inotify allocates one watch per directory, not per file.** File counts are the wrong metric and produced two wrong diagnoses this phase. When you size a watch list, count directories.

**Vite prepends its own defaults — the user array extends, it does not replace.** `resolveChokidarOptions` spreads the user array *after* `.git`, `node_modules`, `test-results` and the cache dir, so anything already on that default list is redundant in `server.watch.ignored`. This was established **by reading the installed Vite source**, not by measurement — weigh it as such, and if it ever matters more than it does now, run it.

**The numbers, each named against the path it was measured on.** Two figures get confused because they have different referents: `storage/` **in total** is **18,102 directories**, of which `storage/app/public/media` **alone** is **17,878**. The `fe6ea47` commit message quotes the first ("18,102 storage directories"); the watch-list entry that actually matters is the second. `vendor` (**2,529 directories**) is real work because it is *not* a Vite default. `public` (**6 directories**) is not worth the reload-on-change it costs.

**What makes the count actionable is the ceiling: 65,536.** The default `fs.inotify.max_user_watches` is 65,536 watches **per user**, not per process — so ~18k directories under `storage/` is more than a quarter of the whole budget spent on one project's dev server. Left unignored, `npm run dev` dies with **ENOSPC** the moment it shares that ceiling with an editor's file watcher and a second project's dev server. It reads like a disk-space error and is not one. A bare directory count is trivia; the ceiling and the symptom are what let you decide.

**Re-measure it — unlike the other two, this figure rots.** `vendor` is pinned by `composer.lock` and `public` by the repo, so their counts move only when a dependency or a commit moves them. `storage/app/public/media` grows with every upload, so its count is stale the day after it is written. Re-run, do not trust:

    find storage -type d | wc -l                    # storage/ total
    find storage/app/public/media -type d | wc -l   # media alone

**Measured 2026-09-02 by running exactly those two commands** on this working tree: **18,322** and **18,098**. The 18,102 / 17,878 pair is the UI port Phase 1 snapshot and was real on the day it was taken — 220 directories of uploads landed between the two readings, while the gap *within* each pair is a constant 224 non-media directories under `storage/`. Both pairs are correct readings of different days, which is the whole point: quote one of these figures with its date and its path, or not at all.
