<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$route = (string) ($_GET['r'] ?? '');
try {
    start_session();
    assert_schema();
} catch (Throwable $e) {
    // Not installed yet (or mid-migration): the product page still renders; nothing else does.
    log_error('schema', $e);
    if ($route === '') {
        page_landing();
        exit;
    }
    http_response_code(503);
    exit('Eixo em manutenção.');
}
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post) {
    require_csrf();
}

$public = ['', 'login', 'totp', 'invite', 'privacy', 'ai', 'd', 'doctor', 'dsum', 'ddoc', 'dfile', 'dseries', 'dlogout', 'logout'];
$user = current_patient();
if (!in_array($route, $public, true) && !$user) {
    redirect('login');
}
$uid = $user ? (int) $user['id'] : 0;

// Every patient page except the consent form needs the storage consent (RF-02).
if ($user && !in_array($route, ['consents', 'logout', 'privacy', 'ai', 'account', 'account_delete', 'export'], true) && !consents($uid)['store']) {
    redirect('consents');
}

try {
    match ($route) {
        '' => $user ? page_dashboard($uid) : page_landing(),
        'login' => page_login($post),
        'totp' => page_totp($post),
        'invite' => page_invite($post),
        'logout' => (function () { logout(); redirect(); })(),
        'privacy' => page_privacy(),
        'ai' => page_ai(),
        'consents' => page_consents($uid, $post),
        'upload' => page_upload($uid, $post),
        'docs' => page_docs($uid),
        'doc' => page_doc($uid, (int) ($_GET['id'] ?? 0), $post),
        'file' => send_file($uid, (int) ($_GET['id'] ?? 0), null),
        'series' => page_series($uid, (int) ($_GET['id'] ?? 0), $post),
        'fix' => action_fix($uid),
        'timeline' => page_timeline($uid),
        'meds' => page_meds($uid, $post),
        'med' => page_med($uid, (int) ($_GET['id'] ?? 0), $post),
        'body' => page_body($uid, $post),
        'shares' => page_shares($uid, $post),
        'summary' => page_patient_summary($uid),
        'audit' => page_audit($uid),
        'account' => page_account($uid, $post),
        'export' => action_export($uid),
        'account_delete' => action_delete($uid),
        'd' => page_doctor_code($post),
        'doctor' => page_doctor_list(),
        'dsum' => page_doctor_summary((int) ($_GET['s'] ?? 0)),
        'ddoc' => page_doctor_doc((int) ($_GET['s'] ?? 0), (int) ($_GET['id'] ?? 0)),
        'dfile' => doctor_file((int) ($_GET['s'] ?? 0), (int) ($_GET['id'] ?? 0)),
        'dseries' => page_doctor_series((int) ($_GET['s'] ?? 0), (int) ($_GET['id'] ?? 0)),
        'dlogout' => (function () { unset($_SESSION['doctor'], $_SESSION['doctor_seen']); redirect(); })(),
        default => deny(),
    };
} catch (Throwable $e) {
    $id = log_error('request ' . $route, $e);
    http_response_code(500);
    layout('Erro', '<h1>Algo deu errado.</h1><p>Código do erro: ' . h($id) . '. Nada foi perdido; tente de novo em instantes.</p>');
}

/* ── Public pages ─────────────────────────────────────────────────────── */

function page_landing(): void
{
    layout('Seus exames num só eixo', '<section class="hero"><p class="eyebrow">Registro pessoal de saúde · versão de testes, por convite</p>'
        . '<h1>Exames, bioimpedância e doses <em>num só eixo.</em></h1>'
        . '<p class="lead">Envie seus laudos em PDF ou foto. Duas IAs de empresas diferentes transcrevem cada um; o código só carrega o que as duas confirmam e o que existe no texto do laudo. Você chega à consulta com a série histórica pronta, e cada número aponta para a página de onde saiu.</p>'
        . '<p><a class="btn" href="' . h(url('login')) . '">Entrar</a> <a class="btn ghost" href="' . h(url('ai')) . '">Como a IA é usada</a></p></section>'
        . '<section class="cols"><div><h2>O que faz</h2><ul class="plain"><li>Junta laudos de laboratórios diferentes numa linha do tempo.</li><li>Mostra cada valor ao lado da faixa do próprio laboratório.</li><li>Registra medicações e ajustes de dose como eventos.</li><li>Gera um resumo descritivo para o seu médico, por convite com validade.</li></ul></div>'
        . '<div><h2>O que não faz</h2><ul class="plain"><li>Não interpreta, não diagnostica, não sugere dose.</li><li>Não calcula índices (TFG, HOMA-IR) nem marca valores como alterados por conta própria.</li><li>Não vende nem cede dados de saúde.</li></ul></div></section>'
        . '<p class="muted">Médico com um convite? Abra o link que o paciente enviou e use o código que ele informar.</p>');
}

