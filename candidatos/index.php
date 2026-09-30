<?php
declare(strict_types=1);
require __DIR__.'/core.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
ini_set('session.use_strict_mode','1');
session_name('candidatos');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/candidatos/']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function h(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function hidden(string $name,mixed $value): void { echo '<input type="hidden" name="'.h($name).'" value="'.h($value).'">'; }
function csrf(string $action): void { hidden('csrf',$_SESSION['csrf']); hidden('action',$action); }
function redirect(string $url='?'): never { header('Location: '.$url, true,303); exit; }
function numberOrNull(string $key): ?int {
    if (!isset($_POST[$key]) || $_POST[$key]==='') return null;
    $value=filter_var($_POST[$key],FILTER_VALIDATE_INT);
    if ($value===false) throw new DomainException('Valor inválido.'); return $value;
}
$error=''; $user=null; $token=(string)($_SESSION['token']??'');
try {
    db(); verifySchema();
    if (isset($_GET['token'])) { $_SESSION['token']=(string)$_GET['token']; redirect('?activate=1'); }
    if (isset($_SESSION['uid'])) {
        $user=query('SELECT * FROM users WHERE id=? AND active=1',[$_SESSION['uid']])->fetch() ?: null;
        if (!$user || $user['auth_version']!==($_SESSION['auth_version']??null) || time()-($_SESSION['last_seen']??0)>7200) {
            unset($_SESSION['uid'],$_SESSION['auth_version']); $user=null;
        } else $_SESSION['last_seen']=time();
    }
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))) throw new DomainException('Formulário expirado. Recarregue a página.');
        $action=(string)($_POST['action']??'');
        if ($action==='login') {
            throttle('login:'.($_SERVER['REMOTE_ADDR']??''));
            $u=query('SELECT * FROM users WHERE email=? AND active=1',[strtolower(trim((string)($_POST['email']??'')))])->fetch();
            if (!$u || !$u['password'] || !password_verify((string)($_POST['password']??''),$u['password'])) throw new DomainException('E-mail ou senha inválidos.');
            session_regenerate_id(true); $_SESSION['uid']=$u['id']; $_SESSION['auth_version']=$u['auth_version']; $_SESSION['last_seen']=time(); unset($_SESSION['token']); redirect();
        } elseif ($action==='activate') {
            throttle('activate:'.($_SERVER['REMOTE_ADDR']??''));
            if (($_POST['password']??'')!==($_POST['confirm']??'')) throw new DomainException('As senhas são diferentes.');
            redeemToken($token,(string)($_POST['password']??'')); unset($_SESSION['token']); $_SESSION['flash']='Senha definida. Entre com seu e-mail.'; redirect();
        } elseif ($action==='logout') {
            $_SESSION=[]; session_destroy(); redirect();
        } else {
            if (!$user) throw new DomainException('Entre novamente para continuar.');
            $uid=(int)$user['id'];
            if ($action==='save') {
                saveEvaluation($uid,(int)$_POST['assignment'],(int)$_POST['version'],numberOrNull('ideological'),numberOrNull('profile'),(string)$_POST['status']);
                $_SESSION['flash']='Avaliação salva.'; redirect('?'.http_build_query($_GET));
            }
            activeUser($uid,true);
            if ($action==='preview') {
                $_SESSION['preview']=atomic(fn()=>plan($_POST)); $_SESSION['operation']=bin2hex(random_bytes(16)); redirect('?view=distribution');
            } elseif ($action==='execute') {
                if (!isset($_SESSION['preview']) || !hash_equals($_SESSION['operation']??'',(string)($_POST['operation']??''))) throw new DomainException('Gere uma nova prévia.');
                $batch=executePlan($uid,$_SESSION['preview'],$_SESSION['operation']); unset($_SESSION['preview'],$_SESSION['operation']);
                $_SESSION['flash']="Operação concluída. Lote $batch."; redirect('?view=distribution');
            } elseif ($action==='invite') {
                $email=strtolower(trim((string)$_POST['email'])); $name=trim((string)$_POST['name']);
                if (!filter_var($email,FILTER_VALIDATE_EMAIL) || $name==='') throw new DomainException('Informe nome e e-mail válidos.');
                $newToken=atomic(function () use ($email,$name,$uid) {
                    if (query('SELECT id FROM users WHERE email=?',[$email])->fetchColumn()) throw new DomainException('E-mail já cadastrado. Use emitir novo link.');
                    query("INSERT INTO users(email,name,role) VALUES(?,?,'reviewer')",[$email,$name]);
                    $id=(int)db()->lastInsertId(); event($uid,'reviewer_invited',['user'=>$id]); return issueToken($id,'invite');
                });
                $_SESSION['link']='?token='.$newToken; redirect('?view=users');
            } elseif ($action==='reset') {
                $newToken=atomic(function () use ($uid) {
                    $target=activeUser((int)$_POST['target']);
                    event($uid,'password_link_issued',['user'=>$target['id']]);
                    return issueToken((int)$target['id'],$target['password']?'reset':'invite');
                });
                $_SESSION['link']='?token='.$newToken; redirect('?view=users');
            } elseif ($action==='access') {
                atomic(function () use ($uid) {
                    $target=query('SELECT * FROM users WHERE id=?',[(int)$_POST['target']])->fetch();
                    if (!$target || $target['role']!=='reviewer') throw new DomainException('Ação disponível apenas para revisores.');
                    query('UPDATE users SET active=1-active,auth_version=auth_version+1 WHERE id=?',[$target['id']]);
                    event($uid,'access_changed',['user'=>$target['id'],'active'=>!$target['active']]);
                }); $_SESSION['flash']='Acesso alterado. As atribuições foram mantidas.'; redirect('?view=users');
            } elseif ($action==='reopen') {
                reopen($uid,(int)$_POST['assignment'],(string)$_POST['reason']); $_SESSION['flash']='Avaliação devolvida para correção.'; redirect();
            } elseif ($action==='settings') {
                $help=trim((string)$_POST['help']); if ($help==='') throw new DomainException('Descreva o critério.');
                atomic(function () use ($uid,$help) {
                    $old=query("SELECT value FROM settings WHERE key='ideology_help'")->fetchColumn();
                    query("UPDATE settings SET value=? WHERE key='ideology_help'",[$help]); event($uid,'criteria_changed',['previous'=>$old,'new'=>$help]);
                }); $_SESSION['flash']='Critério atualizado.'; redirect('?view=settings');
            } elseif ($action==='backup') {
                $file=backup(); event($uid,'backup_created',['file'=>basename($file)]);
                header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="'.basename($file).'"'); readfile($file); exit;
            } else throw new DomainException('Ação desconhecida.');
        }
    }
} catch (DomainException $e) { $error=$e->getMessage(); }
catch (Throwable $e) { error_log((string)$e); http_response_code(503); $error='Operação indisponível. Nenhuma alteração parcial foi aplicada. Consulte o administrador.'; }

