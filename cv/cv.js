const content = {
  pt: {
    subtitle: "Economista · Cientista de Dados",
    lblSummary: "Resumo", lblExp: "Experiência Profissional", lblEdu: "Formação", lblSkills: "Competências Técnicas", lblLangs: "Idiomas",
    summary: "Economista com sólida formação quantitativa e mais de oito anos de experiência aplicando análise de dados, modelagem econômica e inteligência de negócios em ambientes corporativos e institucionais. Atualmente analista no Banco BRB, com atuação em ciência de dados voltada à estratégia de canais de atendimento. Mestrando em Economia na FGV, com pesquisa sobre os impactos macroeconômicos e institucionais da adoção de um bitdólar — cenário de coexistência entre o dólar e o Bitcoin como reservas de valor soberanas.",
    j1t: "Analista — Ciência de Dados e Estratégia de Canais", j1p: "Mai 2022 – Presente", j1c: "Banco BRB · Brasília, DF",
    j1d: "Desenvolvimento de soluções analíticas em Power BI, DAX, SQL e Python para subsidiar decisões estratégicas sobre canais de atendimento ao cliente. Modelagem de dados, construção de dashboards executivos e análises preditivas com Python (pandas, scikit-learn, matplotlib) e R. Inclui atuação anterior em extração, tratamento e análise de dados para a estratégia de recuperação de crédito do banco.",
    j2t: "Analista de Dados", j2c: "Rádio X – Inteligência em Mídia · Brasília, DF",
    j2d: "Coleta e análise de dados para a elaboração de propostas comerciais de veiculação de mídia em rádio, com foco em agregar valor mensurável para os clientes e para a empresa.",
    j3t: "Estagiário em Gerenciamento de Projetos", j3c: "EMBRAPII – Empresa Brasileira de Pesquisa e Inovação Industrial · Brasília, DF",
    j3d: "Estudos de viabilidade, execução orçamentária e distribuição de projetos de inovação industrial a partir de análise de dados. Elaboração de relatórios gerenciais para coordenadores de projetos.",
    j4t: "Estagiário em Análise Econômica", j4c: "CADE – Conselho Administrativo de Defesa Econômica · Brasília, DF",
    j4d: "Estudos econômicos estruturados em análise de dados para subsidiar as decisões do Gabinete da Conselheira Cristiane Alkmin em casos de possível ação lesiva à concorrência e à ordem econômica.",
    j5t: "Estagiário em BI", j5c: "Brasal Refrigerantes – Coca-Cola · Taguatinga, DF",
    j5d: "Análise de dados para acompanhamento de KPIs em uma das maiores operações de bebidas do Brasil — a Brasal é a engarrafadora oficial da Coca-Cola no Distrito Federal e entorno, sendo a maior franquia da marca na região Centro-Oeste. Geração de insights de negócios para apoio à gerência em ambiente de alto volume e complexidade operacional.",
    edu1d: "Mestrado em Economia", edu1s: "FGV", edu1p: "Em andamento · Pesquisa: impactos da adoção do bitdólar",
    edu2d: "MBA em Ciência de Dados", edu3d: "Pós-graduação em Gestão de Projetos", edu4d: "Bacharelado em Economia", edu5d: "Inteligência de Mercado",
    langPt: "Português", langPtLvl: "Nativo", langEn: "Inglês", langEnLvl: "Fluente",
  },
  en: {
    subtitle: "Economist · Data Scientist",
    lblSummary: "Summary", lblExp: "Professional Experience", lblEdu: "Education", lblSkills: "Technical Skills", lblLangs: "Languages",
    summary: "Economist with a strong quantitative background and over eight years of experience applying data analysis, economic modeling, and business intelligence in corporate and institutional settings. Currently a data science analyst at BRB Bank, focused on customer service channel strategy. Master's candidate in Economics at FGV, researching the macroeconomic and institutional impacts of bitdollar adoption — a scenario in which the US dollar and Bitcoin coexist as sovereign stores of value.",
    j1t: "Analyst — Data Science & Channel Strategy", j1p: "May 2022 – Present", j1c: "BRB Bank · Brasília, DF",
    j1d: "Development of analytical solutions in Power BI, DAX, SQL, and Python to support strategic decisions on customer service channels. Data modeling, executive dashboard design, and predictive analytics using Python (pandas, scikit-learn, matplotlib) and R. Includes prior work on data extraction and analysis to support the bank's credit recovery strategy.",
    j2t: "Data Analyst", j2c: "Rádio X – Media Intelligence · Brasília, DF",
    j2d: "Data collection and analysis to build commercial proposals for radio media placement, aimed at generating measurable value for clients and the company.",
    j3t: "Project Management Intern", j3c: "EMBRAPII – Brazilian Industrial Research and Innovation Company · Brasília, DF",
    j3d: "Feasibility studies, budget execution, and project allocation for industrial innovation initiatives through data analysis. Preparation of management reports for project coordinators.",
    j4t: "Economic Analysis Intern", j4c: "CADE – Administrative Council for Economic Defense · Brasília, DF",
    j4d: "Data-driven economic studies to support decisions by Board Member Cristiane Alkmin's office in cases involving potential violations of competition law and economic order.",
    j5t: "Business Intelligence Intern", j5c: "Brasal Refrigerantes – Coca-Cola Bottler · Taguatinga, DF",
    j5d: "Data analysis and KPI tracking at one of Brazil's largest beverage operations — Brasal is the official Coca-Cola bottler for the Federal District and surrounding region, and the largest Coca-Cola franchise in the Center-West of Brazil. Generated business insights to support management in a high-volume, operationally complex environment.",
    edu1d: "Master's in Economics", edu1s: "FGV", edu1p: "In progress · Research: impacts of bitdollar adoption",
    edu2d: "MBA in Data Science", edu3d: "Graduate Certificate in Project Management", edu4d: "Bachelor's in Economics", edu5d: "Market Intelligence",
    langPt: "Portuguese", langPtLvl: "Native", langEn: "English", langEnLvl: "Fluent",
  }
};

function setLang(lang) {
  const c = content[lang];
  document.getElementById('cv-subtitle').textContent = c.subtitle;
  document.getElementById('lbl-summary').textContent = c.lblSummary;
  document.getElementById('lbl-exp').textContent = c.lblExp;
  document.getElementById('lbl-edu').textContent = c.lblEdu;
  document.getElementById('lbl-skills').textContent = c.lblSkills;
  document.getElementById('lbl-langs').textContent = c.lblLangs;
  document.getElementById('summary-text').textContent = c.summary;
  ['j1t','j1p','j1c','j1d','j2t','j2c','j2d','j3t','j3c','j3d','j4t','j4c','j4d','j5t','j5c','j5d'].forEach(id => {
    document.getElementById(id).textContent = c[id];
  });
  ['edu1d','edu1s','edu1p','edu2d','edu3d','edu4d','edu5d'].forEach(id => {
    document.getElementById(id).textContent = c[id];
  });
  document.getElementById('lang-pt').textContent = c.langPt;
  document.getElementById('lang-pt-lvl').textContent = c.langPtLvl;
  document.getElementById('lang-en').textContent = c.langEn;
  document.getElementById('lang-en-lvl').textContent = c.langEnLvl;
}
// The site-wide switch (assets/i18n.js) owns the language; the CV just follows it.
setLang(window.siteLang ? window.siteLang.get() : 'en');
if (window.siteLang) window.siteLang.on(setLang);


document.getElementById('print-cv').addEventListener('click', () => window.print());
