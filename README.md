# andreycosta.com

Personal website source — hosted on Hostinger at [andreycosta.com](https://andreycosta.com).

Built with zero frameworks. Semantic HTML, vanilla CSS/JS, editorial aesthetic.

---

## Structure

```
public_html/
├── index.html                     # Homepage
├── .htaccess                      # Apache rewrite rules
├── curriculum/
│   ├── index.html                 # Bilingual CV (PT/EN toggle, no reload)
│   └── download/
│       ├── ptbr.pdf               # CV in Portuguese
│       └── enus.pdf               # CV in English
├── contrato/
│   └── index.html                 # Client-side rental contract generator
└── emendas-parlamentares/
    └── index.html                 # Parliamentary budget amendments dashboard
```

---

## Pages

### `/` — Homepage
Editorial layout. Cormorant Garamond display, DM Mono for data labels, noise texture overlay. Links to all active projects, research, and writing.

### `/curriculum` — CV
Bilingual (PT/EN) with client-side language toggle. No page reload. PDF download for both languages. Print stylesheet included.

### `/contrato` — Contract Generator
Client-side rental contract generator. Fills a legal template from form inputs, renders a printable document. No server, no storage.

### `/emendas-parlamentares` — Parliamentary Amendments Dashboard
Interactive analytical panel for Brazilian federal parliamentary budget amendments (*emendas parlamentares*). Chart.js visualizations, filter system, anomaly detection. Built entirely in-browser against open government data.

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

## Deployment

Deployed via FTP to Hostinger. No build step. Push to `main` → upload `public_html/` contents.

```
Host:   ftp.andreycosta.com
Path:   /public_html
```

> PDFs in `curriculum/download/` are not tracked in git (`.gitignore`). Upload manually after regeneration.

---

## License

Code: MIT  
Content (text, writing, research): © Andrey Costa — all rights reserved.
