'use strict';

/* ──────────────────────────────────────────
   PERGUNTAS
────────────────────────────────────────── */
const QS = [
  { n:1,  text:"Objetivo principal da sua foto", opts:[
    {id:'A', t:'Corporativo / LinkedIn'},
    {id:'B', t:'Personal branding & redes sociais'},
    {id:'C', t:'Speaker / bio para eventos'},
    {id:'D', t:'Portfólio criativo / artístico'},
    {id:'E', t:'Imprensa / mídia & publicações'},
  ]},
  { n:2,  text:"Apresentação de gênero", opts:[
    {id:'A', t:'Masculino'},
    {id:'B', t:'Feminino'},
    {id:'C', t:'Andrógino / não-binário'},
    {id:'D', t:'Prefiro não especificar'},
  ]},
  { n:3,  text:"Tom de pele", opts:[
    {id:'A', t:'Muito claro / porcelana'},
    {id:'B', t:'Claro / bege'},
    {id:'C', t:'Médio / oliva quente'},
    {id:'D', t:'Bronzeado / caramelo'},
    {id:'E', t:'Marrom profundo'},
    {id:'F', t:'Muito escuro / ébano'},
  ]},
  { n:4,  text:"Cabelo", opts:[
    {id:'A', t:'Curto e escuro (preto/castanho escuro)'},
    {id:'B', t:'Curto e claro (loiro/grisalho/branco)'},
    {id:'C', t:'Médio, liso e escuro'},
    {id:'D', t:'Médio, cacheado/ondulado'},
    {id:'E', t:'Comprido e liso'},
    {id:'F', t:'Comprido e cacheado / volumoso'},
    {id:'G', t:'Raspado / careca / bem curto'},
  ]},
  { n:5,  text:"Setor / profissão", opts:[
    {id:'A', t:'Tecnologia & startups'},
    {id:'B', t:'Finanças, direito ou consultoria'},
    {id:'C', t:'Criação, design ou mídia'},
    {id:'D', t:'Saúde ou ciência'},
    {id:'E', t:'Educação ou academia'},
    {id:'F', t:'Executivo(a) / liderança C-suite'},
    {id:'G', t:'Empreendedor(a) / fundador(a)'},
  ]},
  { n:6,  text:"Nível de formalidade desejado", opts:[
    {id:'A', t:'Ultra-formal (terno/gravata / haute couture)'},
    {id:'B', t:'Formal de negócios (blazer estruturado)'},
    {id:'C', t:'Business casual (polido, mas descontraído)'},
    {id:'D', t:'Smart casual (clean, moderno)'},
    {id:'E', t:'Casual criativo (expressivo, artístico)'},
  ]},
  { n:7,  text:"A impressão que você quer causar", opts:[
    {id:'A', t:'Autoritativo e imponente'},
    {id:'B', t:'Acessível e caloroso'},
    {id:'C', t:'Criativo e inovador'},
    {id:'D', t:'Confiável e sólido'},
    {id:'E', t:'Dinâmico e energético'},
    {id:'F', t:'Sofisticado e elegante'},
  ]},
  { n:8,  text:"Fundo / cenário", opts:[
    {id:'A', t:'Interior de escritório moderno, desfocado'},
    {id:'B', t:'Exterior urbano contemporâneo'},
    {id:'C', t:'Natureza / vegetação exuberante'},
    {id:'D', t:'Fundo de estúdio limpo (branco / cinza claro)'},
    {id:'E', t:'Fundo dramático escuro (carvão / tons profundos)'},
    {id:'F', t:'Arquitetura / geométrico / brutalista'},
  ]},
  { n:9,  text:"Clima da iluminação", opts:[
    {id:'A', t:'Claro e limpo — natural, arejado'},
    {id:'B', t:'Suave e quente — brilho da hora dourada'},
    {id:'C', t:'Dramático — alto contraste, sombras profundas'},
    {id:'D', t:'Frio e moderno — tons azul-branco'},
    {id:'E', t:'Cinematográfico — ciência de cor de filme'},
  ]},
  { n:10, text:"Paleta de cores do vestuário", opts:[
    {id:'A', t:'Neutros (branco, cinza, preto, marinho)'},
    {id:'B', t:'Tons terrosos (bege, marrom, ferrugem, oliva)'},
    {id:'C', t:'Cores saturadas (azul cobalto, esmeralda, carmim)'},
    {id:'D', t:'Pastéis (rosa claro, azul céu, lavanda)'},
    {id:'E', t:'Monocromático (look tonal em uma só cor)'},
  ]},
  { n:11, text:"Enquadramento / recorte", opts:[
    {id:'A', t:'Headshot fechado (rosto e ombros superiores)'},
    {id:'B', t:'Meio-corpo (até a cintura)'},
    {id:'C', t:'Três-quartos'},
    {id:'D', t:'Corpo inteiro'},
    {id:'E', t:'Candid / em movimento'},
  ]},
  { n:12, text:"Sua faixa etária", opts:[
    {id:'A', t:'18–25'},
    {id:'B', t:'26–35'},
    {id:'C', t:'36–45'},
    {id:'D', t:'46–55'},
    {id:'E', t:'56 anos ou mais'},
  ]},
  { n:13, text:"Expressão facial / emoção", opts:[
    {id:'A', t:'Sorriso confiante, boca fechada'},
    {id:'B', t:'Sorriso caloroso, boca aberta (dentes à mostra)'},
    {id:'C', t:'Sério e composto — sem sorriso'},
    {id:'D', t:'Olhar pensativo / contemplativo'},
    {id:'E', t:'Power pose — forte, intenso'},
  ]},
  { n:14, text:"Arquétipo de estilo de referência", opts:[
    {id:'A', t:'Executivo Fortune 500 (clássico, institucional)'},
    {id:'B', t:'Fundador de tech (smart casual, minimalista)'},
    {id:'C', t:'Diretor criativo (editorial, fashion-forward)'},
    {id:'D', t:'Intelectual acadêmico (discreto, credível)'},
    {id:'E', t:'Profissional de luxo (refinado, high-end)'},
  ]},
  { n:15, text:"Tipo físico / estrutura (para fotos de corpo — escolha o mais próximo)", opts:[
    {id:'A', t:'Magro(a) / esguio(a)'},
    {id:'B', t:'Atlético(a) / musculoso(a)'},
    {id:'C', t:'Médio(a) / estrutura regular'},
    {id:'D', t:'Curvilíneo(a) / cheio(a) de curvas'},
    {id:'E', t:'Plus size / estrutura grande'},
  ]},
  { n:16, text:"Acessórios", opts:[
    {id:'A', t:'Nenhum — minimalismo limpo'},
    {id:'B', t:'Óculos clássicos (tartaruga / armação de metal)'},
    {id:'C', t:'Óculos fashion (armação ousada)'},
    {id:'D', t:'Joias sutis (correntes finas, brincos pequenos)'},
    {id:'E', t:'Peça de destaque (relógio, colar, brincos marcantes)'},
  ]},
  { n:17, text:"Ângulo da câmera", opts:[
    {id:'A', t:'Nível dos olhos — neutro, honesto'},
    {id:'B', t:'Levemente baixo — poderoso, imponente'},
    {id:'C', t:'Levemente alto — acessível, suave'},
    {id:'D', t:'Dinâmico / leve inclinação — energia criativa'},
  ]},
  { n:18, text:"Estilo de pós-produção / edição", opts:[
    {id:'A', t:'Natural e limpo — retoque mínimo'},
    {id:'B', t:'Editorial high-fashion — polido, perfeito'},
    {id:'C', t:'Cinematográfico — granulado, correção de cor'},
    {id:'D', t:'Documental — cru, autêntico'},
    {id:'E', t:'Artístico / estilizado — tratamento criativo'},
  ]},
  { n:19, text:"Plataforma principal de distribuição", opts:[
    {id:'A', t:'LinkedIn & redes profissionais'},
    {id:'B', t:'Instagram & redes sociais pessoais'},
    {id:'C', t:'Site da empresa / página da equipe'},
    {id:'D', t:'Speaker bureau & materiais de eventos'},
    {id:'E', t:'Livro, press kit ou publicação'},
  ]},
  { n:20, text:"Elemento especial para incluir", opts:[
    {id:'A', t:'Prop relacionado ao trabalho (laptop, instrumento, ferramenta)'},
    {id:'B', t:'Cores da marca ou elemento de identidade visual'},
    {id:'C', t:'Elemento visual cultural ou de patrimônio'},
    {id:'D', t:'Clima sazonal ou específico de localização'},
    {id:'E', t:'Nenhum — retrato puro e limpo'},
  ]},
];