$admin=$user && $user['role']==='admin';
$view=(string)($_GET['view']??'candidates');
if ($user && !$admin && !in_array($view,['candidates','candidate_history'],true)) { http_response_code(403); $view='candidates'; $error='Área exclusiva do administrador.'; }
$f=filters($_GET); $page=max(1,(int)($_GET['page']??1));
$statuses=['new'=>'Não iniciada','draft'=>'Em andamento','done'=>'Concluída','returned'=>'Devolvida'];
$base=" FROM candidates c LEFT JOIN assignments a ON a.candidate_id=c.id AND a.ended_at IS NULL LEFT JOIN users u ON u.id=a.user_id LEFT JOIN evaluations e ON e.id=(SELECT MAX(id) FROM evaluations WHERE assignment_id=a.id) WHERE 1=1";
if ($user) {
    $params=[]; $where=filterSql($f,$params);
    if (!$admin) { $where.=' AND a.user_id=?'; $params[]=$user['id']; }
    if ($f['status']==='available') $where.=' AND a.id IS NULL';
    elseif ($f['status']!=='') { $where.=' AND a.status=?'; $params[]=$f['status']; }
    if ($admin && $f['source']!=='') { $where.=' AND a.user_id=?'; $params[]=(int)$f['source']; }
    if ($f['batch']!=='') { $where.=' AND a.batch_id=?'; $params[]=(int)$f['batch']; }
    if ($admin && $view==='export') {
        header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="classificacoes.csv"');
        $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['id','ano','eleicao','id_fonte','uf','partido','cargo','nome','revisor','situacao','ideologico','perfil','versao'], ';','"','');
        $stmt=query('SELECT c.id,c.year,c.election,c.source_id,c.uf,c.party,c.office,c.name,u.email,a.status,e.ideological,e.profile,a.version'.$base.$where.' ORDER BY c.id',$params);
        while ($row=$stmt->fetch(PDO::FETCH_NUM)) {
            $row=array_map(fn($v)=>is_string($v) && preg_match('/^[=+@\-\t\r]/',$v)?"'".$v:$v,$row);
            fputcsv($out,$row,';','"','');
        } exit;
    }
}
function filterForm(array $f): void {
    foreach (['year'=>'Ano','office'=>'Cargo','party'=>'Partido'] as $key=>$label) {
        echo '<label>'.h($label).'<select name="'.h($key).'"><option value="">Todos</option>';
        foreach (query("SELECT DISTINCT $key FROM candidates ORDER BY $key")->fetchAll(PDO::FETCH_COLUMN) as $v) echo '<option value="'.h($v).'"'.((string)$v===$f[$key]?' selected':'').'>'.h($v).'</option>';
        echo '</select></label>';
    }
    echo '<label>UFs (separe por vírgula)<input name="uf" value="'.h(implode(',',$f['uf'])).'" placeholder="SP, RJ"></label>';
    echo '<label>Nome<input name="name" value="'.h($f['name']).'"></label>';
}
?>
<!doctype html><html lang="pt-BR"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Classificação de candidatos</title><link rel="stylesheet" href="style.css">
<body><header><a href="/">andreycosta.com</a><h1>Classificação de candidatos</h1>
<?php if ($user): ?><p><?=h($user['name'])?> · <?=$admin?'Administrador':'Revisor'?></p><nav><a href="?">Candidatos</a><?php if ($admin): ?> <a href="?source=<?=$user['id']?>">Minhas candidaturas</a> <a href="?view=distribution">Distribuição</a> <a href="?view=users">Revisores</a> <a href="?view=history">Histórico</a> <a href="?view=settings">Configuração e backup</a><?php endif ?></nav><form method="post"><?php csrf('logout') ?><button>Sair</button></form><?php endif ?></header><main>
<?php if ($error): ?><p role="alert" class="error"><?=h($error)?></p><?php endif ?>
<?php if (isset($_SESSION['flash'])): ?><p role="status" class="success"><?=h($_SESSION['flash'])?></p><?php unset($_SESSION['flash']); endif ?>
<?php if (!$user): ?>
<?php if ($token): ?><h2>Definir senha</h2><form method="post"><?php csrf('activate') ?><label>Senha (12 a 72 caracteres)<input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><label>Repita a senha<input type="password" name="confirm" required autocomplete="new-password"></label><button>Ativar acesso / redefinir senha</button></form>
<?php else: ?><h2>Entrar</h2><form method="post"><?php csrf('login') ?><label>E-mail<input type="email" name="email" required autocomplete="username"></label><label>Senha<input type="password" name="password" required autocomplete="current-password"></label><button>Entrar</button></form><p>O acesso é por convite. Para recuperar sua senha, solicite um novo link ao administrador.</p><?php endif ?>
<?php elseif ($view==='distribution' && $admin): ?>
<h2>Atribuição e retomada em bloco</h2><p>Carga = todas as pendências do revisor, em qualquer ano, cargo ou UF. Ordem das candidaturas: UF, nome e identificador. Desempate dos revisores: identificador.</p>
<form method="post"><?php csrf('preview') ?><div class="filters"><label>Operação<select name="kind"><option value="assign">Atribuir disponíveis</option><option value="reclaim">Retomar / transferir pendências</option></select></label><?php filterForm($f) ?><label>Modo de atribuição<select name="mode"><option value="fixed">Lote fixo por revisor</option><option value="fill">Completar carga total</option></select></label><label>Quantidade / alvo<input type="number" name="quantity" min="1" max="10000" value="50" required></label>
<label>Origem (apenas retomada)<select name="source"><option value="">Selecione</option><?php foreach(query("SELECT * FROM users ORDER BY name")->fetchAll() as $u): ?><option value="<?=$u['id']?>" <?=$f['source']==$u['id']?'selected':''?>><?=h($u['name'])?><?=$u['active']?'':' (suspenso)'?></option><?php endforeach ?></select></label>
<label>Situação (apenas retomada)<select name="status"><option value="">Todas as pendentes</option><?php foreach($statuses as $k=>$v) if($k!=='done') echo '<option value="'.$k.'">'.h($v).'</option>'; ?></select></label><label>Lote de origem (opcional)<input type="number" name="batch" min="1"></label></div>
<fieldset><legend>Destinatários — na retomada, deixe vazio para devolver à fila</legend><?php foreach(query("SELECT * FROM users WHERE active=1 AND password IS NOT NULL ORDER BY name")->fetchAll() as $u): ?><label class="inline"><input type="checkbox" name="users[]" value="<?=$u['id']?>"> <?=h($u['name'])?> (<?=workload((int)$u['id'])?> pendências)</label><?php endforeach ?></fieldset><label>Motivo (obrigatório na retomada)<input name="reason" maxlength="1000"></label><p>Transferências equilibram a carga total. Rascunhos anteriores permanecem no histórico; o novo responsável começa sem marcadores preenchidos. Concluídas não entram na retomada.</p><button>Gerar prévia</button></form>
<?php if (isset($_SESSION['preview'])): $p=$_SESSION['preview']; ?><section><h2>Prévia</h2><p>Filtros: <?=h(jsonValue($p['spec']['filters']))?></p><p>Operação: <?=$p['spec']['kind']==='assign'?'Atribuir':'Retomar / redistribuir'?> · modo: <?=h($p['spec']['mode'])?> · alvo: <?=$p['spec']['quantity']?></p><p><?=count($p['map'])?> candidaturas selecionadas de <?=$p['eligible']?> elegíveis. Saldo: <?=$p['remaining']?>.</p><p>Situações: <?=h(jsonValue($p['states']))?></p><table><tr><th>Revisor</th><th>Carga atual</th><th>Receberá</th><th>Carga final</th></tr><?php foreach($p['received'] as $id=>$n): ?><tr><td><?=h($p['names'][$id])?></td><td><?=$p['loads'][$id]?></td><td><?=$n?></td><td><?=$p['loads'][$id]+$n?></td></tr><?php endforeach ?></table><?php if (!$p['received']): ?><p>Destino: fila disponível.</p><?php endif ?><p>Quando faltam candidaturas, o lote fixo alterna entre revisores; completar carga e transferir priorizam a menor carga.</p><form method="post"><?php csrf('execute'); hidden('operation',$_SESSION['operation']); ?><button <?=!$p['map']?'disabled':''?>>Confirmar esta distribuição</button></form></section><?php endif ?>
<?php elseif ($view==='users' && $admin): ?>
<h2>Revisores e acessos</h2><form method="post" class="filters"><?php csrf('invite') ?><label>Nome<input name="name" required maxlength="150"></label><label>E-mail<input name="email" type="email" required></label><button>Criar convite</button></form>
<?php if (isset($_SESSION['link'])): ?><p class="success">Envie este link exclusivamente ao e-mail cadastrado (válido por 48 horas): <a href="<?=h($_SESSION['link'])?>">Abrir link de ativação/recuperação</a>. Copie o endereço do link. Ele só aparece agora.</p><?php unset($_SESSION['link']); endif ?>
<p>Suspender acesso mantém as atribuições. Retomar trabalho mantém o acesso. A recuperação de senha usa um novo link entregue pelo administrador.</p>
<div class="table"><table><tr><th>Revisor</th><th>Acesso</th><th>Não iniciadas</th><th>Em andamento</th><th>Devolvidas</th><th>Concluídas</th><th>Ações</th></tr>
<?php foreach(query("SELECT * FROM users ORDER BY name")->fetchAll() as $u): $counts=[]; foreach(query('SELECT status,COUNT(*) n FROM assignments WHERE user_id=? AND ended_at IS NULL GROUP BY status',[$u['id']])->fetchAll() as $r) $counts[$r['status']]=$r['n']; ?>
<tr><td><?=h($u['name'])?><br><?=h($u['email'])?></td><td><?=$u['active']?($u['password']?'Ativo':'Convite pendente'):'Suspenso'?></td><?php foreach(['new','draft','returned','done'] as $s) echo '<td>'.($counts[$s]??0).'</td>'; ?><td><?php if($u['role']==='reviewer'): ?><form method="post"><?php csrf('access'); hidden('target',$u['id']); ?><button><?=$u['active']?'Suspender acesso':'Reativar acesso'?></button></form><?php endif ?><?php if($u['active']): ?><form method="post"><?php csrf('reset'); hidden('target',$u['id']); ?><button>Emitir novo link</button></form><?php endif ?><a href="?view=distribution&amp;source=<?=$u['id']?>">Retomar atribuições</a></td></tr><?php endforeach ?></table></div>
<?php elseif ($view==='settings' && $admin): ?>
<h2>Critério de classificação</h2><form method="post"><?php csrf('settings') ?><label>Como identificar se o candidato é ideológico?<textarea name="help" required rows="5"><?=h(query("SELECT value FROM settings WHERE key='ideology_help'")->fetchColumn())?></textarea></label><button>Salvar critério</button></form><h2>Backup integral</h2><p>Inclui contas, candidaturas, avaliações, atribuições e histórico. Guarde em local privado, fora da hospedagem. O arquivo contém dados de acesso protegidos por hash.</p><form method="post"><?php csrf('backup') ?><button>Criar e baixar backup</button></form>
<?php elseif ($view==='history' && $admin): ?>
<h2>Histórico</h2><p>Registros preservados; 100 eventos por página.</p><?php foreach(query('SELECT e.*,u.name actor_name FROM events e LEFT JOIN users u ON u.id=e.actor ORDER BY e.id DESC LIMIT 100 OFFSET '.(($page-1)*100))->fetchAll() as $row): ?><details><summary>#<?=$row['id']?> · <?=h($row['created_at'])?> UTC · <?=h($row['kind'])?> · <?=h($row['actor_name']??'Importação')?></summary><pre><?=h($row['data'])?></pre></details><?php endforeach ?><p><a href="?view=history&amp;page=<?=max(1,$page-1)?>">Anterior</a> · <a href="?view=history&amp;page=<?=$page+1?>">Próxima</a></p>
<?php elseif ($view==='candidate_history'): ?>
<?php
    $cid=(int)($_GET['id']??0);
    $canRead=$admin || query('SELECT id FROM assignments WHERE candidate_id=? AND user_id=? AND ended_at IS NULL',[$cid,$user['id']])->fetchColumn();
