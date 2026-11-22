(function(){
'use strict';

const DATA = JSON.parse(document.getElementById('data-json').textContent);
const DS = DATA.dataset;

/* ── Language (site-wide switch in assets/i18n.js) ── */
const T = (pt,en) => window.siteLang ? window.siteLang.t(pt,en) : en;
const isEN = () => !window.siteLang || window.siteLang.get()==='en';
const comma = () => isEN() ? '.' : ',';
// Display names for Portuguese labels that come from the data (budget categories, functions, sectors, types).
const EN_LABELS = {
  'Assistência Social':'Social Assistance','Saúde':'Health','Infraestrutura':'Infrastructure','Outros':'Other','Direitos e Cidadania':'Rights and Citizenship',
  'Agropecuária':'Agriculture','Trabalho e Emprego':'Labor and Employment','Esporte e Lazer':'Sports and Leisure','Educação':'Education',
  'Segurança e Defesa':'Security and Defense','Meio Ambiente':'Environment','Ciência e Tecnologia':'Science and Technology','Cultura':'Culture',
  'Assistência social':'Social assistance','Urbanismo':'Urban development','Múltiplo':'Multiple','Agricultura':'Agriculture','Comércio e serviços':'Trade and services',
  'Desporto e lazer':'Sports and leisure','Administração':'Administration','Encargos especiais':'Special charges','Defesa nacional':'National defense',
  'Segurança pública':'Public security','Previdência social':'Social security','Transporte':'Transportation','Gestão ambiental':'Environmental management',
  'Indústria':'Industry','Direitos da cidadania':'Citizenship rights','Saneamento':'Sanitation','Organização agrária':'Land organization',
  'Comunicações':'Communications','Trabalho':'Labor','Habitação':'Housing','Energia':'Energy','Essencial à justiça':'Essential to justice',
  'Relações exteriores':'Foreign relations','Sem informação':'No information',
  'Relator':'Rapporteur','Comissão':'Committee','Bancada':'State caucus','Ind. Especial':'Ind. Special transfer','Ind. Finalidade':'Ind. Earmarked',
  'Senador':'Senator','Deputado':'Deputy','S/Partido':'No party',
  'Público':'Public','Privado':'Private','Não identificado':'Unidentified','Terceiro Setor':'Third Sector',
};
const tx = (s) => (isEN() && EN_LABELS[s]) || s;
const ROWS = DS.rows;

/* Row layout:
   0 ano_i | 1 tipo_i | 2 autor_i | 3 uf_i | 4 mun_i | 5 func_i
   6 empenhado | 7 liquidado | 8 pago_total | 9 cat1_i | 10 cat2_i(-1)
   11 sem_execucao | 12 partido_i | 13 rap_pagos */

/* ── Lookup caches ── */
const AUT_LC = DS.autores.map(s=>s.toLowerCase());
const MUN_LC = DS.municipios.map(s=>s.toLowerCase());
const FUN_LC = DS.funcoes.map(s=>s.toLowerCase());
const REAL_UF_NAMES = new Set(DATA.real_ufs);
const REAL_UF_IDX = new Set(DS.ufs.map((u,i)=>REAL_UF_NAMES.has(u)?i:-1).filter(i=>i>=0));
const PLACEHOLDER_MUN = new Set(DS.municipios.map((m,i)=>{
  const u=m.toUpperCase();
  return (u===''||u==='SEM INFORMAÇÃO'||u==='MÚLTIPLO')?i:-1;
}).filter(i=>i>=0));
const INSTITUTIONAL = new Set(['Bancada','Comissão','Relator','S/I','S/Partido']
  .map(s=>DS.partidos.indexOf(s)).filter(i=>i>=0));
const UF_NAME_TO_IDX = {};
DS.ufs.forEach((u,i)=>{ UF_NAME_TO_IDX[u]=i; });
const NAME_TO_ABBR = {};
DATA.brazil_states.forEach(s=>{ NAME_TO_ABBR[s.name]=s.uf; });

/* ── Chart.js defaults ── */
Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
Chart.defaults.font.size = 11;
Chart.defaults.color = '#627267';

/* ── Formatters ── */
const fmt = (n) => {
  if(n==null||isNaN(n))return'—';
  if(Math.abs(n)>=1e9)return'R$ '+(n/1e9).toFixed(2).replace('.',comma())+T(' bi',' bn');
  if(Math.abs(n)>=1e6)return'R$ '+(n/1e6).toFixed(2).replace('.',comma())+T(' mi',' mn');
  if(Math.abs(n)>=1e3)return'R$ '+(n/1e3).toFixed(1).replace('.',comma())+T(' mil','k');
  return'R$ '+n.toFixed(2).replace('.',comma());
};
const fmtN = (n) => n.toLocaleString(T('pt-BR','en-US'));
const fmtPerCapita = (v) => 'R$ '+v.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g,T('.',','))+T(' /hab',' /resident');

const CAT_COLORS = {
  'Saúde':'#2563eb','Educação':'#059669','Infraestrutura':'#7c3aed',
  'Agropecuária':'#d97706','Esporte e Lazer':'#db2777','Assistência Social':'#0891b2',
  'Segurança e Defesa':'#dc2626','Cultura':'#9333ea','Meio Ambiente':'#16a34a',
  'Ciência e Tecnologia':'#0284c7','Trabalho e Emprego':'#ea580c',
  'Direitos e Cidadania':'#65a30d','Outros':'#a1a1aa',
};
const PARTY_COLORS = {
  'PT':'#c4122d','PL':'#1351b4','MDB':'#00a850','PSD':'#f6c500','PP':'#5b92e5',
  'UNIÃO':'#0f4c81','PSDB':'#0080ff','REPUBLICANOS':'#005daa','PSB':'#ffcc00',
  'PDT':'#ff0000','PODE':'#5a8f3c','PODEMOS':'#5a8f3c',
  'Bancada':'#64748b','Comissão':'#94a3b8','Relator':'#475569',
  'S/I':'#d4d4d8','S/Partido':'#d4d4d8',
};

/* ── Core aggregation (runs on every filter change) ── */
function aggregateRows(rows) {
  let emp=0, liq=0, pagoTot=0, rap=0;
  const autoresSet=new Set(), ufSet=new Set(), munSet=new Set();
  const cats={}, byYear={}, byUFAbbr={};
  const pCount={}, pEmp={}, pPago={};
  const aCount={}, aEmp={}, aPago={};
  const rates=[];
  const anomalies=[];

  for(let k=0;k<rows.length;k++){
    const r=rows[k];
    emp+=r[6]; liq+=r[7]; pagoTot+=r[8]; rap+=r[13];
    autoresSet.add(r[2]);
    if(REAL_UF_IDX.has(r[3])) ufSet.add(r[3]);
    if(!PLACEHOLDER_MUN.has(r[4])) munSet.add(r[4]);

    const cn=DS.cats[r[9]];
    cats[cn]=(cats[cn]||0)+1;

    const y=DS.anos[r[0]];
    let yb=byYear[y];
    if(!yb) yb=byYear[y]={emp:0,pago:0,rap:0};
    yb.emp+=r[6]; yb.pago+=r[8]-r[13]; yb.rap+=r[13];

    const ab=NAME_TO_ABBR[DS.ufs[r[3]]];
    if(ab) byUFAbbr[ab]=(byUFAbbr[ab]||0)+r[6];

    const pi=r[12];
    pCount[pi]=(pCount[pi]||0)+1;
    pEmp[pi]=(pEmp[pi]||0)+r[6];
    pPago[pi]=(pPago[pi]||0)+r[8];
    if(!INSTITUTIONAL.has(pi)){
      const ai=r[2];
      aCount[ai]=(aCount[ai]||0)+1;
      aEmp[ai]=(aEmp[ai]||0)+r[6];
      aPago[ai]=(aPago[ai]||0)+r[8];
    }

    if(r[6]>0) rates.push(r[8]/r[6]);
    if(r[11]) anomalies.push(r);
  }

  rates.sort((a,b)=>a-b);
  anomalies.sort((a,b)=>b[6]-a[6]);

  const parties = Object.keys(pCount).map(pi=>({
    sigla:DS.partidos[pi], emendas:pCount[pi],
    empenhado:pEmp[pi], pago:pPago[pi],
    exec_pct:pEmp[pi]?pPago[pi]/pEmp[pi]*100:0,
    institucional:INSTITUTIONAL.has(+pi),
  })).sort((a,b)=>b.empenhado-a.empenhado);

  const authors = Object.keys(aEmp).map(ai=>{
    const m=DS.autores_meta[ai];
    return { nome:DS.autores[ai], partido:DS.partidos[m[0]],
      casa:DS.casas[m[1]], uf:DS.ufs[m[2]],
      emendas:aCount[ai], empenhado:aEmp[ai], pago:aPago[ai],
      exec_pct:aEmp[ai]?aPago[ai]/aEmp[ai]*100:0 };
  }).sort((a,b)=>b.empenhado-a.empenhado).slice(0,25);

  return {
    kpis:{
      total_emendas:rows.length,
      total_autores:autoresSet.size,
      total_ufs:ufSet.size,
      total_municipios:munSet.size,
      total_empenhado:emp,
      total_liquidado:liq,
      total_pago_efetivo:pagoTot,
      total_rap_pagos:rap,
      median_exec_rate:rates.length?rates[Math.floor(rates.length/2)]:0,
      sem_execucao_count:anomalies.length,
    },
    cats, byYear, byUFAbbr, parties, authors,
    anomalies:anomalies.slice(0,200),
  };
}

let currentAgg = null;   // aggregation of the current filter selection
let fullAgg = null;      // aggregation of the entire dataset (boot)

/* ── Progress bar ── */
function renderProgressBar(agg) {
  const k = agg.kpis;
  const pct = k.total_empenhado > 0 ? k.total_pago_efetivo / k.total_empenhado : 0;
  const bar = document.getElementById('top-progress');
  bar.style.width = (pct*100).toFixed(1)+'%';
  if(pct>=0.7) bar.style.background='#059669';
  else if(pct>=0.3) bar.style.background='#d97706';
  else bar.style.background='#dc2626';
}

/* ── KPIs ── */
function renderKPIs(k) {
  document.getElementById('kpi-emendas').textContent = fmtN(k.total_emendas);
  document.getElementById('kpi-autores').textContent = fmtN(k.total_autores);
  document.getElementById('kpi-ufs').textContent = fmtN(k.total_ufs);
  document.getElementById('kpi-municipios').textContent = fmtN(k.total_municipios);
  document.getElementById('kpi-empenhado').textContent = fmt(k.total_empenhado);
  document.getElementById('kpi-liquidado').textContent = fmt(k.total_liquidado);
  document.getElementById('kpi-pago').textContent = fmt(k.total_pago_efetivo);
  document.getElementById('kpi-pago-sub').textContent =
    fmt(k.total_rap_pagos)+T(' via Restos a Pagar',' via carried-over commitments');
  const execPct = (k.median_exec_rate*100).toFixed(1).replace('.',comma());
  document.getElementById('kpi-exec').textContent = execPct+' %';
  document.getElementById('kpi-exec-sub').textContent =
    fmtN(k.sem_execucao_count)+T(' emendas sem execução',' amendments with no execution');
  document.getElementById('header-sub').textContent =
    fmtN(k.total_emendas)+T(' emendas de ',' amendments by ')+fmtN(k.total_autores)+
    T(' autores, somando ',' authors, totaling ')+fmt(k.total_empenhado)+T(' em valores empenhados.',' in committed amounts.');
  document.getElementById('pill-exec').innerHTML =
    T('Execução mediana','Median execution')+' <b>'+execPct+' %</b>';
}

/* ── Category donut ── */
let catChart = null;
function renderCategoryChart(cats) {
  const sorted = Object.entries(cats).sort((a,b)=>b[1]-a[1]);
  const labels = sorted.map(e=>tx(e[0]));
  const values = sorted.map(e=>e[1]);
  const colors = sorted.map(e=>CAT_COLORS[e[0]]||'#a1a1aa');
  const total = values.reduce((a,b)=>a+b,0)||1;

  if(catChart){ catChart.destroy(); catChart=null; }
  catChart = new Chart(document.getElementById('cat-chart'),{
    type:'doughnut',
    data:{ labels, datasets:[{ data:values, backgroundColor:colors,
      borderWidth:2, borderColor:'#fff' }] },
    options:{ responsive:true, maintainAspectRatio:true,
      plugins:{ legend:{ display:false } }, cutout:'68%' }
  });

  document.getElementById('cat-list').innerHTML = sorted.map(([cat,cnt])=>
    '<li><span class="cat-dot" style="background:'+(CAT_COLORS[cat]||'#a1a1aa')+'"></span>'+
    '<span class="cat-name">'+tx(cat)+'</span>'+
    '<span class="cat-count">'+fmtN(cnt)+'</span>'+
    '<span class="cat-pct">'+(cnt/total*100).toFixed(1).replace('.',comma())+'%</span></li>'
  ).join('');
}

/* ── Partidos ── */
let partidoChart = null;
function renderPartidos(parties) {
  const top = parties.filter(p=>!p.institucional).slice(0,15);

  if(partidoChart){ partidoChart.destroy(); partidoChart=null; }
  partidoChart = new Chart(document.getElementById('partido-chart'),{
    type:'bar',
    data:{ labels:top.map(p=>p.sigla),
      datasets:[{ data:top.map(p=>p.empenhado/1e9),
        backgroundColor:top.map(p=>PARTY_COLORS[p.sigla]||'#2d5546'),
        borderRadius:4 }] },
    options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ display:false },
        tooltip:{ callbacks:{ label:(c)=>'R$ '+c.parsed.x.toFixed(2).replace('.',comma())+T(' bi',' bn') } } },
      scales:{
        x:{ grid:{ color:'#f1f1f3' }, ticks:{ callback:v=>v+T(' bi',' bn') } },
        y:{ grid:{ display:false }, ticks:{ font:{ weight:'600' } } } } }
  });

  document.getElementById('partido-tbody').innerHTML = parties.map(p=>{
    const inst = p.institucional ? ' style="opacity:.6;font-style:italic"' : '';
    return '<tr'+inst+'><td style="font-weight:650">'+tx(p.sigla)+'</td>'+
      '<td class="num soft">'+fmtN(p.emendas)+'</td>'+
      '<td class="num">'+fmt(p.empenhado)+'</td>'+
      '<td class="num soft">'+fmt(p.pago)+'</td>'+
      '<td class="num">'+p.exec_pct.toFixed(1).replace('.',comma())+' %</td></tr>';
  }).join('') || '<tr><td colspan="5" style="color:var(--text-3);padding:16px">'+T('Nenhum resultado para os filtros atuais','No results for the current filters')+'</td></tr>';
}

