<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/core.php';
if (($argv[1]??'')==='worker') {
    $p=json_decode(file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR);
    try { executePlan(1,$p,$argv[3]); echo 'ok'; } catch (DomainException $e) { echo 'conflict'; }
    exit;
}
$dir=sys_get_temp_dir().'/candidatos-test-'.bin2hex(random_bytes(6));
putenv('CANDIDATOS_DATA_DIR='.$dir);
$checks=0;
function check(bool $condition,string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function rejected(callable $fn,string $message): void { try { $fn(); } catch (DomainException|PDOException $e) { check(true,$message); return; } throw new RuntimeException('Não rejeitou: '.$message); }
try {
    migrate(); migrate(); verifySchema();
    atomic(function () {
        foreach (['admin','reviewer','reviewer'] as $i=>$role) query('INSERT INTO users(email,name,password,role) VALUES(?,?,?,?)',["user$i@example.test","User $i",password_hash('a-strong-password',PASSWORD_DEFAULT),$role]);
        query("UPDATE settings SET value='Critério de teste' WHERE key='ideology_help'");
    });
    $csv=$dir.'/sample.csv';
    $header=['ANO_ELEICAO','CD_ELEICAO','SQ_CANDIDATO','SG_UF','SG_PARTIDO','DS_CARGO','NM_URNA_CANDIDATO','NM_CANDIDATO'];
    $fp=fopen($csv,'wb'); fputcsv($fp,$header,';','"','');
    for($i=1;$i<=120;$i++) fputcsv($fp,[2026,'10',(string)$i,'SP','ABC','DEPUTADO FEDERAL',sprintf('Pessoa %03d',$i),'Pessoa Completa '.$i],';','"','');
    fputcsv($fp,[2026,'10','1','SP','ABC','DEPUTADO FEDERAL','Pessoa 001','Pessoa Completa 1'],';','"',''); fclose($fp);
    $r=importCsv($csv); check($r['unique']===120 && $r['rows']===121,'Deduplica turnos');
    $spec=['year'=>'2026','office'=>'DEPUTADO FEDERAL','uf'=>'SP','users'=>[2,3],'quantity'=>50];
    $p=plan($spec); check(count($p['map'])===100,'Prévia de 100 candidatos');
    $batch=executePlan(1,$p,'fixed-1'); check(workload(2)===50 && workload(3)===50,'Lote fixo');
    check(executePlan(1,$p,'fixed-1')===$batch,'Repetição idempotente');
    check((int)query('SELECT COUNT(*) FROM assignments')->fetchColumn()===100,'Sem atribuições duplicadas');
    check(plan($spec)['eligible']===20,'Já atribuídos não elegíveis');
    $a=query('SELECT * FROM assignments WHERE user_id=2 LIMIT 1')->fetch();
    saveEvaluation(2,(int)$a['id'],0,null,null,'draft');
    $e=query('SELECT * FROM evaluations WHERE assignment_id=?',[$a['id']])->fetch();
    check($e['ideological']===null && $e['profile']===null,'Nulos não viram zero');
    rejected(fn()=>saveEvaluation(2,(int)$a['id'],0,1,0,'done'),'Conflito de aba');
    rejected(fn()=>saveEvaluation(3,(int)$a['id'],1,1,0,'done'),'Outro revisor');
    rejected(fn()=>saveEvaluation(2,(int)$a['id'],1,null,0,'done'),'Conclusão incompleta');
    saveEvaluation(2,(int)$a['id'],1,0,0,'done');
    rejected(fn()=>saveEvaluation(2,(int)$a['id'],2,1,2,'draft'),'Conclusão bloqueada');
    check(importCsv($csv)['changed']===0,'Reimportação idempotente');
    check((int)query('SELECT COUNT(*) FROM evaluations')->fetchColumn()===2,'Reimportação mantém avaliações');
    $badCsv=$dir.'/bad.csv';
    file_put_contents($badCsv,str_replace('Pessoa 001','Nome alterado',file_get_contents($csv))."linha;invalida\n");
    rejected(fn()=>importCsv($badCsv),'Importação inválida rejeitada');
    check(query('SELECT name FROM candidates WHERE source_id=?',['1'])->fetchColumn()==='Pessoa 001','Falha na importação reverte mudanças anteriores');
    reopen(1,(int)$a['id'],'Verifique a classificação');
    check(query('SELECT status FROM assignments WHERE id=?',[$a['id']])->fetchColumn()==='returned','Devolução');
    saveEvaluation(2,(int)$a['id'],3,1,1,'draft');
    query('UPDATE users SET active=0 WHERE id=2');
    check(workload(2)===50,'Suspender mantém carga');
    rejected(fn()=>saveEvaluation(2,(int)$a['id'],4,1,1,'done'),'Conta suspensa');
    $ret=plan(['kind'=>'reclaim','source'=>'2','status'=>'draft','users'=>[3],'reason'=>'Redistribuição']);
    check(count($ret['map'])===1,'Retomada por situação'); executePlan(1,$ret,'transfer-1');
    query('UPDATE users SET active=1 WHERE id=2');
    rejected(fn()=>saveEvaluation(2,(int)$a['id'],4,1,1,'draft'),'Antigo responsável bloqueado');
    check((int)query('SELECT COUNT(*) FROM evaluations WHERE assignment_id=?',[$a['id']])->fetchColumn()===3,'Histórico de rascunhos preservado');
    $new=query('SELECT * FROM assignments WHERE candidate_id=? AND ended_at IS NULL',[$a['candidate_id']])->fetch();
    check((int)$new['user_id']===3 && $new['status']==='new','Novo responsável começa sem valores herdados');
    $fill=plan(array_merge($spec,['mode'=>'fill','quantity'=>55]));
    check($fill['received'][2]===6 && $fill['received'][3]===4,'Completar carga total'); executePlan(1,$fill,'fill');
    $return=plan(['kind'=>'reclaim','source'=>'2','reason'=>'Voltar à fila']); executePlan(1,$return,'return');
    check(workload(2)===0 && (int)query('SELECT active FROM users WHERE id=2')->fetchColumn()===1,'Retomar mantém acesso');
    $stale=plan($spec); $small=plan(array_merge($spec,['quantity'=>1])); executePlan(1,$small,'small');
    rejected(fn()=>executePlan(1,$stale,'stale'),'Prévia desatualizada');
    $before=(int)query('SELECT COUNT(*) FROM assignments')->fetchColumn();
    try { atomic(function () { query("INSERT INTO batches(actor,spec) VALUES(1,'{}')"); throw new DomainException('interromper'); }); } catch (DomainException $e) {}
    check((int)query('SELECT COUNT(*) FROM assignments')->fetchColumn()===$before,'Rollback');
    rejected(fn()=>query('DELETE FROM evaluations'),'Histórico imutável');
    rejected(fn()=>query("UPDATE events SET kind='alterado'"),'Eventos imutáveis');
    $token=atomic(fn()=>issueToken(2,'reset')); redeemToken($token,'new-strong-password');
    rejected(fn()=>redeemToken($token,'new-strong-password'),'Token de uso único');
    $p=plan(array_merge($spec,['quantity'=>3])); $planFile=$dir.'/plan.json'; file_put_contents($planFile,jsonValue($p));
    $workers=[];
    for($i=0;$i<2;$i++) {
        $proc=proc_open([PHP_BINARY,__FILE__,'worker',$planFile,'concurrent-'.$i],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $workers[]=[$proc,$pipes];
    }
    $results=[];
    foreach($workers as [$proc,$pipes]) { $results[]=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); check(proc_close($proc)===0,'Processo concorrente: '.$err); }
    sort($results); check($results===['conflict','ok'],'Distribuição concorrente serializada');
    $own=plan(array_merge($spec,['users'=>[1],'quantity'=>2])); executePlan(1,$own,'admin-own');
    check(workload(1)===2,'Administrador recebe lote próprio');
    $ownAssignment=query('SELECT * FROM assignments WHERE user_id=1 AND ended_at IS NULL LIMIT 1')->fetch();
    saveEvaluation(1,(int)$ownAssignment['id'],0,1,-1,'done');
    check(workload(1)===1,'Administrador conclui a própria avaliação');
    $takeBack=plan(['kind'=>'reclaim','source'=>'1','users'=>[2],'reason'=>'Transferir trabalho do administrador']);
    check(count($takeBack['map'])===1,'Retomada do administrador exclui concluídas'); executePlan(1,$takeBack,'admin-transfer');
    check(workload(1)===0 && activeUser(1,true)['role']==='admin','Redistribuição preserva acesso administrativo');
    $file=backup(); $restored=new PDO('sqlite:'.$file);
    check($restored->query('PRAGMA integrity_check')->fetchColumn()==='ok','Backup íntegro');
    foreach(['users','candidates','assignments','evaluations','events'] as $table) check((int)$restored->query("SELECT COUNT(*) FROM $table")->fetchColumn()===(int)query("SELECT COUNT(*) FROM $table")->fetchColumn(),'Restauração: '.$table);
    check(query('PRAGMA foreign_key_check')->fetchAll()===[],'Chaves estrangeiras');
    echo "OK: $checks verificações. Banco de teste preservado em $dir\n";
} catch(Throwable $e) { fwrite(STDERR,(string)$e."\nBanco de teste: $dir\n"); exit(1); }
