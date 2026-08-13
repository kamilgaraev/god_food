<?php
declare(strict_types=1);
namespace Theobroma\OneC\Http;
use Theobroma\OneC\CommerceMl\OrderWriter;
use Theobroma\OneC\Import\{CatalogImportParser,CatalogUpdateService,OrderStatusImportParser,OrderStatusUpdateService};
use Theobroma\OneC\Orders\{WooOrderMapper,WooOrderRepository};
use Theobroma\OneC\Settings\Settings;
use Theobroma\OneC\Support\ExchangeLogger;
final class WordPressExchangeEndpoint {
 private const BATCH='theobroma_1c_batch_';private const ATTEMPTS='theobroma_1c_auth_';
 public static function register():void{add_action('init',[self::class,'rewrite']);add_filter('query_vars',[self::class,'queryVars']);add_action('template_redirect',[self::class,'dispatch']);}
 public static function rewrite():void{add_rewrite_rule('^theobroma-1c/exchange/?$','index.php?theobroma_1c_exchange=1','top');}
 /** @param list<string> $v @return list<string> */public static function queryVars(array$v):array{$v[]='theobroma_1c_exchange';return$v;}
 public static function dispatch():void{
  if(!(int)get_query_var('theobroma_1c_exchange'))return;
  $type=sanitize_key((string)($_GET['type']??''));$mode=sanitize_key((string)($_GET['mode']??''));
  if(!in_array($type,['sale','catalog'],true))self::send(new ExchangeResponse(400,"failure\nUnsupported type"));
  if(!is_ssl()&&wp_get_environment_type()==='production')self::send(new ExchangeResponse(403,"failure\nHTTPS required"));
  $settings=(new Settings())->get();$logger=new ExchangeLogger();$bucket=self::authBucket();$limiter=self::limiter();
  if(!$limiter->allowed($bucket))self::send(new ExchangeResponse(429,"failure\nToo many authentication attempts",headers:['Retry-After'=>'900']));
  if(!$settings['enabled'])self::send(new ExchangeResponse(503,"failure\nExchange disabled"));
  $auth=new BasicAuthenticator($settings['username'],$settings['password_hash'],'wp_check_password');
  if(!$auth->valid((string)($_SERVER['PHP_AUTH_USER']??''),(string)($_SERVER['PHP_AUTH_PW']??''))){$limiter->failure($bucket);$logger->info('CommerceML authentication failed',['mode'=>$mode,'type'=>$type,'result'=>'unauthorized']);self::send(new ExchangeResponse(401,"failure\nUnauthorized",headers:['WWW-Authenticate'=>'Basic realm="Theobroma 1C"']));}
  $limiter->success($bucket);
  if($mode==='checkauth')self::send(new ExchangeResponse(200,"success\ntheobroma_1c_session\n".bin2hex(random_bytes(16))));
  if($mode==='init')self::send(new ExchangeResponse(200,"zip=no\nfile_limit=".((int)$settings['upload_limit_mb']*1048576)));
  if(in_array($mode,['file','import'],true))self::send(self::incoming($type,$mode,$settings,$logger));
  if($type!=='sale'||!in_array($mode,['query','success'],true))self::send(new ExchangeResponse(400,"failure\nUnsupported mode"));
  if(!$settings['export_orders'])self::send(new ExchangeResponse(403,"failure\nOrder export disabled"));
  self::send(self::outgoing($mode,$settings,$logger));
 }
 /** @param array<string,mixed> $s */private static function incoming(string$type,string$mode,array$s,ExchangeLogger$logger):ExchangeResponse{
  $token=self::token();$limit=(int)$s['upload_limit_mb']*1048576;$store=new ExchangeFileStore(get_temp_dir().'theobroma-1c',$token,$limit);$enabled=false;
  if($type==='catalog'){$enabled=!empty($s['import_stock'])||!empty($s['import_prices']);$importer=function(string$xml)use($s,$limit,$logger){$updates=(new CatalogImportParser())->parse($xml,$limit);$result=CatalogUpdateService::wordpress()->apply($updates,!empty($s['import_stock']),!empty($s['import_prices']));$logger->info('CommerceML catalog imported',['mode'=>'import','type'=>'catalog','result'=>$result->errors?'partial':'success',...$result->context()]);return$result;};}
  else{$enabled=!empty($s['import_order_statuses']);$importer=function(string$xml)use($limit,$logger){$result=(new OrderStatusUpdateService())->apply((new OrderStatusImportParser())->parse($xml,$limit));$logger->info('CommerceML order statuses imported',['mode'=>'import','type'=>'sale','result'=>$result->errors?'partial':'success',...$result->context()]);return$result;};}
  if($mode==='file'&&(int)($_SERVER['CONTENT_LENGTH']??0)>$limit)return new ExchangeResponse(413,"failure\nXML exceeds configured limit");
  $body=$mode==='file'?(string)file_get_contents('php://input'):'';$response=(new IncomingExchangeService($enabled,$store,$importer))->handle($mode,(string)($_GET['filename']??''),$body);
  $logger->info('CommerceML incoming exchange',['mode'=>$mode,'type'=>$type,'result'=>$response->status<400?'success':'failure']);return$response;
 }
 /** @param array<string,mixed> $s */private static function outgoing(string$mode,array$s,ExchangeLogger$logger):ExchangeResponse{
  $repository=new WooOrderRepository();$mapper=new WooOrderMapper();$token=self::token();
  if($mode==='query'){$pending=$repository->pending((int)$s['batch_size']);set_transient(self::BATCH.$token,array_map(fn($r)=>['order_id'=>(int)$r['order']->get_id(),'revision'=>$r['revision']],$pending),HOUR_IN_SECONDS);$logger->info('CommerceML batch generated',['mode'=>'query','type'=>'sale','result'=>'success','order_count'=>count($pending)]);return new ExchangeResponse(200,(new OrderWriter())->write(array_map(fn($r)=>$mapper->map($r['order']),$pending)),'application/xml; charset=UTF-8');}
  $batch=get_transient(self::BATCH.$token);if(is_array($batch))$repository->acknowledge($batch);delete_transient(self::BATCH.$token);$logger->info('CommerceML batch acknowledged',['mode'=>'success','type'=>'sale','result'=>'success','order_count'=>is_array($batch)?count($batch):0]);return new ExchangeResponse(200,'success');
 }
 private static function limiter():AuthRateLimiter{return new AuthRateLimiter(fn($k)=>(int)get_transient(self::ATTEMPTS.$k),fn($k,$v)=>set_transient(self::ATTEMPTS.$k,$v,15*MINUTE_IN_SECONDS),fn($k)=>delete_transient(self::ATTEMPTS.$k));}
 private static function send(ExchangeResponse$r):never{status_header($r->status);header('Content-Type: '.$r->contentType);foreach($r->headers as$n=>$v)header($n.': '.$v);echo$r->body;exit;}
 private static function token():string{return hash_hmac('sha256',(string)($_COOKIE['theobroma_1c_session']??($_SERVER['PHP_AUTH_USER']??'anonymous')),wp_salt('auth'));}
 private static function authBucket():string{return hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'),wp_salt('auth'));}
}