function page_login(bool $post): void
{
    $error = '';
    if ($post) {
        $email = normalize_email((string) ($_POST['email'] ?? ''));
        $keys = [rate_key('email', $email), rate_key('ip', client_ip())];
        if (too_many_attempts(...$keys)) {
            $error = 'Muitas tentativas. Aguarde 15 minutos.';
        } else {
            $u = row('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);
            // Same work and message whether or not the account exists.
            $ok = password_verify((string) ($_POST['password'] ?? ''), $u['password_hash'] ?? dummy_password_hash());
            if ($u && $ok && $u['totp_enabled']) {
                session_regenerate_id(true);
                $_SESSION['pending_uid'] = (int) $u['id'];
                $_SESSION['pending_at'] = time();
                redirect('totp');
            }
            record_attempt(...$keys);
            $error = 'E-mail ou senha incorretos.';
        }
    }
    layout('Entrar', '<section class="narrow"><h1>Entrar</h1>' . ($error ? '<p class="error" role="alert">' . h($error) . '</p>' : '')
        . '<form method="post" class="form">' . csrf_field()
        . '<label>E-mail<input type="email" name="email" autocomplete="username" required></label>'
        . '<label>Senha<input type="password" name="password" autocomplete="current-password" required></label>'
        . '<button class="btn">Continuar</button></form>'
        . '<p class="muted">O acesso é por convite nesta fase. Esqueceu a senha? Peça um novo convite ao administrador; ele refaz senha e segundo fator.</p></section>');
}

function page_totp(bool $post): void
{
    $uid = (int) ($_SESSION['pending_uid'] ?? 0);
    if (!$uid || time() - (int) ($_SESSION['pending_at'] ?? 0) > 300) {
        unset($_SESSION['pending_uid']);
        redirect('login');
    }
    $error = '';
    if ($post) {
        $keys = [rate_key('totp', (string) $uid), rate_key('ip', client_ip())];
        $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
        if (too_many_attempts(...$keys)) {
            $error = 'Muitas tentativas. Aguarde 15 minutos.';
        } else {
            $code = trim((string) ($_POST['code'] ?? ''));
            $step = totp_verify(user_totp_secret($u), $code, (int) $u['totp_last_step']);
            if ($step !== null || (strlen($code) > 6 && use_recovery_code($uid, $code))) {
                if ($step !== null) {
                    q('UPDATE users SET totp_last_step = ? WHERE id = ?', [$step, $uid]);
                }
                clear_attempts(rate_key('email', $u['email']), ...$keys);
                unset($_SESSION['pending_uid'], $_SESSION['pending_at']);
                login_patient($uid);
                redirect();
            }
            record_attempt(...$keys);
            $error = 'Código inválido.';
        }
    }
    layout('Segundo fator', '<section class="narrow"><h1>Código do autenticador</h1>' . ($error ? '<p class="error" role="alert">' . h($error) . '</p>' : '')
        . '<form method="post" class="form">' . csrf_field()
        . '<label>Código de 6 dígitos (ou um código de recuperação)<input name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus></label>'
        . '<button class="btn">Entrar</button></form></section>');
}

function page_invite(bool $post): void
{
    $t = (string) ($_GET['t'] ?? '');
    $inv = find_invite($t);
    if (!$inv && empty($_SESSION['enroll'])) {
        deny(410, 'Convite inválido ou expirado.');
    }
    $error = '';
    // Step 1: password. Step 2: authenticator. Step 3: recovery codes, shown once.
    if ($post && ($_POST['step'] ?? '') === 'password' && $inv) {
        $p1 = (string) ($_POST['password'] ?? '');
        if (($problem = password_problem($p1)) !== null) {
            $error = $problem;
        } elseif ($p1 !== (string) ($_POST['password2'] ?? '')) {
            $error = 'As senhas não coincidem.';
        } else {
            $secret = base32_encode(random_bytes(20));
            $userId = create_or_reset_user($inv['email'], $p1, $secret);
            q('UPDATE invites SET used_at = ? WHERE id = ?', [now(), $inv['id']]);
            session_regenerate_id(true);
            $_SESSION['enroll'] = ['uid' => $userId, 'at' => time()];
            redirect('invite', ['t' => $t]);
        }
    }
    $enroll = $_SESSION['enroll'] ?? null;
    if ($enroll && time() - $enroll['at'] > 1800) {
        unset($_SESSION['enroll']);
        deny(410, 'O cadastro expirou. Peça um novo convite.');
    }
    if ($enroll) {
        $u = row('SELECT * FROM users WHERE id = ?', [$enroll['uid']]);
        $secret = user_totp_secret($u);
        if ($post && ($_POST['step'] ?? '') === 'totp') {
            $step = totp_verify($secret, (string) ($_POST['code'] ?? ''), 0);
            if ($step === null) {
                $error = 'Código inválido. Confira o horário do celular e tente o código seguinte.';
            } else {
                [$codes, $json] = new_recovery_codes();
                q('UPDATE users SET totp_enabled = 1, totp_last_step = ?, recovery_codes_json = ? WHERE id = ?', [$step, $json, $u['id']]);
                audit((int) $u['id'], 'patient', 'auth.totp_enabled');
                unset($_SESSION['enroll']);
                login_patient((int) $u['id']);
                layout('Códigos de recuperação', '<section class="narrow"><h1>Guarde seus códigos de recuperação</h1>'
                    . '<p>Cada código funciona uma vez, no lugar do autenticador, se você perder o celular. Eles não serão mostrados de novo.</p>'
                    . '<pre class="codes">' . h(implode("\n", $codes)) . '</pre>'
                    . '<p><a class="btn" href="' . h(url('consents')) . '">Guardei, continuar</a></p></section>');
                return;
            }
        }
        layout('Segundo fator', '<section class="narrow"><h1>Ative o segundo fator</h1>' . ($error ? '<p class="error" role="alert">' . h($error) . '</p>' : '')
            . '<ol><li>Abra um aplicativo autenticador (Google Authenticator, Microsoft Authenticator, 1Password, Aegis).</li>'
            . '<li>Adicione uma conta por chave e digite a chave abaixo, ou toque no link no próprio celular.</li><li>Digite o código de 6 dígitos que aparecer.</li></ol>'
            . '<p class="key">' . h(trim(chunk_split($secret, 4, ' '))) . '</p><p><a href="' . h(totp_uri($secret, $u['email'])) . '">Abrir no autenticador deste aparelho</a></p>'
            . '<form method="post" class="form">' . csrf_field() . '<input type="hidden" name="step" value="totp">'
            . '<label>Código<input name="code" inputmode="numeric" autocomplete="one-time-code" required></label><button class="btn">Ativar</button></form></section>');
        return;
    }
    layout('Criar acesso', '<section class="narrow"><h1>Criar acesso</h1><p>Convite para <strong>' . h($inv['email']) . '</strong>.</p>' . ($error ? '<p class="error" role="alert">' . h($error) . '</p>' : '')
        . '<form method="post" class="form">' . csrf_field() . '<input type="hidden" name="step" value="password">'
        . '<label>Senha (ao menos 12 caracteres)<input type="password" name="password" autocomplete="new-password" minlength="12" required></label>'
        . '<label>Repita a senha<input type="password" name="password2" autocomplete="new-password" minlength="12" required></label>'
        . '<button class="btn">Continuar</button></form></section>');
}

function page_privacy(): void
{
    $c = eixo_config();
    layout('Privacidade', '<article class="prose-app"><h1>Como o Eixo trata seus dados</h1>'
        . '<p><strong>Controlador:</strong> ' . h($c['controller_name'] ?: '[definir em config.php]') . ' · ' . h($c['controller_email'] ?: '') . '. <strong>Encarregado (DPO):</strong> ' . h($c['dpo_name'] ?: '[definir]') . ' · ' . h($c['dpo_email'] ?: '') . '.</p>'
        . '<h2>Que dados e para quê</h2><p>Laudos e documentos que você envia, os valores extraídos deles, medicações, doses, medidas corporais e dados mínimos de conta (e-mail, senha cifrada, segundo fator). Usamos para guardar, organizar e mostrar seus dados a você e a quem você compartilhar. Dados de saúde são dados sensíveis (LGPD, art. 5º, II); a base legal é o seu consentimento específico e destacado, separado por finalidade (art. 11, I), que você pode revogar a qualquer momento em Conta.</p>'
        . '<h2>Inteligência artificial e transferência internacional</h2><p>Com o consentimento “processar com IA”, só a camada de texto de cada documento é enviada, para transcrição, a um fornecedor: a Replicate, com processamento fora do Brasil. Lá o texto é lido duas vezes, em grupos de páginas, por modelos de duas empresas diferentes — OpenAI e Google —, que a Replicate opera em nome delas. Ou seja, há fornecedores a montante da Replicate, e o contrato de cada um precisa valer para os seus dados. Antes do envio removemos nome, CPF, número do pedido e dados do médico solicitante quando possível. O documento em si não é enviado: um arquivo sem camada de texto, como uma foto ou uma digitalização, não é lido nem transmitido. O contrato com o fornecedor deve proibir retenção e treinamento com os dados, e a transferência se apoia nas cláusulas-padrão da ANPD (Resolução CD/ANPD 19/2024).</p>'
        . '<h2>Compartilhamento</h2><p>Só você cria compartilhamentos, com escopo e validade, e pode revogá-los na hora. Todo acesso de terceiros aparece na sua trilha de auditoria. Não vendemos, cedemos ou compartilhamos dados de saúde para obter vantagem econômica (LGPD, art. 11, § 4º).</p>'
        . '<h2>Segurança e retenção</h2><p>Conexão cifrada; documentos e campos de saúde cifrados no servidor com uma chave por usuário; segundo fator obrigatório. Os dados ficam enquanto a conta existir. Ao excluir a conta, documentos e dados de saúde são apagados na hora e a sua chave é destruída; cópias de segurança são expurgadas no ciclo seguinte. Registros de acesso, sem dado de saúde, são guardados por 6 meses (Marco Civil da Internet, art. 15).</p>'
        . '<h2>Seus direitos</h2><p>Confirmar, acessar, corrigir, exportar (em Conta, arquivo com documentos originais e dados em formato inspirado no HL7 FHIR), revogar consentimentos e excluir. Fale com o encarregado pelo e-mail acima. Incidentes de segurança são comunicados à ANPD e aos titulares em até 3 dias úteis.</p>'
        . '<p class="muted">Versão ' . h(EIXO_CONSENT_VERSION) . '. Texto a ser revisado por advogado antes da abertura ao público.</p></article>');
}

function page_ai(): void
{
    $c = eixo_config();
    $eval = json_decode(meta_get('eval_last') ?? 'null', true);
    $metrics = $eval
        ? '<p>Última avaliação em laudos sintéticos (' . h(br_date($eval['at'])) . ', ' . (int) $eval['values'] . ' valores em ' . (int) $eval['documents'] . ' laudos): acerto entre os valores carregados de ' . h(br_number(round($eval['precision'] * 100, 2))) . '%; valores carregados sobre o total gerado, ' . h(br_number(round($eval['recall'] * 100, 2))) . '%; valores errados carregados: ' . (int) $eval['wrong_loaded'] . '.</p>'
        : '<p>Nenhuma avaliação em laudos sintéticos registrada ainda. O administrador roda <code>php eixo/cli.php eval</code> a cada troca de modelo ou de instrução.</p>';
    layout('Ficha de transparência da IA', '<article class="prose-app"><h1>Ficha de transparência da IA</h1>'
        . '<p class="muted">Para médicos e pacientes, conforme a Resolução CFM 2.454/2026 (art. 3º, II, e art. 13).</p>'
        . '<h2>O que a IA faz</h2><p>Transcreve laudos: nome do exame como impresso, valor, unidade, faixa de referência, método, material, página e trecho de origem. Propõe um código LOINC para agrupar o mesmo exame vindo de laboratórios diferentes. Classifica o tipo de documento.</p>'
        . '<h2>O que a IA não faz</h2><p>Não calcula, não converte unidades, não interpreta, não marca valores como alterados, não sugere diagnóstico, exames ou dose. Conversões de unidade são feitas por código, por álgebra UCUM, só entre unidades da mesma propriedade. A única marcação de alterado exibida é a impressa pelo laboratório.</p>'
        . '<h2>Como um valor entra na série</h2><ol><li>Leitura A: ' . h(reader_model('A')) . ', pela camada de texto do documento, com identificadores removidos.</li><li>Leitura B: ' . h(reader_model('B')) . ', outro modelo, pela mesma camada de texto.</li>'
        . '<li>Se as duas discordam, uma leitura de desempate (' . h(reader_model('C')) . ') precisa coincidir com uma delas.</li><li>Checagem cruzada por código: o valor existe no texto da página; a unidade é compatível com o exame; a ordem de grandeza é coerente com a faixa impressa e com o histórico no mesmo laboratório.</li>'
        . '<li>O que não passa fica em quarentena, fora das séries, e aparece no resumo com link para o laudo.</li></ol>'
        . '<h2>Limites conhecidos</h2><ul><li>As duas leituras podem errar igual; a concordância não é prova de acerto.</li><li>Em fotos não há camada de texto: a checagem usa os trechos citados pelas leituras, que é mais fraca.</li><li>O código LOINC é proposto pelo modelo, sem curadoria; na dúvida, séries não se juntam.</li><li>Bioimpedância de aparelhos diferentes não forma série contínua.</li></ul>'
        . '<h2>Validação</h2>' . $metrics
        . '<h2>Nível de risco autodeclarado</h2><p>Transcrição e organização: <strong>baixo</strong>. Resumo pré-consulta, por apoiar a decisão clínica: pode ser lido como <strong>médio</strong>. O produto não se apresenta como dispositivo médico; o enquadramento formal junto à Anvisa está pendente.</p>'
        . '<h2>Texto para o prontuário</h2><p class="copy">Dados laboratoriais, de composição corporal e de medicação organizados pelo paciente na plataforma Eixo, com transcrição por inteligência artificial em leitura dupla e conferência automatizada contra o laudo original. Valores conferidos pelo médico nos laudos de origem quando relevantes para a conduta. (Resolução CFM 2.454/2026, art. 4º, V.)</p></article>', ['nav' => doctor_shares() ? 'doctor' : null]);
}

/* ── Consents ─────────────────────────────────────────────────────────── */

function page_consents(int $uid, bool $post): void
{
    $texts = [
        'store' => ['Guardar meus dados de saúde', 'Necessário para usar o Eixo. Laudos, valores, medicações e medidas ficam guardados e cifrados enquanto a conta existir.', true],
        'ai' => ['Processar meus documentos com IA', 'Envia a camada de texto de cada documento à Replicate (fora do Brasil) só para transcrição, onde dois modelos, da OpenAI e da Google, a leem. Sem isso, os documentos ficam guardados, mas nada é extraído.', false],
        'share' => ['Compartilhar com profissionais que eu escolher', 'Permite criar convites com escopo e validade. Revogar encerra na hora todos os compartilhamentos ativos.', false],
        'research' => ['Usar dados anonimizados para melhorar o produto', 'Opcional. Hoje nenhum dado é usado para isso; se mudar, você será avisado antes.', false],
    ];
    if ($post) {
        foreach (array_keys($texts) as $p) {
            set_consent($uid, $p, !empty($_POST[$p]));
        }
        if (!consents($uid)['store']) {
            flash('Sem o consentimento para guardar, o Eixo não pode funcionar. Você pode excluir a conta em Conta.');
            redirect('consents');
        }
        flash('Consentimentos atualizados.');
        redirect();
    }
    $cur = consents($uid);
    $html = '<section class="narrow"><h1>Consentimentos</h1><p>Cada finalidade é separada e pode ser revogada a qualquer momento. <a href="' . h(url('privacy')) . '">Como tratamos seus dados</a>.</p><form method="post" class="form consents">' . csrf_field();
    foreach ($texts as $k => [$title, $desc, $required]) {
        $html .= '<label class="check"><input type="checkbox" name="' . $k . '" value="1"' . ($cur[$k] ? ' checked' : '') . '><span><strong>' . h($title) . '</strong>' . ($required ? ' <em>(obrigatório)</em>' : '') . '<br><span class="muted">' . h($desc) . '</span></span></label>';
    }
    layout('Consentimentos', $html . '<button class="btn">Salvar</button></form></section>', ['nav' => $cur['store'] ? 'patient' : null, 'active' => 'account']);
}

/* ── Dashboard and timeline ──────────────────────────────────────────── */

function page_dashboard(int $uid): void
{
    $series = series_for($uid);
    $quar = quarantine_for($uid);
    $pending = (int) row("SELECT COUNT(*) n FROM documents WHERE user_id = ? AND status IN ('queued','processing')", [$uid])['n'];
    $q = trim((string) ($_GET['q'] ?? ''));
    $ctx = patient_context($uid);

    // Três grupos: o que o paciente escolheu seguir, o que saiu da faixa, e o resto
    // guardado por seção. A página abre com o que pede atenção, não com tudo.
    $fixados = [];
    $atencao = [];
    $secoes = [];
    foreach ($series as $s) {
        $exame = exam_of($s['payload']);
        $busca = $exame['nome'] . ' ' . ($s['payload']['label'] ?? '') . ' ' . ($s['payload']['section'] ?? '');
        if ($q !== '' && !str_contains(norm_text($busca), norm_text($q))) {
            continue;
        }
        $ind = exam_indicator($s, $ctx);
        if ($s['pinned']) {
            $fixados[] = $s;
        } elseif (in_array($ind['posicao'], ['acima', 'abaixo'], true) || ($ind['tipo'] === 'qualitativo' && $ind['cor'] === 'ruim')) {
            $atencao[] = $s;
        } else {
            $secoes[($s['payload']['section'] ?? '') ?: 'Outros exames'][] = $s;
        }
    }
    ksort($secoes);

    $cartoes = function (array $lista) use ($ctx): string {
        $out = '<div class="cards exams">';
        foreach ($lista as $s) {
            $out .= exam_card_html($s, $ctx);
        }
        return $out . '</div>';
    };

    $html = '<div class="page-head"><h1>Visão geral</h1><a class="btn" href="' . h(url('upload')) . '">Enviar laudos</a></div>';
    if ($pending) {
        $html .= '<p class="note">' . $pending . ' documento(s) em leitura. A página de documentos mostra o andamento.</p>';
    }
    if (!consents($uid)['ai']) {
        $html .= '<p class="note">O processamento por IA está desligado: documentos ficam guardados, sem extração. <a href="' . h(url('consents')) . '">Consentimentos</a>.</p>';
    }
    if ($quar) {
        $html .= '<p class="note">' . count($quar) . ' valor(es) em quarentena: as leituras não confirmaram. <a href="' . h(url('docs')) . '">Ver nos documentos</a>.</p>';
    }
    $html .= '<form class="search" method="get"><input type="hidden" name="r" value=""><label class="sr-only" for="q">Buscar exame</label><input id="q" name="q" value="' . h($q) . '" placeholder="Buscar exame (TSH, glicada, ferritina…)"><button class="btn ghost">Buscar</button></form>';
    if (!$series) {
        $html .= '<p class="empty">Nenhum exame ainda. Envie seus laudos em PDF ou foto; cada valor confirmado aparece aqui, agrupado pelas seções do próprio laudo.</p>';
    } elseif (!$fixados && !$atencao && !$secoes) {
        $html .= '<p class="empty">Nenhum exame corresponde a “' . h($q) . '”.</p>';
    }
    if ($fixados) {
        $html .= '<h2>Acompanhando</h2>' . $cartoes($fixados);
    }
    if ($atencao) {
        $html .= '<h2>Fora da faixa</h2><p class="muted">Comparado com a faixa que o próprio laboratório imprimiu para o seu sexo e idade.</p>' . $cartoes($atencao);
    }
    // As seções abrem fechadas, a não ser que não haja nada antes delas para ler.
    $abrirPrimeira = !$fixados && !$atencao;
    foreach ($secoes as $nome => $lista) {
        $html .= '<details class="exam-group"' . ($abrirPrimeira ? ' open' : '') . '><summary>' . h($nome)
            . ' <span class="count">(' . count($lista) . ')</span></summary>' . $cartoes($lista) . '</details>';
        $abrirPrimeira = false;
    }
    layout('Visão geral', $html, ['nav' => 'patient', 'active' => '']);
}

function page_timeline(int $uid): void
{
    $items = [];
    foreach (documents_for($uid) as $d) {
        $items[] = [$d['collected_on'] ?? substr($d['uploaded_at'], 0, 10), 'doc', '<a href="' . h(url('doc', ['id' => $d['id']])) . '">' . h(doc_type_label($d['doc_type'])) . '</a> · ' . h($d['meta']['laboratory'] ?? '') . ' · ' . h(status_label($d['status']))
            . (isset($d['meta']['counts']) ? ' · ' . (int) $d['meta']['counts']['loaded'] . ' valores carregados' : '')];
    }
    $types = ['start' => 'Início', 'adjust' => 'Ajuste', 'pause' => 'Pausa', 'resume' => 'Retomada', 'stop' => 'Suspensão'];
    foreach (medications_for($uid) as $m) {
        foreach ($m['events'] as $e) {
            $items[] = [$e['event_on'], 'dose', h($types[$e['type']] . ': ' . ($m['p']['name'] ?? '') . (($e['p']['dose'] ?? '') !== '' ? ' ' . $e['p']['dose'] : '') . (($e['p']['posology'] ?? '') !== '' ? ', ' . $e['p']['posology'] : ''))];
        }
    }
    $body = body_for($uid);
    foreach ($body['bia'] as $b) {
        $items[] = [$b['measured_on'], 'body', 'Bioimpedância · ' . h(trim(($b['p']['device']['brand'] ?? '') . ' ' . ($b['p']['device']['model'] ?? '')) ?: 'aparelho não identificado') . ' · ' . count($b['p']['metrics'] ?? []) . ' métricas'];
    }
    foreach ($body['anthro'] as $a) {
        $items[] = [$a['measured_on'], 'body', h(anthro_text($a['p']))];
    }
    usort($items, fn ($a, $b) => strcmp($b[0], $a[0]));
    $html = '<h1>Linha do tempo</h1><p class="muted">Exames, composição corporal e mudanças de dose no mesmo eixo. Os gráficos de cada exame mostram as mudanças de dose como marcos.</p><ol class="timeline">';
    foreach ($items as [$date, $kind, $text]) {
        $html .= '<li class="t-' . $kind . '"><time>' . h(br_date($date)) . '</time><span>' . $text . '</span></li>';
    }
    layout('Linha do tempo', $html . '</ol>' . ($items ? '' : '<p class="empty">Nada ainda.</p>'), ['nav' => 'patient', 'active' => 'timeline']);
}

/* ── Upload and documents ─────────────────────────────────────────────── */

function page_upload(int $uid, bool $post): void
{
    $msgs = [];
    if ($post && !empty($_FILES['files'])) {
        $max = (int) eixo_config()['max_upload_mb'] * 1024 * 1024;
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - 30 * 86400);
        $recent = (int) row('SELECT COUNT(*) n FROM documents WHERE user_id = ? AND uploaded_at > ?', [$uid, $since])['n'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $files = $_FILES['files'];
        $ai = consents($uid)['ai'];
        foreach ((array) $files['tmp_name'] as $i => $tmp) {
            $name = (string) ($files['name'][$i] ?? 'arquivo');
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                $msgs[] = [$name, 'não foi recebido'];
                continue;
            }
            if (filesize($tmp) > $max) {
                $msgs[] = [$name, 'maior que o limite de ' . eixo_config()['max_upload_mb'] . ' MB'];
                continue;
            }
            $mime = (string) $finfo->file($tmp);
            if (!in_array($mime, ALLOWED_MIME, true)) {
                $msgs[] = [$name, 'formato não aceito (envie PDF, JPG, PNG ou WebP)'];
                continue;
            }
            $bytes = (string) file_get_contents($tmp);
            $sha = hash('sha256', $bytes);
            if (row('SELECT id FROM documents WHERE user_id = ? AND sha256 = ?', [$uid, $sha])) {
                $msgs[] = [$name, 'já enviado antes (arquivo idêntico)'];
                continue;
            }
            $blob = store_blob($uid, $bytes);
            q('INSERT INTO documents(user_id, sha256, mime, size, blob_name, status, meta_enc, uploaded_at) VALUES (?,?,?,?,?,?,?,?)',
                [$uid, $sha, $mime, strlen($bytes), $blob, 'stored', enc($uid, ['original_name' => $name]), now()]);
            $docId = (int) db()->lastInsertId();
            audit($uid, 'patient', 'document.upload', 'document:' . $docId);
            if ($ai && $recent < (int) eixo_config()['max_documents_per_30_days']) {
                enqueue_document($docId);
                $recent++;
                $msgs[] = [$name, 'recebido; na fila de leitura'];
            } else {
                $msgs[] = [$name, $ai ? 'guardado; limite mensal de leituras atingido' : 'guardado; processamento por IA desligado'];
            }
        }
    }
    $list = $msgs ? '<ul class="plain">' . implode('', array_map(fn ($m) => '<li><strong>' . h($m[0]) . '</strong>: ' . h($m[1]) . '</li>', $msgs)) . '</ul>' : '';
    layout('Enviar laudos', '<section class="narrow"><h1>Enviar laudos</h1>' . $list
        . '<form method="post" enctype="multipart/form-data" class="form">' . csrf_field()
        . '<label>PDFs ou fotos dos laudos (vários de uma vez)<input type="file" name="files[]" accept="application/pdf,image/jpeg,image/png,image/webp" capture="environment" multiple required></label>'
        . '<button class="btn">Enviar</button></form>'
        . '<p class="muted">Aceitamos qualquer documento: laudo laboratorial, bioimpedância, receita, laudo de imagem. Prefira o PDF original do laboratório: a camada de texto permite conferir cada valor. Em fotos, fotografe a página inteira, plana e bem iluminada.</p></section>',
        ['nav' => 'patient', 'active' => 'docs']);
}

