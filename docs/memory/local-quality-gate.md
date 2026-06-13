# Memory — Run the FULL local gate before declaring "green"

> Internal note (excluded from the published docs site). Captures a lesson
> learned while shipping the soft-delete guards (PR #67, 2026-06-13).

## The lesson

**Do not rely on CI to catch what the local gate should catch.** PR #67 was
declared "verified" and pushed, then CI failed two checks — **Rector** and
**PHP CS Fixer** — because the local gate run was incomplete:

1. **Rector dry-run was never run locally.** CI's `Rector (dry-run)` exits `2`
   (= changes pending = failure) when any rule would rewrite the diff. Here
   `RemoveNullArgOnNullDefaultParamRector` wanted `->where($field, null)` →
   `->where($field)`.
2. **PHP CS Fixer was dismissed as "CRLF noise."** On this Windows checkout the
   working copy is **CRLF**, so `composer cs` reports `line_ending` diffs on
   *every* file — masking the *real* style issue (an unordered `use` statement).
   CI runs on **LF**, where `line_ending` is a no-op, so it surfaced the real
   problem the noisy local run hid.

Both were fixed before merge, but the gate should have caught them first.

## The full gate (run ALL of these before pushing / declaring done)

```bash
vendor/bin/phpunit --no-coverage          # tests (1079+ at time of writing)
composer phpstan:check                     # PHPStan level 5 — must say "No errors"
vendor/bin/rector process --dry-run        # MUST be clean; exit code 2 = changes pending = FAIL
vendor/bin/php-cs-fixer fix --dry-run      # style (see CRLF caveat below)
composer inspect                           # deptrac architecture
mkdocs build --strict                      # only when docs changed
```

`composer ci` chains cs + deduplicate + inspect + analyze + test, but **Rector
dry-run is the one most easily forgotten** when running checks à la carte.

## The CRLF caveat (Windows working copy)

`git` stores **LF** (autocrlf), but the working copy is **CRLF**, so
`php-cs-fixer` flags `line_ending` on the whole repo locally. To see the *real*
style verdict for the files you changed — without the line-ending noise — scope
the run to your files. When passing **multiple** explicit paths you must also
pass `--config` and `--path-mode=intersection`:

```bash
FILES="src/Foo.php tests/FooTest.php"   # only the files you touched
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php \
    --path-mode=intersection --dry-run --using-cache=no $FILES
```

A clean result here (`"files":[]`) means CI's LF run will pass. Confirm the
*committed* change is noise-free with `git diff` — git normalises line endings,
so a content-only diff is what CI sees.

`git commit --no-verify` is acceptable **only** to bypass the environmental CRLF
`pre-commit` hook (which runs `php-cs-fixer` over the whole CRLF working copy and
always fails locally). Disclose it in the commit message. Never use it to skip a
real quality check.

## Idiom confirmed by this work

`->where('column')` (single argument) builds `column IS NULL` in CI4's query
builder — the same as `->where('column', null)` but without the redundant arg.
The codebase already uses this for `until_at` / `revoked_at`; the soft-delete
guards follow suit. Rector enforces it via `RemoveNullArgOnNullDefaultParamRector`.

## See also

- `CLAUDE.md` → Commands (the canonical gate list)
- [Authentication → Deleted Users](../03-authentication.md#deleted-users)
- [Authorization → Soft-Deleted Records](../06-authorization.md#soft-deleted-records)