/* ── Top Autores ── */
function renderAutores(authors) {
  document.getElementById('autores-tbody').innerHTML =
    authors.map((a,i)=>
    '<tr><td class="rank">'+(i+1)+'</td>'+
    '<td class="ellip" style="max-width:220px" title="'+a.nome+'">'+a.nome+'</td>'+
    '<td><span class="party-badge" style="border-left-color:'+(PARTY_COLORS[a.partido]||'#a1a1aa')+'">'+a.partido+'</span></td>'+
    '<td>'+tx(a.casa)+'</td><td>'+(a.uf||'—')+'</td>'+
    '<td class="num soft">'+fmtN(a.emendas)+'</td>'+
    '<td class="num">'+fmt(a.empenhado)+'</td>'+
    '<td class="num soft">'+fmt(a.pago)+'</td>'+
    '<td class="num">'+a.exec_pct.toFixed(1).replace('.',comma())+' %</td></tr>'
  ).join('') || '<tr><td colspan="9" style="color:var(--text-3);padding:16px">'+T('Nenhum autor individual para os filtros atuais','No individual authors for the current filters')+'</td></tr>';
}

/* ── Stacked execution chart ── */
let execChart = null;
function renderExecChart(byYear) {
  const anos = Object.keys(byYear).sort();
  if(execChart){ execChart.destroy(); execChart=null; }
  execChart = new Chart(document.getElementById('exec-chart'),{
    type:'bar',
    data:{ labels:anos, datasets:[
      { label:T('Pago no exercício','Paid in the year'), data:anos.map(a=>byYear[a].pago/1e6),
        backgroundColor:'#059669', stack:'s' },
      { label:T('Pago via Restos a Pagar','Paid via carried-over commitments'), data:anos.map(a=>byYear[a].rap/1e6),
        backgroundColor:'#14b8a6', stack:'s' },
      { label:T('Empenhado não pago','Committed, unpaid'), data:anos.map(a=>Math.max(0,(byYear[a].emp-byYear[a].pago-byYear[a].rap))/1e6),
        backgroundColor:'#d4d4d8', stack:'s' },
    ] },
    options:{ responsive:true, maintainAspectRatio:true,
      plugins:{ legend:{ position:'bottom' } },
      scales:{
        x:{ grid:{ display:false } },
        y:{ stacked:true, grid:{ color:'#f1f1f3' }, ticks:{ callback:v=>v+T('M','M') } } } }
  });
}