/* ──────────────────────────────────────────
   PROFILE MAPS
   • English strings → go into AI prompts (must stay EN)
   • *Lbl / *PT strings → display only, translated to PT
────────────────────────────────────────── */
const M = {
  gender:     {A:'man',    B:'woman',  C:'person', D:'person'},
  genderPoss: {A:'his',   B:'her',    C:'their',  D:'their'},

  skin: {
    A:'very fair porcelain complexion',
    B:'fair light beige complexion',
    C:'medium warm olive complexion',
    D:'tan caramel-brown complexion',
    E:'deep brown complexion',
    F:'rich ebony complexion',
  },

  hair: {
    A:'short dark hair',
    B:'short light hair (blonde/silver)',
    C:'medium-length straight dark hair',
    D:'medium-length curly hair',
    E:'long straight hair',
    F:'long voluminous curly hair',
    G:'closely cropped / shaved head',
  },

  age: {
    A:'early 20s', B:'late 20s to mid-30s', C:'late 30s to mid-40s',
    D:'late 40s to mid-50s', E:'late 50s or older',
  },

  formality: {
    A:'an impeccably tailored formal suit with tie',
    B:'a structured tailored blazer with dress shirt',
    C:'a polished business-casual blazer ensemble',
    D:'a sharp smart-casual modern outfit',
    E:'an expressive creative-casual look',
  },
  formalityLbl: {
    A:'Ultra-Formal', B:'Formal de Negócios', C:'Business Casual',
    D:'Smart Casual',  E:'Casual Criativo',
  },

  palette: {
    A:'in a crisp neutral palette — white, charcoal gray, or deep navy',
    B:'in rich earth tones — warm beige, walnut brown, rust, or olive',
    C:'in a bold saturated color — cobalt blue, deep emerald, or crimson',
    D:'in soft pastels — blush rose, sky blue, or dusty lavender',
    E:'in a refined tonal monochromatic look',
  },
  paletteLbl: {
    A:'Neutros', B:'Tons Terrosos', C:'Saturados',
    D:'Pastéis',  E:'Monocromático',
  },

  bg: {
    A:'shallow-focus modern open-plan office interior — blurred bokeh workspace background',
    B:'contemporary urban exterior — clean architectural facade, natural light, city texture',
    C:'lush natural greenery — dappled light through leaves, soft emerald bokeh',
    D:'clean studio seamless backdrop — bright white or dove gray, even gradient',
    E:'deep charcoal dramatic studio backdrop — dark vignette, atmospheric gradient',
    F:'brutalist concrete architecture — strong geometric shapes, commanding negative space',
  },
  /* bgLbl stays in English — it is interpolated into AI prompts in promptB */
  bgLbl: {
    A:'Modern Office', B:'Urban Exterior', C:'Nature / Greenery',
    D:'Clean Studio',  E:'Dark Studio',    F:'Architectural',
  },
  /* bgLblPT is for display only (identity card, director note) */
  bgLblPT: {
    A:'Escritório Moderno', B:'Exterior Urbano', C:'Natureza',
    D:'Estúdio Limpo',     E:'Estúdio Escuro',  F:'Arquitetura',
  },

  lighting: {
    A:'large 120cm octabox key light at 45°, beauty-dish fill at 1:4 ratio, clean 5600K white balance — bright, airy, shadow-free',
    B:'warm golden-hour window light from camera-left, soft bounce fill from right, 3:1 Rembrandt ratio at 3200K — glowing and warm',
    C:'dramatic single hard-source key light from high camera-right, deep sculptural shadows, no fill — chiaroscuro contrast',
    D:'cool blue-white LED panel array, 6500K daylight white balance, crisp modern editorial illumination',
    E:'cinematic three-point setup with warm practicals, subtle amber/teal split gel, film-grade color science',
  },
  lightingLbl: {
    A:'Claro & Limpo', B:'Suave & Quente', C:'Dramático',
    D:'Frio & Moderno', E:'Cinematográfico',
  },

  impression: {
    A:'authoritative, commanding presence',
    B:'warm, genuinely approachable energy',
    C:'creative, visionary, innovative quality',
    D:'trustworthy, grounded, reliable character',
    E:'dynamic, energetic, driven momentum',
    F:'sophisticated, elegant, refined poise',
  },
  /* impressionPT: used in director note (PT) only */
  impressionPT: {
    A:'presença autoritativa e imponente',
    B:'energia acessível e genuinamente calorosa',
    C:'criatividade visionária e inovadora',
    D:'caráter confiável, sólido e seguro',
    E:'dinamismo energético e determinado',
    F:'postura sofisticada, elegante e refinada',
  },

  expression: {
    A:'confident closed-mouth smile — composed and assured',
    B:'warm open smile, teeth visible — radiating genuine approachability',
    C:'serious, composed expression — no smile, focused intense gaze',
    D:'thoughtful pensive expression — intellectually engaged, gaze slightly off-axis',
    E:'direct power gaze into camera — strong, unwavering, commanding',
  },

  framing: {
    A:'tight headshot — face and upper shoulders fill the frame',
    B:'upper-body waist-up portrait',
    C:'three-quarter length portrait',
    D:'full-body portrait, head to toe',
    E:'candid lifestyle shot — subject in natural motion',
  },
  framingLbl: {
    A:'Headshot Fechado', B:'Meio-Corpo', C:'Três-Quartos',
    D:'Corpo Inteiro',    E:'Candid / Em Movimento',
  },

  lens: {
    A:'85mm f/1.4 portrait lens, compressed background, shallow depth of field',
    B:'85mm f/1.8 portrait lens',
    C:'70mm f/2.8 medium telephoto',
    D:'50mm f/2 full-frame standard lens',
    E:'35mm f/2 environmental wide-normal lens',
  },
  lensShort: {
    A:'85mm f/1.4', B:'85mm f/1.8', C:'70mm f/2.8', D:'50mm f/2', E:'35mm f/2',
  },

  angle: {
    A:'camera at exact eye level — neutral, direct, honest perspective',
    B:'camera positioned slightly below eye level — empowering, commanding perspective',
    C:'camera positioned slightly above eye level — soft, open, approachable',
    D:'subtle dutch tilt — slight diagonal frame for creative dynamic tension',
  },
  angleLbl: {
    A:'Nível dos Olhos', B:'Ângulo Baixo', C:'Ângulo Alto', D:'Inclinação Criativa',
  },

  postProcess: {
    A:'natural clean edit — skin texture preserved, minimal retouching, honest finish',
    B:'high-fashion editorial retouching — flawless, polished, perfected skin, impeccable finish',
    C:'cinematic color grade — subtle film grain, split-toning: warm highlights, cool shadows',
    D:'documentary-style raw processing — authentic, unmanipulated, journalistic honesty',
    E:'artistic stylized treatment — signature color science, creative post-production',
  },
  postProcessLbl: {
    A:'Natural & Limpo', B:'Editorial High-Fashion', C:'Cinematográfico',
    D:'Documental',      E:'Artístico / Estilizado',
  },

  /* archetype stays English — used literally inside AI prompts */
  archetype: {
    A:'Fortune 500 Executive',
    B:'Tech Founder',
    C:'Creative Director',
    D:'Academic Intellectual',
    E:'Luxury Professional',
  },
  archetypeDesc: {
    A:'classic, institutional, quietly powerful authority',
    B:'minimalist, modern, understated confidence',
    C:'editorial, fashion-forward, visually articulate',
    D:'understated, credible, scholarly gravitas',
    E:'refined, impeccable, high-end luxury sensibility',
  },
  /* archetypeDescPT: used in director note (PT) only */
  archetypeDescPT: {
    A:'autoridade clássica e institucional',
    B:'confiança minimalista e moderna',
    C:'expressão editorial e fashion-forward',
    D:'gravitas acadêmica e credível',
    E:'sensibilidade refinada de alto padrão',
  },

  industry: {
    A:'the technology and startup ecosystem',
    B:'finance, law, and professional consulting',
    C:'creative, design, and media industries',
    D:'healthcare and scientific research',
    E:'education and academia',
    F:'C-suite executive leadership',
    G:'entrepreneurship and the founder community',
  },

  /* purposeLbl stays English — interpolated into AI prompts */
  purposeLbl: {
    A:'Corporate / LinkedIn', B:'Personal Brand', C:'Speaker Bio',
    D:'Creative Portfolio',   E:'Press / Media',
  },

  platformLbl: {
    A:'LinkedIn & Redes Profissionais', B:'Instagram & Redes Sociais',
    C:'Site da Empresa',                D:'Speaker Bureau',
    E:'Press Kit / Publicação',
  },

  body: {
    A:'slim, lean build',
    B:'athletic, muscular build',
    C:'average medium build',
    D:'curvy, full-figured frame',
    E:'plus-size, generous frame',
  },

  accessory: {
    A:'',
    B:'wearing classic tortoiseshell or metal-frame glasses',
    C:'wearing bold fashion-forward statement glasses',
    D:'with subtle fine jewelry — a delicate chain or small earring studs',
    E:'with a commanding statement accessory — luxury watch, bold necklace, or striking earrings',
  },

  special: {
    A:' A relevant work prop is incorporated naturally — laptop, professional instrument, or creative tool.',
    B:' Brand color elements are woven organically into the composition.',
    C:' A meaningful cultural or heritage visual element is embedded in the setting.',
    D:' The atmosphere carries a strong seasonal and location-specific mood.',
    E:'',
  },
};