function page_docs(int $uid): void
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $docs = documents_for($uid);
    if ($q !== '') {
        $needle = norm_text($q);
        $docs = array_values(array_filter($docs, function ($d) use ($uid, $needle) {
            $hay = norm_text(($d['meta']['original_name'] ?? '') . ' ' . ($d['meta']['laboratory'] ?? '') . ' ' . (dec($uid, $d['text_enc']) ?? ''));
            return str_contains($hay, $needle);
        }));
    }
    $html = '<div class="page-head"><h1>Documentos</h1><a class="btn" href="' . h(url('upload')) . '">Enviar</a></div>'
        . '<form class="search" method="get"><input type="hidden" name="r" value="docs"><label class="sr-only" for="q">Buscar no texto</label><input id="q" name="q" value="' . h($q) . '" placeholder="Buscar no texto dos documentos"><button class="btn ghost">Buscar</button></form>';
    if (!$docs) {
        $html .= '<p class="empty">Nenhum documento.</p>';
    } else {
        $html .= '<div class="table-wrap"><table><thead><tr><th>Data</th><th>Documento</th><th>Laboratório</th><th>Situação</th></tr></thead><tbody>';
        foreach ($docs as $d) {
            $c = $d['meta']['counts'] ?? null;
            $html .= '<tr><td>' . h(br_date($d['collected_on'] ?? $d['uploaded_at'])) . '</td><td><a href="' . h(url('doc', ['id' => $d['id']])) . '">' . h($d['meta']['original_name'] ?? doc_type_label($d['doc_type'])) . '</a><br><span class="muted">' . h(doc_type_label($d['doc_type'])) . '</span></td><td>' . h($d['meta']['laboratory'] ?? '—') . '</td><td>'
                . h(status_label($d['status'])) . ($c ? '<br><span class="muted">' . (int) $c['loaded'] . ' carregados · ' . (int) $c['quarantine'] . ' em quarentena</span>' : '') . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }
    layout('Documentos', $html, ['nav' => 'patient', 'active' => 'docs']);
}