/* ── Tendency / YoY (base de favorecidos — global, não filtrada) ── */
let tendChart = null;
const MONTH_LABELS_PT = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
const MONTH_LABELS_EN = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
function renderTendencia() {
  const yoyA = document.getElementById('yoy-a').value;
  const yoyB = document.getElementById('yoy-b').value;
  const ms = DATA.monthly_series;
  const datasets = [];
  if(yoyA && ms[yoyA]) datasets.push({
    label:yoyA, data:ms[yoyA].map(v=>v/1e6),
    borderColor:'#2d5546', backgroundColor:'rgba(45,85,70,.08)',
    fill:true, tension:.3, borderWidth:2, pointRadius:3 });
  if(yoyB && ms[yoyB] && yoyB!==yoyA) datasets.push({
    label:yoyB, data:ms[yoyB].map(v=>v/1e6),
    borderColor:'#d97706', backgroundColor:'rgba(217,119,6,.08)',
    fill:true, tension:.3, borderWidth:2, pointRadius:3, borderDash:[4,3] });
  if(datasets.length===0){
    const years = Object.keys(ms).sort();
    const y = years[years.length-1];
    if(y && ms[y]) datasets.push({
      label:y, data:ms[y].map(v=>v/1e6),
      borderColor:'#2d5546', backgroundColor:'rgba(45,85,70,.08)',
      fill:true, tension:.3, borderWidth:2, pointRadius:3 });
  }
  if(tendChart){ tendChart.destroy(); tendChart=null; }
  tendChart = new Chart(document.getElementById('tendencia-chart'),{
    type:'line',
    data:{ labels:isEN()?MONTH_LABELS_EN:MONTH_LABELS_PT, datasets },
    options:{ responsive:true, maintainAspectRatio:true,
      interaction:{ mode:'index', intersect:false },
      plugins:{ legend:{ position:'bottom' } },
      scales:{
        x:{ grid:{ display:false } },
        y:{ grid:{ color:'#f1f1f3' }, ticks:{ callback:v=>v.toFixed(0)+'M' } } } }
  });
}