/* ──────────────────────────────────────────
   STATE
────────────────────────────────────────── */
let cur = 0;
let ans = {};
let busy = false;

/* ──────────────────────────────────────────
   SCREEN MANAGEMENT
────────────────────────────────────────── */
function show(id) {
  ['screen-intro','screen-quiz','screen-results'].forEach(function(sid) {
    document.getElementById(sid).style.display = 'none';
  });
  document.getElementById(id).style.display = 'block';
  window.scrollTo(0, 0);
}

/* ──────────────────────────────────────────
   QUIZ
────────────────────────────────────────── */
function startQuiz() {
  cur = 0;
  ans = {};
  show('screen-quiz');
  renderQ();
}

function renderQ() {
  var q = QS[cur];
  var total = QS.length;
  var pct = Math.max(Math.round((cur / total) * 100), 3);

  document.getElementById('prog-frac').textContent = (cur + 1) + ' / ' + total;
  document.getElementById('prog-fill').style.width = pct + '%';

  var html = '<div class="question-block">'
    + '<div class="q-number">Pergunta ' + q.n + '  /  ' + total + '</div>'
    + '<h2 class="q-text">' + q.text + '</h2>'
    + '<div class="options-stack">';

  q.opts.forEach(function(o) {
    var sel = ans[q.n] === o.id ? ' selected' : '';
    html += '<div class="opt-card' + sel + '" data-pick="' + o.id + '" id="opt-' + o.id + '">'
      + '<div class="opt-badge">' + o.id + '</div>'
      + '<div class="opt-text">' + o.t + '</div>'
      + '</div>';
  });

  html += '</div></div>';
  document.getElementById('q-area').innerHTML = html;
}

