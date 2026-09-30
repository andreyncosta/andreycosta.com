<?php
declare(strict_types=1);

// Runtime state is never stored alongside deployed source code.
function dataDir(): string {
    $path = getenv('CANDIDATOS_DATA_DIR') ?: dirname(__DIR__, 2) . '/candidatos-private';
    if (!is_dir($path) && !mkdir($path, 0700, true)) throw new RuntimeException('Não foi possível criar a pasta privada.');
    $path = realpath($path);
    $public = str_replace('\\', '/', realpath(dirname(__DIR__)));
    $normalized = str_replace('\\', '/', $path);
    if (str_starts_with(strtolower($normalized . '/'), strtolower($public . '/'))) {
        throw new RuntimeException('A pasta de dados deve ficar fora da pasta pública.');
    }
    return $path;
}
function db(): PDO {
    static $db;
    if (!$db) {
        $file = dataDir() . '/candidatos.sqlite';
        if (!is_file($file) && PHP_SAPI !== 'cli') throw new RuntimeException('Aplicação ainda não inicializada.');
        $db = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=15000; PRAGMA journal_mode=WAL; PRAGMA synchronous=FULL;');
        @chmod($file, 0600);
    }
    return $db;
}
function query(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt;
}
function atomic(callable $fn): mixed {
    db()->exec('BEGIN IMMEDIATE');
    try { $result = $fn(); db()->exec('COMMIT'); return $result; }
    catch (Throwable $e) { db()->exec('ROLLBACK'); throw $e; }
}
function jsonValue(mixed $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
function event(?int $actor, string $kind, array $data): void {
    query('INSERT INTO events(actor,kind,data) VALUES(?,?,?)', [$actor,$kind,jsonValue($data)]);
}
function backup(): string {
    $folder = dataDir() . '/backups';
    if (!is_dir($folder)) mkdir($folder, 0700, true);
    $file = $folder . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.sqlite';
    db()->exec('VACUUM INTO ' . db()->quote($file));
    @chmod($file, 0600);
    $check = new PDO('sqlite:' . $file);
    if ($check->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new RuntimeException('Backup inválido.');
    file_put_contents($file . '.sha256', hash_file('sha256', $file));
    return $file;
}
function migrate(): void {
    $lock = fopen(dataDir() . '/migration.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Falha no bloqueio de migração.');
    try {
        $hasSchema = (bool)query("SELECT name FROM sqlite_master WHERE name='schema_migrations'")->fetchColumn();
        if (!$hasSchema) db()->exec('CREATE TABLE schema_migrations(version TEXT PRIMARY KEY, checksum TEXT NOT NULL, applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        foreach (glob(__DIR__ . '/migrations/*.sql') as $file) {
            $version = basename($file); $hash = hash_file('sha256', $file);
            $old = query('SELECT checksum FROM schema_migrations WHERE version=?', [$version])->fetchColumn();
            if ($old) {
                if (!hash_equals($old, $hash)) throw new RuntimeException("Migração já aplicada foi alterada: $version");
                continue;
            }
            backup();
            atomic(function () use ($file,$version,$hash) {
                db()->exec(file_get_contents($file));
                query('INSERT INTO schema_migrations(version,checksum) VALUES(?,?)', [$version,$hash]);
            });
        }
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
function verifySchema(): void {
    $applied=query('SELECT version,checksum FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
    $files=glob(__DIR__.'/migrations/*.sql');
    if (count($applied)!==count($files)) throw new RuntimeException('Versão do banco incompatível. Execute/verifique as migrações antes de servir a aplicação.');
    foreach ($files as $file) {
        if (!isset($applied[basename($file)]) || !hash_equals($applied[basename($file)],hash_file('sha256',$file))) throw new RuntimeException('Migrações incompatíveis com o banco.');
    }
}
function activeUser(int $id, bool $admin = false): array {
    $user = query('SELECT * FROM users WHERE id=? AND active=1', [$id])->fetch();
    if (!$user || ($admin && $user['role'] !== 'admin')) throw new DomainException('Acesso não autorizado.');
    return $user;
}
function issueToken(int $uid, string $kind): string {
    $token = bin2hex(random_bytes(32));
    query('UPDATE tokens SET used_at=CURRENT_TIMESTAMP WHERE user_id=? AND used_at IS NULL', [$uid]);
    query('INSERT INTO tokens(hash,user_id,expires,kind) VALUES(?,?,?,?)', [hash('sha256',$token),$uid,time()+86400*2,$kind]);
    return $token;
}
function redeemToken(string $token, string $password): void {
    if (strlen($password)<12 || strlen($password)>72) throw new DomainException('Use uma senha entre 12 e 72 bytes.');
    atomic(function () use ($token,$password) {
        $t = query('SELECT * FROM tokens WHERE hash=? AND used_at IS NULL AND expires>?', [hash('sha256',$token),time()])->fetch();
        if (!$t) throw new DomainException('Link inválido, utilizado ou expirado.');
        activeUser((int)$t['user_id']);
        query('UPDATE users SET password=?,auth_version=auth_version+1 WHERE id=?', [password_hash($password,PASSWORD_DEFAULT),$t['user_id']]);
        query('UPDATE tokens SET used_at=CURRENT_TIMESTAMP WHERE user_id=? AND used_at IS NULL', [$t['user_id']]);
        event((int)$t['user_id'],'account_activated_or_password_reset',['kind'=>$t['kind']]);
    });
}
function throttle(string $key, int $limit = 10): void {
    $allowed = atomic(function () use ($key,$limit) {
        query('DELETE FROM attempts WHERE expires<?', [time()]);
        query('INSERT INTO attempts(key,hits,expires) VALUES(?,1,?) ON CONFLICT(key) DO UPDATE SET hits=hits+1', [hash('sha256',$key),time()+900]);
        return query('SELECT hits FROM attempts WHERE key=?',[hash('sha256',$key)])->fetchColumn() <= $limit;
    });
    if (!$allowed) throw new DomainException('Muitas tentativas. Aguarde 15 minutos.');
}
function filters(array $input): array {
    $uf = $input['uf'] ?? [];
    if (!is_array($uf)) $uf = preg_split('/[\s,;]+/', strtoupper(trim((string)$uf)), -1, PREG_SPLIT_NO_EMPTY);
    $result = [];
    foreach (['year','office','party','name','status','source','batch'] as $key) $result[$key] = trim((string)($input[$key] ?? ''));
    $result['uf'] = array_values(array_unique(array_map('strtoupper',$uf)));
    return $result;
}
function filterSql(array $f, array &$params): string {
    $sql = '';
    foreach (['year','office','party'] as $key) if ($f[$key] !== '') { $sql .= " AND c.$key=?"; $params[]=$f[$key]; }
    if ($f['uf']) { $sql .= ' AND c.uf IN (' . implode(',',array_fill(0,count($f['uf']),'?')) . ')'; array_push($params,...$f['uf']); }
    if ($f['name'] !== '') { $sql .= ' AND (c.name LIKE ? OR c.full_name LIKE ?)'; $params[]='%'.$f['name'].'%'; $params[]='%'.$f['name'].'%'; }
    return $sql;
}
function workload(int $uid): int {
    return (int)query("SELECT COUNT(*) FROM assignments WHERE user_id=? AND ended_at IS NULL AND status!='done'",[$uid])->fetchColumn();
}
function plan(array $input): array {
    $f = filters($input); $kind = (string)($input['kind'] ?? 'assign');
    if (!in_array($kind,['assign','reclaim'],true)) throw new DomainException('Operação inválida.');
    $mode = (string)($input['mode'] ?? 'fixed');
    if (!in_array($mode,['fixed','fill'],true)) throw new DomainException('Modo inválido.');
    $qty = filter_var($input['quantity'] ?? 50,FILTER_VALIDATE_INT);
    if (!$qty || $qty<1 || $qty>10000) throw new DomainException('Quantidade deve estar entre 1 e 10000.');
    $users = array_values(array_unique(array_map('intval',(array)($input['users']??[])))); sort($users);
    if ($kind==='assign' && (!$users || !$f['year'] || !$f['office'] || !$f['uf'])) throw new DomainException('Selecione ano, cargo, UF e revisores.');
    $loads=[]; $names=[];
    foreach ($users as $id) {
        $u = activeUser($id);
        if ($u['role']!=='reviewer' || !$u['password']) throw new DomainException('Selecione revisores ativos com convite aceito.');
        if ($kind==='reclaim' && $id===(int)$f['source']) throw new DomainException('O destinatário deve ser diferente da origem.');
        $loads[$id]=workload($id); $names[$id]=$u['name'];
    }
    $params=[]; $where=filterSql($f,$params);
    if ($kind==='assign') {
        $sql="SELECT c.id, NULL assignment_id, 0 version, 'available' status FROM candidates c LEFT JOIN assignments a ON a.candidate_id=c.id AND a.ended_at IS NULL WHERE a.id IS NULL $where";
    } else {
        if (!(int)$f['source'] || trim((string)($input['reason']??''))==='') throw new DomainException('Informe revisor de origem e motivo.');
        $sql="SELECT c.id,a.id assignment_id,a.version,a.status FROM candidates c JOIN assignments a ON a.candidate_id=c.id AND a.ended_at IS NULL WHERE a.status!='done' $where AND a.user_id=?";
        $params[]=(int)$f['source'];
        if ($f['status']!=='') { $sql.=' AND a.status=?'; $params[]=$f['status']; }
        if ($f['batch']!=='') { $sql.=' AND a.batch_id=?'; $params[]=(int)$f['batch']; }
    }
    $rows=query($sql.' ORDER BY c.uf,c.name,c.id',$params)->fetchAll();
    $map=[]; $received=array_fill_keys($users,0); $sim=$loads; $states=[];
    foreach ($rows as $row) {
        $dest=null;
        if ($users) {
            $eligible=array_filter($users,fn($id)=>$kind==='reclaim' || ($mode==='fixed' ? $received[$id]<$qty : $sim[$id]<$qty));
            if (!$eligible) break;
            usort($eligible,fn($a,$b)=>(($kind==='assign' && $mode==='fixed' ? $received[$a]<=>$received[$b] : $sim[$a]<=>$sim[$b]) ?: $a<=>$b));
            $dest=$eligible[0]; $received[$dest]++; $sim[$dest]++;
        }
        $map[]=['candidate'=>(int)$row['id'],'assignment'=>$row['assignment_id'],'version'=>(int)$row['version'],'to'=>$dest];
        $states[$row['status']]=($states[$row['status']]??0)+1;
    }
    $spec=['kind'=>$kind,'mode'=>$mode,'quantity'=>$qty,'users'=>$users,'filters'=>$f,'reason'=>trim((string)($input['reason']??''))];
    $result=['spec'=>$spec,'loads'=>$loads,'names'=>$names,'received'=>$received,'eligible'=>count($rows),'remaining'=>count($rows)-count($map),'states'=>$states,'map'=>$map];
    $result['hash']=hash('sha256',jsonValue($result)); return $result;
}
function executePlan(int $actor, array $preview, string $operation): int {
    return atomic(function () use ($actor,$preview,$operation) {
        activeUser($actor,true);
        $old=query('SELECT * FROM operations WHERE id=?',[$operation])->fetch();
        if ($old) { if ((int)$old['actor']!==$actor) throw new DomainException('Operação inválida.'); return (int)$old['result']; }
        $s=$preview['spec']; $now=plan(array_merge($s['filters'],$s));
        if (!hash_equals($preview['hash'],$now['hash'])) throw new DomainException('A distribuição mudou. Gere uma nova prévia.');
        if (!$now['map']) throw new DomainException('Nenhuma candidatura elegível.');
        query('INSERT INTO batches(actor,spec) VALUES(?,?)',[$actor,jsonValue($s)]); $batch=(int)db()->lastInsertId();
        foreach ($now['map'] as $item) {
            if ($item['assignment']) query('UPDATE assignments SET ended_at=CURRENT_TIMESTAMP,version=version+1 WHERE id=?',[$item['assignment']]);
            if ($item['to']) query('INSERT INTO assignments(candidate_id,user_id,batch_id) VALUES(?,?,?)',[$item['candidate'],$item['to'],$batch]);
            event($actor,'assignment_changed',['batch'=>$batch]+$item);
        }
        query('INSERT INTO operations(id,actor,result) VALUES(?,?,?)',[$operation,$actor,(string)$batch]); return $batch;
    });
}
function saveEvaluation(int $actor,int $assignment,int $version,?int $ideological,?int $profile,string $status): void {
    if (!in_array($status,['draft','done'],true) || ($ideological!==null && !in_array($ideological,[0,1],true)) || ($profile!==null && ($profile < -2 || $profile > 2))) throw new DomainException('Marcadores inválidos.');
    if ($status==='done' && ($ideological===null || $profile===null)) throw new DomainException('Preencha os dois marcadores para concluir.');
    atomic(function () use ($actor,$assignment,$version,$ideological,$profile,$status) {
        activeUser($actor);
        $a=query('SELECT * FROM assignments WHERE id=?',[$assignment])->fetch();
        if (!$a || (int)$a['user_id']!==$actor || $a['ended_at']!==null) throw new DomainException('Esta candidatura não está mais sob sua responsabilidade.');
        if ((int)$a['version']!==$version) throw new DomainException('A avaliação mudou em outra tela. Recarregue antes de salvar.');
        if ($a['status']==='done') throw new DomainException('Avaliação concluída. Solicite devolução ao administrador.');
        if ($status==='done' && trim((string)query("SELECT value FROM settings WHERE key='ideology_help'")->fetchColumn())==='') throw new DomainException('O administrador precisa definir o critério de Ideológico antes das conclusões.');
        query('INSERT INTO evaluations(assignment_id,actor,ideological,profile,status,version) VALUES(?,?,?,?,?,?)',[$assignment,$actor,$ideological,$profile,$status,$version+1]);
        query('UPDATE assignments SET status=?,version=version+1 WHERE id=?',[$status,$assignment]);
        event($actor,'evaluation_saved',['assignment'=>$assignment,'version'=>$version+1,'status'=>$status]);
    });
}
function reopen(int $actor,int $assignment,string $reason): void {
    if (trim($reason)==='') throw new DomainException('Informe o motivo da correção.');
    atomic(function () use ($actor,$assignment,$reason) {
        activeUser($actor,true);
        if (!query("UPDATE assignments SET status='returned',version=version+1 WHERE id=? AND ended_at IS NULL AND status='done'",[$assignment])->rowCount()) throw new DomainException('Avaliação não está concluída ou foi alterada.');
        event($actor,'evaluation_returned',['assignment'=>$assignment,'reason'=>$reason]);
    });
}
function importCsv(string $file): array {
    if (!is_file($file)) throw new DomainException('CSV não encontrado.');
    $handle=fopen($file,'rb'); $header=fgetcsv($handle,0,';','"','');
    if (!$header) throw new DomainException('CSV vazio.');
    $header[0]=ltrim($header[0],"\xEF\xBB\xBF");
    $fields=['ANO_ELEICAO','CD_ELEICAO','SQ_CANDIDATO','SG_UF','SG_PARTIDO','DS_CARGO','NM_URNA_CANDIDATO','NM_CANDIDATO'];
    foreach ($fields as $field) if (!in_array($field,$header,true)) throw new DomainException("Coluna ausente: $field");
    $snapshot=backup();
    try { return atomic(function () use ($handle,$header,$fields,$file,$snapshot) {
        $count=0; $changed=0; $seen=[];
        while (($row=fgetcsv($handle,0,';','"',''))!==false) {
            if ($row===[null]) continue;
            if (count($row)!==count($header)) throw new DomainException('Linha inválida: '.($count+2));
            $raw=array_combine($header,$row); $values=[];
            foreach ($fields as $field) {
                $text=trim($raw[$field]);
                if (!preg_match('//u',$text)) $text=iconv('Windows-1252','UTF-8',$text);
                $values[]=$text;
            }
            if (!ctype_digit($values[0]) || !ctype_digit($values[1]) || !ctype_digit($values[2])) throw new DomainException('Identidade inválida na linha '.($count+2));
            if ($values[6]==='' || str_starts_with($values[6],'#')) $values[6]=$values[7];
            $key=implode(':',array_slice($values,0,3)); $count++;
            if (isset($seen[$key])) {
                if ($seen[$key]!==$values) throw new DomainException("Duplicata com dados diferentes: $key. Revise a fonte.");
                continue;
            }
            $seen[$key]=$values;
            $old=query('SELECT * FROM candidates WHERE year=? AND election=? AND source_id=?',array_slice($values,0,3))->fetch();
            if ($old && [$old['uf'],$old['party'],$old['office'],$old['name'],$old['full_name']]===array_slice($values,3)) continue;
            query('INSERT INTO candidates(year,election,source_id,uf,party,office,name,full_name) VALUES(?,?,?,?,?,?,?,?) ON CONFLICT(year,election,source_id) DO UPDATE SET uf=excluded.uf,party=excluded.party,office=excluded.office,name=excluded.name,full_name=excluded.full_name,updated_at=CURRENT_TIMESTAMP',$values);
            if ($old) event(null,'candidate_source_updated',['previous'=>$old,'new'=>$values]);
            $changed++;
        }
        $result=['rows'=>$count,'unique'=>count($seen),'changed'=>$changed,'sha256'=>hash_file('sha256',$file),'file'=>basename($file),'backup'=>basename($snapshot)];
        event(null,'csv_imported',$result); return $result;
    }); } finally { fclose($handle); }
}
