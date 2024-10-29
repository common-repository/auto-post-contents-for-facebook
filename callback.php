<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

include("Facebook/autoload.php");

function raefghribi_truncatefbpage()	
{    
	global $wpdb;	
	$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}fb_pages");	
}

function raefghribi_insertfbpage($page_id, $name, $access_token, $app_id)
{	
	global $wpdb;	
	$table = "{$wpdb->prefix}fb_pages";
	$data = array('page_id' => $page_id, 'name' => $name, 'page_access_token' => $access_token, 'app_id' => $app_id);
	$format = array('%s','%s','%s');
	$wpdb->insert($table,$data,$format);
}

function raefghribi_create_long_lived_access_token($short_lived_user_token)
{
	$short_token=$short_lived_user_token;		
	$app_id = get_option('autopost_field1');		
	$app_secret = get_option('autopost_field2');		
	$url="https://graph.facebook.com/v3.2/oauth/access_token?grant_type=fb_exchange_token&client_id={$app_id}&client_secret={$app_secret}&fb_exchange_token={$short_token}";		
	$response = wp_remote_get($url);
	if( is_wp_error( $response ) ) {
		return false; // Bail early
	}	
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body );
	return $data->access_token ;
}

$fb = new Facebook\Facebook([  'app_id' => get_option('autopost_field1'),  'app_secret' => get_option('autopost_field2'),  ]);
$helper = $fb->getRedirectLoginHelper();
if (isset($_GET['state'])) 
{
	$helper->getPersistentDataHandler()->set('state', $_GET['state']);
}
try 
{
	$accessToken = $helper->getAccessToken();
}

catch(Facebook\Exceptions\FacebookResponseException $e) 
{ 
	echo 'Graph returned an error: ' . $e->getMessage();  
	exit;
}

catch(Facebook\Exceptions\FacebookSDKException $e) 
{ 
	echo 'Facebook SDK returned an error: ' . $e->getMessage();  
	exit;
}

if (! isset($accessToken)) 
{  
	if ($helper->getError()) 
	{
		header('HTTP/1.0 401 Unauthorized');    
		echo "Error: " . $helper->getError() . "\n";    
		echo "Error Code: " . $helper->getErrorCode() . "\n";    
		echo "Error Reason: " . $helper->getErrorReason() . "\n";    
		echo "Error Description: " . $helper->getErrorDescription() . "\n";  
	} 
	else 
	{
		header('HTTP/1.0 400 Bad Request');    
		echo 'Bad request';  
	}
	exit;
}

$oAuth2Client = $fb->getOAuth2Client();
$tokenMetadata = $oAuth2Client->debugToken($accessToken);
$tokenMetadata->validateAppId(get_option('autopost_field1')); 
$tokenMetadata->validateExpiration();

if (! $accessToken->isLongLived()) {    
try 
{
	$accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);  
} 	catch (Facebook\Exceptions\FacebookSDKException $e) 
{   
	exit;  
}
}

$_SESSION['fb_access_token'] = raefghribi_create_long_lived_access_token($accessToken) ;


function raefghribi_get_page_list()
{		
	$url = 'https://graph.facebook.com/v3.2/me/accounts?limit=100&access_token='.$_SESSION['fb_access_token'];		
	$response = wp_remote_get($url);
	if( is_wp_error( $response ) ) 
	{
		return false; // Bail early
	}	
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body );
	return $data ;
}

raefghribi_truncatefbpage();
$data = raefghribi_get_page_list() ;

if( ! empty( $data ) ) {
	
	$app_id = get_option('autopost_field1') ;
	
	foreach( $data->data as $d ) {
		
			$page_id = $d->id;
			$page_access_token = $d->access_token;
			$page_name = $d->name;
			raefghribi_insertfbpage($page_id, $page_name, $page_access_token, $app_id) ;
	}
}

?>