function pick(optId) {
  if (busy) return;
  var q = QS[cur];
  ans[q.n] = optId;

  document.querySelectorAll('.opt-card').forEach(function(c) {
    c.classList.remove('selected');
  });
  var el = document.getElementById('opt-' + optId);
  if (el) el.classList.add('selected');

  busy = true;
  setTimeout(function() {
    busy = false;
    cur++;
    if (cur >= QS.length) {
      saveHash();
      buildResults();
    } else {
      renderQ();
    }
  }, 480);
}

/* ──────────────────────────────────────────
   URL HASH
────────────────────────────────────────── */
function saveHash() {
  var encoded = QS.map(function(q) { return ans[q.n] || q.opts[0].id; }).join('');
  history.replaceState(null, '', '#' + encoded);
}

function loadHash() {
  var h = window.location.hash.slice(1);
  if (!h || h.length < 20) return false;
  QS.forEach(function(q, i) {
    var letter = h[i] ? h[i].toUpperCase() : q.opts[0].id;
    var valid = q.opts.map(function(o) { return o.id; });
    ans[q.n] = valid.indexOf(letter) !== -1 ? letter : q.opts[0].id;
  });
  return true;
}

/* ──────────────────────────────────────────
   PROFILE BUILDER
────────────────────────────────────────── */
function profile() {
  var a = ans;
  var fk = a[11] || 'A';
  return {
    gender:         M.gender[a[2]]         || 'person',
    genderPoss:     M.genderPoss[a[2]]     || 'their',
    skin:           M.skin[a[3]]           || 'neutral complexion',
    hair:           M.hair[a[4]]           || 'hair',
    age:            M.age[a[12]]           || 'adult',
    formality:      M.formality[a[6]]      || 'professional attire',
    formalityLbl:   M.formalityLbl[a[6]]   || 'Professional',
    palette:        M.palette[a[10]]       || 'neutral palette',
    paletteLbl:     M.paletteLbl[a[10]]    || 'Neutros',
    bg:             M.bg[a[8]]             || 'studio backdrop',
    bgLbl:          M.bgLbl[a[8]]          || 'Studio',
    bgLblPT:        M.bgLblPT[a[8]]        || 'Estúdio',
    lighting:       M.lighting[a[9]]       || 'studio lighting',
    lightingLbl:    M.lightingLbl[a[9]]    || 'Claro & Limpo',
    impression:     M.impression[a[7]]     || 'professional presence',
    expression:     M.expression[a[13]]    || 'composed expression',
    framing:        M.framing[fk]          || 'portrait',
    framingLbl:     M.framingLbl[fk]       || 'Headshot Fechado',
    framingKey:     fk,
    lens:           M.lens[fk]             || '85mm f/1.8',
    lensShort:      M.lensShort[fk]        || '85mm f/1.8',
    angle:          M.angle[a[17]]         || 'eye level',
    angleLbl:       M.angleLbl[a[17]]      || 'Nível dos Olhos',
    postProcess:    M.postProcess[a[18]]   || 'natural edit',
    postProcessLbl: M.postProcessLbl[a[18]]|| 'Natural & Limpo',
    archetype:      M.archetype[a[14]]     || 'Professional',
    archetypeDesc:  M.archetypeDesc[a[14]] || 'professional quality',
    industry:       M.industry[a[5]]       || 'professional field',
    purposeLbl:     M.purposeLbl[a[1]]     || 'Professional',
    platformLbl:    M.platformLbl[a[19]]   || 'Plataforma Profissional',
    body:           M.body[a[15]]          || 'medium build',
    accessory:      M.accessory[a[16]]     || '',
    special:        M.special[a[20]]       || '',
  };
}

