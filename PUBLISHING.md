# Publishing `nombaone/nombaone-php`

For the person who owns the release — you do **not** need to be a PHP developer.
Publishing this SDK to [Packagist](https://packagist.org) (the Composer
registry) needs **no tokens and no uploads**: a pushed git tag *is* the release,
exactly like Go. Packagist reads the code straight from GitHub.

Once published, developers install it with:

```bash
composer require nombaone/nombaone-php
```

---

## One-time setup (do this once, ever)

1. **Create the GitHub repo** `github.com/nombaone/nombaone-php` and push this
   repository to it (branch `main`). The package name is already set to
   `nombaone/nombaone-php` in `composer.json`.
2. **Create a Packagist account** at <https://packagist.org> and turn on 2FA.
3. **Confirm the name is free** — search `nombaone/nombaone-php` on Packagist; it
   should not exist yet. (The name comes from `composer.json`, so there's nothing
   to type.)
4. **Submit the package once:** Packagist → **Submit** → paste
   `https://github.com/nombaone/nombaone-php` → **Check** → **Submit**. Packagist
   reads `composer.json` and registers `nombaone/nombaone-php`.
5. **Turn on auto-updates:** on your Packagist profile click **Connect GitHub**
   (or add the Packagist webhook to the repo). After this, every tag you push
   appears on Packagist within seconds — no button-clicking per release.

That's the whole setup. No API token ever lives on a laptop or in CI.

---

## The release ritual (every release)

1. Make sure CI on `main` is **green** (lint + static analysis + tests on PHP
   8.2 / 8.3 / 8.4).
2. Run the live proof once (needs a sandbox key from the operator):

   ```bash
   NOMBAONE_API_KEY=nbo_sandbox_… composer verify
   ```

   Read the last line — it must say `… DEFECTS 0`.
3. **Bump the one version line** in `src/Version.php`:

   ```php
   public const FALLBACK = '0.2.0';   // was '0.1.0'
   ```

   and add the matching entry to `CHANGELOG.md`.
4. **Merge to `main`.** That's the whole ritual.

When `main` goes green, CI reads that version line and — if no `v0.2.0` tag
exists yet — creates and pushes it for you (`.github/workflows/ci.yml`, the
`tag` job). Packagist ingests the new tag via its webhook within seconds.
`composer require nombaone/nombaone-php` then resolves the new version. No
manual tagging, no tokens, no laptop uploads.

- **A merge that doesn't change the version line is a no-op** — the tag already
  exists, so CI releases nothing.
- **Re-releasing a version is impossible** — the tag already exists and git
  never overwrites it.
- **Versions are semver.** Bug fixes bump the patch (`0.1.1`), new methods the
  minor (`0.2.0`), breaking changes the major.
- Want to cut a tag by hand instead? `git tag v0.2.0 && git push origin v0.2.0`
  does the same thing; the CI job just automates it.

---

## Post-publish clean-room check (once, after the first release)

Prove the *published* package installs from scratch:

```bash
mkdir /tmp/nombaone-smoke && cd /tmp/nombaone-smoke
composer require nombaone/nombaone-php guzzlehttp/guzzle
php -r 'require "vendor/autoload.php";
  $c = new NombaOne\Nombaone(getenv("NOMBAONE_API_KEY"));
  echo $c->customers->create(["email"=>"clean-".time()."@example.com","name"=>"Clean Room"])->id, "\n";'
```

A printed `nbo…cus` id means the released package works end-to-end.
