'use strict';

/* ──────────────────────────────────────────
   QUESTIONS
────────────────────────────────────────── */
const QS = [
  { n:1,  text:"Primary purpose of your photo", opts:[
    {id:'A', t:'Corporate / LinkedIn profile'},
    {id:'B', t:'Personal branding & social media'},
    {id:'C', t:'Speaker / conference bio'},
    {id:'D', t:'Creative / artistic portfolio'},
    {id:'E', t:'Press / media & publications'},
  ]},
  { n:2,  text:"Gender presentation", opts:[
    {id:'A', t:'Masculine'},
    {id:'B', t:'Feminine'},
    {id:'C', t:'Androgynous / non-binary'},
    {id:'D', t:'Prefer not to specify'},
  ]},
  { n:3,  text:"Skin tone", opts:[
    {id:'A', t:'Very fair / porcelain'},
    {id:'B', t:'Fair / light beige'},
    {id:'C', t:'Medium / warm olive'},
    {id:'D', t:'Tan / caramel brown'},
    {id:'E', t:'Deep brown'},
    {id:'F', t:'Very deep / ebony'},
  ]},
  { n:4,  text:"Hair", opts:[
    {id:'A', t:'Short & dark (black/dark brown)'},
    {id:'B', t:'Short & light (blonde/gray/white)'},
    {id:'C', t:'Medium length, straight, dark'},
    {id:'D', t:'Medium length, curly/wavy'},
    {id:'E', t:'Long & straight'},
    {id:'F', t:'Long & curly / voluminous'},
    {id:'G', t:'Shaved / bald / very short'},
  ]},
  { n:5,  text:"Industry / profession", opts:[
    {id:'A', t:'Technology & startups'},
    {id:'B', t:'Finance, law or consulting'},
    {id:'C', t:'Creative, design or media'},
    {id:'D', t:'Healthcare or science'},
    {id:'E', t:'Education or academia'},
    {id:'F', t:'Executive / C-suite leadership'},
    {id:'G', t:'Entrepreneur / founder'},
  ]},
  { n:6,  text:"Desired level of formality", opts:[
    {id:'A', t:'Ultra-formal (suit & tie / haute couture)'},
    {id:'B', t:'Business formal (blazer, structured)'},
    {id:'C', t:'Business casual (polished but relaxed)'},
    {id:'D', t:'Smart casual (clean, modern)'},
    {id:'E', t:'Creative casual (expressive, artistic)'},
  ]},
  { n:7,  text:"The impression you want to make", opts:[
    {id:'A', t:'Authoritative and commanding'},
    {id:'B', t:'Approachable and warm'},
    {id:'C', t:'Creative and innovative'},
    {id:'D', t:'Trustworthy and grounded'},
    {id:'E', t:'Dynamic and energetic'},
    {id:'F', t:'Sophisticated and elegant'},
  ]},
  { n:8,  text:"Background / setting", opts:[
    {id:'A', t:'Blurred modern office interior'},
    {id:'B', t:'Contemporary urban exterior'},
    {id:'C', t:'Lush nature / greenery'},
    {id:'D', t:'Clean studio backdrop (white / light gray)'},
    {id:'E', t:'Dark, dramatic backdrop (charcoal / deep tones)'},
    {id:'F', t:'Architectural / geometric / brutalist'},
  ]},
  { n:9,  text:"Lighting mood", opts:[
    {id:'A', t:'Bright & clean — natural, airy'},
    {id:'B', t:'Soft & warm — golden hour glow'},
    {id:'C', t:'Dramatic — strong contrast, deep shadows'},
    {id:'D', t:'Cool & modern — blue-white tones'},
    {id:'E', t:'Cinematic — film-grade color science'},
  ]},
  { n:10, text:"Wardrobe color palette", opts:[
    {id:'A', t:'Neutrals (white, gray, black, navy)'},
    {id:'B', t:'Earth tones (beige, brown, rust, olive)'},
    {id:'C', t:'Bold saturated colors (cobalt, emerald, crimson)'},
    {id:'D', t:'Pastels (light rose, sky blue, lavender)'},
    {id:'E', t:'Monochromatic (tonal dressing in one color)'},
  ]},
  { n:11, text:"Framing / crop", opts:[
    {id:'A', t:'Tight headshot (face and upper shoulders)'},
    {id:'B', t:'Upper body (waist up)'},
    {id:'C', t:'Three-quarter length'},
    {id:'D', t:'Full body portrait'},
    {id:'E', t:'Candid / in-motion'},
  ]},
  { n:12, text:"Your age range", opts:[
    {id:'A', t:'18–25'},
    {id:'B', t:'26–35'},
    {id:'C', t:'36–45'},
    {id:'D', t:'46–55'},
    {id:'E', t:'56 and above'},
  ]},
  { n:13, text:"Facial expression / emotion", opts:[
    {id:'A', t:'Confident, closed-mouth smile'},
    {id:'B', t:'Warm, open smile (teeth showing)'},
    {id:'C', t:'Serious and composed — no smile'},
    {id:'D', t:'Pensive / thoughtful gaze'},
    {id:'E', t:'Power pose — strong, intense'},
  ]},
  { n:14, text:"Style archetype reference", opts:[
    {id:'A', t:'Fortune 500 executive (classic, institutional)'},
    {id:'B', t:'Tech founder (smart casual, minimalist)'},
    {id:'C', t:'Creative director (editorial, fashion-forward)'},
    {id:'D', t:'Academic intellectual (understated, credible)'},
    {id:'E', t:'Luxury professional (refined, high-end)'},
  ]},
  { n:15, text:"Body type / build (for body shots — select closest)", opts:[
    {id:'A', t:'Slim / lean'},
    {id:'B', t:'Athletic / muscular'},
    {id:'C', t:'Average / medium build'},
    {id:'D', t:'Curvy / full-figured'},
    {id:'E', t:'Plus-size / large frame'},
  ]},
  { n:16, text:"Accessories", opts:[
    {id:'A', t:'None — clean minimalism'},
    {id:'B', t:'Classic glasses (tortoiseshell / metal frames)'},
    {id:'C', t:'Fashion-forward glasses (bold frames)'},
    {id:'D', t:'Subtle jewelry (thin chains, studs)'},
    {id:'E', t:'Statement piece (watch, bold necklace, earrings)'},
  ]},
  { n:17, text:"Camera angle", opts:[
    {id:'A', t:'Eye level — neutral, honest'},
    {id:'B', t:'Slightly low — powerful, imposing'},
    {id:'C', t:'Slightly high — approachable, soft'},
    {id:'D', t:'Dynamic / slight dutch — creative energy'},
  ]},
  { n:18, text:"Post-processing / editing style", opts:[
    {id:'A', t:'Natural & clean — minimal retouching'},
    {id:'B', t:'High-fashion editorial — polished, perfect'},
    {id:'C', t:'Cinematic — film grain, color grade'},
    {id:'D', t:'Documentary — raw, authentic'},
    {id:'E', t:'Artistic / stylized — creative treatment'},
  ]},
  { n:19, text:"Primary distribution platform", opts:[
    {id:'A', t:'LinkedIn & professional networks'},
    {id:'B', t:'Instagram & personal social media'},
    {id:'C', t:'Company website / team page'},
    {id:'D', t:'Speaker bureau & conference materials'},
    {id:'E', t:'Book, press kit or publication'},
  ]},
  { n:20, text:"Special element to include", opts:[
    {id:'A', t:'Work-related prop (laptop, instrument, tool)'},
    {id:'B', t:'Brand colors or company identity element'},
    {id:'C', t:'Cultural or heritage visual element'},
    {id:'D', t:'Seasonal or location-specific mood'},
    {id:'E', t:'None — pure, clean portrait'},
  ]},
];

