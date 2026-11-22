const today = new Date().toISOString().split('T')[0];
document.getElementById('a_data').value = today;
document.getElementById('p_inicio').value = today;

function v(id) { return (document.getElementById(id).value || '').trim(); }
function chk(id) { return document.getElementById(id).checked; }

function fmtR(n) {
  const num = parseFloat(n);
  if (isNaN(num)) return 'R$ 0,00';
  return 'R$ ' + num.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function numExtenso(n) {
  const ext = {
    1:'um',2:'dois',3:'três',4:'quatro',5:'cinco',6:'seis',7:'sete',8:'oito',9:'nove',10:'dez',
    11:'onze',12:'doze',13:'treze',14:'quatorze',15:'quinze',16:'dezesseis',17:'dezessete',
    18:'dezoito',19:'dezenove',20:'vinte',21:'vinte e um',22:'vinte e dois',23:'vinte e três',
    24:'vinte e quatro',25:'vinte e cinco',26:'vinte e seis',27:'vinte e sete',28:'vinte e oito',
    29:'vinte e nove',30:'trinta',36:'trinta e seis',48:'quarenta e oito',60:'sessenta'
  };
  return ext[n] ? ext[n] : n;
}

function reaisExtenso(valor) {
  const n = parseFloat(valor);
  if (isNaN(n) || n === 0) return 'zero reais';
  const inteiro = Math.floor(n);
  const centavos = Math.round((n - inteiro) * 100);
  const unidades = ['','um','dois','três','quatro','cinco','seis','sete','oito','nove','dez','onze','doze','treze','quatorze','quinze','dezesseis','dezessete','dezoito','dezenove'];
  const dezenas = ['','','vinte','trinta','quarenta','cinquenta','sessenta','setenta','oitenta','noventa'];
  const centenas = ['','cem','duzentos','trezentos','quatrocentos','quinhentos','seiscentos','setecentos','oitocentos','novecentos'];
  function grupo(n) {
    if (n === 0) return '';
    if (n < 20) return unidades[n];
    const d = Math.floor(n/10), u = n%10;
    return dezenas[d] + (u ? ' e ' + unidades[u] : '');
  }
  function num(n) {
    if (n === 0) return 'zero';
    if (n === 100) return 'cem';
    const c = Math.floor(n/100), resto = n%100;
    const partes = [];
    if (c > 0) partes.push(centenas[c]);
    if (resto > 0) partes.push(grupo(resto));
    return partes.join(' e ');
  }
  let resultado = num(inteiro) + (inteiro === 1 ? ' real' : ' reais');
  if (centavos > 0) resultado += ' e ' + num(centavos) + (centavos === 1 ? ' centavo' : ' centavos');
  return resultado;
}

function fmtDate(str) {
  if (!str) return '___/___/______';
  const [y,m,d] = str.split('-');
  const meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
  return `${parseInt(d)} de ${meses[parseInt(m)-1]} de ${y}`;
}

function ordinal(n) {
  return n + 'º';
}

function clausulaNum(i) {
  const romanos = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII','XIII','XIV','XV'];
  return romanos[i] || (i+1);
}

function gerar() {
  const locNome = v('loc_nome');
  const latNome = v('lat_nome');
  const imEnd   = v('im_end');
  const aluguel = v('v_aluguel');

  if (!locNome || !latNome || !imEnd || !aluguel) {
    alert(window.siteLang ? siteLang.t('Preencha ao menos: nome da locadora, nome do locatário, endereço do imóvel e valor do aluguel.', 'Please fill in at least: landlord name, tenant name, property address and rent amount.') : 'Preencha ao menos: nome da locadora, nome do locatário, endereço do imóvel e valor do aluguel.');
    return;
  }

  // coleta
  const loc = {
    nome: locNome.toUpperCase(),
    ec: v('loc_ec'), nac: v('loc_nac'), prof: v('loc_prof'),
    rg: v('loc_rg'), rg_org: v('loc_rg_org'), cpf: v('loc_cpf'), end: v('loc_end')
  };
  const lat = {
    nome: latNome.toUpperCase(),
    ec: v('lat_ec'), nac: v('lat_nac'), prof: v('lat_prof'),
    rg: v('lat_rg'), rg_org: v('lat_rg_org'), cpf: v('lat_cpf'), end: v('lat_end')
  };
  const test = { nome: v('test_nome'), cpf: v('test_cpf') };
  const im = { end: imEnd, desc: v('im_desc'), fin: v('im_fin') };
  const val = {
    aluguel: parseFloat(aluguel) || 0,
    venc: v('v_venc') || '5',
    desc: parseFloat(v('v_desc')) || 0,
    desc_n: v('v_desc_n') || '0',
    multa: v('v_multa') || '3',
    juros: v('v_juros') || '1',
  };
  const prazo = {
    meses: parseInt(v('p_meses')) || 24,
    indice: v('p_indice') || 'IGP-M',
    inicio: v('p_inicio'),
  };
  const ass = {
    cidade: v('a_cidade'), uf: v('a_uf'),
    data: v('a_data'), foro: v('a_foro')
  };

  // monta cláusulas
  let clausulas = [];
  let ci = 0; // clause index

  // CLÁUSULA I – OBJETO
  let objTexto = `A <strong>LOCADORA</strong> cede ao <strong>LOCATÁRIO</strong>, a título de locação, o imóvel localizado em: <strong>${im.end}</strong>.`;
  let objParags = [];
  if (im.desc) objParags.push(`O imóvel objeto desta locação possui as seguintes características: ${im.desc}.`);
  objParags.push(`O imóvel deverá ser utilizado exclusivamente para fins <strong>${im.fin}</strong>, sendo vedada sua utilização de forma diversa, salvo autorização expressa e escrita da <strong>LOCADORA</strong>, sob pena de multa e rescisão contratual de pleno direito.`);
  clausulas.push({ titulo: 'DO OBJETO', corpo: objTexto, pars: objParags });
  ci++;

  // CLÁUSULA – SUBLOCAÇÃO
  if (chk('cl_sub')) {
    clausulas.push({
      titulo: 'DA VEDAÇÃO À SUBLOCAÇÃO E EMPRÉSTIMO',
      corpo: `Fica expressamente vedado ao <strong>LOCATÁRIO</strong> sublocar, ceder ou emprestar o imóvel ora locado, no todo ou em parte, a qualquer título, sem prévia e expressa autorização da <strong>LOCADORA</strong>, sob pena de rescisão imediata do presente contrato, sem prejuízo das demais penalidades legais cabíveis.`
    }); ci++;
  }

  // CLÁUSULA – VALORES
  let valCorpo = `O <strong>LOCATÁRIO</strong> pagará à <strong>LOCADORA</strong> o valor mensal de <strong>${fmtR(val.aluguel)} (${reaisExtenso(val.aluguel)})</strong>, com vencimento no <strong>${ordinal(val.venc)} dia útil de cada mês</strong>, em caráter antecipado, referente ao período de locação imediatamente subsequente ao pagamento.`;
  let valPars = [];
  if (val.desc > 0 && parseInt(val.desc_n) > 0) {
    valPars.push(`As ${numExtenso(parseInt(val.desc_n))} (${val.desc_n}) primeiras parcelas do presente contrato farão jus a desconto de <strong>${fmtR(val.desc)} (${reaisExtenso(val.desc)})</strong> sobre o valor mensal total.`);
  }
  valPars.push(`Em caso de atraso no pagamento, incidirá multa moratória de <strong>${val.multa}%</strong> sobre o valor do saldo em atraso, acrescida de juros de mora de <strong>${val.juros}%</strong> ao dia.`);
  valPars.push(`Ficam a cargo do <strong>LOCATÁRIO</strong>, durante toda a vigência do contrato e eventual prorrogação, o pagamento de todas as contas de consumo incidentes sobre o imóvel, incluindo energia elétrica, água, esgoto e gás, bem como quaisquer multas ou infrações que der causa.`);
  valPars.push(`Também ficará a cargo do <strong>LOCATÁRIO</strong> o pagamento da totalidade dos impostos, taxas e tributos incidentes sobre o imóvel, em especial o IPTU. O não pagamento de tais encargos poderá ensejar rescisão imediata do contrato.`);
  clausulas.push({ titulo: 'DOS VALORES, ENCARGOS E FORMA DE PAGAMENTO', corpo: valCorpo, pars: valPars });
  ci++;

  // CLÁUSULA – PRAZO
  let prazoCorpo = `O presente contrato tem prazo determinado de <strong>${numExtenso(prazo.meses)} (${prazo.meses}) meses</strong>, com início em <strong>${fmtDate(prazo.inicio)}</strong>${prazo.inicio ? `, encerrando-se em ${fmtDate(addMonths(prazo.inicio, prazo.meses))}` : ''}.`;
  let prazoPars = [];
  prazoPars.push(`Findo o prazo contratual, caso o <strong>LOCATÁRIO</strong> permaneça no imóvel por mais de 30 (trinta) dias sem oposição da <strong>LOCADORA</strong>, o contrato será considerado prorrogado por prazo indeterminado, nas mesmas condições.`);
  prazoPars.push(`Em caso de prorrogação por prazo indeterminado, qualquer das partes poderá rescindir o contrato mediante aviso prévio de 30 (trinta) dias.`);
  prazoCorpo += `<br><br>Fica estabelecido o índice <strong>${prazo.indice}</strong>, aferido pela Fundação Getúlio Vargas, como critério de reajuste dos valores do presente contrato, a ser aplicado a cada 12 (doze) meses.`;
  prazoPars.push(`O reajuste é um direito da <strong>LOCADORA</strong> e deverá ser calculado anualmente. A não utilização do direito ao reajuste em determinado período não implicará direito a reajustes retroativos.`);
  clausulas.push({ titulo: 'DO PRAZO DA LOCAÇÃO E DO REAJUSTE', corpo: prazoCorpo, pars: prazoPars });
  ci++;

  // CLÁUSULA – DEVERES LOCATÁRIO
  let deveresLat = [
    'Usar o imóvel de acordo com sua destinação e com o convencionado no presente contrato;',
    'Cuidar e zelar pelo imóvel como se fosse de sua propriedade, mantendo-o em perfeitas condições de higiene e conservação;',
    'Restituir o imóvel ao final do contrato no mesmo estado em que o recebeu, ressalvado o desgaste natural pelo uso regular;',
    'Comunicar prontamente à <strong>LOCADORA</strong> quaisquer danos ou avarias de que seja responsável, providenciando os reparos necessários no prazo máximo de 30 (trinta) dias;',
    'Não realizar modificações na estrutura interna ou externa do imóvel sem o consentimento prévio e escrito da <strong>LOCADORA</strong>;',
    'Permitir vistorias do imóvel pela <strong>LOCADORA</strong>, mediante prévio agendamento com antecedência mínima de 24 (vinte e quatro) horas.',
  ];
  if (chk('cl_pint')) deveresLat.push('Devolver o imóvel ao final do contrato devidamente pintado nas cores originais, às suas expensas.');
  if (chk('cl_anim')) deveresLat.push('Não manter animais de estimação no imóvel sem autorização prévia, expressa e escrita da <strong>LOCADORA</strong>.');
  clausulas.push({ titulo: 'DOS DEVERES DO LOCATÁRIO', lista: deveresLat });
  ci++;

  // CLÁUSULA – DEVERES LOCADORA
  clausulas.push({
    titulo: 'DOS DEVERES DA LOCADORA',
    lista: [
      'Entregar o imóvel em condições adequadas ao uso e à destinação contratada;',
      'Garantir ao <strong>LOCATÁRIO</strong> o uso pacífico do imóvel durante toda a vigência do contrato;',
      'Manter a forma e o destino do imóvel durante o período de locação;',
      'Responder pelos vícios ou defeitos anteriores à locação que prejudiquem o uso do imóvel;',
      'Fornecer ao <strong>LOCATÁRIO</strong>, quando solicitado, documento descritivo das condições do imóvel.',
    ]
  }); ci++;

  // VISTORIA
  if (chk('cl_vis')) {
    clausulas.push({
      titulo: 'DO LAUDO DE VISTORIA',
      corpo: `Na data de entrega das chaves, as partes realizarão, em conjunto, vistoria detalhada do imóvel, lavrando laudo descritivo das condições de conservação de todos os cômodos, instalações e equipamentos. O referido laudo será assinado por ambas as partes e constituirá parte integrante do presente contrato, servindo como parâmetro para a verificação do estado do imóvel por ocasião de sua devolução.`
    }); ci++;
  }

  // BENFEITORIAS
  if (chk('cl_benf')) {
    clausulas.push({
      titulo: 'DAS BENFEITORIAS',
      corpo: `As benfeitorias realizadas pelo <strong>LOCATÁRIO</strong> no imóvel, sejam necessárias, úteis ou voluptuárias, não lhe conferirão direito de retenção, indenização ou compensação, ainda que realizadas com expressa autorização da <strong>LOCADORA</strong>, incorporando-se definitivamente ao imóvel ao término do contrato.`
    }); ci++;
  }

  // FIADOR
  if (chk('cl_fiad')) {
    clausulas.push({
      titulo: 'DA GARANTIA FIDEJUSSÓRIA',
      corpo: `Como garantia do fiel cumprimento das obrigações assumidas no presente contrato, o <strong>LOCATÁRIO</strong> apresentará fiador solidário, que responderá, em caráter solidário e ilimitado, por todas as obrigações decorrentes deste instrumento, incluindo aluguéis, encargos, multas, danos ao imóvel e custas processuais, nos termos do art. 37, inciso II, da Lei nº 8.245/1991.`,
      pars: ['A fiança se estende por toda a vigência do contrato, inclusive em caso de prorrogação, até a efetiva entrega das chaves.']
    }); ci++;
  }

  // DIREITO DE PREFERÊNCIA
  if (chk('cl_pref')) {
    clausulas.push({
      titulo: 'DO DIREITO DE PREFERÊNCIA',
      corpo: `Na hipótese de a <strong>LOCADORA</strong> decidir alienar o imóvel locado, deverá notificar o <strong>LOCATÁRIO</strong> por escrito, com todas as condições do negócio, especialmente o preço e a forma de pagamento. O <strong>LOCATÁRIO</strong> terá o prazo de 30 (trinta) dias, a contar do recebimento da notificação, para exercer seu direito de preferência na aquisição, em igualdade de condições com terceiros, nos termos do art. 27 da Lei nº 8.245/1991.`
    }); ci++;
  }

  // EXTINÇÃO
  clausulas.push({
    titulo: 'DA EXTINÇÃO DO CONTRATO',
    corpo: 'O presente contrato extinguir-se-á nas seguintes hipóteses:',
    lista: [
      'Ocorrendo sinistro, incêndio ou qualquer evento que impossibilite a utilização do imóvel;',
      'Em caso de desapropriação do imóvel pelo Poder Público;',
      'Com a restituição voluntária do imóvel pelo <strong>LOCATÁRIO</strong>;',
      'Pela resilição por qualquer das partes, observados os prazos e condições legais;',
      'Em caso de falecimento, falência, recuperação judicial ou extrajudicial de qualquer das partes.',
    ],
    pars: ['O contrato poderá ser rescindido de pleno direito, sem direito a indenização, caso o imóvel seja utilizado de forma diversa da convencionada.']
  }); ci++;

  // DESCUMPRIMENTO
  clausulas.push({
    titulo: 'DO DESCUMPRIMENTO CONTRATUAL',
    corpo: `O descumprimento de qualquer das cláusulas ou condições do presente contrato por qualquer das partes poderá acarretar rescisão imediata, sem prejuízo da responsabilidade civil e criminal cabível.`,
    pars: ['A tolerância de qualquer das partes quanto ao descumprimento de obrigação pela outra não constituirá novação, renúncia ou alteração das cláusulas contratuais, podendo ser exercido o direito a qualquer tempo.']
  }); ci++;

  // CLÁUSULA EXTRA
  if (v('obs')) {
    clausulas.push({
      titulo: 'DAS DISPOSIÇÕES ESPECIAIS',
      corpo: v('obs')
    }); ci++;
  }

  // FORO
  clausulas.push({
    titulo: 'DO FORO',
    corpo: `As partes elegem o foro da Comarca de <strong>${ass.foro || (ass.cidade + ', ' + ass.uf)}</strong> para dirimir quaisquer controvérsias oriundas do presente contrato, renunciando expressamente a qualquer outro, por mais privilegiado que seja.`
  }); ci++;

  // monta HTML
  let html = `<div class="ct">`;

  // título
  html += `<h1>CONTRATO DE LOCAÇÃO DE IMÓVEL</h1>`;

  // partes
  html += `<p class="entre">Entre:</p>`;
  html += `<p class="parte"><strong>${loc.nome}</strong>, estado civil: ${loc.ec}, nacionalidade: ${loc.nac}${loc.prof ? ', profissão: ' + loc.prof : ''}${loc.rg ? ', carteira de identidade n.º ' + loc.rg + ' ' + loc.rg_org : ''}${loc.cpf ? ', CPF n.º ' + loc.cpf : ''}${loc.end ? ', residente e domiciliado(a) em ' + loc.end : ''}, doravante denominado(a) <strong>LOCADORA</strong>;</p>`;
  html += `<p class="separador">e</p>`;
  html += `<p class="parte"><strong>${lat.nome}</strong>, estado civil: ${lat.ec}, nacionalidade: ${lat.nac}${lat.prof ? ', profissão: ' + lat.prof : ''}${lat.rg ? ', carteira de identidade n.º ' + lat.rg + ' ' + lat.rg_org : ''}${lat.cpf ? ', CPF n.º ' + lat.cpf : ''}${lat.end ? ', residente e domiciliado(a) em ' + lat.end : ''}, doravante denominado(a) <strong>LOCATÁRIO</strong>.</p>`;

  html += `<p class="abertura">As partes acima identificadas têm entre si justo e acertado o presente contrato de locação, que se regerá pelas cláusulas e condições abaixo descritas, e pela Lei n.º 8.245, de 18 de outubro de 1991 (Lei do Inquilinato).</p>`;

  // cláusulas
  clausulas.forEach((cl, i) => {
    html += `<h2>CLÁUSULA ${clausulaNum(i)} – ${cl.titulo}</h2>`;
    if (cl.corpo) html += `<p>${cl.corpo}</p>`;
    if (cl.lista) {
      html += `<ol class="deveres">`;
      cl.lista.forEach((item, j) => {
        html += `<li>${String.fromCharCode(73+j) || (j+1)}. ${item}</li>`;
      });
      html += `</ol>`;
    }
    if (cl.pars) {
      html += `<ol class="paragrafos">`;
      cl.pars.forEach((p, j) => {
        html += `<li><strong>§ ${j+1}º</strong> — ${p}</li>`;
      });
      html += `</ol>`;
    }
  });

  // data e assinatura
  html += `<p class="city-date">${ass.cidade || '___________'}${ass.uf ? ' (' + ass.uf + ')' : ''}, ${fmtDate(ass.data)}.</p>`;

  html += `<div class="signatures">`;
  html += `<div class="sig-block"><hr class="sig-line-rule"><p class="sig-role">LOCADORA:</p><p class="sig-name">${loc.nome}</p>${loc.cpf ? `<p class="sig-cpf">CPF: ${loc.cpf}</p>` : ''}</div>`;
  html += `<div class="sig-block"><hr class="sig-line-rule"><p class="sig-role">LOCATÁRIO:</p><p class="sig-name">${lat.nome}</p>${lat.cpf ? `<p class="sig-cpf">CPF: ${lat.cpf}</p>` : ''}</div>`;
  if (test.nome) {
    html += `<div class="sig-block"><hr class="sig-line-rule"><p class="sig-role">TESTEMUNHA:</p><p class="sig-name">${test.nome.toUpperCase()}</p>${test.cpf ? `<p class="sig-cpf">CPF: ${test.cpf}</p>` : ''}</div>`;
  }
  html += `</div>`;
  html += `</div>`; // .ct

  // renderiza
  document.getElementById('preview-empty').style.display = 'none';
  const wrapper = document.getElementById('contract-wrapper');
  wrapper.innerHTML = `<div class="a4-page">${html}</div>`;
  wrapper.style.display = 'flex';
  document.getElementById('btn-print').style.display = 'block';
  wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function addMonths(dateStr, months) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  d.setMonth(d.getMonth() + months);
  return d.toISOString().split('T')[0];
}


document.getElementById("btn-generate").addEventListener("click", gerar);
document.getElementById("btn-print").addEventListener("click", () => window.print());
