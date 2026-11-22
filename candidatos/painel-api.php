<?php
declare(strict_types=1);
require __DIR__.'/core.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: public, max-age=300');header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
function pq(PDO $db,string $sql,array $args=[]): array {$s=$db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);}
function ranks(array $values): array {$sorted=$values;asort($sorted,SORT_NUMERIC);$keys=array_keys($sorted);$result=[];$n=count($keys);for($i=0;$i<$n;){$j=$i+1;while($j<$n && $sorted[$keys[$j]]==$sorted[$keys[$i]])$j++;$rank=($i+$j-1)/2+1;for($k=$i;$k<$j;$k++)$result[$keys[$k]]=$rank;$i=$j;}ksort($result);return $result;}
function spearman(array $x,array $y): ?float {if(count($x)<3)return null;$x=ranks($x);$y=ranks($y);$n=count($x);$mx=array_sum($x)/$n;$my=array_sum($y)/$n;$cov=0.;$sx=0.;$sy=0.;foreach($x as $i=>$v){$dx=$v-$mx;$dy=$y[$i]-$my;$cov+=$dx*$dy;$sx+=$dx*$dx;$sy+=$dy*$dy;}return $sx>0 && $sy>0?$cov/sqrt($sx*$sy):null;}
function mbSafe(string $s,int $length):string{return function_exists('mb_substr')?mb_substr($s,0,$length,'UTF-8'):substr($s,0,$length);}
try {
 $path=dataDir().'/painel.sqlite';if(!is_file($path))throw new RuntimeException('Base ainda indisponível');
 $db=new PDO('sqlite:file:'.str_replace('\\','/',$path).'?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db->exec('PRAGMA query_only=ON; PRAGMA busy_timeout=5000');
 $meta=[];foreach(pq($db,'SELECT * FROM metadata') as $r)$meta[$r['key']]=json_decode($r['value'],true,512,JSON_THROW_ON_ERROR);unset($meta['electoral_capture']);
 $office=(string)($_GET['office']??'6');if(!in_array($office,['1','3','5','6','7','8','25'],true))$office='6';$uf=(string)($_GET['uf']??'');if(!preg_match('/^[A-Z]{2}$/',$uf))$uf='';$party=mbSafe((string)($_GET['party']??''),40);
 $where='office=?';$args=[$office];foreach(['uf'=>$office==='1'?'':$uf,'party'=>$party] as $key=>$value)if($value!==''){$where.=" AND $key=?";$args[]=$value;}
 $action=$_GET['action']??'overview';$result=[];
 if($action==='overview') {
  $result=['metadata'=>$meta,'filters'=>['office'=>$office,'uf'=>$uf,'party'=>$party], 'ufs'=>array_column(pq($db,"SELECT DISTINCT uf FROM candidates WHERE uf!='BR' ORDER BY uf"),'uf'),'party_options'=>array_column(pq($db,'SELECT DISTINCT party FROM candidates ORDER BY party'),'party'),
   'spectra'=>pq($db,"SELECT year,profile,COUNT(*) candidates,COUNT(votes) observed,SUM(votes) votes,SUM(ideological=1) ideological,SUM(gender='FEMININO') women,COUNT(gender) gender_known,AVG(age) age,SUM(outcome LIKE 'ELEITO%' OR outcome LIKE 'Eleito%') elected FROM candidates WHERE $where GROUP BY year,profile",$args),
   'parties'=>pq($db,"SELECT year,party,profile,COUNT(*) candidates,COUNT(votes) observed,SUM(votes) votes FROM candidates WHERE $where GROUP BY year,party,profile ORDER BY year,votes DESC",$args),
   'states'=>pq($db,'SELECT year,uf,profile,COUNT(*) candidates,COUNT(votes) observed,SUM(votes) votes FROM candidates WHERE office=?'.($party!==''?' AND party=?':'').' GROUP BY year,uf,profile',$party!==''?[$office,$party]:[$office])];
  foreach(['gender','race','education','occupation'] as $field)$result['demographics'][$field]=pq($db,"SELECT year,profile,$field category,COUNT(*) candidates,SUM(votes) votes FROM candidates WHERE $where GROUP BY year,profile,$field",$args);
  $result['age_groups']=pq($db,"SELECT year,profile,CASE WHEN age IS NULL THEN 'Não informado' WHEN age<30 THEN 'Até 29' WHEN age<45 THEN '30–44' WHEN age<60 THEN '45–59' ELSE '60+' END category,COUNT(*) candidates,SUM(votes) votes FROM candidates WHERE $where GROUP BY year,profile,category",$args);
  $result['transitions']=pq($db,"WITH cohort AS (SELECT year,person_id,MAX(profile) profile,MAX(party) party FROM candidates WHERE $where AND person_id IS NOT NULL GROUP BY year,person_id HAVING COUNT(*)=1) SELECT a.profile previous,b.profile current,COUNT(*) quantity,SUM(a.party!=b.party) switched_party FROM cohort a JOIN cohort b ON a.person_id=b.person_id WHERE a.year=2022 AND b.year=2026 GROUP BY a.profile,b.profile",$args);
  $result['cohort']=pq($db,"SELECT year,COUNT(DISTINCT person_id) identifiable FROM candidates WHERE $where",$args);
 } elseif($action==='territory') {
  $indicator=(string)($_GET['indicator']??'income');if(!array_key_exists($indicator,$meta['indicators']))$indicator='income';$profile=(string)($_GET['profile']??'1');if(!in_array($profile,['-2','-1','0','1','2'],true))$profile='1';
  $mw='g.office=?';$ma=[$office];if($uf!==''){$mw.=' AND g.uf=?';$ma[]=$uf;}if($party!==''){$mw.=' AND g.party=?';$ma[]=$party;}
  $rows=pq($db,"SELECT g.year,g.ibge,MAX(g.uf) uf,m.name,m.$indicator indicator,SUM(g.votes) total,SUM(CASE WHEN g.profile=? THEN g.votes ELSE 0 END) selected FROM municipal g JOIN municipalities m ON m.ibge=g.ibge WHERE $mw GROUP BY g.year,g.ibge",array_merge([(int)$profile],$ma));$by=[];$all=[];$covered=[];
  foreach($rows as $r){$year=(int)$r['year'];$all[$year]=($all[$year]??0)+(int)$r['total'];if($r['indicator']===null || $r['total']<=0)continue;$covered[$year]=($covered[$year]??0)+(int)$r['total'];$id=$r['ibge'];$by[$id]??=['ibge'=>$id,'name'=>$r['name'],'uf'=>$r['uf'],'x'=>(float)$r['indicator']];$by[$id]['share'.$year]=(int)$r['selected']/(int)$r['total'];$by[$id]['votes'.$year]=(int)$r['total'];$by[$id]['selected'.$year]=(int)$r['selected'];}
  $points=array_values($by);$corr=[];$bins=[];
  foreach([2022,2026] as $year){$eligible=array_values(array_filter($points,fn($p)=>isset($p['share'.$year])));usort($eligible,fn($a,$b)=>$a['x']<=>$b['x']);$n=count($eligible);$corr[$year]=['n'=>$n,'rho'=>spearman(array_column($eligible,'x'),array_column($eligible,'share'.$year))];for($b=0;$b<5;$b++){$slice=array_slice($eligible,(int)floor($n*$b/5),(int)floor($n*($b+1)/5)-(int)floor($n*$b/5));$weight=array_sum(array_column($slice,'votes'.$year));$bins[]=['year'=>$year,'quintile'=>$b+1,'n'=>count($slice),'min'=>$slice[0]['x']??null,'max'=>$slice?end($slice)['x']:null,'mean_share'=>$slice?array_sum(array_column($slice,'share'.$year))/count($slice):null,'vote_share'=>$weight?array_sum(array_column($slice,'selected'.$year))/$weight:null];}}
  $pairs=array_values(array_filter($points,fn($p)=>isset($p['share2022'],$p['share2026'])));foreach($pairs as &$p)$p['change']=$p['share2026']-$p['share2022'];unset($p);$corr['change']=['n'=>count($pairs),'rho'=>spearman(array_column($pairs,'x'),array_column($pairs,'change'))];
  $result=['indicator'=>$indicator,'profile'=>(int)$profile,'points'=>$points,'correlations'=>$corr,'quintiles'=>$bins,'coverage'=>['matched_territorial_votes'=>$covered,'ibge_mapped_territorial_votes'=>$all],'paired_municipalities'=>count($pairs)];
 } elseif($action==='search') {
  $q=mbSafe(trim((string)($_GET['q']??'')),100);$year=($_GET['year']??'2026')==='2022'?2022:2026;$query=$where.' AND year=?';$params=array_merge($args,[$year]);if($q!==''){$query.=" AND (name LIKE ? ESCAPE '\\' OR source_id=?)";$params[]='%'.strtr($q,['\\'=>'\\\\','%'=>'\\%','_'=>'\\_']).'%';$params[]=$q;}
  $result=['candidates'=>pq($db,"SELECT id,year,name,uf,office,party,profile,votes,rank,peers FROM candidates WHERE $query ORDER BY votes DESC,name LIMIT 60",$params)];
 } elseif($action==='individual') {
  $rows=pq($db,'SELECT * FROM candidates WHERE id=?',[max(0,(int)($_GET['id']??0))]);if(!$rows){http_response_code(404);throw new RuntimeException('Candidatura não encontrada');}$c=$rows[0];$person=$c['person_id'];unset($c['person_id']);$c['geography']=json_decode($c['geo_json']??'null',true);unset($c['geo_json']);
  $timeline=$person===null?[]:pq($db,'SELECT id,year,name,office,uf,party,profile,votes,vote_share,rank,peers,metric,status FROM candidates WHERE person_id=? ORDER BY year,office,id',[$person]);
  $benchmark=[];foreach(array_keys($meta['indicators']) as $indicator)$benchmark[$indicator]=pq($db,"SELECT AVG(json_extract(geo_json,'$.context.$indicator.value')) mean,COUNT(json_extract(geo_json,'$.context.$indicator.value')) n FROM candidates WHERE year=? AND office=? AND uf=?",[$c['year'],$c['office'],$c['uf']])[0];
  $result=['candidate'=>$c,'timeline'=>$timeline,'context_benchmark'=>$benchmark,'indicators'=>$meta['indicators']];
 } else {http_response_code(400);throw new RuntimeException('Consulta inválida');}
 echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
} catch(Throwable $e){error_log((string)$e);if(http_response_code()===200)http_response_code(503);header('Cache-Control: no-store');echo json_encode(['error'=>http_response_code()===404?'Candidatura não encontrada.':'Análise indisponível. Tente novamente.'],JSON_UNESCAPED_UNICODE);}