function page_doc(int $uid, int $id, bool $post): void
{
    $d = document_for($uid, $id);
    if (!$d) {
        deny();
    }
    if ($post) {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'retry' && in_array($d['status'], ['failed', 'stored'], true) && consents($uid)['ai']) {
            enqueue_document($id);
            flash('Documento de volta à fila de leitura.');
        } elseif ($action === 'delete') {
            q('DELETE FROM results WHERE document_id = ?', [$id]);
            q('DELETE FROM body_measurements WHERE document_id = ?', [$id]);
            q('DELETE FROM documents WHERE id = ?', [$id]);
            delete_blob($d['blob_name']);
            audit($uid, 'patient', 'document.delete', 'document:' . $id);
            flash('Documento e valores dele excluídos.');
            redirect('docs');
        } elseif ($action === 'add_med') {
            $item = $d['meta']['prescription'][(int) ($_POST['i'] ?? -1)] ?? null;
            if ($item) {
                q('INSERT INTO medications(user_id, kind, payload_enc, created_at) VALUES (?,?,?,?)', [$uid, 'medication',
                    enc($uid, ['name' => $item['drug'], 'strength' => $item['strength'], 'form' => $item['form'], 'route' => $item['route'], 'dosage' => $item['instructions'], 'source_document' => $id]), now()]);
                audit($uid, 'patient', 'medication.create', 'medication:' . db()->lastInsertId());
                flash('Medicação adicionada. Registre a data de início em Medicações.');
            }
        }
        redirect('doc', ['id' => $id]);
    }
    audit($uid, 'patient', 'document.view', 'document:' . $id);
    layout('Documento', document_html($uid, $d, url('file', ['id' => $id]), true), ['nav' => 'patient', 'active' => 'docs']);
}

/** Document detail shared by the patient and the doctor views. */
function document_html(int $uid, array $d, string $fileUrl, bool $owner): string
{
    $id = (int) $d['id'];
    $m = $d['meta'] ?? [];
    $html = '<div class="page-head"><h1>' . h(doc_type_label($d['doc_type'])) . '</h1><a class="btn ghost" href="' . h($fileUrl) . '" target="_blank" rel="noopener">Abrir original ↗</a></div>'
        . '<p class="muted">' . h($m['original_name'] ?? '') . ' · ' . h($m['laboratory'] ?? 'laboratório não identificado') . ' · coleta ' . h(br_date($d['collected_on'])) . ' · ' . h(status_label($d['status'])) . '</p>';
    if ($d['status'] === 'duplicate') {
        $html .= '<p class="note">Mesmo laboratório e mesma data de coleta de um documento já lido; os valores não foram carregados de novo.</p>';
    }
    if ($d['status'] === 'failed') {
        $html .= '<p class="note">Não foi possível ler este documento (' . h((string) $d['error_code']) . '). Ele continua guardado.</p>';
    }
    if ($owner) {
        $html .= '<form method="post" class="inline-actions">' . csrf_field()
            . (in_array($d['status'], ['failed', 'stored'], true) ? '<button class="btn ghost" name="action" value="retry">Ler de novo</button>' : '')
            . '<button class="btn danger" name="action" value="delete" data-confirm="Excluir este documento e todos os valores extraídos dele?">Excluir documento</button></form>';
    }
    $results = document_results($uid, $id);
    if ($results) {
        $html .= '<div class="table-wrap"><table><thead><tr><th>Exame como impresso</th><th>Valor como impresso</th><th>Faixa do laboratório</th><th>Situação</th></tr></thead><tbody>';
        foreach ($results as $r) {
            $p = $r['p'];
            $state = ['loaded' => 'Carregado', 'quarantine' => 'Em quarentena', 'corrected' => 'Substituído por correção'][$r['status']];
            $checks = implode('; ', array_map(fn ($k, $v) => check_label($k, $v), array_keys($p['checks'] ?? []), $p['checks'] ?? []));
            $fix = $owner && $r['status'] !== 'corrected'
                ? '<details class="fix"><summary>Corrigir</summary><form method="post" action="' . h(url('fix')) . '" class="form compact">' . csrf_field() . '<input type="hidden" name="id" value="' . $r['id'] . '">'
                  . '<label>Valor como está no laudo<input name="value" value="' . h($p['value_printed']) . '" required></label><label>Unidade<input name="unit" value="' . h($p['unit_printed']) . '"></label><button class="btn ghost">Salvar correção</button></form></details>'
                : '';
            $html .= '<tr class="' . h($r['status']) . '"><td>' . h($p['name_printed']) . ($p['section'] ? '<br><span class="muted">' . h($p['section']) . '</span>' : '') . ($p['column'] === 'previous' ? '<br><span class="muted">resultado anterior impresso no laudo, ' . h(br_date($r['observed_on'])) . '</span>' : '') . '</td>'
                . '<td>' . value_html($p) . flag_html($p['flag_printed']) . ($r['origin'] === 'patient_correction' ? '<br><span class="muted">corrigido pelo paciente</span>' : '') . provenance_html($p, $fileUrl) . '</td>'
                . '<td>' . ranges_html($p['reference_printed']) . '</td>'
                . '<td>' . h($state) . '<br><span class="muted">' . h(resolution_label($p['resolution'] ?? 'ab')) . ($checks ? '; ' . h($checks) : '') . '</span>'
                . (!empty($p['alternative']) ? '<br><span class="muted">outra leitura: ' . h(printed_with_unit($p['alternative']['value_printed'] ?? '', $p['alternative']['unit_printed'] ?? '')) . '</span>' : '') . $fix . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }
    if (!empty($m['body_unconfirmed'])) {
        $html .= '<h2>Métricas de bioimpedância não confirmadas</h2><ul class="plain">' . implode('', array_map(fn ($x) => '<li>' . h(($x['name_printed'] ?? '') . ': ' . printed_with_unit($x['value_printed'] ?? '', $x['unit_printed'] ?? '')) . '</li>', $m['body_unconfirmed'])) . '</ul>';
    }
    if (!empty($m['prescription'])) {
        $html .= '<h2>Itens da receita</h2><ul class="plain">';
        foreach ($m['prescription'] as $i => $it) {
            $html .= '<li><strong>' . h($it['drug']) . '</strong> ' . h(trim(($it['strength'] ?? '') . ' ' . ($it['form'] ?? ''))) . ' — ' . h($it['instructions'] ?? '')
                . ($it['confirmed'] ? '' : ' <span class="muted">(só uma leitura listou este item)</span>')
                . ($owner ? ' <form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="add_med"><input type="hidden" name="i" value="' . $i . '"><button class="link">Adicionar às medicações</button></form>' : '') . '</li>';
        }
        $html .= '</ul>';
    }
    if ($d['mime'] === 'application/pdf') {
        $html .= '<iframe class="doc-frame" src="' . h($fileUrl) . '" title="Laudo original"></iframe>';
    } else {
        $html .= '<img class="doc-image" src="' . h($fileUrl) . '" alt="Imagem do documento enviado">';
    }
    return $html;
}

function send_file(int $uid, int $id, ?int $shareId): void
{
    $d = row('SELECT * FROM documents WHERE id = ? AND user_id = ?', [$id, $uid]);
    if (!$d) {
        deny();
    }
    audit($uid, $shareId ? 'doctor' : 'patient', 'document.file', 'document:' . $id, $shareId);
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'self\'');
    header('Cache-Control: no-store');
    header('Content-Type: ' . $d['mime']);
    header('Content-Disposition: inline; filename="documento-' . $id . doc_ext($d['mime']) . '"');
    echo load_blob($uid, $d['blob_name']);
    exit;
}