function buildSubject(p) {
  var acc = p.accessory ? ', ' + p.accessory : '';
  var body = (p.framingKey !== 'A') ? ', ' + p.body : '';
  return 'A ' + p.age + ' ' + p.gender + ' with ' + p.skin + ' and ' + p.hair + acc + body;
}

/* ──────────────────────────────────────────
   PROMPT GENERATION
   Stays entirely in English — AI image tools
   (Midjourney, DALL·E, Flux, SD) interpret
   English prompts with far greater accuracy.
────────────────────────────────────────── */
function promptA(p) {
  var parts = [
    'Professional portrait photograph of ' + buildSubject(p) + ',',
    'wearing ' + p.formality + ' ' + p.palette + '.',
    'Expression: ' + p.expression + ', projecting ' + p.impression + '.',
    p.framing + ', shot on ' + p.lens + ', ' + p.angle + '.',
    'Lighting: ' + p.lighting + '.',
    'Background: ' + p.bg + '.',
    'Post-processing: ' + p.postProcess + '.',
    'Style archetype: ' + p.archetype + ' — ' + p.archetypeDesc + '.',
    'Intended for ' + p.purposeLbl + ' in ' + p.industry + '.',
    p.special.trim() || '',
    'Ultra-high resolution, commercial portrait photography, shot on Phase One IQ4 150MP. --ar 4:5 --style raw --q 2',
  ];
  return parts.filter(Boolean).join(' ');
}

