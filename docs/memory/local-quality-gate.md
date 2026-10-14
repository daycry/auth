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
vendor/bin/rector process --dry-run        # MUST be clean; exit code 2 = changes pending = FAIL (add --debug if workers time out)
vendor/bin/php-cs-fixer fix --dry-run      # style (see CRLF caveat below)
composer inspect                           # deptrac architecture
mkdocs build --strict                      # only when docs changed
```

`composer ci` chains cs + deduplicate + inspect + analyze + test, but **Rector
dry-run is the one most easily forgotten** when running checks à la carte.

## Windows/Laragon performance traps (learned 2026-10-13, deps sync)

- **Disable Xdebug for the gate.** Laragon's `php.ini` loads Xdebug with
  `xdebug.mode=develop,debug,coverage`; the suite then runs ~50× slower
  (3 s/test, >1 h) and looks hung. Prefix every gate command with
  `XDEBUG_MODE=off` (the full suite then takes ~15 min locally vs ~2 min in CI).
- **Rector parallel workers time out** (`Child process timed out after 120
  seconds` → "results for N of M files are missing") on this machine. A partial
  run is *not* a clean run. Use `vendor/bin/rector process --dry-run --debug`
  (serial) to get a complete verdict.
- **PHPStan/Rector CI runs on PHP 8.5.** After regenerating the baseline on the
  default PHP, re-validate it with a Laragon 8.5 binary, e.g.
  `/c/laragon/bin/php/php-8.5.11-nts-Win32-vs17-x64/php.exe -d memory_limit=2G vendor/phpstan/phpstan/phpstan analyse`.

## The CRLF caveat (Windows working copy)

`git` stores **LF** (`git ls-files --eol` → `i/lf`), but the working copy is
**CRLF** (`core.autocrlf=true`), so `php-cs-fixer` flags `line_ending` on the
whole repo locally. (Don't verify this with `grep -c $'\r'` in Git Bash — it
reports every line; trust `git ls-files --eol`.) To see the *real*
style verdict for the files you changed — without the line-ending noise — scope
the run to your files. When passing **multiple** explicit paths you must also
pass `--config` and `--path-mode=intersection`:

```bash
FILES="src/Foo.php tests/FooTest.php"   # only the files you touched
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php \
    --path-mode=intersection --dry-run --using-cache=no $FILES
```

A clean scoped result only proves **your** files are fine. It does **not** prove
CI's whole-repo run will pass: a php-cs-fixer / `nexusphp/cs-config` upgrade can
start flagging files you never touched (2026-10-13: Email2FA/EmailActivator
after a `composer update` — CI failed while the scoped run was clean). And on
this CRLF checkout a whole-repo run is useless: `\r` also triggers
`align_multiline_comment`, `method_argument_space`, … on ~130 files.

**Exact CI replica** — run the fixer on a worktree checked out *without* CRLF
conversion (it reproduced CI's verdict file-for-file):

```bash
W="$TMPDIR/lfcheck"; git -c core.autocrlf=false worktree add --detach "$W" HEAD
(cd "$W" && XDEBUG_MODE=off php "$OLDPWD/vendor/bin/php-cs-fixer" fix --dry-run --using-cache=no)
git worktree remove --force "$W"
```

Run it on the commit you are about to push (commit first, then check `HEAD`).
Always **re-create** the worktree for each check: a later `git -C "$W" checkout`
uses the global `autocrlf=true` again, re-introduces CRLF and gives a false
whole-file `line_ending` failure.
Confirm the committed change is noise-free with `git diff`, which compares
content after line-ending conversion.

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