function action_fix(int $uid): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $r = row("SELECT * FROM results WHERE id = ? AND user_id = ? AND status <> 'corrected'", [$id, $uid]);
    if (!$r) {
        deny();
    }
    $p = dec($uid, $r['payload_enc']);
    $printed = trim((string) ($_POST['value'] ?? ''));
    $unit = trim((string) ($_POST['unit'] ?? ''));
    // A correção passa pelo mesmo separador de número e dimensão da leitura, de modo que
    // "182 mg/dL" digitado no campo do valor rende 182 com mg/dL, e não um texto sem número.
    $corte = split_printed($printed);
    $num = $corte['numbers'] === 1 && $corte['rest'] === '' ? $corte['number'] : null;
    if ($unit === '' && $corte['unit'] !== '') {
        $unit = $corte['unit'];
    }
    $new = ['value_printed' => $printed, 'operator' => $corte['operator'], 'value_number' => $num, 'value_text' => $num === null ? $printed : null,
        'unit_printed' => $unit !== '' ? $unit : null, 'unit_ucum' => ucum_from_printed($unit), 'series_value' => $num, 'converted' => null,
        'resolution' => 'patient', 'checks' => [], 'alternative' => ['value_printed' => $p['value_printed'], 'unit_printed' => $p['unit_printed']]] + $p;
    // A corrected value keeps the series only when its unit is the series unit; otherwise it stays unconverted in its own series.
    $seriesId = $r['series_id'];
    if ($seriesId) {
        $s = row('SELECT payload_enc FROM series WHERE id = ?', [$seriesId]);
        $sp = dec($uid, $s['payload_enc']);
        if (($sp['unit_ucum'] ?? null) !== $new['unit_ucum']) {
            $conv = $num !== null ? ucum_convert($num, $new['unit_ucum'], $sp['unit_ucum'] ?? null, $p['loinc']['property'] ?? null, $sp['property'] ?? null) : null;
            if ($conv === null) {
                $seriesId = null;
            } else {
                $new['series_value'] = $conv;
                $new['converted'] = ['value' => $conv, 'unit' => $sp['unit_ucum']];
            }
        }
    }
    db()->beginTransaction();
    q("UPDATE results SET status = 'corrected' WHERE id = ?", [$id]);
    q('INSERT INTO results(user_id, document_id, series_id, status, origin, observed_on, replaces, payload_enc, created_at) VALUES (?,?,?,?,?,?,?,?,?)',
        [$uid, $r['document_id'], $seriesId, $seriesId ? 'loaded' : 'quarantine', 'patient_correction', $r['observed_on'], $id, enc($uid, $new), now()]);
    db()->commit();
    audit($uid, 'patient', 'result.correct', 'result:' . $id);
    flash($seriesId ? 'Correção salva; o valor original continua no histórico do documento.' : 'Correção salva. A unidade não é a da série, então o valor ficou fora do gráfico.');
    redirect('doc', ['id' => $r['document_id']]);
}

/* ── Series ───────────────────────────────────────────────────────────── */

function page_series(int $uid, int $id, bool $post): void
{
    $root = series_root($id);
    $all = series_for($uid);
    $s = null;
    foreach ($all as $x) {
        if ((int) $x['id'] === $root) {
            $s = $x;
        }
    }
    if (!$s) {
        deny();
    }
    if ($post) {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'pin') {
            q('UPDATE series SET pinned = 1 - pinned WHERE id = ? AND user_id = ?', [$root, $uid]);
        } elseif ($action === 'merge') {
            $other = series_root((int) ($_POST['other'] ?? 0));
            $o = row('SELECT * FROM series WHERE id = ? AND user_id = ?', [$other, $uid]);
            if ($o && $other !== $root) {
                q('UPDATE series SET merged_into = ? WHERE id = ?', [$root, $other]);
                audit($uid, 'patient', 'series.merge', 'series:' . $other . '>' . $root);
                flash('Séries juntadas por sua decisão. Você pode separá-las de novo aqui.');
            }
        } elseif ($action === 'unmerge') {
            q('UPDATE series SET merged_into = NULL WHERE merged_into = ? AND user_id = ?', [$root, $uid]);
            audit($uid, 'patient', 'series.unmerge', 'series:' . $root);
            flash('Séries separadas.');
        }
        redirect('series', ['id' => $root]);
    }
    $units = array_unique(array_map(fn ($p) => norm_unit($p['unit']), $s['points']));
    $candidates = '';
    foreach ($all as $x) {
        if ((int) $x['id'] !== $root) {
            $candidates .= '<option value="' . (int) $x['id'] . '">' . h($x['payload']['label'] . ' · ' . ($x['last']['unit'] ?? '') . ' · ' . ($x['last']['lab'] ?? '')) . '</option>';
        }
    }
    $merged = count($s['members']) > 1;
    $html = '<div class="page-head"><h1>' . h($s['payload']['label']) . '</h1><form method="post" class="inline">' . csrf_field() . '<button class="btn ghost" name="action" value="pin">' . ($s['pinned'] ? 'Desafixar' : 'Fixar na visão geral') . '</button></form></div>'
        . '<p class="muted">' . ($s['payload']['loinc'] ? 'LOINC ' . h($s['payload']['loinc']) . ' (proposto pela IA) · ' : 'Série agrupada pelo nome impresso (sem correspondência LOINC forte) · ') . 'unidade da série: ' . h($s['payload']['unit_printed'] ?? '—') . '</p>'
        . (count($units) > 1 ? '<p class="note">Esta série tem valores em unidades diferentes, juntadas por você. O gráfico mostra cada valor na unidade em que veio.</p>' : '')
        . chart_html($s, dose_markers(medications_for($uid)), 'chart', patient_context($uid))
        . series_table_html($s, fn ($doc, $page) => url('doc', ['id' => $doc]))
        . '<h2>Juntar ou separar</h2><p class="muted">O Eixo só junta séries com correspondência forte (mesmo código, material e propriedade). Se este exame aparece em outra série por diferença de nome, você pode juntar; na dúvida, mantenha separado: juntar por engano cria tendência falsa.</p>'
        . ($candidates ? '<form method="post" class="form compact">' . csrf_field() . '<input type="hidden" name="action" value="merge"><label>Juntar a esta série<select name="other">' . $candidates . '</select></label><button class="btn ghost" data-confirm="Juntar as duas séries num só gráfico?">Juntar</button></form>' : '')
        . ($merged ? '<form method="post">' . csrf_field() . '<button class="btn ghost" name="action" value="unmerge">Separar as séries juntadas</button></form>' : '');
    layout($s['payload']['label'], $html, ['nav' => 'patient', 'active' => '']);
}

/* ── Medications ─────────────────────────────────────────────────────── */

function med_fields(array $p = []): string
{
    $f = fn ($name, $label, $type = 'text', $req = false) => '<label>' . $label . '<input type="' . $type . '" name="' . $name . '" value="' . h($p[$name] ?? '') . '"' . ($req ? ' required' : '') . '></label>';
    return $f('name', 'Princípio ativo', 'text', true) . $f('brand', 'Nome comercial') . $f('strength', 'Concentração (ex.: 0,25 mg)') . $f('form', 'Forma (comprimido, caneta…)')
        . $f('route', 'Via (oral, subcutânea…)') . $f('dosage', 'Posologia atual') . $f('time', 'Horário') . $f('reason', 'Motivo') . $f('prescriber', 'Prescritor');
}

function page_meds(int $uid, bool $post): void
{
    if ($post) {
        $name = trim((string) ($_POST['name'] ?? ''));
        $start = (string) ($_POST['start'] ?? '');
        if ($name !== '') {
            $p = [];
            foreach (['name', 'brand', 'strength', 'form', 'route', 'dosage', 'time', 'reason', 'prescriber'] as $k) {
                $p[$k] = trim((string) ($_POST[$k] ?? ''));
            }
            $kind = ($_POST['kind'] ?? '') === 'supplement' ? 'supplement' : 'medication';
            q('INSERT INTO medications(user_id, kind, payload_enc, created_at) VALUES (?,?,?,?)', [$uid, $kind, enc($uid, $p), now()]);
            $mid = (int) db()->lastInsertId();
            if (valid_date($start)) {
                q('INSERT INTO dose_events(user_id, medication_id, type, event_on, payload_enc, created_at) VALUES (?,?,?,?,?,?)',
                    [$uid, $mid, 'start', $start, enc($uid, ['dose' => $p['strength'], 'posology' => $p['dosage'], 'note' => '']), now()]);
            }
            audit($uid, 'patient', 'medication.create', 'medication:' . $mid);
            flash('Registrado.');
        }
        redirect('meds');
    }
    $types = ['start' => 'início', 'adjust' => 'ajuste', 'pause' => 'pausa', 'resume' => 'retomada', 'stop' => 'suspensão'];
    $html = '<h1>Medicações e suplementos</h1><p class="muted">Cada mudança de dose é um evento com data, e aparece como marco nos gráficos dos exames. Suplementos entram aqui também (biotina, creatina, vitamina D…).</p>';
    foreach (medications_for($uid) as $m) {
        $last = end($m['events']) ?: null;
        $html .= '<article class="med"><h2><a href="' . h(url('med', ['id' => $m['id']])) . '">' . h($m['p']['name'] ?? '') . '</a> <span class="muted">' . h(trim(($m['p']['brand'] ?? '') . ' ' . ($m['p']['strength'] ?? ''))) . ($m['kind'] === 'supplement' ? ' · suplemento' : '') . '</span></h2>'
            . '<p>' . ($last ? h('Último evento: ' . $types[$last['type']] . ' em ' . br_date($last['event_on']) . (($last['p']['dose'] ?? '') !== '' ? ', ' . $last['p']['dose'] : '')) : '<span class="muted">Sem eventos de dose.</span>') . '</p></article>';
    }
    $html .= '<details class="add" open><summary>Adicionar</summary><form method="post" class="form grid">' . csrf_field()
        . '<label>Tipo<select name="kind"><option value="medication">Medicação</option><option value="supplement">Suplemento</option></select></label>'
        . med_fields() . '<label>Data de início<input type="date" name="start"></label><button class="btn">Salvar</button></form></details>';
    layout('Medicações', $html, ['nav' => 'patient', 'active' => 'meds']);
}

