"use strict";
const el = id => document.getElementById(id);
const t = (pt, en) => window.siteLang ? window.siteLang.t(pt, en) : pt;
const locale = () => t("pt-BR", "en-US");
// Official Treasury line items, shown in English when the site is in English.
const CATEGORY_EN = {"1.1": "Revenue administered by the Federal Revenue Service", "1.2": "Tax incentives", "1.3": "Net social security (RGPS) collections", "1.4": "Revenue not administered by the Federal Revenue Service", "4.1": "Social security benefits", "4.2": "Personnel and payroll charges", "4.3": "Other mandatory spending", "4.4": "Executive spending subject to financial programming"};
const money = value => value == null ? "—" : `${value < 0 ? "−" : ""}R$ ${Math.abs(value / 1000).toLocaleString(locale(), {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${t("bi", "bn")}`;
const number = value => value == null ? "—" : value.toLocaleString(locale(), {minimumFractionDigits: 2, maximumFractionDigits: 2});
const monthLabel = month => new Date(`${month.slice(0, 7)}-15T12:00:00Z`).toLocaleDateString(locale(), {month: "short", year: "numeric", timeZone: "UTC"});
const ns = "http://www.w3.org/2000/svg";
let dataset, selected = [];
// Unit strings come from the dataset in Portuguese ("R$ Milhões - Valores Correntes").
const unitLabel = text => t(text, text.replace("R$ Milhões", "R$ millions").replace("Valores Correntes", "Current values").replace("Valores a preços de", "Values at prices of").replace("Valores de", "Values at").replace("Valores Constantes", "Constant values"));
function svgNode(tag, attrs = {}, text) {
  const node = document.createElementNS(ns, tag);
  Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
  if (text != null) node.textContent = text;
  el("chart").append(node);
  return node;
}
function drawChart(rows) {
  el("chart").replaceChildren();
  svgNode("desc", {id: "chart-description"}, t("Dados mensais em bilhões de reais. Consulte a tabela para acessar todos os valores.", "Monthly data in billions of reais. See the table for every value."));
  const view = el("view").value;
  const series = view === "flows" ? [{key: "net_revenue", label: t("Receita líquida", "Net revenue"), color: "#72d2b1"}, {key: "expense", label: t("Despesa", "Expenditure"), color: "#efac75"}] : [{key: view === "rolling" ? "primary_balance_12m" : "primary_balance", label: view === "rolling" ? t("Resultado em 12 meses", "12-month balance") : t("Resultado mensal", "Monthly balance"), color: "#72d2b1"}];
  el("chart-title").textContent = view === "flows" ? t("Receita líquida e despesa", "Net revenue and expenditure") : series[0].label;
  el("legend").replaceChildren();
  series.forEach(s => {const item = document.createElement("span"); const mark = document.createElement("i"); mark.style.background = s.color; item.append(mark, document.createTextNode(s.label)); el("legend").append(item);});
  const values = rows.flatMap(r => series.map(s => r[s.key])).filter(v => v != null).map(v => v / 1000);
  let min = Math.min(0, ...values), max = Math.max(0, ...values);
  const pad = (max - min || 1) * .08; min -= pad; max += pad;
  const x = i => 74 + (rows.length === 1 ? .5 : i / (rows.length - 1)) * 900;
  const y = value => 270 - (value / 1000 - min) / (max - min) * 240;
  for (let i = 0; i <= 4; i++) {
    const v = min + (max - min) * i / 4, yy = y(v * 1000);
    svgNode("line", {x1: 74, x2: 974, y1: yy, y2: yy, stroke: "#3d494d", "stroke-width": 1});
    svgNode("text", {x: 64, y: yy + 4, "text-anchor": "end"}, v.toLocaleString(locale(), {maximumFractionDigits: 0}));
  }
  svgNode("line", {x1: 74, x2: 974, y1: y(0), y2: y(0), stroke: "#819097", "stroke-dasharray": "4 5"});
  const ticks = [...new Set([0, Math.floor((rows.length - 1) / 2), rows.length - 1])];
  ticks.forEach(i => svgNode("text", {x: x(i), y: 300, "text-anchor": "middle"}, monthLabel(rows[i].month)));
  series.forEach(s => {
    let path = "", open = false;
    rows.forEach((r, i) => {if (r[s.key] == null) {open = false; return;} path += `${open ? "L" : "M"}${x(i)},${y(r[s.key])} `; open = true;});
    svgNode("path", {d: path, fill: "none", stroke: s.color, "stroke-width": 2, "vector-effect": "non-scaling-stroke"});
    if (rows.length === 1 && rows[0][s.key] != null) svgNode("circle", {cx: x(0), cy: y(rows[0][s.key]), r: 4, fill: s.color});
  });
  const cursor = svgNode("line", {x1: 0, x2: 0, y1: 25, y2: 275, stroke: "#dce2d7", opacity: 0});
  const detail = index => {const r = rows[index]; el("chart-detail").textContent = `${monthLabel(r.month)} · ${series.map(s => `${s.label}: ${money(r[s.key])}`).join(" · ")}`;};
  detail(rows.length - 1);
  el("chart").onpointermove = event => {
    const rect = el("chart").getBoundingClientRect();
    const index = Math.max(0, Math.min(rows.length - 1, Math.round(((event.clientX - rect.left) / rect.width * 1000 - 74) / 900 * (rows.length - 1))));
    cursor.setAttribute("x1", x(index)); cursor.setAttribute("x2", x(index)); cursor.setAttribute("opacity", .6); detail(index);
  };
  el("chart").onpointerleave = () => {cursor.setAttribute("opacity", 0); detail(rows.length - 1);};
}
function bars(target, prefix, denominator, row) {
  el(target).replaceChildren();
  dataset.categories.filter(c => c.month === row.month && c.basis === row.basis && c.code.startsWith(prefix)).forEach(c => {
    const share = c.value == null || denominator === 0 ? null : c.value / denominator * 100;
    const item = document.createElement("div"); item.className = "bar-row";
    const line = document.createElement("div"); line.className = "bar-label";
    const label = document.createElement("span"); label.textContent = t(c.label.replace(/^[\d.]+\s*-?\s*/, "").replace(/\s+\d+\/$/, ""), CATEGORY_EN[c.code] || c.label);
    const amount = document.createElement("span"); amount.textContent = share == null ? "—" : `${share.toLocaleString(locale(), {maximumFractionDigits: 1})}%`;
    amount.title = money(c.value); line.append(label, amount);
    const track = document.createElement("div"); track.className = "bar-track";
    const fill = document.createElement("div"); fill.className = "bar-fill"; fill.style.width = `${Math.min(100, Math.max(0, share || 0))}%`;
    track.append(fill); item.append(line, track); el(target).append(item);
  });
}
function render() {
  const start = el("start").value, end = el("end").value, basis = el("basis").value;
  selected = dataset.monthly.filter(r => r.basis === basis && r.month.slice(0, 7) >= start && r.month.slice(0, 7) <= end);
  const invalid = !start || !end || start > end || !selected.length;
  el("filter-error").hidden = !invalid; el("dashboard").hidden = invalid; el("download").disabled = invalid;
  if (invalid) return;
  const latest = selected[selected.length - 1];
  el("revenue").textContent = money(latest.net_revenue); el("expense").textContent = money(latest.expense);
  el("balance").textContent = money(latest.primary_balance); el("rolling").textContent = money(latest.primary_balance_12m);
  el("balance-label").textContent = `${latest.primary_balance >= 0 ? t("Superávit", "Surplus") : t("Déficit", "Deficit")} · ${monthLabel(latest.month)}`;
  el("units").textContent = `${t("Gráfico em R$ bilhões", "Chart in R$ billions")} · ${unitLabel(latest.unit_description.replace("R$ Milhões - ", ""))}`;
  el("provenance").textContent = `${t("Tesouro Nacional", "National Treasury")} / RTN · ${monthLabel(dataset.metadata.first_month)} ${t("a", "to")} ${monthLabel(dataset.metadata.last_month)} · ${t("Indicadores", "Indicators")}: ${monthLabel(latest.month)} · ${t("Coleta", "Collected")}: ${new Date(dataset.metadata.extracted_at).toLocaleDateString(locale(), {timeZone: "America/Sao_Paulo"})}`;
  drawChart(selected); bars("revenue-bars", "1.", latest.gross_revenue, latest); bars("expense-bars", "4.", latest.expense, latest);
  el("table-count").textContent = `· ${selected.length} ${t("meses", "months")}`;
  el("table-caption").textContent = `${unitLabel(latest.unit_description)}. ${t("Último mês primeiro.", "Latest month first.")}`;
  el("table-body").replaceChildren();
  [...selected].reverse().forEach(r => {
    const tr = document.createElement("tr");
    [monthLabel(r.month), number(r.net_revenue), number(r.expense), number(r.primary_balance), number(r.primary_balance_12m)].forEach(value => {const td = document.createElement("td"); td.textContent = value; tr.append(td);});
    el("table-body").append(tr);
  });
}
el("download").onclick = () => {
  const columns = ["month", "basis", "net_revenue", "expense", "primary_balance", "primary_balance_12m", "net_revenue_12m", "expense_12m", "unit_description"];
  const quote = value => `"${String(value ?? "").replaceAll('"', '""')}"`;
  const csv = "\uFEFF" + [columns.join(";"), ...selected.map(r => columns.map(c => quote(r[c])).join(";"))].join("\r\n");
  const url = URL.createObjectURL(new Blob([csv], {type: "text/csv;charset=utf-8"}));
  const link = document.createElement("a"); link.href = url; link.download = `observatorio-fiscal-${el("basis").value}.csv`; link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
};
["start", "end", "basis", "view"].forEach(id => el(id).addEventListener("change", () => {if (dataset) render();}));
fetch("dataset.json").then(response => {if (!response.ok) throw new Error("Dados indisponíveis"); return response.json();}).then(data => {
  dataset = data;
  const first = data.metadata.first_month.slice(0, 7), last = data.metadata.last_month.slice(0, 7);
  ["start", "end"].forEach(id => {el(id).min = first; el(id).max = last;});
  el("start").value = `${Math.max(Number(first.slice(0, 4)), Number(last.slice(0, 4)) - 5)}-01`; el("end").value = last;
  const version = () => {el("version").textContent = `${t("Versão da fonte", "Source version")} (SHA-256): ${data.metadata.version.slice(0, 16)}… · ${t("Cobertura e base de preços vinculadas à planilha coletada.", "Coverage and price base tied to the collected spreadsheet.")}`;};
  version(); render();
  if (window.siteLang) window.siteLang.on(() => {version(); render();});
}).catch(error => {el("provenance").textContent = t("Não foi possível carregar os dados. Tente novamente mais tarde ou consulte a fonte oficial abaixo.", "The data could not be loaded. Please try again later or see the official source below."); el("download").disabled = true; console.error(error);});
