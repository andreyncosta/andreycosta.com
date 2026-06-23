# emendas-parlamentares

Interactive analytical dashboard for Brazilian federal parliamentary budget amendments (*emendas parlamentares*).

Live at [andreycosta.com/emendas-parlamentares](https://andreycosta.com/emendas-parlamentares).

## What it does

Tracks the allocation and execution of parliamentary budget amendments across:

- **Distribution by category** — individual, committee, bench, rapporteur
- **Party breakdown** — total committed vs. paid, execution rate
- **Top authors** — individual parliamentarians ranked by allocated value
- **Top recipients** — municipalities and entities receiving funds
- **Budget execution timeline** — committed → liquidated → paid flow
- **Convenios** — execution via *convênio* agreements
- **Anomaly detection** — flags statistically unusual patterns

## Tech

Single HTML file, zero build step, zero dependencies beyond CDN:

- [Chart.js 4.4](https://www.chartjs.org/) — all visualizations
- Sidebar navigation with scroll-spy
- Filter system with chip UI (party, category, author, text search)
- Data embedded as JS objects — no API calls

## Data source

Portal da Transparência — open data from the Brazilian federal government.  
[portaldatransparencia.gov.br](https://portaldatransparencia.gov.br)

## Updating data

All data lives in JS objects at the top of `index.html`. To update with a new fiscal year:

1. Export the relevant tables from Portal da Transparência
2. Transform into the expected shape (see existing data objects for schema)
3. Replace the data constants in `index.html`
4. Update the `ANO_REF` constant at the top

> A Python extraction script is planned — see [job-market-lens](https://github.com/andreyncosta/job-market-lens) for the pipeline pattern.