function page_med(int $uid, int $id, bool $post): void
{
    $m = row('SELECT * FROM medications WHERE id = ? AND user_id = ?', [$id, $uid]);
    if (!$m) {
        deny();
    }
    $p = dec($uid, $m['payload_enc']);
    if ($post) {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'event') {
            $type = (string) ($_POST['type'] ?? '');
            $date = (string) ($_POST['date'] ?? '');
            if (in_array($type, ['start', 'adjust', 'pause', 'resume', 'stop'], true) && valid_date($date)) {
                q('INSERT INTO dose_events(user_id, medication_id, type, event_on, payload_enc, created_at) VALUES (?,?,?,?,?,?)', [$uid, $id, $type, $date,
                    enc($uid, ['dose' => trim((string) ($_POST['dose'] ?? '')), 'posology' => trim((string) ($_POST['posology'] ?? '')), 'note' => trim((string) ($_POST['note'] ?? ''))]), now()]);
                audit($uid, 'patient', 'dose.event', 'medication:' . $id);
            }
        } elseif ($action === 'edit') {
            foreach (['name', 'brand', 'strength', 'form', 'route', 'dosage', 'time', 'reason', 'prescriber'] as $k) {
                $p[$k] = trim((string) ($_POST[$k] ?? ''));
            }
            q('UPDATE medications SET payload_enc = ? WHERE id = ?', [enc($uid, $p), $id]);
            audit($uid, 'patient', 'medication.edit', 'medication:' . $id);
        } elseif ($action === 'delete_event') {
            q('DELETE FROM dose_events WHERE id = ? AND medication_id = ?', [(int) ($_POST['event'] ?? 0), $id]);
            audit($uid, 'patient', 'dose.delete', 'medication:' . $id);
        } elseif ($action === 'delete') {
            q('DELETE FROM medications WHERE id = ?', [$id]);
            audit($uid, 'patient', 'medication.delete', 'medication:' . $id);
            redirect('meds');
        }
        redirect('med', ['id' => $id]);
    }
    $types = ['start' => 'Início', 'adjust' => 'Ajuste', 'pause' => 'Pausa', 'resume' => 'Retomada', 'stop' => 'Suspensão'];
    $events = '';
    foreach (rows('SELECT * FROM dose_events WHERE medication_id = ? ORDER BY event_on, id', [$id]) as $e) {
        $ep = dec($uid, $e['payload_enc']);
        $events .= '<li><time>' . h(br_date($e['event_on'])) . '</time> ' . h($types[$e['type']]) . ' ' . h(trim(($ep['dose'] ?? '') . ' ' . ($ep['posology'] ?? ''))) . ($ep['note'] ? ' <span class="muted">' . h($ep['note']) . '</span>' : '')
            . ' <form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="delete_event"><input type="hidden" name="event" value="' . $e['id'] . '"><button class="link" data-confirm="Excluir este evento?">excluir</button></form></li>';
    }
    $opts = implode('', array_map(fn ($k, $v) => '<option value="' . $k . '">' . $v . '</option>', array_keys($types), $types));
    layout($p['name'] ?? 'Medicação', '<h1>' . h($p['name'] ?? '') . '</h1><h2>Histórico de doses</h2><ol class="timeline">' . ($events ?: '<li class="muted">Nenhum evento.</li>') . '</ol>'
        . '<form method="post" class="form grid">' . csrf_field() . '<input type="hidden" name="action" value="event"><label>Evento<select name="type">' . $opts . '</select></label>'
        . '<label>Data<input type="date" name="date" required></label><label>Dose<input name="dose" placeholder="ex.: 0,5 mg"></label><label>Posologia<input name="posology" placeholder="ex.: 1x por semana"></label><label>Observação<input name="note"></label><button class="btn">Registrar evento</button></form>'
        . '<details><summary>Editar dados da medicação</summary><form method="post" class="form grid">' . csrf_field() . '<input type="hidden" name="action" value="edit">' . med_fields($p) . '<button class="btn">Salvar</button></form></details>'
        . '<form method="post">' . csrf_field() . '<button class="btn danger" name="action" value="delete" data-confirm="Excluir a medicação e todo o histórico de doses?">Excluir medicação</button></form>',
        ['nav' => 'patient', 'active' => 'meds']);
}

/* ── Body composition ─────────────────────────────────────────────────── */

function page_body(int $uid, bool $post): void
{
    if ($post) {
        $kind = (string) ($_POST['kind'] ?? '');
        $date = (string) ($_POST['date'] ?? '');
        if (!valid_date($date)) {
            flash('Informe a data da medição.');
            redirect('body');
        }
        $num = fn ($v) => ($v = trim(str_replace(',', '.', (string) $v))) === '' ? null : (is_numeric($v) ? (float) $v : null);
        $conditions = ['time' => (string) ($_POST['time'] ?? ''), 'fasting' => (string) ($_POST['fasting'] ?? ''), 'exercise' => (string) ($_POST['exercise'] ?? ''),
            'hydration' => (string) ($_POST['hydration'] ?? ''), 'cycle' => (string) ($_POST['cycle'] ?? '')];
        if ($kind === 'bia') {
            $device = ['brand' => trim((string) ($_POST['brand'] ?? '')), 'model' => trim((string) ($_POST['model'] ?? '')), 'technology' => trim((string) ($_POST['technology'] ?? ''))];
            $metrics = [];
            foreach ((array) ($_POST['metric_name'] ?? []) as $i => $name) {
                $v = $num($_POST['metric_value'][$i] ?? '');
                if (trim((string) $name) !== '' && $v !== null) {
                    $metrics[] = ['name' => trim((string) $name), 'value' => $v, 'value_printed' => (string) $_POST['metric_value'][$i], 'unit' => trim((string) ($_POST['metric_unit'][$i] ?? '')), 'page' => null, 'quote' => null];
                }
            }
            $deviceKey = norm_text($device['brand'] . ' ' . $device['model']);
            q('INSERT INTO body_measurements(user_id, kind, measured_on, device_hash, payload_enc, created_at) VALUES (?,?,?,?,?,?)',
                [$uid, 'bia', $date, $deviceKey !== '' ? keyed_hash($uid, $deviceKey) : null, enc($uid, ['device' => $device, 'conditions' => $conditions, 'metrics' => $metrics, 'date_from' => 'manual']), now()]);
        } elseif ($kind === 'anthro') {
            $p = ['weight' => $num($_POST['weight'] ?? ''), 'height' => $num($_POST['height'] ?? ''), 'waist' => $num($_POST['waist'] ?? ''), 'sbp' => $num($_POST['sbp'] ?? ''), 'dbp' => $num($_POST['dbp'] ?? ''), 'conditions' => $conditions];
            q('INSERT INTO body_measurements(user_id, kind, measured_on, payload_enc, created_at) VALUES (?,?,?,?,?)', [$uid, 'anthro', $date, enc($uid, $p), now()]);
        } elseif ($kind === 'conditions') {
            $b = row("SELECT * FROM body_measurements WHERE id = ? AND user_id = ? AND kind = 'bia'", [(int) ($_POST['id'] ?? 0), $uid]);
            if ($b) {
                $bp = dec($uid, $b['payload_enc']);
                $bp['conditions'] = $conditions;
                q('UPDATE body_measurements SET payload_enc = ?, measured_on = ? WHERE id = ?', [enc($uid, $bp), $date, $b['id']]);
            }
        }
        audit($uid, 'patient', 'body.' . $kind);
        flash('Medida registrada.');
        redirect('body');
    }
    $body = body_for($uid);
    $html = '<h1>Composição corporal</h1><p class="muted">Séries de bioimpedância só seguem contínuas com o mesmo aparelho: percentual de gordura e gordura visceral saem de equações próprias de cada fabricante. Registre também as condições da medição.</p>';
    $i = 0;
    foreach ($body['metrics'] as $m) {
        if (count($m['points']) < 1) {
            continue;
        }
        $pts = array_map(fn ($p) => ['d' => $p['date'], 'v' => $p['value'], 'op' => '', 'r' => null, 'b' => $p['condition_change'], 'lab' => null], $m['points']);
        $html .= '<section class="metric"><h2>' . h($m['label']) . ' <span class="muted">' . h($m['unit'] . ' · ' . $m['device']) . '</span></h2>' . ($m['warning'] ? '<p class="note">' . h($m['warning']) . '</p>' : '')
            . '<figure class="chart" data-chart="b' . $i . '"><div class="chart-canvas" aria-hidden="true"></div><script type="application/json" id="b' . $i . '">' . json_encode(['unit' => $m['unit'], 'points' => $pts, 'markers' => dose_markers(medications_for($uid))], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) . '</script>'
            . '<figcaption>Linha tracejada: as condições da medição mudaram em relação à anterior.</figcaption></figure>'
            . '<p class="muted">' . implode(' · ', array_map(fn ($p) => h(br_date($p['date']) . ': ' . br_number($p['value'])), $m['points'])) . '</p></section>';
        $i++;
    }
    foreach ($body['bia'] as $b) {
        if (empty($b['p']['conditions'])) {
            $html .= '<p class="note">Bioimpedância de ' . h(br_date($b['measured_on'])) . ' (vinda de documento) sem condições registradas. <details><summary>Registrar condições</summary>'
                . body_conditions_form($b) . '</details></p>';
        }
    }
    $html .= '<details class="add"><summary>Registrar bioimpedância manualmente</summary><form method="post" class="form grid">' . csrf_field() . '<input type="hidden" name="kind" value="bia">'
        . '<label>Data<input type="date" name="date" required></label><label>Marca do aparelho<input name="brand"></label><label>Modelo<input name="model"></label><label>Tecnologia<input name="technology" placeholder="ex.: octapolar, multifrequência"></label>'
        . body_conditions_fields() . '<fieldset class="metrics"><legend>Métricas impressas</legend>';
    foreach ([['Peso', 'kg'], ['Massa gorda', 'kg'], ['Percentual de gordura', '%'], ['Massa magra', 'kg'], ['Massa muscular esquelética', 'kg'], ['Água corporal total', 'L'], ['Ângulo de fase', '°'], ['Gordura visceral', 'nível'], ['Taxa metabólica basal', 'kcal'], ['', ''], ['', '']] as [$n, $u]) {
        $html .= '<div class="metric-row"><input name="metric_name[]" value="' . h($n) . '" placeholder="Métrica" aria-label="Métrica"><input name="metric_value[]" inputmode="decimal" placeholder="Valor" aria-label="Valor"><input name="metric_unit[]" value="' . h($u) . '" placeholder="Unidade" aria-label="Unidade"></div>';
    }
    $html .= '</fieldset><button class="btn">Salvar</button></form></details>'
        . '<details class="add"><summary>Registrar peso, altura, cintura ou pressão</summary><form method="post" class="form grid">' . csrf_field() . '<input type="hidden" name="kind" value="anthro">'
        . '<label>Data<input type="date" name="date" required></label><label>Peso (kg)<input name="weight" inputmode="decimal"></label><label>Altura (cm)<input name="height" inputmode="decimal"></label>'
        . '<label>Circunferência abdominal (cm)<input name="waist" inputmode="decimal"></label><label>PA sistólica (mmHg)<input name="sbp" inputmode="numeric"></label><label>PA diastólica (mmHg)<input name="dbp" inputmode="numeric"></label>'
        . body_conditions_fields() . '<button class="btn">Salvar</button></form></details>';
    if ($body['anthro']) {
        $html .= '<h2>Medidas</h2><ul class="plain">' . implode('', array_map(fn ($a) => '<li>' . h(br_date($a['measured_on']) . ' — ' . anthro_text($a['p'])) . '</li>', array_reverse($body['anthro']))) . '</ul>';
    }
    layout('Corpo', $html, ['nav' => 'patient', 'active' => 'body']);
}

