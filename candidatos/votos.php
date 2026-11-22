<?php
declare(strict_types=1);
require __DIR__.'/core.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
function ah(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function an(mixed $v): string { return $v===null?'Indisponível':number_format((int)$v,0,',','.'); }
function atime(?string $v): string { return $v?(new DateTimeImmutable($v))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i:s'):'Indisponível'; }
function aq(PDO $db,string $sql,array $params=[]): PDOStatement { $s=$db->prepare($sql); $s->execute($params); return $s; }
function alink(array $params): string { return '?'.http_build_query($params); }
$offices=['1'=>'Presidente','2'=>'Vice-presidente','3'=>'Governador','4'=>'Vice-governador','5'=>'Senador','6'=>'Deputado federal','7'=>'Deputado estadual','8'=>'Deputado distrital','9'=>'1º suplente','10'=>'2º suplente','25'=>'Conselheiro distrital'];
$labels=['final'=>'Final','parcial'=>'Parcial','aguardando_apuracao'=>'Aguardando apuração','ausente_na_divulgacao'=>'Ausente na divulgação','nao_aplicavel_vice_suplente'=>'Vice / suplente (sem voto separado)','apurado_fonte_historica'=>'Fonte histórica','apurado_fonte_secao'=>'Complemento por seção','ausente_na_fonte'=>'Ausente na fonte'];
$error=''; $rows=[]; $detail=null; $territories=[]; $total=0; $summary=[]; $capture=[];
try {
    $path=dataDir().'/analise.sqlite';
    if (!is_file($path)) throw new RuntimeException('A base de análise ainda não foi publicada.');
    // SQLite read-only mode also protects against accidental writes by future queries.
    $analysis=new PDO('sqlite:file:'.str_replace('\\','/',$path).'?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $analysis->exec('PRAGMA query_only=ON; PRAGMA busy_timeout=5000');
    $year=in_array($_GET['year']??'2026',['2022','2026'],true)?(int)($_GET['year']??2026):2026;
    $f=['year'=>$year,'uf'=>trim((string)($_GET['uf']??'')),'office'=>trim((string)($_GET['office']??'')), 'party'=>trim((string)($_GET['party']??'')),'q'=>substr(trim((string)($_GET['q']??'')),0,150)];
    $page=max(1,min(100000,(int)($_GET['page']??1)));
    $where=' WHERE year=?'; $params=[$year];
    foreach (['uf','office','party'] as $key) if($f[$key]!=='') { $where.=" AND $key=?"; $params[]=$f[$key]; }
    if($f['q']!=='') { $where.=' AND (name LIKE ? ESCAPE \'\\\' OR source_id=?)'; $params[]='%'.strtr($f['q'],['\\'=>'\\\\','%'=>'\\%','_'=>'\\_']).'%'; $params[]=$f['q']; }
    $total=(int)aq($analysis,'SELECT COUNT(*) FROM candidates'.$where,$params)->fetchColumn();
    $summary=aq($analysis,'SELECT status,COUNT(*) quantity FROM candidates'.$where.' GROUP BY status',$params)->fetchAll();
    $capture=json_decode((string)$analysis->query("SELECT value FROM metadata WHERE key='capture'")->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
    if(($_GET['export']??'')==='csv') {
        header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="candidaturas-votos-'.$year.'.csv"');
        $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
        $cols=['year','election','source_id','office','uf','name','party','votes','valid_votes','status','metric','generated_at','url','source_sha256'];
        fputcsv($out,$cols,';','"','');
        foreach(aq($analysis,'SELECT '.implode(',',$cols).' FROM candidates'.$where.' ORDER BY uf,office,votes DESC,name',$params) as $r) {
            foreach($r as &$value) if(is_string($value) && preg_match('/^[=+@\-\t\r\n]/u',$value)) $value="'".$value;
            unset($value); fputcsv($out,$r,';','"','');
        }
        fclose($out); exit;
    }
    $rows=aq($analysis,'SELECT * FROM candidates'.$where.' ORDER BY votes DESC,name,id LIMIT 50 OFFSET '.(($page-1)*50),$params)->fetchAll();
    $ufs=$analysis->query('SELECT DISTINCT uf FROM candidates ORDER BY uf')->fetchAll(PDO::FETCH_COLUMN);
    $parties=aq($analysis,'SELECT DISTINCT party FROM candidates WHERE year=? ORDER BY party',[$year])->fetchAll(PDO::FETCH_COLUMN);
    if(isset($_GET['id'])) {
        $detail=aq($analysis,'SELECT * FROM candidates WHERE id=?',[(int)$_GET['id']])->fetch();
        if($detail) {
            $level=($_GET['level']??'municipio')==='zona'?'zona':'municipio';
            $tp=max(1,min(100000,(int)($_GET['tp']??1)));
            if($level==='zona') {
                $tsql="SELECT * FROM territorial WHERE candidate_id=? AND level='zona' ORDER BY votes DESC,uf,municipality,zone";
                $countSql="SELECT COUNT(*) FROM territorial WHERE candidate_id=? AND level='zona'";
            } elseif((int)$detail['year']===2022) {
                $tsql="SELECT uf,municipality,name,NULL zone,ibge,SUM(votes) votes,SUM(valid_votes) valid_votes,MAX(generated_at) generated_at,MIN(progress) progress,MIN(final_totalization) final_totalization,url FROM territorial WHERE candidate_id=? AND level='zona' GROUP BY uf,municipality ORDER BY votes DESC,uf,municipality";
                $countSql="SELECT COUNT(*) FROM (SELECT 1 FROM territorial WHERE candidate_id=? AND level='zona' GROUP BY uf,municipality)";
            } else {
                $tsql="SELECT * FROM territorial WHERE candidate_id=? AND level IN ('municipio','mu') ORDER BY votes DESC,uf,municipality";
                $countSql="SELECT COUNT(*) FROM territorial WHERE candidate_id=? AND level IN ('municipio','mu')";
            }
            $territories=aq($analysis,$tsql.' LIMIT 100 OFFSET '.(($tp-1)*100),[$detail['id']])->fetchAll();
            $territoryCount=(int)aq($analysis,$countSql,[$detail['id']])->fetchColumn();
        }
    }
} catch(Throwable $e) {
    error_log((string)$e); http_response_code(503); $error='Análise indisponível. Consulte o administrador.';
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Análise eleitoral · andreycosta.com</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="editorial.css"></head>
<body class="workspace"><header><a class="brand" href="/">ac. andreycosta.com</a><div class="header-title"><span class="eyebrow">Observatório eleitoral</span><h1>Análise de candidaturas e votos.</h1></div><nav><a href="./">Classificação de candidatos</a><a href="analise.php" aria-current="page">Análise</a></nav></header><main id="content">
<?php if($error): ?><p role="alert"><?=ah($error)?></p><?php else: ?>
<p>Primeiro turno de 2022 e 2026. Totais por candidatura e votação por município e zona, com fonte e geração preservadas.</p>
<p>Captura 2026: <?=ah(atime($capture['capture_started']??null))?> a <?=ah(atime($capture['capture_finished']??null))?> (horário de Brasília). Cada arquivo tem sua própria geração; resultados parciais podem mudar.</p>
<form method="get" class="filters"><label>Ano<select name="year"><?php foreach([2022,2026] as $y): ?><option value="<?=$y?>" <?=$year===$y?'selected':''?>><?=$y?></option><?php endforeach ?></select></label>
<label>UF<select name="uf"><option value="">Todas</option><?php foreach($ufs as $v): ?><option value="<?=ah($v)?>" <?=$f['uf']===$v?'selected':''?>><?=ah($v)?></option><?php endforeach ?></select></label>
<label>Cargo<select name="office"><option value="">Todos</option><?php foreach($offices as $v=>$label): ?><option value="<?=ah($v)?>" <?=$f['office']===(string)$v?'selected':''?>><?=ah($label)?></option><?php endforeach ?></select></label>
<label>Partido<select name="party"><option value="">Todos</option><?php foreach($parties as $v): ?><option value="<?=ah($v)?>" <?=$f['party']===$v?'selected':''?>><?=ah($v)?></option><?php endforeach ?></select></label>
<label>Nome ou SQ_CANDIDATO<input name="q" value="<?=ah($f['q'])?>" maxlength="150"></label><button>Filtrar</button></form>
<p><?=an($total)?> candidaturas · <a href="<?=ah(alink($f+['export'=>'csv']))?>">Exportar este recorte (CSV)</a></p>
<ul><?php foreach($summary as $r): ?><li><?=ah($labels[$r['status']]??$r['status'])?>: <?=an($r['quantity'])?></li><?php endforeach ?></ul>
<p>Votos de cargos distintos não representam um total de eleitores. Em 2026 há duas vagas ao Senado. Ausência é indisponível; vices e suplentes não têm voto nominal separado. Os campos de 2022 e 2026 usam medidas diferentes, identificadas na tabela.</p>
<?php if($detail): ?><section><h2><?=ah($detail['name'])?> · <?=ah($detail['year'])?></h2><p><?=ah($offices[$detail['office']]??$detail['office'])?> · <?=ah($detail['uf'])?> · <?=ah($detail['party'])?> · <?=an($detail['votes'])?> votos · <?=ah($labels[$detail['status']]??$detail['status'])?></p>
<p>Chave: <?=ah($detail['year'].' / '.$detail['election'].' / '.$detail['source_id'])?>. Medida: <?=ah($detail['metric'])?>. Geração: <?=ah($detail['generated_at']??'Não informada')?>. <?php if($detail['url']): ?><a href="<?=ah($detail['url'])?>" rel="noreferrer">Fonte TSE</a><?php endif ?></p>
<p><a href="<?=ah(alink($f+['id'=>$detail['id'],'level'=>'municipio']))?>">Por município</a> · <a href="<?=ah(alink($f+['id'=>$detail['id'],'level'=>'zona']))?>">Por zona</a> · <?=an($territoryCount)?> recortes</p>
<p>Não somar níveis sobrepostos. Em 2022 os municípios são agregados das zonas do arquivo histórico; a geração pode diferir do complemento por seção. Zona identifica município × zona e ano. Indicadores socioeconômicos ainda não integram esta análise.</p>
<div class="table"><table><thead><tr><th>UF / município TSE</th><th>Município</th><th>Zona</th><th>IBGE</th><th>Votos</th><th>Válidos (2022)</th><th>Geração / fonte</th></tr></thead><tbody><?php foreach($territories as $r): ?><tr><td><?=ah($r['uf'].' / '.$r['municipality'])?></td><td><?=ah($r['name'])?></td><td><?=ah($r['zone']??'—')?></td><td><?=ah($r['ibge']??'—')?></td><td><?=an($r['votes'])?></td><td><?=an($r['valid_votes'])?></td><td><?=ah($r['generated_at'])?> · <?=ah($r['progress'])?> / <?=ah($r['final_totalization'])?> <a href="<?=ah($r['url'])?>" rel="noreferrer">TSE</a></td></tr><?php endforeach ?></tbody></table></div>
<?php if(!$territories): ?><p>Detalhamento indisponível nesta fonte.</p><?php endif ?><p><?php if($tp>1): ?><a href="<?=ah(alink($f+['id'=>$detail['id'],'level'=>$level,'tp'=>$tp-1]))?>">Anterior</a><?php endif ?> <?php if($tp*100<$territoryCount): ?><a href="<?=ah(alink($f+['id'=>$detail['id'],'level'=>$level,'tp'=>$tp+1]))?>">Próxima</a><?php endif ?></p></section><?php endif ?>
<h2>Candidaturas por votação</h2><div class="table"><table><thead><tr><th>Candidatura</th><th>UF</th><th>Cargo</th><th>Partido</th><th>Votos</th><th>Válidos (2022)</th><th>Situação</th><th>Medida</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><a href="<?=ah(alink($f+['id'=>$r['id'],'page'=>$page]))?>"><?=ah($r['name'])?></a></td><td><?=ah($r['uf'])?></td><td><?=ah($offices[$r['office']]??$r['office'])?></td><td><?=ah($r['party'])?></td><td><?=an($r['votes'])?></td><td><?=an($r['valid_votes'])?></td><td><?=ah($labels[$r['status']]??$r['status'])?></td><td><?=ah($r['metric'])?></td></tr><?php endforeach ?></tbody></table></div>
<?php if(!$rows): ?><p>Nenhuma candidatura encontrada.</p><?php endif ?><p>Página <?=$page?> · <?php if($page>1): ?><a href="<?=ah(alink($f+['page'=>$page-1]))?>">Anterior</a><?php endif ?> <?php if($page*50<$total): ?><a href="<?=ah(alink($f+['page'=>$page+1]))?>">Próxima</a><?php endif ?></p>
<?php endif ?></main></body></html>