/* ── Favorecidos (global) ── */
function renderFavorecidos() {
  document.getElementById('fav-tbody').innerHTML =
    DATA.top_favorecidos.map((f,i)=>{
    const sectorClass = f.setor.split(' ')[0];
    return '<tr><td class="rank">'+(i+1)+'</td>'+
      '<td style="max-width:240px;word-break:break-word">'+f.nome+'</td>'+
      '<td><span class="setor-badge '+sectorClass+'">'+tx(f.setor)+'</span></td>'+
      '<td>'+(f.uf||'—')+'</td>'+
      '<td class="num">'+fmt(f.total)+'</td></tr>';
  }).join('');

  const st = DATA.fav_sector_totals;
  const sectors = Object.entries(st).sort((a,b)=>b[1]-a[1]);
  const maxVal = sectors.length ? sectors[0][1] : 1;
  document.getElementById('sector-breakdown').innerHTML = sectors.map(([s,v])=>
    '<div class="sector-row"><span>'+tx(s)+'</span>'+
    '<span style="font-weight:650;font-variant-numeric:tabular-nums">'+fmt(v)+'</span></div>'+
    '<div class="sector-bar-wrap"><div class="sector-bar" style="width:'+
    (v/maxVal*100).toFixed(1)+'%"></div></div>'
  ).join('');

  document.getElementById('conc-10').textContent =
    DATA.conc_10_pct.toFixed(1).replace('.',comma())+' %';
  document.getElementById('conc-100').textContent =
    DATA.conc_100_pct.toFixed(1).replace('.',comma())+' %';
}

/* ── Convênios (global) ── */
let convChart = null;
function renderConvenios() {
  const c = DATA.convenios;
  document.getElementById('conv-kpis').innerHTML =
    '<div class="stat"><div class="stat-label">'+T('Convênios firmados','Agreements signed')+'</div>'+
    '<div class="stat-value">'+fmtN(c.total_qtd)+'</div></div>'+
    '<div class="stat accent"><div class="stat-label">'+T('Valor total','Total value')+'</div>'+
    '<div class="stat-value money">'+fmt(c.total_valor)+'</div></div>'+
    '<div class="stat"><div class="stat-label">'+T('Convenentes únicos','Unique counterparties')+'</div>'+
    '<div class="stat-value">'+fmtN(c.convenentes_unicos)+'</div></div>';

  document.getElementById('conv-tbody').innerHTML =
    c.top_convenentes.map((cv,i)=>
    '<tr><td class="rank">'+(i+1)+'</td>'+
    '<td style="max-width:280px;word-break:break-word">'+cv.nome+'</td>'+
    '<td class="num soft">'+fmtN(cv.qtd)+'</td>'+
    '<td class="num">'+fmt(cv.total)+'</td></tr>'
  ).join('');

  if(convChart){ convChart.destroy(); convChart=null; }
  convChart = new Chart(document.getElementById('conv-chart'),{
    type:'bar',
    data:{ labels:c.por_funcao.map(f=>tx(f.nome)),
      datasets:[{ data:c.por_funcao.map(f=>f.total/1e9),
        backgroundColor:'#2d5546', borderRadius:4 }] },
    options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ display:false },
        tooltip:{ callbacks:{ label:(ctx)=>'R$ '+ctx.parsed.x.toFixed(2).replace('.',comma())+T(' bi',' bn') } } },
      scales:{
        x:{ grid:{ color:'#f1f1f3' }, ticks:{ callback:v=>v+T(' bi',' bn') } },
        y:{ grid:{ display:false }, ticks:{ font:{ size:10 } } } } }
  });
}

/* ── Brazil map (reflects current filters) ── */
let selectedUF = null;
let mapMode = 'total';