function promptB(p) {
  /* lightingLbl is now in PT — compare against PT strings */
  var editLight = (p.lightingLbl === 'Dramático' || p.lightingLbl === 'Cinematográfico')
    ? p.lighting
    : 'hard directional single-source key light with dramatic shadow fall-off, fashion-editorial contrast';
  /* bgLbl stays English — compare and interpolate against EN strings */
  var editBg = (p.bgLbl === 'Dark Studio' || p.bgLbl === 'Architectural')
    ? p.bg
    : 'editorial reinterpretation of ' + p.bgLbl + ' — graphic, high-contrast, compositionally bold';
  return [
    'Editorial portrait — ' + buildSubject(p) + ',',
    'wearing an elevated editorial interpretation of ' + p.formality + ' ' + p.palette + '.',
    'Expression: commanding, striking — owning the frame with ' + p.impression + '.',
    p.framing + '. ' + p.lens + ', ' + p.angle + '.',
    'Lighting: ' + editLight + '.',
    'Background: ' + editBg + '.',
    'Post-processing: high-fashion editorial retouching — sculpted, polished, perfected; impeccable finish.',
    'Shot with a luxury fashion-magazine sensibility, conveying ' + p.archetypeDesc + '.',
    'For ' + p.industry + '. --ar 4:5 --style raw --q 2',
  ].join(' ');
}

function promptC(p) {
  var ctxMap = {
    A:'caught reviewing a document at a sleek desk, natural window light from the side — observational, unposed',
    B:'walking purposefully through a professional environment, in natural confident stride — caught mid-motion',
    C:'in animated professional conversation, gesturing naturally, fully engaged — genuine and unguarded',
    D:'a quiet reflective moment — leaning against a wall or window, half-turned, candidly observed',
    E:'in a candid moment of movement — turning, stepping, or gesturing in a professional environment',
  };
  var ctx = ctxMap[p.framingKey] || ctxMap['B'];
  return [
    'Candid lifestyle portrait — ' + buildSubject(p) + ',',
    'wearing ' + p.formality + ' ' + p.palette + ', ' + ctx + '.',
    'The shot feels observed, not posed — ' + p.impression + ' expressed through natural body language and environment.',
    'Environmental framing with a wider field of view to establish context.',
    p.lens + ', ' + p.angle + '.',
    'Lighting: ' + p.lighting + ' adapted to a natural uncontrolled environment — authentic, less controlled.',
    'Background: ' + p.bg + ' — integrated as contextual storytelling, not purely decorative.',
    'Post-processing: ' + p.postProcess + ' — the overall feel is aspirational cinema-verite.',
    'For ' + p.industry + ', ' + p.purposeLbl + '. --ar 4:5 --style raw --q 2',
  ].join(' ');
}

/* ──────────────────────────────────────────
   NOTA DE DIREÇÃO DO FOTÓGRAFO  (PT)
────────────────────────────────────────── */
function dirNote(p) {
  var imp       = M.impressionPT[ans[7]]    || p.impression;
  var archDescPT = M.archetypeDescPT[ans[14]] || p.archetypeDesc;
  return 'Para este retrato de <strong>' + p.archetype + '</strong> — destinado a <strong>' + p.platformLbl + '</strong> — o objetivo é transmitir em cada frame: <strong>' + imp + '</strong>. '
    + 'Fotografaremos com uma objetiva <strong>' + p.lensShort + '</strong>, ' + p.angleLbl.toLowerCase() + ', sob <strong>iluminação ' + p.lightingLbl + '</strong> (' + p.lighting.split('—')[0].trim() + '). '
    + 'O(a) fotografado(a) estará em look ' + p.formalityLbl.toLowerCase() + ', paleta ' + p.paletteLbl.toLowerCase() + ' — o vestuário deve harmonizar com o cenário "' + p.bgLblPT + '" sem conflito de cores. '
    + 'Toda a pós-produção deve buscar um resultado <strong>' + p.postProcessLbl.toLowerCase() + '</strong> — transmitindo ' + archDescPT + ', sem exageros ou artificialidade.';
}

