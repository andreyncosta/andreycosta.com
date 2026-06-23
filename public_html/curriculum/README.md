# curriculum

Bilingual CV page at [andreycosta.com/curriculum](https://andreycosta.com/curriculum).

## Features

- **PT/EN language toggle** — client-side only, zero page reload, zero JS framework
- **PDF download** — separate files for each language (`download/ptbr.pdf`, `download/enus.pdf`)
- **Print stylesheet** — hides UI chrome, clean A4 output
- **Responsive** — single-column on mobile

## Updating content

All content lives in the `content` object in the `<script>` block at the bottom of `index.html`. Each language key (`pt`, `en`) contains all strings. No template engine — just update the strings directly.

```js
const content = {
  pt: { summary: "...", j1t: "...", ... },
  en: { summary: "...", j1t: "...", ... }
}
```

## PDF generation

PDFs are generated externally (e.g. browser print → Save as PDF) and placed in `download/`. They are excluded from git tracking — upload to Hostinger manually when updated.

> PDF filenames are hardcoded: `ptbr.pdf` and `enus.pdf`. Don't rename.