function currentUfEmp() {
  return currentAgg ? currentAgg.byUFAbbr : {};
}
function mapValueFor(uf) {
  const total = currentUfEmp()[uf]||0;
  if(mapMode==='total') return total;
  const pop = DATA.uf_population[uf]||0;
  return pop>0 ? total/pop : 0;
}

function renderMap() {
  const svg = document.getElementById('brazil-svg');
  const ufEmp = currentUfEmp();
  const values = DATA.brazil_states.map(s=>mapValueFor(s.uf)).filter(v=>v>0);
  const maxVal = values.length ? Math.max(...values) : 1;
  const minVal = values.length ? Math.min(...values) : 0;

  function blueColor(val) {
    if(!val||val<=0) return '#dce2d7';
    const t = Math.pow((val-minVal)/(maxVal-minVal||1), 0.45);
    const r = Math.round(231 + (45-231)*t);
    const g = Math.round(239 + (85-239)*t);
    const b = Math.round(232 + (70-232)*t);
    return 'rgb('+r+','+g+','+b+')';
  }

  svg.querySelectorAll('.map-state,.map-label').forEach(el=>el.remove());

  const fmtMapVal = mapMode==='total' ? fmt : fmtPerCapita;
  const tooltip = document.getElementById('map-tooltip');
  DATA.brazil_states.forEach(st=>{
    const val = mapValueFor(st.uf);
    const totalAbs = ufEmp[st.uf]||0;
    const rect = document.createElementNS('http://www.w3.org/2000/svg','rect');
    rect.setAttribute('class','map-state');
    rect.setAttribute('x',st.x); rect.setAttribute('y',st.y);
    rect.setAttribute('width',st.w); rect.setAttribute('height',st.h);
    rect.setAttribute('rx','4');
    rect.setAttribute('fill',blueColor(val));
    rect.setAttribute('data-uf',st.uf);
    if(selectedUF===st.uf) rect.classList.add('selected');

    const text = document.createElementNS('http://www.w3.org/2000/svg','text');
    text.setAttribute('class','map-label');
    text.setAttribute('x',st.x+st.w/2);
    text.setAttribute('y',st.y+st.h/2+4);
    text.setAttribute('text-anchor','middle');
    text.setAttribute('font-size','9');
    text.setAttribute('font-weight','600');
    text.setAttribute('fill','#3f3f46');
    text.setAttribute('pointer-events','none');
    text.textContent = st.uf;

    rect.addEventListener('mouseenter',function(){
      tooltip.style.opacity='1';
      tooltip.innerHTML = mapMode==='total'
        ? '<strong>'+st.name+'</strong><br>'+fmt(val)
        : '<strong>'+st.name+'</strong><br>'+fmtPerCapita(val)+
          '<br><span style="opacity:.7">'+fmt(totalAbs)+' total</span>';
    });
    rect.addEventListener('mousemove',function(e){
      tooltip.style.left=(e.clientX+12)+'px';
      tooltip.style.top=(e.clientY-10)+'px';
    });
    rect.addEventListener('mouseleave',function(){ tooltip.style.opacity='0'; });
    rect.addEventListener('click',function(){
      if(selectedUF===st.uf){
        selectedUF=null;
        setFilter('uf','');
        document.getElementById('map-selected-info').textContent=T('Clique em um estado para filtrar','Click a state to filter');
      } else {
        selectedUF=st.uf;
        const idx = UF_NAME_TO_IDX[st.name];
        setFilter('uf', idx==null ? '' : String(idx));
        const pop = DATA.uf_population[st.uf]||0;
        document.getElementById('map-selected-info').innerHTML =
          '<strong>'+st.name+'</strong><br>'+fmt(totalAbs)+T(' empenhados',' committed')+'<br>'+
          fmtPerCapita(pop>0?totalAbs/pop:0);
      }
    });

    svg.appendChild(rect);
    svg.appendChild(text);
  });

  const sorted = DATA.brazil_states
    .map(s=>[s.uf, mapValueFor(s.uf)])
    .filter(p=>p[1]>0)
    .sort((a,b)=>b[1]-a[1]).slice(0,8);
  const maxTop = sorted.length ? sorted[0][1] : 1;
  document.getElementById('map-top-ufs').innerHTML = sorted.map(p=>
    '<li><span style="min-width:34px;font-weight:650">'+p[0]+'</span>'+
    '<div class="uf-bar-wrap"><div class="uf-bar" style="width:'+
    (p[1]/maxTop*100).toFixed(1)+'%"></div></div>'+
    '<span class="uf-val">'+fmtMapVal(p[1])+'</span></li>'
  ).join('') || '<li style="color:var(--text-3)">'+T('Sem dados para os filtros atuais','No data for the current filters')+'</li>';
}

function initMapModeToggle() {
  const btnT = document.getElementById('map-mode-total');
  const btnP = document.getElementById('map-mode-percapita');
  function setMode(mode){
    mapMode = mode;
    btnT.classList.toggle('active', mode==='total');
    btnP.classList.toggle('active', mode==='percapita');
    renderMap();
  }
  btnT.addEventListener('click',()=>setMode('total'));
  btnP.addEventListener('click',()=>setMode('percapita'));
}