/* ──────────────────────────────────────────
   FERRAMENTAS RECOMENDADAS
────────────────────────────────────────── */
function scoredTools() {
  var a = ans;
  var scores = {
    'Midjourney': 0,
    'DALL·E 3': 0,
    'Flux (FLUX.1 dev)': 0,
    'Stable Diffusion + ControlNet': 0,
  };
  var descs = {
    'Midjourney': 'Referência para estética editorial, fashion-forward e cinematográfica em retratos. Excelente em mood e atmosfera.',
    'DALL·E 3': 'Ótimo para headshots corporativos limpos e naturais. Prompts simples, resultados confiáveis para LinkedIn e páginas de equipe.',
    'Flux (FLUX.1 dev)': 'Fotorrealismo de ponta. Ideal para fotos documentais e de lifestyle que precisam parecer fotografias reais.',
    'Stable Diffusion + ControlNet': 'Controle preciso de pose corporal via mapas de profundidade — essencial para retratos de corpo inteiro e estruturas físicas não-padrão.',
  };

  if (['C','E'].indexOf(a[14]) !== -1) scores['Midjourney'] += 3;
  if (['C','E'].indexOf(a[9])  !== -1) scores['Midjourney'] += 2;
  if (['B','E'].indexOf(a[18]) !== -1) scores['Midjourney'] += 2;
  if (['C','D','E'].indexOf(a[1]) !== -1) scores['Midjourney'] += 1;

  if (a[1] === 'A' || a[1] === 'C')       scores['DALL·E 3'] += 2;
  if (['A','B'].indexOf(a[14]) !== -1)     scores['DALL·E 3'] += 2;
  if (['A','B'].indexOf(a[9])  !== -1)     scores['DALL·E 3'] += 2;
  if (a[18] === 'A')                       scores['DALL·E 3'] += 2;
  if (['D','E'].indexOf(a[8])  !== -1)     scores['DALL·E 3'] += 1;

  if (['B','D'].indexOf(a[14]) !== -1)     scores['Flux (FLUX.1 dev)'] += 2;
  if (['D','A'].indexOf(a[18]) !== -1)     scores['Flux (FLUX.1 dev)'] += 2;
  if (a[1] === 'B')                        scores['Flux (FLUX.1 dev)'] += 1;
  if (['B','C'].indexOf(a[8])  !== -1)     scores['Flux (FLUX.1 dev)'] += 1;
  if (['A','B'].indexOf(a[9])  !== -1)     scores['Flux (FLUX.1 dev)'] += 1;

  if (a[11] === 'D')                               scores['Stable Diffusion + ControlNet'] += 4;
  if (a[11] === 'C')                               scores['Stable Diffusion + ControlNet'] += 2;
  if (['B','D','E'].indexOf(a[15]) !== -1)         scores['Stable Diffusion + ControlNet'] += 2;
  if (a[11] === 'E')                               scores['Stable Diffusion + ControlNet'] += 1;

  return Object.keys(scores)
    .sort(function(x, y) { return scores[y] - scores[x]; })
    .map(function(name, i) { return { name: name, desc: descs[name], rank: i }; });
}