/* ──────────────────────────────────────────
   PROFILE MAPS
────────────────────────────────────────── */
const M = {
  gender:        {A:'man',     B:'woman',   C:'person',  D:'person'},
  genderPoss:    {A:'his',     B:'her',     C:'their',   D:'their'},

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
    A:'Ultra-Formal', B:'Business Formal', C:'Business Casual',
    D:'Smart Casual',  E:'Creative Casual',
  },

  palette: {
    A:'in a crisp neutral palette — white, charcoal gray, or deep navy',
    B:'in rich earth tones — warm beige, walnut brown, rust, or olive',
    C:'in a bold saturated color — cobalt blue, deep emerald, or crimson',
    D:'in soft pastels — blush rose, sky blue, or dusty lavender',
    E:'in a refined tonal monochromatic look',
  },
  paletteLbl: {
    A:'Neutrals', B:'Earth Tones', C:'Bold Saturated',
    D:'Pastels',  E:'Monochromatic',
  },

  bg: {
    A:'shallow-focus modern open-plan office interior — blurred bokeh workspace background',
    B:'contemporary urban exterior — clean architectural facade, natural light, city texture',
    C:'lush natural greenery — dappled light through leaves, soft emerald bokeh',
    D:'clean studio seamless backdrop — bright white or dove gray, even gradient',
    E:'deep charcoal dramatic studio backdrop — dark vignette, atmospheric gradient',
    F:'brutalist concrete architecture — strong geometric shapes, commanding negative space',
  },
  bgLbl: {
    A:'Modern Office', B:'Urban Exterior', C:'Nature / Greenery',
    D:'Clean Studio',  E:'Dark Studio',    F:'Architectural',
  },

  lighting: {
    A:'large 120cm octabox key light at 45°, beauty-dish fill at 1:4 ratio, clean 5600K white balance — bright, airy, shadow-free',
    B:'warm golden-hour window light from camera-left, soft bounce fill from right, 3:1 Rembrandt ratio at 3200K — glowing and warm',
    C:'dramatic single hard-source key light from high camera-right, deep sculptural shadows, no fill — chiaroscuro contrast',
    D:'cool blue-white LED panel array, 6500K daylight white balance, crisp modern editorial illumination',
    E:'cinematic three-point setup with warm practicals, subtle amber/teal split gel, film-grade color science',
  },
  lightingLbl: {
    A:'Bright & Clean', B:'Soft & Warm', C:'Dramatic',
    D:'Cool & Modern',  E:'Cinematic',
  },

  impression: {
    A:'authoritative, commanding presence',
    B:'warm, genuinely approachable energy',
    C:'creative, visionary, innovative quality',
    D:'trustworthy, grounded, reliable character',
    E:'dynamic, energetic, driven momentum',
    F:'sophisticated, elegant, refined poise',
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
    A:'Tight Headshot', B:'Upper Body', C:'Three-Quarter',
    D:'Full Body',      E:'Candid / In-Motion',
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
    A:'Eye Level', B:'Low Angle', C:'High Angle', D:'Dutch Tilt',
  },

  postProcess: {
    A:'natural clean edit — skin texture preserved, minimal retouching, honest finish',
    B:'high-fashion editorial retouching — flawless, polished, perfected skin, impeccable finish',
    C:'cinematic color grade — subtle film grain, split-toning: warm highlights, cool shadows',
    D:'documentary-style raw processing — authentic, unmanipulated, journalistic honesty',
    E:'artistic stylized treatment — signature color science, creative post-production',
  },
  postProcessLbl: {
    A:'Natural & Clean', B:'High-Fashion Editorial', C:'Cinematic',
    D:'Documentary',     E:'Artistic / Stylized',
  },

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

  industry: {
    A:'the technology and startup ecosystem',
    B:'finance, law, and professional consulting',
    C:'creative, design, and media industries',
    D:'healthcare and scientific research',
    E:'education and academia',
    F:'C-suite executive leadership',
    G:'entrepreneurship and the founder community',
  },

  purposeLbl: {
    A:'Corporate / LinkedIn', B:'Personal Brand', C:'Speaker Bio',
    D:'Creative Portfolio',   E:'Press / Media',
  },

  platformLbl: {
    A:'LinkedIn & Professional Networks', B:'Instagram & Social Media',
    C:'Company Website',                  D:'Speaker Bureau',
    E:'Press Kit / Publication',
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
    + '<div class="q-number">Question ' + q.n + '  /  ' + total + '</div>'
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
    paletteLbl:     M.paletteLbl[a[10]]    || 'Neutrals',
    bg:             M.bg[a[8]]             || 'studio backdrop',
    bgLbl:          M.bgLbl[a[8]]          || 'Studio',
    lighting:       M.lighting[a[9]]       || 'studio lighting',
    lightingLbl:    M.lightingLbl[a[9]]    || 'Studio',
    impression:     M.impression[a[7]]     || 'professional presence',
    expression:     M.expression[a[13]]    || 'composed expression',
    framing:        M.framing[fk]          || 'portrait',
    framingLbl:     M.framingLbl[fk]       || 'Portrait',
    framingKey:     fk,
    lens:           M.lens[fk]             || '85mm f/1.8',
    lensShort:      M.lensShort[fk]        || '85mm f/1.8',
    angle:          M.angle[a[17]]         || 'eye level',
    angleLbl:       M.angleLbl[a[17]]      || 'Eye Level',
    postProcess:    M.postProcess[a[18]]   || 'natural edit',
    postProcessLbl: M.postProcessLbl[a[18]]|| 'Natural & Clean',
    archetype:      M.archetype[a[14]]     || 'Professional',
    archetypeDesc:  M.archetypeDesc[a[14]] || 'professional quality',
    industry:       M.industry[a[5]]       || 'professional field',
    purposeLbl:     M.purposeLbl[a[1]]     || 'Professional',
    platformLbl:    M.platformLbl[a[19]]   || 'Professional Platform',
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
  var editLight = (p.lightingLbl === 'Dramatic' || p.lightingLbl === 'Cinematic')
    ? p.lighting
    : 'hard directional single-source key light with dramatic shadow fall-off, fashion-editorial contrast';
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

function dirNote(p) {
  return 'For this <strong>' + p.archetype + '</strong> portrait — destined for <strong>' + p.platformLbl + '</strong> — we want to lead every frame with <strong>' + p.impression + '</strong>. '
    + 'We\'ll shoot on a <strong>' + p.lensShort + '</strong>, ' + p.angle + ', under <strong>' + p.lightingLbl.toLowerCase() + ' lighting</strong> (' + p.lighting.split('—')[0].trim() + '). '
    + 'The subject is in ' + p.formalityLbl.toLowerCase() + ', ' + p.paletteLbl.toLowerCase() + ' — ensure the wardrobe reads cleanly against the ' + p.bgLbl.toLowerCase() + ' background with no color clashing. '
    + 'All retouching should aim for <strong>' + p.postProcessLbl.toLowerCase() + '</strong> — this should feel like ' + p.archetypeDesc + ', never over-produced or sterile.';
}

function scoredTools() {
  var a = ans;
  var scores = {
    'Midjourney': 0,
    'DALL·E 3': 0,
    'Flux (FLUX.1 dev)': 0,
    'Stable Diffusion + ControlNet': 0,
  };
  var descs = {
    'Midjourney': 'Best-in-class for editorial, fashion-forward, and cinematic portrait aesthetics. Excels at mood and atmosphere.',
    'DALL·E 3': 'Excellent for clean, natural corporate headshots. Simple prompts, reliable results for LinkedIn and team pages.',
    'Flux (FLUX.1 dev)': 'State-of-the-art photorealism. Ideal for authentic documentary and lifestyle shots that must look like genuine photos.',
    'Stable Diffusion + ControlNet': 'Precise body-pose control via depth maps and pose references — essential for full-body portraits and non-standard builds.',
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
   RENDER RESULTS
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
      ? '<span class="tool-rec-badge">★ Top Recommendation</span>'
      : t.rank === 1
        ? '<span class="tool-rec-badge" style="color:var(--text2)">◆ Strong Alternative</span>'
        : '';
    return '<div class="' + cls + '">' + badge + '<div class="tool-name">' + t.name + '</div><div class="tool-desc">' + t.desc + '</div></div>';
  }).join('');

  document.getElementById('results-root').innerHTML =
    '<div class="results-header">'
    + '<div class="gold-rule" style="margin-bottom:1.2rem"></div>'
    + '<div class="results-eyebrow">✶ Your Results</div>'
    + '<h2 class="results-title">Your Photographic Profile</h2>'
    + '<p class="results-desc">Your complete professional portrait blueprint — ready for AI generation.</p>'
    + '</div>'

    + '<div class="id-card">'
    + '<div class="id-card-label">✶ Photographic Identity Card</div>'
    + '<div class="id-grid">'
    + '<div class="id-item"><div class="id-item-label">Purpose</div><div class="id-item-value">' + p.purposeLbl + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Archetype</div><div class="id-item-value">' + p.archetype + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Mood</div><div class="id-item-value">' + p.lightingLbl + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Setting</div><div class="id-item-value">' + p.bgLbl + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Palette</div><div class="id-item-value">' + p.paletteLbl + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Framing</div><div class="id-item-value">' + p.framingLbl + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Formality</div><div class="id-item-value">' + p.formalityLbl + '</div></div>'
    + '<div class="id-item"><div class="id-item-label">Platform</div><div class="id-item-value">' + p.platformLbl + '</div></div>'
    + '</div></div>'

    + '<div class="section">'
    + '<div class="section-head">AI Image Prompts</div>'

    + '<div class="prompt-card">'
    + '<div class="prompt-label">Prompt A &mdash; Primary &middot; Midjourney / DALL&middot;E 3 / Flux</div>'
    + '<div class="prompt-body" id="pA">' + pA + '</div>'
    + '<button class="copy-btn" data-copy="pA">&#x2398; Copy Prompt A</button>'
    + '</div>'

    + '<div class="prompt-card">'
    + '<div class="prompt-label">Prompt B &mdash; Editorial Variant</div>'
    + '<div class="prompt-body" id="pB">' + pB + '</div>'
    + '<button class="copy-btn" data-copy="pB">&#x2398; Copy Prompt B</button>'
    + '</div>'

    + '<div class="prompt-card">'
    + '<div class="prompt-label">Prompt C &mdash; Candid / Lifestyle Variant</div>'
    + '<div class="prompt-body" id="pC">' + pC + '</div>'
    + '<button class="copy-btn" data-copy="pC">&#x2398; Copy Prompt C</button>'
    + '</div>'
    + '</div>'

    + '<div class="section">'
    + '<div class="section-head">Photographer\'s Direction Note</div>'
    + '<div class="director-note">' + note + '</div>'
    + '</div>'

    + '<div class="section">'
    + '<div class="section-head">Recommended AI Tools</div>'
    + '<div class="tools-grid">' + toolsHTML + '</div>'
    + '</div>'

    + '<div class="action-bar">'
    + '<button class="btn-outline" id="btn-retake">&larr; Retake Quiz</button>'
    + '<button class="btn-gold-outline" id="btn-share">Share Results &nearr;</button>'
    + '</div>';

  show('screen-results');
}