?>
<h2>Histórico da candidatura</h2>
<?php if (!$canRead): ?><p>Acesso não autorizado.</p><?php else: ?>
<p>Versões anteriores são somente leitura e mantêm a autoria original.</p>
<?php foreach(query('SELECT e.*,u.name author,a.ended_at FROM evaluations e JOIN assignments a ON a.id=e.assignment_id JOIN users u ON u.id=e.actor WHERE a.candidate_id=? ORDER BY e.id DESC',[$cid])->fetchAll() as $item): ?>
<p><?=h($item['created_at'])?> UTC · <?=h($item['author'])?> · <?=h($item['status'])?> · Ideológico: <?=$item['ideological']===null?'Sem resposta':($item['ideological']?'Sim':'Não')?> · Perfil: <?=h($item['profile']??'Sem resposta')?> · versão <?=$item['version']?></p>
<?php endforeach ?>
<?php foreach(query("SELECT ev.* FROM events ev WHERE ev.kind='evaluation_returned' AND CAST(json_extract(ev.data,'$.assignment') AS INTEGER) IN (SELECT id FROM assignments WHERE candidate_id=?) ORDER BY ev.id DESC",[$cid])->fetchAll() as $item): ?><p>Devolução: <?=h($item['created_at'])?> UTC · <?=h(json_decode($item['data'],true)['reason'])?></p><?php endforeach ?>
<?php endif ?>
<?php else: ?>
<h2><?=$admin?'Candidaturas':'Minhas candidaturas'?></h2><form method="get"><div class="filters"><?php filterForm($f) ?><label>Situação<select name="status"><option value="">Todas</option><?php foreach(($admin?['available'=>'Disponível']:[])+$statuses as $k=>$v) echo '<option value="'.$k.'"'.($f['status']===$k?' selected':'').'>'.h($v).'</option>'; ?></select></label><?php if($admin): ?><label>Revisor<select name="source"><option value="">Todos</option><?php foreach(query("SELECT id,name FROM users ORDER BY name")->fetchAll() as $u) echo '<option value="'.$u['id'].'"'.($f['source']==$u['id']?' selected':'').'>'.h($u['name']).'</option>'; ?></select></label><?php endif ?><label>Lote<input type="number" name="batch" value="<?=h($f['batch'])?>" min="1"></label></div><button>Filtrar</button></form>
<?php $help=(string)query("SELECT value FROM settings WHERE key='ideology_help'")->fetchColumn(); ?><p><strong>Ideológico:</strong> <?=h($help?:'Critério ainda não definido. Você pode salvar rascunhos; a conclusão ficará disponível após configuração pelo administrador.')?></p><p>Perfil: −2 Esquerda · −1 Centro-esquerda · 0 Centro · +1 Centro-direita · +2 Direita. Os campos são independentes.</p>
<?php $total=(int)query('SELECT COUNT(*)'.$base.$where,$params)->fetchColumn(); $rows=query('SELECT c.*,a.id assignment,a.version,a.status,a.user_id,a.batch_id,u.name reviewer,e.ideological,e.profile'.$base.$where.' ORDER BY c.uf,c.name,c.id LIMIT 30 OFFSET '.(($page-1)*30),$params)->fetchAll(); ?><p><?=$total?> candidaturas neste filtro. Página <?=$page?>.<?php if($admin): ?> <a href="?<?=h(http_build_query(array_merge($_GET,['view'=>'export'])))?>">Exportar este recorte</a><?php endif ?></p>
<div class="table"><table class="candidates"><thead><tr><th>Ano</th><th>UF</th><th>Partido</th><th>Cargo</th><th>Nome</th><th>Ideológico?</th><th>Perfil</th><th>Situação / ações</th></tr></thead><tbody>
<?php foreach($rows as $row): $editable=(int)$row['user_id']===(int)$user['id'] && $row['status']!=='done'; $fid='eval'.$row['id']; ?>
<tr><td data-label="Ano"><?=$row['year']?></td><td data-label="UF"><?=h($row['uf'])?></td><td data-label="Partido"><?=h($row['party'])?></td><td data-label="Cargo"><?=h($row['office'])?></td><td data-label="Nome"><span title="<?=h($row['full_name'])?>"><?=h($row['name'])?></span><?php if($admin): ?><small><?=h($row['reviewer']??'Sem responsável')?> · lote <?=h($row['batch_id']??'—')?></small><?php endif ?></td>
<?php foreach(['ideological'=>[0=>'Não',1=>'Sim'],'profile'=>[-2=>'−2',-1=>'−1',0=>'0',1=>'+1',2=>'+2']] as $field=>$options): ?><td data-label="<?=$field==='profile'?'Perfil':'Ideológico'?>"><fieldset class="segments" <?=$editable?'':'disabled'?>><legend class="sr-only"><?=h($field==='profile'?'Perfil':'Ideológico')?> de <?=h($row['name'])?></legend><?php foreach($options as $value=>$label): ?><label><input form="<?=$fid?>" type="radio" name="<?=$field?>" value="<?=$value?>" <?=$row[$field]!==null && (int)$row[$field]===$value?'checked':''?>><span><?=h($label)?></span></label><?php endforeach ?></fieldset><?php if($editable): ?><label class="clear"><input form="<?=$fid?>" type="radio" name="<?=$field?>" value="" <?=$row[$field]===null?'checked':''?>> Sem resposta</label><?php elseif($row[$field]===null): ?><small>Sem resposta</small><?php endif ?></td><?php endforeach ?>
<td data-label="Ações"><a href="?view=candidate_history&amp;id=<?=$row['id']?>">Histórico</a><br><?=h($statuses[$row['status']]??'Disponível')?><?php if($editable): ?><form method="post" id="<?=$fid?>"><?php csrf('save'); hidden('assignment',$row['assignment']); hidden('version',$row['version']); ?><button name="status" value="draft">Salvar rascunho</button><button name="status" value="done" <?=$help===''?'disabled':''?>>Concluir</button></form><?php endif ?><?php if($admin && $row['status']==='done'): ?><form method="post"><?php csrf('reopen'); hidden('assignment',$row['assignment']); ?><label>Motivo da correção<input name="reason" required></label><button>Devolver para correção</button></form><?php endif ?></td></tr><?php endforeach ?>
</tbody></table></div><?php if(!$rows): ?><p>Nenhuma candidatura encontrada.</p><?php endif ?><p><?php if($page>1): ?><a href="?<?=h(http_build_query(array_merge($_GET,['page'=>$page-1])))?>">Anterior</a><?php endif ?> <?php if($page*30<$total): ?><a href="?<?=h(http_build_query(array_merge($_GET,['page'=>$page+1])))?>">Próxima</a><?php endif ?></p>
<?php endif ?></main></body></html>