/* ── Per-capita ranking (reflects current filters) ── */
function renderPerCapitaRanking() {
  const ufEmp = currentUfEmp();
  const rows = DATA.brazil_states.map(st=>{
    const emp = ufEmp[st.uf]||0;
    const pop = DATA.uf_population[st.uf]||0;
    return { uf:st.uf, name:st.name, emp, pop, percap: pop>0 ? emp/pop : 0 };
  }).sort((a,b)=>b.percap-a.percap);

  const attribTotal = rows.reduce((s,r)=>s+r.emp,0);
  const totalPop = rows.reduce((s,r)=>s+r.pop,0);
  const brPercap = totalPop>0 ? attribTotal/totalPop : 0;
  const maxPercap = rows.length && rows[0].percap>0 ? rows[0].percap : 1;

  const bar = (v,color)=>
    '<div class="uf-bar-wrap" style="margin:0"><div class="uf-bar" style="width:'+
    (v/maxPercap*100).toFixed(1)+'%;background:'+color+'"></div></div>';

  const brRow =
    '<tr style="background:var(--accent-soft);font-weight:700">'+
    '<td class="rank">—</td>'+
    '<td>'+T('BRASIL','BRAZIL')+' <span style="font-weight:500;color:var(--text-3);font-size:.72rem">'+T('média nacional','national average')+'</span></td>'+
    '<td class="num">'+fmtN(totalPop)+'</td>'+
    '<td class="num">'+fmt(attribTotal)+'</td>'+
    '<td class="num">'+fmtPerCapita(brPercap)+'</td>'+
    '<td>'+bar(brPercap,'var(--accent-strong)')+'</td></tr>';

  document.getElementById('percapita-tbody').innerHTML = brRow +
    rows.map((r,i)=>{
      const aboveAvg = r.percap >= brPercap && r.percap > 0;
      return '<tr><td class="rank">'+(i+1)+'</td>'+
        '<td><b>'+r.uf+'</b> <span style="color:var(--text-2);font-size:.76rem">'+r.name+'</span></td>'+
        '<td class="num soft">'+fmtN(r.pop)+'</td>'+
        '<td class="num soft">'+fmt(r.emp)+'</td>'+
        '<td class="num" style="color:'+(aboveAvg?'var(--accent-strong)':'var(--text)')+'">'+
        fmtPerCapita(r.percap)+'</td>'+
        '<td>'+bar(r.percap,aboveAvg?'var(--accent)':'#cbd5e1')+'</td></tr>';
    }).join('');

  const totalEmp = currentAgg ? currentAgg.kpis.total_empenhado : 0;
  const nonAttrib = Math.max(0, totalEmp - attribTotal);
  document.getElementById('percapita-note').textContent =
    T('Barras verdes: UFs acima da média nacional. ','Green bars: states above the national average. ')+
    fmt(nonAttrib)+T(' em emendas "Múltiplo" ou "Sem informação" não são atribuíveis a uma UF e ficam fora deste ranking (mas compõem os totais do painel). ',
      ' in "Multiple" or "No information" amendments cannot be attributed to a state and are left out of this ranking (but count in the dashboard totals). ')+
    T('O ranking reflete os filtros ativos.','The ranking reflects the active filters.');
}

/* ── Anomalias (reflects current filters) ── */
function renderAnomalies(agg) {
  const semExec = agg.kpis.sem_execucao_count;
  const totalRows = agg.kpis.total_emendas||1;
  const sampleEmp = agg.anomalies.reduce((s,r)=>s+r[6],0);
  document.getElementById('anomaly-summary').innerHTML =
    '<div class="stat"><div class="stat-label">'+T('Emendas sem execução','Amendments with no execution')+'</div>'+
    '<div class="stat-value" style="color:var(--red)">'+fmtN(semExec)+'</div></div>'+
    '<div class="stat"><div class="stat-label">'+T('Empenhado bloqueado','Committed, stalled')+'</div>'+
    '<div class="stat-value money" style="color:var(--red)">'+fmt(sampleEmp)+'</div>'+
    '<div class="stat-sub">'+T('amostra: top 200 por valor','sample: top 200 by amount')+'</div></div>'+
    '<div class="stat"><div class="stat-label">'+T('% das emendas filtradas','% of filtered amendments')+'</div>'+
    '<div class="stat-value" style="color:var(--red)">'+
    (semExec/totalRows*100).toFixed(1).replace('.',comma())+' %</div></div>';

  document.getElementById('anomaly-tbody').innerHTML =
    agg.anomalies.map(r=>{
      const autor = DS.autores[r[2]];
      return '<tr><td>'+DS.anos[r[0]]+'</td>'+
      '<td class="ellip" style="max-width:180px" title="'+autor+'">'+(autor||'—')+'</td>'+
      '<td>'+(tx(DS.ufs[r[3]])||'—')+'</td><td>'+(tx(DS.funcoes[r[5]])||'—')+'</td>'+
      '<td><span class="cat-badge">'+tx(DS.cats[r[9]])+'</span></td>'+
      '<td class="num">'+fmt(r[6])+'</td>'+
      '<td><span class="flag-danger">'+T('Sem execução','No execution')+'</span></td></tr>';
    }).join('') || '<tr><td colspan="7" style="color:var(--text-3);padding:16px">'+T('Nenhuma anomalia para os filtros atuais','No anomalies for the current filters')+'</td></tr>';
}

/* ── Filter state ── */
const filterState = { ano:'', uf:'', tipo:'', cat:'', partido:'' };
let filteredRows = ROWS;
let searchTerm = '';
let currentPage = 0;
const PAGE_SIZE = 50;
const FILTER_LABELS_PT = { ano:'Ano', uf:'UF', tipo:'Tipo', cat:'Categoria', partido:'Partido' };
const FILTER_LABELS_EN = { ano:'Year', uf:'State', tipo:'Type', cat:'Category', partido:'Party' };
const FILTER_DICTS = { ano:'anos', uf:'ufs', tipo:'tipos', cat:'cats', partido:'partidos' };

function filterDisplay(key, val) {
  const dict = DS[FILTER_DICTS[key]];
  const name = dict[parseInt(val)];
  return name===undefined ? val : tx(name);
}