/* ──────────────────────────────────────────
   UTILITIES
────────────────────────────────────────── */
function doCopy(elId, btn) {
  var text = document.getElementById(elId).textContent;
  var orig = btn.textContent;
  function confirm() {
    btn.textContent = '✓ Copied to clipboard';
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
      btn.textContent = '✓ Link copied!';
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
   INIT — wire up all events here, no inline
   onclick anywhere in the HTML
────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {

  // "Begin the Quiz" button
  document.getElementById('btn-start').addEventListener('click', startQuiz);

  // Option cards (delegated — they're re-created on every question)
  document.getElementById('q-area').addEventListener('click', function(e) {
    var card = e.target.closest('[data-pick]');
    if (card) pick(card.dataset.pick);
  });

  // Results buttons (delegated — created by buildResults)
  document.getElementById('results-root').addEventListener('click', function(e) {
    var btn = e.target.closest('button');
    if (!btn) return;
    if (btn.id === 'btn-retake') { retake(); return; }
    if (btn.id === 'btn-share')  { doShare(btn); return; }
    if (btn.dataset.copy)        { doCopy(btn.dataset.copy, btn); }
  });

  // Load from URL hash or show intro
  if (loadHash()) {
    buildResults();
  } else {
    show('screen-intro');
  }
});
