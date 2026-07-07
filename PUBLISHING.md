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
3. Update `CHANGELOG.md` with the new version, and (optionally) bump the
   `FALLBACK` line in `src/Version.php` to match — the **git tag is the real
   version**, so this is only cosmetic for people installing from source.
4. **Tag and push:**

   ```bash
   git tag v0.1.0
   git push origin main --tags
   ```

That's it. Packagist ingests the tag automatically. `composer require
nombaone/nombaone-php` now resolves the new version.

- **A merge to `main` with no new tag publishes nothing.** Only a tag releases.
- **Re-tagging an existing version is impossible** — git rejects a duplicate
  tag, so you can never accidentally overwrite a release.
- **Versions are semver.** Start at `v0.1.0`; bug fixes bump the patch (`v0.1.1`),
  new methods bump the minor (`v0.2.0`), breaking changes bump the major.

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