function body_conditions_fields(array $c = []): string
{
    $sel = fn ($name, $label, $opts) => '<label>' . $label . '<select name="' . $name . '"><option value="">não informado</option>' . implode('', array_map(fn ($o) => '<option' . (($c[$name] ?? '') === $o ? ' selected' : '') . '>' . h($o) . '</option>', $opts)) . '</select></label>';
    return '<label>Horário<input type="time" name="time" value="' . h($c['time'] ?? '') . '"></label>'
        . $sel('fasting', 'Jejum', ['em jejum', 'sem jejum']) . $sel('exercise', 'Atividade física nas 12 h anteriores', ['não', 'sim'])
        . $sel('hydration', 'Hidratação', ['habitual', 'abaixo do habitual', 'acima do habitual']) . $sel('cycle', 'Fase do ciclo menstrual', ['não se aplica', 'folicular', 'ovulatória', 'lútea', 'menstruação']);
}

function body_conditions_form(array $b): string
{
    return '<form method="post" class="form grid">' . csrf_field() . '<input type="hidden" name="kind" value="conditions"><input type="hidden" name="id" value="' . (int) $b['id'] . '">'
        . '<label>Data<input type="date" name="date" value="' . h($b['measured_on']) . '" required></label>' . body_conditions_fields() . '<button class="btn">Salvar</button></form>';
}

/* ── Shares, summary, audit, account ─────────────────────────────────── */

function page_shares(int $uid, bool $post): void
{
    $created = null;
    if ($post) {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create') {
            if (!consents($uid)['share']) {
                flash('Ative o consentimento de compartilhamento em Conta → Consentimentos.');
                redirect('shares');
            }
            $scope = ($_POST['scope'] ?? '') === 'period' ? 'period' : 'all';
            $from = (string) ($_POST['from'] ?? '');
            $to = (string) ($_POST['to'] ?? '');
            if ($scope === 'period' && (!valid_date($from) || !valid_date($to) || $from > $to)) {
                flash('Período inválido.');
                redirect('shares');
            }
            $created = create_share($uid, trim((string) ($_POST['recipient'] ?? '')), $scope, $from ?: null, $to ?: null, (int) ($_POST['days'] ?? 30));
        } elseif ($action === 'revoke') {
            q('UPDATE shares SET revoked_at = ? WHERE id = ? AND user_id = ? AND revoked_at IS NULL', [now(), (int) ($_POST['id'] ?? 0), $uid]);
            audit($uid, 'patient', 'share.revoke', 'share:' . (int) ($_POST['id'] ?? 0), (int) ($_POST['id'] ?? 0));
            flash('Acesso revogado.');
            redirect('shares');
        } elseif ($action === 'code') {
            $code = reissue_share_code($uid, (int) ($_POST['id'] ?? 0));
            if ($code) {
                $created = ['code' => $code, 'token' => null, 'id' => (int) $_POST['id']];
            }
        }
    }
    $html = '<h1>Compartilhar com seu médico</h1><p class="muted">O médico recebe um link e, por outro canal, um código de uso único que vale 30 minutos. Ele vê o resumo descritivo, os gráficos e os laudos dentro do escopo, sem instalar nada. Cada acesso aparece na sua <a href="' . h(url('audit')) . '">trilha de auditoria</a>.</p>' . ai_disclosure_html();
    if ($created) {
        $html .= '<section class="created"><h2>Envie ao médico</h2>' . ($created['token'] ? '<p>Link (envie por e-mail ou mensagem):</p><p class="copy">' . h(absolute_url('d', ['t' => $created['token']])) . '</p><p class="muted">O link não aparece de novo. Guarde-o até enviar.</p>' : '')
            . '<p>Código de uso único (informe por outro canal, por exemplo por telefone ou pessoalmente na consulta):</p><p class="key">' . h(chunk_split($created['code'], 4, ' ')) . '</p></section>';
    }
    if (!consents($uid)['share']) {
        $html .= '<p class="note">O consentimento de compartilhamento está desligado. <a href="' . h(url('consents')) . '">Ligar</a>.</p>';
    } else {
        $html .= '<details class="add"' . ($created ? '' : ' open') . '><summary>Novo compartilhamento</summary><form method="post" class="form grid">' . csrf_field() . '<input type="hidden" name="action" value="create">'
            . '<label>Para quem (nome ou e-mail, só para você identificar)<input name="recipient" required></label>'
            . '<label>Escopo<select name="scope"><option value="all">Tudo</option><option value="period">Um período</option></select></label><label>De<input type="date" name="from"></label><label>Até<input type="date" name="to"></label>'
            . '<label>Validade<select name="days"><option value="7">7 dias</option><option value="30" selected>30 dias</option><option value="90">90 dias</option><option value="365">1 ano</option></select></label>'
            . '<button class="btn">Criar convite</button></form></details>';
    }
    $list = '';
    foreach (rows('SELECT * FROM shares WHERE user_id = ? ORDER BY id DESC', [$uid]) as $s) {
        $p = dec($uid, $s['payload_enc']);
        $active = !$s['revoked_at'] && $s['expires_at'] > now();
        $list .= '<tr><td>' . h($p['recipient'] ?? '') . '</td><td>' . ($s['scope'] === 'all' ? 'Tudo' : h(br_date($s['scope_from']) . ' a ' . br_date($s['scope_to']))) . '</td><td>' . h(br_date($s['expires_at'])) . '</td><td>'
            . ($active ? 'Ativo' : ($s['revoked_at'] ? 'Revogado' : 'Expirado')) . '</td><td>'
            . ($active ? '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . $s['id'] . '"><button class="link" name="action" value="code">Novo código</button> · <button class="link" name="action" value="revoke" data-confirm="Revogar o acesso agora?">Revogar</button></form>' : '') . '</td></tr>';
    }
    if ($list) {
        $html .= '<div class="table-wrap"><table><thead><tr><th>Destinatário</th><th>Escopo</th><th>Validade</th><th>Situação</th><th></th></tr></thead><tbody>' . $list . '</tbody></table></div>';
    }
    $html .= '<p><a href="' . h(url('summary')) . '">Ver o resumo como o médico verá</a></p>';
    layout('Compartilhar', $html, ['nav' => 'patient', 'active' => 'shares']);
}

function since_param(): string
{
    $since = (string) ($_GET['since'] ?? '');
    return valid_date($since) ? $since : gmdate('Y-m-d', strtotime('-12 months'));
}

function page_patient_summary(int $uid): void
{
    $since = since_param();
    $html = '<div class="page-head"><h1>Resumo pré-consulta</h1><button class="btn ghost" data-print>Imprimir ou salvar PDF</button></div>'
        . '<form class="search" method="get"><input type="hidden" name="r" value="summary"><label>Desde<input type="date" name="since" value="' . h($since) . '"></label><button class="btn ghost">Atualizar</button></form>'
        . ai_disclosure_html() . summary_html(summary_for($uid, null, $since), fn ($d, $p) => url('doc', ['id' => $d]), fn ($s) => url('series', ['id' => $s]));
    layout('Resumo', $html, ['nav' => 'patient', 'active' => 'shares']);
}

function page_audit(int $uid): void
{
    $labels = ['auth.login' => 'Entrada na conta', 'document.view' => 'Documento aberto', 'document.file' => 'Arquivo original aberto', 'summary.view' => 'Resumo aberto', 'series.view' => 'Gráfico aberto',
        'share.access' => 'Médico entrou pelo convite', 'share.create' => 'Compartilhamento criado', 'share.revoke' => 'Compartilhamento revogado', 'share.new_code' => 'Novo código gerado',
        'document.upload' => 'Documento enviado', 'document.processed' => 'Documento lido', 'document.failed' => 'Leitura falhou', 'document.duplicate' => 'Documento duplicado',
        'consent.grant' => 'Consentimento concedido', 'consent.revoke' => 'Consentimento revogado', 'account.export' => 'Exportação', 'result.correct' => 'Valor corrigido'];
    $rows = '';
    foreach (rows('SELECT * FROM access_log WHERE user_id = ? ORDER BY id DESC LIMIT 500', [$uid]) as $a) {
        $who = ['patient' => 'Você', 'doctor' => 'Médico (convite #' . (int) $a['share_id'] . ')', 'system' => 'Sistema'][$a['actor']];
        $rows .= '<tr><td>' . h(str_replace(['T', 'Z'], [' ', ' UTC'], $a['at'])) . '</td><td>' . h($who) . '</td><td>' . h($labels[$a['action']] ?? $a['action']) . '</td><td>' . h((string) $a['object']) . '</td></tr>';
    }
    layout('Trilha de auditoria', '<h1>Trilha de auditoria</h1><p class="muted">Quem acessou o quê, quando e por qual compartilhamento. Últimos 500 registros.</p><div class="table-wrap"><table><thead><tr><th>Quando</th><th>Quem</th><th>O quê</th><th>Objeto</th></tr></thead><tbody>' . $rows . '</tbody></table></div>',
        ['nav' => 'patient', 'active' => 'account']);
}