/* ──────────────────────────────────────────
   RENDER RESULTADOS
────────────────────────────────────────── */
function buildResults() {
  var p = profile();
  var pA = promptA(p);
  var pB = promptB(p);
  var pC = promptC(p);
  var note = dirNote(p);
  var tools = scoredTools();

  var toolsHTML = tools.map(function(t) {
    var cls = t.rank === 0 ? 'tool-card top' : t.rank === 1 ? 'tool-card second' : 'tool-card';
    var badge = t.rank === 0
      ? '<span class="tool-rec-badge">★ Recomendação Principal</span>'
      : t.rank === 1
        ? '<span class="tool-rec-badge" style="color:var(--text2)">◆ Forte Alternativa</span>'
        : '';
    return '<div class="' + cls + '">' + badge
      + '<div class="tool-name">' + t.name + '</div>'
      + '<div class="tool-desc">' + t.desc + '</div></div>';
  }).join('');

  document.getElementById('results-root').innerHTML =
    '<div class="results-header">'
    + '<div class="gold-rule" style="margin-bottom:1.2rem"></div>'
    + '<div class="results-eyebrow">✶ Seus Resultados</div>'
    + '<h2 class="results-title">Seu Perfil Fotográfico</h2>'
    + '<p class="results-desc">Seu blueprint profissional de retrato — pronto para geração por IA.</p>'
    + '</div>'

    + '<div class="id-card">'
    + '<div class="id-card-label">✶ Cartão de Identidade Fotográfica</div>'
    + '<div class="id-grid">'
    + '<div class="id-item"><div class="id-item-label">Objetivo</div><div class="id-item-value">' + p.purposeLbl + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Arquétipo</div><div class="id-item-value">' + p.archetype + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Iluminação</div><div class="id-item-value">' + p.lightingLbl + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Cenário</div><div class="id-item-value">' + p.bgLblPT + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Paleta</div><div class="id-item-value">' + p.paletteLbl + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Enquadramento</div><div class="id-item-value">' + p.framingLbl + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Formalidade</div><div class="id-item-value">' + p.formalityLbl + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Plataforma</div><div class="id-item-value">' + p.platformLbl + '</div></div>'
    + '</div></div>'

    + '<div class="section">'
    + '<div class="section-head">Prompts para IA</div>'
    + '<p style="font-size:0.72rem;color:var(--muted);margin:-0.5rem 0 1.2rem;letter-spacing:0.03em;">em inglês — as ferramentas de IA funcionam melhor com prompts em inglês</p>'

    + '<div class="prompt-card">'
    + '<div class="prompt-label">Prompt A &mdash; Principal &middot; Midjourney / DALL&middot;E 3 / Flux</div>'
    + '<div class="prompt-body" id="pA">' + pA + '</div>'
    + '<button class="copy-btn" data-copy="pA">&#x2398; Copiar Prompt A</button>'
    + '</div>'

    + '<div class="prompt-card">'
    + '<div class="prompt-label">Prompt B &mdash; Variante Editorial</div>'
    + '<div class="prompt-body" id="pB">' + pB + '</div>'
    + '<button class="copy-btn" data-copy="pB">&#x2398; Copiar Prompt B</button>'
    + '</div>'

    + '<div class="prompt-card">'
    + '<div class="prompt-label">Prompt C &mdash; Variante Candid / Lifestyle</div>'
    + '<div class="prompt-body" id="pC">' + pC + '</div>'
    + '<button class="copy-btn" data-copy="pC">&#x2398; Copiar Prompt C</button>'
    + '</div>'
    + '</div>'

    + '<div class="section">'
    + '<div class="section-head">Nota de Direção do Fotógrafo</div>'
    + '<div class="director-note">' + note + '</div>'
    + '</div>'

    + '<div class="section">'
    + '<div class="section-head">Ferramentas de IA Recomendadas</div>'
    + '<div class="tools-grid">' + toolsHTML + '</div>'
    + '</div>'

    + '<div class="action-bar">'
    + '<button class="btn-outline" id="btn-retake">&larr; Refazer o Quiz</button>'
    + '<button class="btn-gold-outline" id="btn-share">Compartilhar Resultados &nearr;</button>'
    + '</div>';

  show('screen-results');
}

/* ──────────────────────────────────────────
   UTILITÁRIOS
────────────────────────────────────────── */
function doCopy(elId, btn) {
  var text = document.getElementById(elId).textContent;
  var orig = btn.textContent;
  function confirm() {
    btn.textContent = '✓ Copiado!';
    btn.classList.add('copied');
    setTimeout(function() { btn.textContent = orig; btn.classList.remove('copied'); }, 2600);
  }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(confirm).catch(function() { fallbackCopy(text, confirm); });
  } else {
    fallbackCopy(text, confirm);
  }
}

function fallbackCopy(text, cb) {
  var ta = document.createElement('textarea');
  ta.value = text;
  ta.style.cssText = 'position:fixed;opacity:0';
  document.body.appendChild(ta);
  ta.focus(); ta.select();
  try { document.execCommand('copy'); cb(); } catch(e) {}
  document.body.removeChild(ta);
}

function doShare(btn) {
  var url = location.href.split('#')[0] + location.hash;
  var orig = btn.textContent;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(function() {
      btn.textContent = '✓ Link copiado!';
      setTimeout(function() { btn.textContent = orig; }, 2600);
    });
  }
}

function retake() {
  history.replaceState(null, '', location.pathname + location.search);
  cur = 0; ans = {};
  show('screen-intro');
}

/* ──────────────────────────────────────────
   INIT — eventos delegados, sem onclick inline
────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {

  // Botão "Iniciar o Quiz"
  document.getElementById('btn-start').addEventListener('click', startQuiz);

  // Opções (delegação — recriadas a cada pergunta)
  document.getElementById('q-area').addEventListener('click', function(e) {
    var card = e.target.closest('[data-pick]');
    if (card) pick(card.dataset.pick);
  });

  // Botões dos resultados (delegação — criados por buildResults)
  document.getElementById('results-root').addEventListener('click', function(e) {
    var btn = e.target.closest('button');
    if (!btn) return;
    if (btn.id === 'btn-retake') { retake(); return; }
    if (btn.id === 'btn-share')  { doShare(btn); return; }
    if (btn.dataset.copy)        { doCopy(btn.dataset.copy, btn); }
  });

  // Carregar do hash da URL ou mostrar a intro
  if (loadHash()) {
    buildResults();
  } else {
    show('screen-intro');
  }
});
