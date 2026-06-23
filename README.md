# andreycosta.com

Personal website — hosted on Hostinger at [andreycosta.com](https://andreycosta.com).

Built with zero frameworks. Semantic HTML, vanilla CSS/JS, editorial aesthetic.
Auto-deployed to Hostinger on every push to `main`.

---

## Structure

```
/
├── index.html                     # Homepage
├── .htaccess                      # Apache rewrite rules
├── curriculum/
│   ├── index.html                 # Bilingual CV (PT/EN toggle, no reload)
│   └── download/
│       ├── ptbr.pdf               # CV in Portuguese (upload manually)
│       └── enus.pdf               # CV in English (upload manually)
├── contrato/
│   └── index.html                 # Client-side rental contract generator
└── emendas-parlamentares/
    └── index.html                 # Parliamentary budget amendments dashboard
```

---

## Pages

### `/` — Homepage
Editorial layout. Cormorant Garamond display, DM Mono for data labels, noise texture overlay.
Links to all active projects, research, and writing.

### `/curriculum` — CV
Bilingual (PT/EN) with client-side language toggle. No page reload.
PDF download for both languages. Print stylesheet included.

### `/contrato` — Contract Generator
Client-side rental contract generator. No server, no storage.

### `/emendas-parlamentares` — Parliamentary Amendments Dashboard
Interactive analytical panel for Brazilian federal parliamentary budget amendments.
Chart.js visualizations, filter system, anomaly detection. Built entirely in-browser.

---

## Design system

```css
--ink:    #1C1B18   /* near-black text */
--paper:  #F5F2EB   /* warm off-white background */
--muted:  #7A7469   /* secondary text */
--accent: #8B2E0F   /* terracotta red */
--rule:   #C8C2B4   /* hairline dividers */
```

**Typefaces:** Cormorant Garamond (display) · DM Sans (body) · DM Mono (data/labels)

---

## Related projects

| Repo | Description |
|------|-------------|
| [job-market-lens](https://github.com/andreyncosta/job-market-lens) | DuckDB pipeline for DS job market analysis |
| [yt-minimal](https://github.com/andreyncosta/yt-minimal) | Minimalist YouTube client (FastAPI + React Native) |
| [bitcoin-sovereign-reserve](https://github.com/andreyncosta/bitcoin-sovereign-reserve) | FGV research on bitdollar adoption |

---

## Branches

| Branch | Description |
|--------|-------------|
| `main` | Current production site — auto-deployed to andreycosta.com |
| `legacy` | Snapshot of original site prior to 2025-06 redesign |

---

## License

Code: MIT  
Content (text, writing, research): © Andrey Costa — all rights reserved.
