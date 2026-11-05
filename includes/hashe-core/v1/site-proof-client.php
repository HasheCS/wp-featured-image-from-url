<?php
/** HP-PLUG-001 v1.2 opt-in WordPress site proof responder. PHP 7.4+. */
namespace Hashe\PluginTools\SiteProofV1;

defined('ABSPATH') || exit;
if (!class_exists(__NAMESPACE__.'\\Responder', false)) {
    final class Responder {
        private static $clients = array();
        private static $hooked = false;
        public static function boot($slug, $consentOption) {
            if (!is_string($slug) || !preg_match('/\A[a-z0-9-]{2,100}\z/D',$slug)
                || !is_string($consentOption) || !preg_match('/\A[a-z0-9_]{5,100}\z/D',$consentOption)) return;
            self::$clients[$slug]=$consentOption;
            if (!self::$hooked) {
                add_action('rest_api_init',array(__CLASS__,'routes'));
                self::$hooked=true;
            }
        }
        public static function routes() {
            register_rest_route('hashe-plugins/v1','/site-proof',array(
                'methods'=>'POST',
                'callback'=>array(__CLASS__,'prove'),
                'permission_callback'=>'__return_true',
            ));
        }
        private static function deny() {
            return new \WP_Error('hashe_site_proof_unavailable','Site proof unavailable',array('status'=>404));
        }
        public static function prove($request) {
            if (!is_object($request) || !method_exists($request,'get_body') || !method_exists($request,'get_json_params')) return self::deny();
            $raw=$request->get_body();
            if (!is_string($raw) || strlen($raw)>2048 || $raw==='') return self::deny();
            $p=$request->get_json_params();
            if (!is_array($p) || ($p['schema_version']??null)!==1) return self::deny();
            $slug=$p['plugin']??'';
            if (!is_string($slug) || !isset(self::$clients[$slug])) return self::deny();
            $nonce=(string)($p['nonce']??'');
            $uuid=(string)($p['installation_id']??'');
            $host=(string)($p['hostname']??'');
            $notice=(string)($p['notice_version']??'');
            if (!preg_match('/\A[a-f0-9]{64}\z/D',$nonce)
                || !preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D',$uuid)
                || !preg_match('/\A[A-Za-z0-9._-]{8,64}\z/D',$notice)
                || !is_string($host) || strlen($host)>253) return self::deny();
            $url=get_site_url();
            $local=wp_parse_url((string)$url);
            if (!is_array($local) || strtolower((string)($local['scheme']??''))!=='https'
                || empty($local['host']) || isset($local['port'])) return self::deny();
            $actualHost=strtolower(rtrim((string)$local['host'],'.'));
            if ($host!==$actualHost || !filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)) return self::deny();
            $option=get_option(self::$clients[$slug],false);
            if (!is_array($option) || ($option['status']??'')!=='allowed'
                || !isset($option['installation_id'],$option['policy_version'],$option['secret_hex'])
                || !hash_equals((string)$option['installation_id'],$uuid)
                || !hash_equals((string)$option['policy_version'],$notice)
                || !preg_match('/\A[a-f0-9]{64}\z/D',(string)$option['secret_hex'])) return self::deny();
            // Ephemeral one-use nonce: both plugins share this route but have distinct options.
            $nonceKey='hp7_sp_'.substr(hash('sha256',$slug.'\n'.$uuid.'\n'.$nonce),0,34);
            if (get_transient($nonceKey)!==false) return self::deny();
            set_transient($nonceKey,'used',600);
            $message="hashe-site-proof-v1\n".$nonce."\n".$slug."\n".$uuid."\n".$host."\n".$notice;
            $proof=hash_hmac('sha256',$message,hex2bin((string)$option['secret_hex']));
            return new \WP_REST_Response(array('schema_version'=>1,'proof'=>$proof),200);
        }
    }
}