function page_account(int $uid, bool $post): void
{
    if ($post && ($_POST['action'] ?? '') === 'profile') {
        $sex = in_array($_POST['sex'] ?? '', ['female', 'male', 'other', ''], true) ? $_POST['sex'] : '';
        $birth = valid_date($_POST['birth'] ?? null) ? $_POST['birth'] : null;
        q('UPDATE users SET profile_enc = ? WHERE id = ?', [enc($uid, ['name' => trim((string) ($_POST['name'] ?? '')), 'sex' => $sex, 'birth_date' => $birth]), $uid]);
        flash('Perfil salvo.');
        redirect('account');
    }
    $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
    $p = dec($uid, $u['profile_enc']) ?? [];
    $codesLeft = count(json_decode($u['recovery_codes_json'], true) ?: []);
    layout('Conta', '<h1>Conta</h1><p>' . h($u['email']) . ' · segundo fator ativo · ' . $codesLeft . ' código(s) de recuperação restantes</p>'
        . '<p><a href="' . h(url('consents')) . '">Consentimentos</a> · <a href="' . h(url('audit')) . '">Trilha de auditoria</a> · <a href="' . h(url('privacy')) . '">Privacidade</a></p>'
        . '<h2>Perfil</h2><form method="post" class="form grid">' . csrf_field() . '<input type="hidden" name="action" value="profile">'
        . '<label>Nome<input name="name" value="' . h($p['name'] ?? '') . '"></label><label>Sexo<select name="sex"><option value="">não informado</option>'
        . implode('', array_map(fn ($k, $v) => '<option value="' . $k . '"' . (($p['sex'] ?? '') === $k ? ' selected' : '') . '>' . $v . '</option>', ['female', 'male', 'other'], ['feminino', 'masculino', 'outro']))
        . '</select></label><label>Data de nascimento<input type="date" name="birth" value="' . h($p['birth_date'] ?? '') . '"></label><button class="btn">Salvar</button></form>'
        . '<h2>Seus dados</h2><form method="post" action="' . h(url('export')) . '">' . csrf_field() . '<button class="btn ghost">Exportar tudo (ZIP com laudos originais e dados em JSON FHIR)</button></form>'
        . '<h2>Excluir conta</h2><p class="muted">Apaga na hora todos os documentos, valores, medicações, medidas e compartilhamentos, e destrói sua chave de cifragem. Não há como desfazer.</p>'
        . '<form method="post" action="' . h(url('account_delete')) . '" class="form compact">' . csrf_field() . '<label>Digite EXCLUIR para confirmar<input name="confirm" required></label><button class="btn danger">Excluir minha conta</button></form>',
        ['nav' => 'patient', 'active' => 'account']);
}

function action_export(int $uid): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        deny(405, 'Use o botão em Conta.');
    }
    $file = export_zip($uid);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="eixo-export-' . today() . '.zip"');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: no-store');
    readfile($file);
    unlink($file);
    exit;
}

function action_delete(int $uid): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || trim((string) ($_POST['confirm'] ?? '')) !== 'EXCLUIR') {
        flash('Digite EXCLUIR para confirmar.');
        redirect('account');
    }
    delete_account($uid);
    logout();
    layout('Conta excluída', '<section class="narrow"><h1>Conta excluída</h1><p>Seus documentos e dados de saúde foram apagados. Cópias de segurança são expurgadas no próximo ciclo.</p></section>');
}

/* ── Doctor ───────────────────────────────────────────────────────────── */

function page_doctor_code(bool $post): void
{
    $t = (string) ($_GET['t'] ?? '');
    $error = '';
    if ($post) {
        $keys = [rate_key('share', $t), rate_key('ip', client_ip())];
        if (too_many_attempts(...$keys)) {
            $error = 'Muitas tentativas. Aguarde 15 minutos.';
        } else {
            $s = redeem_share_code($t, (string) ($_POST['code'] ?? ''));
            if ($s) {
                doctor_login((int) $s['id']);
                audit((int) $s['user_id'], 'doctor', 'share.access', 'share:' . $s['id'], (int) $s['id']);
                redirect('dsum', ['s' => $s['id']]);
            }
            record_attempt(...$keys);
            $error = 'Código inválido ou expirado. Peça ao paciente um novo código.';
        }
    }
    layout('Acesso do médico', '<section class="narrow"><h1>Dados compartilhados por um paciente</h1>' . ($error ? '<p class="error" role="alert">' . h($error) . '</p>' : '')
        . '<p>Digite o código de 8 dígitos que o paciente informou. Ele vale uma vez e por 30 minutos. A sessão termina após 30 minutos sem uso.</p>'
        . '<form method="post" class="form">' . csrf_field() . '<label>Código<input name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus></label><button class="btn">Entrar</button></form></section>');
}

function page_doctor_list(): void
{
    $shares = doctor_shares();
    if (!$shares) {
        page_landing();
        return;
    }
    $html = '<h1>Pacientes que compartilharam dados</h1><ul class="plain">';
    foreach ($shares as $s) {
        $u = row('SELECT * FROM users WHERE id = ?', [$s['user_id']]);
        $p = dec((int) $u['id'], $u['profile_enc']) ?? [];
        $html .= '<li><a href="' . h(url('dsum', ['s' => $s['id']])) . '">' . h($p['name'] ?? '' ?: 'Paciente #' . $u['id']) . '</a> <span class="muted">· escopo: ' . ($s['scope'] === 'all' ? 'tudo' : h(br_date($s['scope_from']) . ' a ' . br_date($s['scope_to']))) . ' · válido até ' . h(br_date($s['expires_at'])) . '</span></li>';
    }
    layout('Pacientes', $html . '</ul>', ['nav' => 'doctor']);
}

function require_doctor_share(int $shareId): array
{
    $s = doctor_share($shareId);
    if (!$s) {
        deny(403, 'Acesso expirado ou revogado. Peça ao paciente um novo código.');
    }
    return $s;
}

function page_doctor_summary(int $shareId): void
{
    $s = require_doctor_share($shareId);
    $uid = (int) $s['user_id'];
    $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
    $p = dec($uid, $u['profile_enc']) ?? [];
    $since = since_param();
    audit($uid, 'doctor', 'summary.view', 'since:' . $since, $shareId);
    $age = !empty($p['birth_date']) ? (int) date_diff(date_create($p['birth_date']), date_create('today'))->y . ' anos' : null;
    $html = '<div class="page-head"><h1>' . h($p['name'] ?? '' ?: 'Paciente') . '</h1><button class="btn ghost" data-print>Imprimir ou salvar PDF</button></div>'
        . '<p class="muted">' . h(implode(' · ', array_filter([['female' => 'feminino', 'male' => 'masculino', 'other' => 'outro'][$p['sex'] ?? ''] ?? null, $age, 'escopo: ' . ($s['scope'] === 'all' ? 'tudo' : br_date($s['scope_from']) . ' a ' . br_date($s['scope_to']))]))) . '</p>'
        . '<form class="search" method="get"><input type="hidden" name="r" value="dsum"><input type="hidden" name="s" value="' . $shareId . '"><label>Desde<input type="date" name="since" value="' . h($since) . '"></label><button class="btn ghost">Atualizar</button></form>'
        . ai_disclosure_html()
        . summary_html(summary_for($uid, $s, $since), fn ($d, $pg) => url('ddoc', ['s' => $shareId, 'id' => $d]) . '#p' . $pg, fn ($sid) => url('dseries', ['s' => $shareId, 'id' => $sid]))
        . '<details><summary>Texto para registrar no prontuário (Resolução CFM 2.454/2026, art. 4º, V)</summary><p class="copy">Dados laboratoriais, de composição corporal e de medicação organizados pelo paciente na plataforma Eixo, com transcrição por inteligência artificial em leitura dupla e conferência automatizada contra o laudo original. Valores conferidos nos laudos de origem quando relevantes para a conduta.</p></details>';
    layout('Resumo', $html, ['nav' => 'doctor']);
}

function page_doctor_series(int $shareId, int $id): void
{
    $s = require_doctor_share($shareId);
    $uid = (int) $s['user_id'];
    $root = series_root($id);
    foreach (series_for($uid, $s) as $x) {
        if ((int) $x['id'] === $root) {
            audit($uid, 'doctor', 'series.view', 'series:' . $root, $shareId);
            layout($x['payload']['label'], '<h1>' . h($x['payload']['label']) . '</h1>' . chart_html($x, dose_markers(medications_for($uid, $s)))
                . series_table_html($x, fn ($doc, $page) => url('ddoc', ['s' => $shareId, 'id' => $doc])), ['nav' => 'doctor']);
            return;
        }
    }
    deny();
}

function page_doctor_doc(int $shareId, int $id): void
{
    $s = require_doctor_share($shareId);
    $uid = (int) $s['user_id'];
    $d = document_for($uid, $id, $s);
    if (!$d) {
        deny(403, 'Documento fora do escopo compartilhado.');
    }
    audit($uid, 'doctor', 'document.view', 'document:' . $id, $shareId);
    layout('Documento', document_html($uid, $d, url('dfile', ['s' => $shareId, 'id' => $id]), false), ['nav' => 'doctor']);
}

function doctor_file(int $shareId, int $id): void
{
    $s = require_doctor_share($shareId);
    $uid = (int) $s['user_id'];
    if (!document_for($uid, $id, $s)) {
        deny(403, 'Documento fora do escopo compartilhado.');
    }
    send_file($uid, $id, $shareId);
}
