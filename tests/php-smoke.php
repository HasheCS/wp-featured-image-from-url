<?php
/** WordPress-behavior smoke test with isolated functions and no real network. */
$mock_root = sys_get_temp_dir() . '/fifu-wordpress-fixture-' . getmypid() . '/';
@mkdir($mock_root . 'wp-admin/includes', 0700, true);
foreach (array('file', 'media', 'image', 'post') as $stub) {
    file_put_contents($mock_root . 'wp-admin/includes/' . $stub . '.php', "<?php\n");
}
define('ABSPATH', $mock_root);
register_shutdown_function(function() use ($mock_root) {
    foreach (array('file', 'media', 'image', 'post') as $stub) {
        @unlink($mock_root . 'wp-admin/includes/' . $stub . '.php');
    }
    @rmdir($mock_root . 'wp-admin/includes');
    @rmdir($mock_root . 'wp-admin');
    @rmdir($mock_root);
});
class FauxResult extends Exception {
    public $type; public $data; public $status;
    public function __construct($type,$data,$status=200) {
        $this->type=$type;$this->data=$data;$this->status=$status;
    }
}
function reset_fixture() {
    $GLOBALS['f']=array(
        'upload'=>true,'edit'=>true,'status'=>200,
        'binary'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLttAAAAABJRU5ErkJggg=='),
        'network'=>0,'thumb'=>array(),'media'=>array(),'cleaned'=>array()
    );
    $_POST=array('url'=>'https://example.org/photo.png','postId'=>'0','mode'=>'library');
}
function add_action($hook,$callback){}
function __($s,$domain){return $s;}
function load_plugin_textdomain(){}
function plugin_basename($s){return basename($s);}
function current_user_can($s,$id=0){return $GLOBALS['f'][$s==='upload_files'?'upload':'edit'];}
function check_ajax_referer($a,$b){$GLOBALS['f']['nonce']=true;}
function absint($v){return abs((int)$v);}
function get_post_type($v){return 'post';}
function post_type_supports($a,$b){return true;}
function sanitize_key($s){return strtolower((string)$s);}
function wp_unslash($v){return $v;}
function esc_url_raw($url,$protocols=array()){return $url;}
function wp_http_validate_url($url){
    $host=parse_url($url,PHP_URL_HOST);
    return $host && !in_array($host,array('localhost','127.0.0.1','169.254.169.254'),true)?$url:false;
}
function wp_tempnam($s){return tempnam(sys_get_temp_dir(),'fifu');}
function wp_safe_remote_get($url,$options){
    $GLOBALS['f']['network']++;
    $GLOBALS['f']['options']=$options;
    file_put_contents($options['filename'],$GLOBALS['f']['binary']);
    return array('code'=>$GLOBALS['f']['status']);
}
function is_wp_error($v){return false;}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_check_filetype($file){return array('type'=>str_ends_with_compat($file,'.avif')?null:'image/png');}
function str_ends_with_compat($s,$suffix){return substr($s,-strlen($suffix))===$suffix;}
function wp_parse_url($url,$flag){return parse_url($url,$flag);}
function sanitize_file_name($s){return preg_replace('/[^a-z0-9._-]/i','-',$s);}
function media_handle_sideload($file,$post){$GLOBALS['f']['media'][]=array($file['name'],$file['type'],$post);return 501;}
function set_post_thumbnail($post,$id){$GLOBALS['f']['thumb'][]=array($post,$id);return true;}
function wp_prepare_attachment_for_js($id){return array('id'=>$id,'url'=>'https://example.org/uploads/photo.png');}
function _wp_post_thumbnail_html($id,$post){return "<img data-id='$id'>";}
function wp_send_json_error($v,$status=400){throw new FauxResult('error',$v,$status);}
function wp_send_json_success($v){throw new FauxResult('success',$v,200);}
function wp_delete_file($path){$GLOBALS['f']['cleaned'][]=$path;@unlink($path);}
function check($ok,$msg){if(!$ok){fwrite(STDERR,"FAIL $msg\n");exit(1);}}
reset_fixture();
require __DIR__.'/../featured-image-from-url.php';
function run_import(){
    try {FIFU_Plugin::ajax_fetch();}catch(FauxResult $e){return $e;}
    throw new Exception('Expected WordPress response');
}
$r=run_import();
check($r->type==='success','general import');
check(count($GLOBALS['f']['media'])===1,'media handle called');
check(count($GLOBALS['f']['thumb'])===0,'never change featured on library import');
check($r->data['attachment']['id']===501,'return native attachment');
check($GLOBALS['f']['options']['limit_response_size']===FIFU_Plugin::MAX_BYTES+1,'stream size cap');
check($GLOBALS['f']['options']['reject_unsafe_urls']===true,'SSRF redirects guarded');
check($GLOBALS['f']['options']['stream']===true,'stream enabled');
echo "PASS native library attachment and no featured-image side effect\n";
reset_fixture();$_POST['postId']=27;$_POST['mode']='featured';$r=run_import();
check($r->type==='success','featured import');
check($GLOBALS['f']['thumb']===array(array(27,501)),'sets featured image');
echo "PASS featured image update\n";
reset_fixture();$_POST['postId']=27;$GLOBALS['f']['edit']=false;$r=run_import();
check($r->type==='error' && $r->status===403 && $GLOBALS['f']['network']===0,'edit permission');
echo "PASS edit permission\n";
reset_fixture();$GLOBALS['f']['upload']=false;$r=run_import();
check($r->status===403 && $GLOBALS['f']['network']===0,'upload permission');
echo "PASS upload permission\n";
reset_fixture();$_POST['url']='http://127.0.0.1/secret';$r=run_import();
check($r->type==='error' && $GLOBALS['f']['network']===0,'SSRF private address');
echo "PASS private address blocked\n";
reset_fixture();$_POST['url']='file:///etc/passwd';$r=run_import();
check($r->type==='error' && $GLOBALS['f']['network']===0,'unsafe scheme');
echo "PASS file scheme blocked\n";
reset_fixture();$GLOBALS['f']['binary']='<html>not an image</html>';$r=run_import();
check($r->type==='error' && count($GLOBALS['f']['media'])===0,'disguised image');
check(count($GLOBALS['f']['cleaned'])>=1,'failed temp cleanup');
echo "PASS disguised image rejected and cleaned\n";
reset_fixture();$GLOBALS['f']['binary']=str_repeat('x',FIFU_Plugin::MAX_BYTES+1);$r=run_import();
check($r->type==='error' && count($GLOBALS['f']['media'])===0,'oversize');
check(count($GLOBALS['f']['cleaned'])>=1,'oversize cleanup');
echo "PASS oversized body rejected and cleaned\n";