function setFilter(key, val) {
  filterState[key] = val;
  const sel = document.getElementById('f-'+key);
  if(sel) sel.value = val;
  if(key==='uf'){
    // keep map selection in sync when the filter changes via dropdown/chip
    const name = val==='' ? null : DS.ufs[parseInt(val)];
    selectedUF = name ? (NAME_TO_ABBR[name]||null) : null;
    if(!selectedUF)
      document.getElementById('map-selected-info').textContent=T('Clique em um estado para filtrar','Click a state to filter');
  }
  applyFilters();
  renderFilterChips();
}

function renderFilterChips() {
  const container = document.getElementById('active-chips');
  const active = Object.entries(filterState).filter(e=>e[1]!=='');
  if(active.length===0){
    container.innerHTML='<span class="no-filters">'+T('Nenhum filtro ativo','No active filters')+'</span>';
    return;
  }
  container.innerHTML = active.map(e=>
    '<span class="chip" data-key="'+e[0]+'">'+(isEN()?FILTER_LABELS_EN:FILTER_LABELS_PT)[e[0]]+': '+
    filterDisplay(e[0],e[1])+' <span class="remove">×</span></span>'
  ).join('');
  container.querySelectorAll('.chip').forEach(chip=>{
    chip.addEventListener('click',function(){ setFilter(this.dataset.key,''); });
  });
}

function applyFilters() {
  const fa = filterState.ano==='' ? -1 : parseInt(filterState.ano);
  const fu = filterState.uf==='' ? -1 : parseInt(filterState.uf);
  const ft = filterState.tipo==='' ? -1 : parseInt(filterState.tipo);
  const fc = filterState.cat==='' ? -1 : parseInt(filterState.cat);
  const fp = filterState.partido==='' ? -1 : parseInt(filterState.partido);
  const term = searchTerm.toLowerCase();

  if(fa===-1&&fu===-1&&ft===-1&&fc===-1&&fp===-1&&!term){
    filteredRows = ROWS;
  } else {
    filteredRows = ROWS.filter(r=>{
      if(fa!==-1 && r[0]!==fa) return false;
      if(fu!==-1 && r[3]!==fu) return false;
      if(ft!==-1 && r[1]!==ft) return false;
      if(fc!==-1 && r[9]!==fc) return false;
      if(fp!==-1 && r[12]!==fp) return false;
      if(term && !(AUT_LC[r[2]].includes(term)||
                   MUN_LC[r[4]].includes(term)||
                   FUN_LC[r[5]].includes(term))) return false;
      return true;
    });
  }
  currentPage = 0;

  // Re-aggregate everything from the filtered rows
  currentAgg = (filteredRows===ROWS && fullAgg) ? fullAgg : aggregateRows(filteredRows);

  renderProgressBar(currentAgg);
  renderKPIs(currentAgg.kpis);
  renderCategoryChart(currentAgg.cats);
  renderPartidos(currentAgg.parties);
  renderAutores(currentAgg.authors);
  renderExecChart(currentAgg.byYear);
  renderMap();
  renderPerCapitaRanking();
  renderAnomalies(currentAgg);
  renderTable();
}

/* ── Main table ── */
function renderTable() {
  const start = currentPage * PAGE_SIZE;
  const page = filteredRows.slice(start, start+PAGE_SIZE);
  document.getElementById('main-tbody').innerHTML = page.map(r=>{
    const rowClass = r[11] ? ' class="sem-exec"' : '';
    const cat2 = r[10]>=0 ? ' <span class="cat-badge secondary">'+tx(DS.cats[r[10]])+'</span>' : '';
    const autor = DS.autores[r[2]], mun = DS.municipios[r[4]];
    return '<tr'+rowClass+'><td>'+DS.anos[r[0]]+'</td>'+
      '<td style="white-space:nowrap">'+tx(DS.tipos[r[1]])+'</td>'+
      '<td class="ellip" style="max-width:150px" title="'+autor+'">'+(autor||'—')+'</td>'+
      '<td>'+(tx(DS.ufs[r[3]])||'—')+'</td>'+
      '<td class="ellip" style="max-width:130px" title="'+mun+'">'+(mun||'—')+'</td>'+
      '<td>'+(tx(DS.funcoes[r[5]])||'—')+'</td>'+
      '<td class="num">'+fmt(r[6])+'</td>'+
      '<td class="num soft">'+fmt(r[7])+'</td>'+
      '<td class="num soft">'+fmt(r[8])+'</td>'+
      '<td><span class="cat-badge">'+tx(DS.cats[r[9]])+'</span>'+cat2+'</td></tr>';
  }).join('');

  const total = filteredRows.length;
  document.getElementById('table-badge').textContent = fmtN(total);
  document.getElementById('table-info').textContent = total===0
    ? T('Nenhum resultado','No results')
    : T('Mostrando ','Showing ')+fmtN(start+1)+'–'+fmtN(Math.min(start+PAGE_SIZE,total))+T(' de ',' of ')+fmtN(total);
  renderPagination(total);
}

function renderPagination(total) {
  const pages = Math.ceil(total/PAGE_SIZE);
  const pg = document.getElementById('pagination');
  if(pages<=1){ pg.innerHTML=''; return; }
  let html = '<button class="page-btn" id="prev-btn" '+(currentPage===0?'disabled':'')+'>‹ '+T('Anterior','Previous')+'</button>';
  const w = 2;
  for(let i=0;i<pages;i++){
    if(i===0||i===pages-1||Math.abs(i-currentPage)<=w){
      html+='<button class="page-btn'+(i===currentPage?' active':'')+'" data-page="'+i+'">'+(i+1)+'</button>';
    } else if(Math.abs(i-currentPage)===w+1){
      html+='<span style="padding:0 4px;color:var(--text-3)">…</span>';
    }
  }
  html+='<button class="page-btn" id="next-btn" '+(currentPage===pages-1?'disabled':'')+'>'+T('Próximo','Next')+' ›</button>';
  pg.innerHTML = html;
  pg.querySelectorAll('[data-page]').forEach(btn=>{
    btn.addEventListener('click',function(){
      currentPage=parseInt(this.dataset.page); renderTable();
      document.getElementById('emendas').scrollIntoView({behavior:'smooth'});
    });
  });
  const prev = document.getElementById('prev-btn');
  const next = document.getElementById('next-btn');
  if(prev) prev.addEventListener('click',()=>{ currentPage=Math.max(0,currentPage-1); renderTable(); });
  if(next) next.addEventListener('click',()=>{ currentPage=Math.min(pages-1,currentPage+1); renderTable(); });
}

