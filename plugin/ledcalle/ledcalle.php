<?php
/**
 * Plugin Name: LEDCalle – Publicidad en pantallas LED
 * Description: Reserva de bloques de 20 s en pantallas LED. Shortcodes: [ledcalle] (clientes) y [ledcalle_admin] (propietarios).
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Text Domain: ledcalle
 */
if(!defined('ABSPATH'))exit;

final class LEDCalle{
 const NS='ledcalle/v1',CAP='ledcalle_manage',VER='1.0.0';

 static function init(){
  add_action('init',[__CLASS__,'types']);
  add_action('rest_api_init',[__CLASS__,'routes']);
  add_shortcode('ledcalle',[__CLASS__,'sc_client']);
  add_shortcode('ledcalle_admin',[__CLASS__,'sc_admin']);
 }
 static function activate(){
  add_role('ledcalle_owner','Propietario LED',['read'=>true,self::CAP=>true]);
  if($a=get_role('administrator'))$a->add_cap(self::CAP);
 }
 static function types(){
  foreach(['ledc_screen','ledc_booking'] as $t)register_post_type($t,['public'=>false,'show_ui'=>false,'supports'=>['title'],'rewrite'=>false]);
 }

 /* ---------- Shortcodes ---------- */
 static function assets($mode){
  $u=plugins_url('assets/',__FILE__);
  if(apply_filters('ledcalle_google_fonts',false))wp_enqueue_style('ledcalle-fonts','https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;800&family=Hanken+Grotesk:wght@400;600;700&display=swap',[],null);
  wp_enqueue_script('ledcalle',$u.'app.js',[],self::VER,true);
  wp_localize_script('ledcalle','LEDC',['rest'=>esc_url_raw(rest_url(self::NS)),'nonce'=>wp_create_nonce('wp_rest'),'mode'=>$mode,'css'=>$u.'app.css?ver='.self::VER,'logout'=>wp_logout_url(get_permalink())]);
 }
 static function sc_client(){self::assets('c');return '<div id="ledcalle-root"></div>';}
 static function sc_admin(){
  if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);
  if(!is_user_logged_in())return '<div class="ledc-login">'.wp_login_form(['echo'=>false,'redirect'=>get_permalink()]).'</div>';
  if(!current_user_can(self::CAP))return '<p>No tienes permisos para acceder al backoffice.</p>';
  self::assets('o');return '<div id="ledcalle-root"></div>';
 }

 /* ---------- REST ---------- */
 static function routes(){
  $r=function($p,$m,$cb,$perm){register_rest_route(self::NS,$p,['methods'=>$m,'callback'=>[__CLASS__,$cb],'permission_callback'=>$perm]);};
  $pub='__return_true';$adm=[__CLASS__,'can'];
  $r('/screens','GET','screens',$pub);
  $r('/bookings','POST','book',$pub);
  $r('/upload','POST','upload',$pub);
  $r('/admin/data','GET','admin_data',$adm);
  $r('/admin/screens','POST','screen_save',$adm);
  $r('/admin/screens/(?P<id>\d+)','POST','screen_save',$adm);
  $r('/admin/bookings/(?P<id>\d+)','POST','booking_act',$adm);
 }
 static function can(){return current_user_can(self::CAP);}

 static function screen_out($p){
  $id=$p->ID;$m=function($k)use($id){return get_post_meta($id,$k,true);};
  return['id'=>$id,'name'=>$p->post_title,'zone'=>$m('zone'),'open'=>(int)$m('open'),'close'=>(int)$m('close'),'active'=>$m('active')==='1','hue'=>($id*47)%360,'photo'=>$m('photo')?:null];
 }
 static function status($id){
  $st=get_post_meta($id,'st',true);$t=current_time('Y-m-d');$s=get_post_meta($id,'start',true);$e=get_post_meta($id,'end',true);
  if($st==='rejected')return 'Rechazado';
  if($t>$e)return 'Finalizado';
  if($st==='pending')return 'Pendiente de aprobación';
  return $t<$s?'Programado':'Activo';
 }
 static function bk_out($p){
  $id=$p->ID;$m=function($k)use($id){return get_post_meta($id,$k,true);};
  $ad=json_decode($m('ad'),true)?:[];$f=function($d){return (new DateTime($d))->format('d/m/Y');};
  return['id'=>$id,'sc'=>(int)$m('screen'),'col'=>(int)$m('slot'),'client'=>$p->post_title,'name'=>$ad['text']??($ad['name']??'Anuncio'),'start'=>$f($m('start')),'end'=>$f($m('end')),'status'=>self::status($id),'pay'=>$m('pay')?:'Pendiente de pago','ad'=>$ad,'reason'=>$m('reason')?:null,'email'=>$m('email'),'tel'=>$m('tel')];
 }
 static function taken(){
  $q=get_posts(['post_type'=>'ledc_booking','numberposts'=>-1,'meta_query'=>[['key'=>'st','value'=>['pending','approved'],'compare'=>'IN'],['key'=>'end','value'=>current_time('Y-m-d'),'compare'=>'>=','type'=>'DATE']]]);
  $o=[];foreach($q as $p)$o[]=['screen'=>(int)get_post_meta($p->ID,'screen',true),'slot'=>(int)get_post_meta($p->ID,'slot',true)];
  return $o;
 }
 static function screens(){
  $q=get_posts(['post_type'=>'ledc_screen','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC','meta_key'=>'active','meta_value'=>'1']);
  return['screens'=>array_map([__CLASS__,'screen_out'],$q),'taken'=>self::taken()];
 }
 static function admin_data(){
  $s=get_posts(['post_type'=>'ledc_screen','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC']);
  $b=get_posts(['post_type'=>'ledc_booking','numberposts'=>-1,'orderby'=>'ID','order'=>'DESC']);
  return['screens'=>array_map([__CLASS__,'screen_out'],$s),'bookings'=>array_map([__CLASS__,'bk_out'],$b)];
 }
 static function throttle($key,$max){
  $k='ledc_'.$key.md5($_SERVER['REMOTE_ADDR']??'');$n=(int)get_transient($k);
  if($n>=$max)return new WP_Error('rate','Demasiados intentos. Inténtalo más tarde.',['status'=>429]);
  set_transient($k,$n+1,HOUR_IN_SECONDS);return true;
 }
 static function lock(){ // bloqueo atómico válido en cualquier base de datos (MySQL, MariaDB, SQLite)
  for($i=0;$i<20;$i++){
   if(add_option('ledcalle_lock',time(),'','no'))return true;
   wp_cache_delete('ledcalle_lock','options');wp_cache_delete('alloptions','options');
   if(time()-(int)get_option('ledcalle_lock')>10)delete_option('ledcalle_lock');
   usleep(250000);
  }
  return false;
 }
 static function own_url($u){$d=wp_get_upload_dir();return is_string($u)&&strpos($u,$d['baseurl'].'/ledcalle/')===0;}
 static function clean_ad($a){
  $k=in_array($a['k']??'',['img','vid','gen'],true)?$a['k']:'gen';$o=['k'=>$k];
  if($k==='gen'){
   $o['text']=sanitize_text_field(mb_substr($a['text']??'',0,80));$o['sub']=sanitize_text_field(mb_substr($a['sub']??'',0,160));
   $o['tpl']=max(0,min(3,(int)($a['tpl']??0)));$o['anim']=in_array($a['anim']??'',['slide','zoom','fade','none'],true)?$a['anim']:'none';
   $o['lay']=($a['lay']??'')==='left'?'left':'c';
   $o['font']=in_array($a['font']??'',['Bricolage Grotesque','Georgia','Hanken Grotesk','Courier New'],true)?$a['font']:'Bricolage Grotesque';
   if(!empty($a['logo'])&&self::own_url($a['logo']))$o['logo']=esc_url_raw($a['logo']);
   if($o['text']==='')return null;
  }else{
   if(empty($a['src'])||!self::own_url($a['src']))return null;
   $o['src']=esc_url_raw($a['src']);$o['name']=sanitize_text_field(mb_substr($a['name']??'Anuncio',0,80));
  }
  return $o;
 }

 /* ---------- Público ---------- */
 static function upload(WP_REST_Request $r){
  $t=self::throttle('u',20);if(is_wp_error($t))return $t;
  $f=$r->get_file_params()['file']??null;
  if(!$f||$f['size']>30*MB_IN_BYTES)return new WP_Error('big','Archivo no válido o superior a 30 MB.',['status'=>400]);
  require_once ABSPATH.'wp-admin/includes/file.php';
  $cb=function($d){$d['subdir']='/ledcalle';$d['path']=$d['basedir'].'/ledcalle';$d['url']=$d['baseurl'].'/ledcalle';return $d;};
  add_filter('upload_dir',$cb);
  $res=wp_handle_upload($f,['test_form'=>false,'mimes'=>['jpg|jpeg'=>'image/jpeg','png'=>'image/png','mp4'=>'video/mp4']]);
  remove_filter('upload_dir',$cb);
  if(isset($res['error']))return new WP_Error('up',$res['error'],['status'=>400]);
  return['url'=>$res['url']];
 }
 static function book(WP_REST_Request $r){
  $t=self::throttle('b',5);if(is_wp_error($t))return $t;
  $b=$r->get_json_params();$sc=(int)($b['screen']??0);$slot=(int)($b['slot']??-1);
  $biz=sanitize_text_field($b['biz']??'');$em=sanitize_email($b['email']??'');$tel=sanitize_text_field($b['tel']??'');
  $ad=self::clean_ad($b['ad']??[]);
  if(get_post_type($sc)!=='ledc_screen'||get_post_meta($sc,'active',true)!=='1'||$slot<0||$slot>44||!$biz||!is_email($em)||!$ad)return new WP_Error('bad','Datos no válidos.',['status'=>400]);
  if(!self::lock())return new WP_Error('busy','Servidor ocupado. Inténtalo de nuevo.',['status'=>503]);
  try{
   foreach(self::taken() as $x)if($x['screen']===$sc&&$x['slot']===$slot)return new WP_Error('taken','Este bloque acaba de ser reservado. Elige otro.',['status'=>409]);
   $s=new DateTime('tomorrow',wp_timezone());$e=(clone $s)->modify('+1 month -1 day');
   $id=wp_insert_post(['post_type'=>'ledc_booking','post_status'=>'publish','post_title'=>$biz]);
   foreach(['screen'=>$sc,'slot'=>$slot,'start'=>$s->format('Y-m-d'),'end'=>$e->format('Y-m-d'),'st'=>'pending','pay'=>'Pendiente de pago','email'=>$em,'tel'=>$tel] as $k=>$v)update_post_meta($id,$k,$v);
   update_post_meta($id,'ad',wp_slash(wp_json_encode($ad)));
  }finally{delete_option('ledcalle_lock');}
  $p=get_post($id);$o=self::bk_out($p);
  $o['checkout']=(string)apply_filters('ledcalle_checkout_url','',$id,$o); // integra aquí tu pasarela (Stripe, WooCommerce, Redsys…)
  do_action('ledcalle_booking_created',$id,$o);
  self::mail($p,'new');
  return $o;
 }

 /* ---------- Propietario ---------- */
 static function screen_save(WP_REST_Request $r){
  $id=(int)$r['id'];$b=$r->get_json_params();
  if($id){$p=get_post($id);if(!$p||$p->post_type!=='ledc_screen')return new WP_Error('nf','No existe',['status'=>404]);}
  else $id=wp_insert_post(['post_type'=>'ledc_screen','post_status'=>'publish','post_title'=>'Nueva pantalla']);
  if(!metadata_exists('post',$id,'open')){update_post_meta($id,'open',6);update_post_meta($id,'close',23);}
  if(!metadata_exists('post',$id,'active'))update_post_meta($id,'active','1');
  if(!empty($b['name']))wp_update_post(['ID'=>$id,'post_title'=>sanitize_text_field($b['name'])]);
  if(isset($b['zone']))update_post_meta($id,'zone',sanitize_text_field($b['zone']));
  if(isset($b['open'])){$o=max(0,min(23,(int)$b['open']));$c=max($o+1,min(24,(int)($b['close']??23)));update_post_meta($id,'open',$o);update_post_meta($id,'close',$c);}
  if(isset($b['active']))update_post_meta($id,'active',$b['active']?'1':'0');
  if(!empty($b['photo'])&&self::own_url($b['photo']))update_post_meta($id,'photo',esc_url_raw($b['photo']));
  return self::screen_out(get_post($id));
 }
 static function booking_act(WP_REST_Request $r){
  $p=get_post((int)$r['id']);if(!$p||$p->post_type!=='ledc_booking')return new WP_Error('nf','No existe',['status'=>404]);
  $b=$r->get_json_params();$a=$b['action']??'';
  if($a==='approve'){update_post_meta($p->ID,'st','approved');delete_post_meta($p->ID,'reason');self::mail($p,'approved');}
  elseif($a==='reject'){update_post_meta($p->ID,'st','rejected');update_post_meta($p->ID,'reason',sanitize_textarea_field($b['reason']??'')?:'Sin motivo indicado');self::mail($p,'rejected');}
  elseif($a==='paid')update_post_meta($p->ID,'pay','Pagado');
  else return new WP_Error('bad','Acción no válida',['status'=>400]);
  return self::bk_out($p);
 }
 static function mail($p,$ev){
  $to=get_post_meta($p->ID,'email',true);$n=get_bloginfo('name');
  if($ev==='new'){
   wp_mail($to,"[$n] Reserva recibida","Hola {$p->post_title},\n\nHemos recibido tu reserva de publicidad. Revisaremos tu anuncio y te indicaremos cómo completar el pago.\n\nGracias.");
   wp_mail(get_option('admin_email'),"[$n] Nueva reserva LED","Cliente: {$p->post_title}\nEmail: $to\nTeléfono: ".get_post_meta($p->ID,'tel',true)."\n\nGestiónala en tu backoffice.");
  }elseif($ev==='approved')wp_mail($to,"[$n] Anuncio aprobado","Hola {$p->post_title}, tu anuncio ha sido aprobado y saldrá en pantalla desde la fecha de inicio.");
  else wp_mail($to,"[$n] Anuncio rechazado","Hola {$p->post_title}, no hemos podido aprobar tu anuncio.\nMotivo: ".get_post_meta($p->ID,'reason',true)."\n\nPuedes volver a contratar con otro anuncio.");
 }
}
register_activation_hook(__FILE__,['LEDCalle','activate']);
LEDCalle::init();
