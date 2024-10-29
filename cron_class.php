<?php  

class raefghribi_cron_auto_post

{
	
private $timez ;

public function __construct($t)
  {
    $this->timez = $t;
  }	

	
public function link_to_facebook($link,$message,$id_page,$access_token)
{
	$url="https://graph.facebook.com/{$id_page}/feed?message={$message}&link={$link}&access_token={$access_token}";
	$response = wp_remote_post($url);
	if( is_wp_error( $response ) ) {
		return false;
	}	
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body );
	return $data ;
}

public function getLastPostedID()
{
	global $wpdb;
	$result = $wpdb->get_row("SELECT id_last_post_done from {$wpdb->prefix}last_done order by id desc Limit 1");
	$l = $result->id_last_post_done ;
	return $l ;
}

public function setLastPostedID($id)
{
	global $wpdb;
	$table = "{$wpdb->prefix}last_done";
	$data = array('id_last_post_done' => $id);
	$format = array('%d');
	$wpdb->insert($table,$data,$format);
}

public function setAutoPrograms()
{
	global $wpdb;
	$results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fb_auto_programs where live = '1'");
	foreach ( $results as $res )
	{
		$id_page = $res->id_page ;
		$access_token = $this->getTokenByIdPage($id_page) ;
		$nbr = $res->nbr ;
		$sched = $res->sched ;
		$latest_random = $res->latest_random ;
		$cat = $res->cat ;
		$this->PostsToPage ($nbr,$id_page,$access_token,$sched,$latest_random,$cat);
	}
}

public function setLogs($page, $link, $article, $datex)
{
	global $wpdb;
	$table = "{$wpdb->prefix}fb_logs";
	$data = array('page' => $page, 'article' => $article, 'lien' => $link, 'datex' => $datex);
	$format = array('%s', '%s', '%s', '%s');
	return $wpdb->insert($table,$data,$format);
}

public function namePageByID($id_page)	
{
	global $wpdb;
	$results = $wpdb->get_row( "SELECT name FROM {$wpdb->prefix}fb_pages where page_id = '$id_page' Limit 1");
	return $results->name ;
}

public function getTokenByIdPage($id_page)	
{
	global $wpdb;
	$result = $wpdb->get_row("SELECT page_access_token from {$wpdb->prefix}fb_pages where page_id = '$id_page' Limit 1");	
	return $result->page_access_token ;
}

public function PostsToPage ($nbr,$id_page,$access_token,$sched,$latest_random,$cat)
{
			if ($latest_random == 'LATEST')
	{
	$defaults = array(
	'numberposts' => $nbr,
	'category' => $cat,
	'orderby' => 'date',
	'order' => 'DESC',
	'include' => array(),
	'exclude' => array(),
	'meta_key' => '',
	'meta_value' =>'',
	'post_type' => 'post',
	'suppress_filters' => true 	);
	}
	else if ($latest_random == 'RANDOM')
	{
	$defaults = array(
	'numberposts' => $nbr,
	'category' => $cat,
	'orderby' => 'rand',
	'order' => 'DESC',
	'include' => array(),
	'exclude' => array(), 		
	'meta_key' => '',
	'meta_value' =>'', 		
	'post_type' => 'post',
	'suppress_filters' => true);
	}

	$resultat = get_posts( $defaults );

	foreach ($resultat as $def)
	{
	$message = urlencode($def->post_title);
	$link = urlencode($def->guid);
	$page_name = $this->namePageByID($id_page) ;
	$id_post = $def->ID ;
	$datex = new DateTime('now', new DateTimeZone($this->timez));			
	$datex = $datex->format('M j, Y, H:i');
		if ($latest_random == 'RANDOM')
		{
						
			$d1 = new DateTime('now', new DateTimeZone($this->timez));			
			$d1 = $d1->format('H:i');						
					
			$d2 = new DateTime('now + 2 minutes', new DateTimeZone($this->timez));			
			$d2 = $d2->format('H:i');	
						
			if ($sched <= $d2  && $sched >= $d1)
			{
				$this->link_to_facebook($link,$message,$id_page,$access_token) ;
				sleep(2) ;
				$this->setLogs($page_name, $def->guid, $def->post_title, $datex);
			}
		}
		else
		{
			if ( $this->getLastPostedID() < $id_post ) 
				{
					$this->link_to_facebook($link,$message,$id_page,$access_token) ;
					$this->setLastPostedID($id_post);
					sleep(2) ;
					$this->setLogs($page_name, $def->guid, $def->post_title, $datex);
				}
		}
	}

}
}


?>