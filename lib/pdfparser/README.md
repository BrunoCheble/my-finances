# smalot/pdfparser v2.13.1 (bundled)

Reads the text of the PDF bank statements (statement import).

- Source: https://github.com/smalot/pdfparser — tag `v2.13.1` (LGPL-3.0, see `LICENSE.txt`), the same version as `composer.lock`.
- Only `src/` and the license are kept, unchanged.

It lives in the repository because the production server has no Composer: the deploy only pulls the repository
(see `docs/DEPLOY.md`). Composer's own copy in `vendor/` wins when it is there (local, tests); otherwise
`App\Providers\AppServiceProvider` loads `Smalot\PdfParser\` from `lib/pdfparser/src/`. To update: `composer update
smalot/pdfparser`, then copy `vendor/smalot/pdfparser/src` here and change the version above.