/* ── Filters init ── */
function addOptions(selId, pairs) {
  const sel = document.getElementById(selId);
  pairs.forEach(p=>{
    const opt = document.createElement('option');
    opt.value=String(p[0]); opt.textContent=tx(p[1]); opt.dataset.label=p[1];
    sel.appendChild(opt);
  });
}

function initFilters() {
  // anos: sorted by label
  addOptions('f-ano', DS.anos.map((a,i)=>[i,a]).sort((x,y)=>x[1]<y[1]?-1:1));
  // UFs: real states first (alphabetical), then placeholders
  const ufPairs = DS.ufs.map((u,i)=>[i,u]);
  const realUF = ufPairs.filter(p=>REAL_UF_NAMES.has(p[1])).sort((x,y)=>x[1]<y[1]?-1:1);
  const otherUF = ufPairs.filter(p=>!REAL_UF_NAMES.has(p[1])).sort((x,y)=>x[1]<y[1]?-1:1);
  addOptions('f-uf', realUF.concat(otherUF));
  addOptions('f-tipo', DS.tipos.map((t,i)=>[i,t]).sort((x,y)=>x[1]<y[1]?-1:1));
  addOptions('f-cat', DS.cats.map((c,i)=>[i,c]).sort((x,y)=>x[1]<y[1]?-1:1));
  // partidos: ordered by total empenhado (full dataset)
  const partOrder = fullAgg.parties.map(p=>[DS.partidos.indexOf(p.sigla), p.sigla]);
  addOptions('f-partido', partOrder);

  const years = Object.keys(DATA.monthly_series).sort();
  ['yoy-a','yoy-b'].forEach(id=>{
    const sel = document.getElementById(id);
    years.forEach(y=>{
      const o=document.createElement('option'); o.value=y; o.textContent=y; sel.appendChild(o);
    });
  });

  document.getElementById('btn-edit-filters').addEventListener('click',function(){
    const panel = document.getElementById('filter-panel');
    panel.classList.toggle('open');
    this.textContent = panel.classList.contains('open') ? T('Fechar filtros','Close filters') : T('Editar filtros','Edit filters');
  });

  ['f-ano','f-uf','f-tipo','f-cat','f-partido'].forEach(id=>{
    const key = id.replace('f-','');
    document.getElementById(id).addEventListener('change',function(){
      setFilter(key, this.value);
    });
  });

  document.getElementById('btn-clear-filters').addEventListener('click',function(){
    ['ano','uf','tipo','cat','partido'].forEach(k=>{ filterState[k]=''; });
    ['f-ano','f-uf','f-tipo','f-cat','f-partido'].forEach(id=>{
      document.getElementById(id).value='';
    });
    searchTerm='';
    document.getElementById('search-input').value='';
    selectedUF=null;
    document.getElementById('map-selected-info').textContent=T('Clique em um estado para filtrar','Click a state to filter');
    applyFilters();
    renderFilterChips();
  });

  document.getElementById('search-input').addEventListener('input',function(){
    searchTerm=this.value;
    applyFilters();
  });

  ['yoy-a','yoy-b'].forEach(id=>{
    document.getElementById(id).addEventListener('change', renderTendencia);
  });
}

/* ── Scrollspy ── */
function initScrollspy() {
  const links = Array.from(document.querySelectorAll('#side-nav a'));
  const byId = {};
  links.forEach(l=>{ byId[l.getAttribute('href').slice(1)] = l; });
  const sections = Array.from(document.querySelectorAll('section[id]'));
  const obs = new IntersectionObserver(entries=>{
    entries.forEach(en=>{
      if(en.isIntersecting){
        links.forEach(l=>l.classList.remove('active'));
        const link = byId[en.target.id];
        if(link) link.classList.add('active');
      }
    });
  },{ rootMargin:'-30% 0px -60% 0px' });
  sections.forEach(s=>obs.observe(s));
}

/* ── Labels set once from code (re-applied when the language changes) ── */
function applyStaticLabels() {
  const panel = document.getElementById('filter-panel');
  document.getElementById('btn-edit-filters').textContent =
    panel.classList.contains('open') ? T('Fechar filtros','Close filters') : T('Editar filtros','Edit filters');
  if(!selectedUF) document.getElementById('map-selected-info').textContent=T('Clique em um estado para filtrar','Click a state to filter');
}

/* ── Boot ── */
function boot() {
  fullAgg = aggregateRows(ROWS);
  initFilters();
  initMapModeToggle();
  renderTendencia();
  renderFavorecidos();
  renderConvenios();
  applyFilters();        // renders all filter-aware sections from fullAgg
  renderFilterChips();
  applyStaticLabels();
  initScrollspy();
  if(window.siteLang) window.siteLang.on(function(){
    // Re-render everything that builds text from code; filters and page stay as they are.
    document.querySelectorAll('#filter-panel option[data-label]').forEach(o=>{ o.textContent=tx(o.dataset.label); });
    applyStaticLabels();
    renderTendencia(); renderFavorecidos(); renderConvenios();
    const page=currentPage; applyFilters(); currentPage=page; renderTable();
    renderFilterChips();
  });
}

document.addEventListener('DOMContentLoaded', boot);

})();
