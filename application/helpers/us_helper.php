<?php

function is_production(){
	$CI =& get_instance();
	return in_array($_SERVER['SERVER_NAME'],$CI->config->item('live_websites'));
}

function version_salt(){
	//This variable ensures that the CSS/JS files are being updated upon each launch
	//Also appended a timestamp To prevent static file cashing for local development
	//TODO Implemenet in sesseion when user logs in and logout if not matched!
	return 'v0.69'.( !is_production() ? '.'.substr(time(),7) : '' );
}

function fetch_file_ext($url){
	//https://cdn.fbsbx.com/v/t59.3654-21/19359558_10158969505640587_4006997452564463616_n.aac/audioclip-1500335487327-1590.aac?oh=5344e3d423b14dee5efe93edd432d245&oe=596FEA95
	$url_parts = explode('?',$url,2);
	$url_parts = explode('/',$url_parts[0]);
	$file_parts = explode('.',end($url_parts));
	return end($file_parts);
}

function save_file($file_url,$json_data){
	$CI =& get_instance();
	
	$file_name = md5($file_url.time().'someSa!t').'.'.fetch_file_ext($file_url);
	$file_path = 'application/cache/temp_files/';
	
	//Fetch Remote:
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $file_url);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	$result = curl_exec($ch);
	curl_close($ch);
	
	//Write in directory:
	$fp = fopen( $file_path.$file_name , 'w');
	fwrite($fp, $result);
	fclose($fp);
	
	//Then upload to AWS S3:
	require( '/var/www/us/application/libraries/aws/aws-autoloader.php' );
	$s3 = new Aws\S3\S3Client([
			'version' 		=> 'latest',
			'region'  		=> 'us-west-2',
			'credentials' 	=> $CI->config->item('aws_credentials'),
	]);
	$result = $s3->putObject(array(
			'Bucket'       => 's3foundation', //Same bucket for now
			'Key'          => $file_name,
			'SourceFile'   => $file_path.$file_name,
			'ACL'          => 'public-read'
	));
	
	if(isset($result['ObjectURL']) && strlen($result['ObjectURL'])>10){
		@unlink($file_path.$file_name);
		return $result['ObjectURL'];
	} else {
		log_error('Unable to upload Facebook Message Attachment ['.$file_url.'] to Internal Storage.' , $json_data);
		return false;
	}
}

function ping_admin($message , $from_log_error=false){
	$CI =& get_instance();
	$CI->Messenger_model->send_message(array(
			'recipient' => array(
					'id' => '1344093838979504', //Shervin
			),
			'message' => array(
					'text' => $message,
					'metadata' => 'SKIP_ECHO_LOGGING', //Prevent further impression logging on this.
			),
			'notification_type' => 'REGULAR' //Can be REGULAR, SILENT_PUSH or NO_PUSH
	) , $from_log_error );
}

function log_error($error_message, $json_data=array()){
	$CI =& get_instance();
	
	//First log error in DB:
	//TODO improve to log details like platform_pid and us_id based on error origin
	$res = $CI->Us_model->log_engagement(array(
			'message' => $error_message,
			'action_pid' => 1033, //Error logging
			'json_blob' => json_encode($json_data),
			'us_id' => 766,
			'platform_pid' => 766,
	));
	
	//Notifty admin via Messenger:
	ping_admin('Error #'.$res['id'].': '.$error_message, true);
	//Return error ID:
	return $res['id'];
}



function fetch_grandchild($pid,$grandpa_id,$json_data){
	$pid = intval($pid);
	if($pid<=0){
		return 0;
	}
	
	//We have an intent_pid, lets look for it!
	$CI =& get_instance();
	$node = $CI->Us_model->fetch_node($pid, 'fetch_top_plain');
	
	if(!isset($node['node_id'])){
		return 0;
	} elseif(!($node['grandpa_id']==$grandpa_id)){
		log_error('Referrer Grandpa for PID ['.$node['node_id'].'] is NOT equal to ['.$grandpa_id.'].' , $json_data);
		return 0;
	}
	
	//Everything was ok!
	return $node['node_id'];
}

function fb_page_pid($fb_page_id){
	$CI =& get_instance();
	$active_bots = $CI->config->item('active_bots');
	foreach($active_bots as $bot){
		if($bot['fb_page_id']==$fb_page_id){
			return $bot['entity_pid'];
		}
	}
	//Not found?!
	log_error('PHP Helper Function fb_page_pid() failed to find Facebook Page ID ['.$fb_page_id.'].');
	return 0;
}

function fb_time($unix_time){
	//It has milliseconds like "1458668856253", which we need to tranform for DB insertion:
	return date("Y-m-d H:i:s",round($unix_time/1000));
}

function curl_html($url){
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_POST => FALSE,
			CURLOPT_RETURNTRANSFER => TRUE,
	));
	return curl_exec($ch);
}

function alphanumeric_lower($string){
	return preg_replace("/[^a-z0-9]/", '', strtolower($string));
}

function boost_power(){
	ini_set('memory_limit', '-1');
	ini_set('max_execution_time', 600);
}

function ml_related($pid){
	//Patterns realted to api.ai machine learning:
	return in_array($pid,array(590,561,595));
}

function content_related($pid){
	//Patterns realted to conversational content
	return in_array($pid,array(567,575,576,577,578));
}

function objectToArray( $object ) {
	if( !is_object( $object ) && !is_array( $object ) ) {
		return $object;
	}
	if( is_object( $object ) ) {
		$object = (array) $object;
	}
	return array_map( 'objectToArray', $object );
}


function arrayToObject($array){
	$obj = new stdClass;
	foreach($array as $k => $v) {
		if(strlen($k)) {
			if(is_array($v)) {
				$obj->{$k} = arrayToObject($v); //RECURSION
			} else {
				$obj->{$k} = $v;
			}
		}
	}
	return $obj;
}


function user_login($user_email,$user_pass){
	
	$CI =& get_instance();
	
	if(!isset($user_email) || !filter_var($user_email, FILTER_VALIDATE_EMAIL)){
		//Invalid email:
		return array(
			'status' => 0,
			'message' => 'Invalid email address.',
		);
	} elseif(!isset($user_pass) || strlen($user_pass)<2){
		//Invalid password:
		return array(
			'status' => 0,
			'message' => 'Invalid password.',
		);
	} else {
		
		//Fetch user nodes with this email:
		//TODO We can wire this in Agolia for faster string search!
		$matching_users = $CI->Us_model->search_node($user_email,24);
		
		if(count($matching_users)<1){
			//We could not find this email linked to the email node
			return array(
				'status' => 0,
				'message' => 'Email "'.$user_email.'" not found.',
			);
		}
		
		//Now fetch entire user node:
		$user_node = $CI->Us_model->fetch_node($matching_users[0]['node_id']);
			
		if($user_node[0]['grandpa_id']!=1){
			//We could not find this email linked to the email node
			//This should technically never happen!
			return array(
				'status' => 0,
					'message' => 'Email not associated to a valid user.',
			);
		}
		
		//Now lets see if this user has a login password and if it matches the entered password
		$has_password = false;
		foreach($user_node as $link){
			if($link['parent_id']==44){
				//TODO: We should prevent duplicate password relations to be created
				//Yes they have a password link attached to the user node!
				$has_password = true;
				
				//Does it match login form entry?
				$matched_password = ($link['value']==sha1($user_pass));
				
				break;
			}
		}
		
		
		if(!$has_password){
			//We could not find this password linked to anyone!
			return array(
				'status' => 0,
				'message' => 'A login password has not been assigned to your account.',
			);
		} elseif(!$matched_password){
			//Invalid
			return array(
				'status' => 0,
					'message' => 'Invalid password for "'.$user_email.'".',
			);
		} else {
			
			//Good to go!
			//Assign some extra variables to return array:
			$user_node[0]['timestamp'] = time();
			
			//Detect if this user is a moderator, IF they belong to Moderators node:
			$user_node[0]['is_mod'] = ( $user_node[0]['parent_id']==18 ? 1 : 0 );
			
			//Append email data:
			$user_node[0]['email'] = $matching_users[0];
			
			return array(
				'status' => 1,
				'message' => 'Successfully authenticated user.',
				'link' => $user_node[0],
			);
		}
	}
}



function status_descriptions($status_id){
	//translates numerical status fields to descriptive meanings
	if($status_id==-2){
		return array(
				'valid' => 1,
				'name' => 'Deleted',
				'description' => 'When content does not follow community guidelines.',
		);
	} elseif($status_id==-1){
		return array(
				'valid' => 1,
				'name' => 'Updated',
				'description' => 'When a new update replaces this update.',
		);
	} elseif($status_id==0){
		return array(
				'valid' => 1,
				'name' => 'Pending',
				'description' => 'The initial status updates have when submitted by guest users.',
		);
	} elseif($status_id==1){
		return array(
				'valid' => 1,
				'name' => 'Active',
				'description' => 'Active node links with content association.',
		);
	} else {
		//This should never happen!
		return array(
				'valid' => 0,
				'name' => 'Unknown!',
				'description' => 'Error: '.$status_id.' is an unknown status ID.',
		);
	}
}


function action_type_descriptions($action_type_id){
	//translates numerical status fields to descriptive meanings
	if($action_type_id==-4){
		return array(
				'valid' => 1,
				'name' => 'Nuclear',
				'description' => 'Delete node and all direct child nodes.',
		);
	} elseif($action_type_id==-3){
		return array(
				'valid' => 1,
				'name' => 'Delete & Move',
				'description' => 'Delete a node and mode all child nodes to a different node.',
		);
	} elseif($action_type_id==-2){
		return array(
				'valid' => 1,
				'name' => 'Delete Node & Links',
				'description' => 'Delete childless node and all links.',
		);
	} elseif($action_type_id==-1){
		return array(
				'valid' => 1,
				'name' => 'Delete Link',
				'description' => 'Delete node link.',
		);
	} elseif($action_type_id==1){
		return array(
				'valid' => 1,
				'name' => 'Added',
				'description' => 'Created a new link from scratch.',
		);
	} elseif($action_type_id==2){
		return array(
				'valid' => 1,
				'name' => 'Updated',
				'description' => 'Updated the content or parent of the link.',
		);
	} elseif($action_type_id==3){
		return array(
				'valid' => 1,
				'name' => 'Sorted',
				'description' => 'Re-sorted child nodes.',
		);
	} elseif($action_type_id==4){
		return array(
				'valid' => 1,
				'name' => 'Linked',
				'description' => 'Linked two existing nodes to each other.',
		);
	} elseif($action_type_id==5){
		return array(
				'valid' => 1,
				'name' => 'Sys Updated',
				'description' => 'When the system updates the link.',
		);
	} else {
		//This should never happen!
		return array(
				'valid' => 0,
				'name' => 'Unknown!',
				'description' => 'Error: '.$action_type_id.' is unknown.',
		);
	}
}

function echo_html($status,$message,$set_flash=false){
	
	//Take action:
	if($set_flash){
		
		//Compile message:
		if($status){
			$message = '<div class="alert alert-success" role="alert"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span> '.$message.'</div>';
		} else {
			$message = '<div class="alert alert-danger"  role="alert"><span class="glyphicon glyphicon-warning-sign" aria-hidden="true"></span> '.$message.'</div>';
		}
		
		//Set to session:
		$CI =& get_instance();
		$CI->session->set_flashdata('hm', $message);
		
	} else {
		//Compile message:
		if($status){
			echo '<span class="success shrink"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span> '.$message.'</span>';
		} else {
			echo '<div><span class="danger shrink"><span class="glyphicon glyphicon-warning-sign" aria-hidden="true"></span> '.$message.' Refresh to try again.</span></div>';
		}
	}
		
	//Return status:
	return $status;
}

function format_timestamp($t){
	
	
	$time = time() - strtotime(substr($t,0,19)); // to get the time since that moment
	$time = ($time<1)? 1 : $time;
	$tokens = array (
			31536000 => 'year',
			2592000 => 'month',
			604800 => 'week',
			86400 => 'day',
			3600 => 'hr',
			60 => 'min',
			1 => 'sec'
	);
	
	foreach ($tokens as $unit => $text) {
		if ($time < $unit) continue;
		$numberOfUnits = floor($time / $unit);
		return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':'');
	}
}

function redirect_message($url,$message){
	//For message handling across the platform.
	$CI =& get_instance();
	$CI->session->set_flashdata('hm', $message);
	header("Location: ".$url);
	die();
}


function auth($donot_redirect=false){
	$CI =& get_instance();
	$user_data = $CI->session->userdata('user');	
	if($donot_redirect){
		return (isset($user_data['id']));
	} elseif(!isset($user_data['id'])){
		$node_id = $CI->uri->segment(1);
		redirect_message('/login','<div class="alert alert-danger" role="alert">Login to access this page.</div>');
	}
}

function auth_admin($donot_redirect=false){
	$CI =& get_instance();
	$user_data = $CI->session->userdata('user');
	$node_id = $CI->uri->segment(1);
	
	if($donot_redirect){
		return (isset($user_data['is_mod']) && $user_data['is_mod']);
	} elseif(!isset($user_data['is_mod']) || !$user_data['is_mod']){
		redirect_message('/login','<div class="alert alert-danger" role="alert">Login as moderator to access this page.</div>');
	}
}


function nodeName($text){
	//Cleans text and
	return substr(str_replace(' ','',preg_replace("/[^a-zA-Z0-9]+/", "", $text)),0,30);
}


function extract_patterns($value){
	//TODO merge this into $this->Apiai_model->sync_intent() as that is a more comprehensive function
	
	$prefs = array();
	$temp = explode('||',$value);
	
	//Anything?
	if(count($temp)<=1){
		//No pattern reference found:
		return $prefs;
	}
	
	
	//We have something...
	$CI =& get_instance();
	$grandparents = $CI->config->item('grand_parents');
	
	foreach($temp as $key=>$t){
		if($key>0){
			//Do we have any space?
			if(substr_count($t,' ')>0){
				$temp2 = explode(' ',$t,2);
				$num_attemp = intval($temp2[0]);
			} elseif(intval(substr($t,0,1))==substr($t,0,1)){
				$num_attemp = intval($t);
			} else {
				$num_attemp = 0;
			}
			
			if($num_attemp && !isset($prefs[$num_attemp])){
				$INs = $CI->Us_model->fetch_node($num_attemp);
				if(isset($INs[0]['node_id'])){
					//Process to see if we have User Says or Sysnonyms:
					//TODO show the related content in tool tip:
					/*
					$tooltip = null;
					foreach($INs as $key=>$IN){
						if(in_array($IN['parent_id'],array(561,595))){
							$tooltip .= $IN['value'].' ';
						}
					}
					
					if($tooltip){
						
					} else {
						
					}
					*/
					
					$prefs[$INs[0]['node_id']] = array(
							'clean_name' => $grandparents[$INs[0]['grandpa_id']]['sign'].nodeName($INs[0]['value']),
							'html' => '<a href="/'.$INs[0]['node_id'].'">'.$grandparents[$INs[0]['grandpa_id']]['sign'].nodeName($INs[0]['value']).'</a>',
					);
				}
			}
		}
	}
	
	return $prefs;
}

function echoValue($value){
	
	$value = nl2br($value);
	$prefs = extract_patterns($value);
		
	if(count($prefs)>0){
		foreach($prefs as $pid=>$res){
			//Replace in Value:
			$value = str_replace( '||'.$pid , $res['html'] , $value );
		}
	}
	
	return $value;
}

function one_two_explode($one,$two,$content){
	if(substr_count($content, $one)<1){
		return NULL;
	}
	$temp = explode($one,$content,2);
	$temp = explode($two,$temp[1],2);
	return trim($temp[0]);
}


function quick_message($fb_user_id,$message){
	$CI =& get_instance();
	
	//Detect what type of message is this?
	if(substr($message,0,8)=='/attach '){
		//Some sort of attachment:
		$attachment_type = trim(one_two_explode('/attach ',':',$message));
		
		if(in_array($attachment_type,array('image','audio','video','file'))){
			$temp = explode($attachment_type.':',$message,2);
			$attachment_url = $temp[1];
			$CI->Messenger_model->send_message(array(
					'recipient' => array(
							'id' => $fb_user_id
					),
					'message' => array(
							'attachment' => array(
									'type' => $attachment_type,
									'payload' => array(
											'url' => $attachment_url,
									),
							),
					),
					'notification_type' => 'REGULAR' //Can be REGULAR, SILENT_PUSH or NO_PUSH
			));
			return 1;
		}
		
		//Still here? oops:
		return 0;
		
	} else {
		
		//Assumption is that this is a regular text message:
		$CI->Messenger_model->send_message(array(
				'recipient' => array(
						'id' => $fb_user_id
				),
				'message' => array(
						'text' => $message,
				),
				'notification_type' => 'REGULAR' //Can be REGULAR, SILENT_PUSH or NO_PUSH
		));
		return 1;
	}
}

function echoNode($node,$key,$load_open=false){
	
	$CI =& get_instance();
	$user_data = $CI->session->userdata('user');
	$active_bots = $CI->config->item('active_bots');
	
	//Loop through parent nodes to apply any settings:
	//$status = status_descriptions($node[$key]['status']);
	$return_string = '';
	$flow_IN = ($node[0]['node_id']==$node[$key]['node_id']);
	$is_direct = ($node[$key]['ui_parent_rank']==1);
	$is_last_IN = ($node[$key]['node_id']==$node[0]['node_id'] && ( !isset($node[($key+1)]) || $node[($key+1)]['node_id']!=$node[0]['node_id']));
	$is_first_OUT = ($key>0 && $node[($key-1)]['node_id']==$node[0]['node_id'] && $node[$key]['node_id']!=$node[0]['node_id']);
	$attention_color = ( $flow_IN ? 'blue' : 'pink' ); //Used for elements that need more attention
	
	if($flow_IN){
		//Parent nodes:
		$href = '/'.$node[$key]['parents'][0]['node_id']; // SELF: $node[$key]['parents'][0]['node_id']==$node[0]['node_id']
		$anchor = $node[$key]['parents'][0]['value'];
		$direct_anchor = ( $is_direct ? 'DIRECT ' : '').'IN <span class="glyphicon glyphicon-arrow-right rotate45" aria-hidden="true"></span>';
	} else {
		//Child nodes:
		$href = '/'.$node[$key]['node_id'];
		$anchor = $node[$key]['parents'][0]['value'];
		$direct_anchor = ( $is_direct ? 'DIRECT ' : '').'OUT <span class="glyphicon glyphicon-arrow-up rotate45" aria-hidden="true"></span>';
	}
	
	
	//Go through the UI setting and extract details based on custom-coded nodes:
	$ui_setting = array(
			'template_matched' => 0,
			'workflow_dev' => 0,
			'auto_open' => ( $load_open ),
			'is_live' => 0, //Used for intents and entities that are being synced
			'value_template' => null,
			'followup_content' => null,
			'node_description' => null,
			'is_ml_related' => null,
	);
	
	
	//First from direct parents:
	if($key>0){
		foreach($node[$key]['parents'] as $k=>$p){
			
			//Custom Node-driven logical block:
			if($p['parent_id']==63 && substr_count($p['value'],'{value}')>0){
				
				//This belogs to the templating node:
				if(substr($p['value'],0,8)=='__eval__'){
					//This needs a PHP evaluation call to attempt to call the function and fill-in {value}
					eval("\$ui_setting['value_template'] = ".str_replace('__eval__','',str_replace('{value}','"'.$node[$key]['value'].'"',$p['value'])).";");
				} else {
					$ui_setting['value_template'] = str_replace('{value}',$node[$key]['value'],$p['value']);
				}
				
				// TODO #142 } elseif(substr_count($p['value'],'php_')){
				// Sample entry: php_md5(strtolower(trim("{value}")))
				
				$ui_setting['template_matched'] = 1;
				
			} elseif($p['parent_id']==237 && substr($node[$key]['value'],0,6)=='slice/'){
				
				//This is a Slice of video/audio:
				$times = explode(':',str_replace('slice/','',$node[$key]['value']),2);
				$start_time = intval($times[0]);
				$end_time = intval($times[1]);
				if($start_time>0 || $end_time>0){
					$ui_setting['template_matched'] = 1;
					$ui_setting['value_template'] = '<span class="fullwidthcontnet"><iframe src="//www.youtube.com/embed/'.$p['value'].'?start='.$start_time.'&end='.$end_time.'" frameborder="0" allowfullscreen class="video"></iframe></span>';
				}
				
			} elseif($p['parent_id']==910){
				//This is the admin description, which we can append:
				$ui_setting['node_description']= $p['value'];
			} elseif($p['parent_id']==463){
				$ui_setting['auto_open'] = 1;
			} elseif($p['parent_id']==628){
				//Workflow Under development
				$ui_setting['workflow_dev'] = 1;
			} elseif(in_array(590,array($p['parent_id'],$p['node_id']))){
				//Workflow Under development
				$ui_setting['is_live'] = 1;
			} elseif(($p['ui_parent_rank']==1 && ml_related($p['parent_id'])) || ml_related($p['node_id'])){
				$ui_setting['is_ml_related'] = 1;
			}
		}
	}
	
	
	//Now go through main templates, assuming this is a child node:
	if($key>0 && !$ui_setting['template_matched'] && !$flow_IN){
		//Try searching in the main parent:
		foreach($node as $p){
			if($node[0]['node_id']==$p['node_id'] && $p['parent_id']==63 && substr_count($p['value'],'{value}')>0){
				//This belogs to the templating node:
				$ui_setting['value_template'] = str_replace('{value}',$node[$key]['value'],$p['value']);
				$ui_setting['template_matched'] = 1;
			}
		}
	}
	
	
	//Start the display:
	$return_string .= '<div class="list-group-item'.( $key==0 ? ' is_top ' : ' node_details child-node ').($is_last_IN ? ' lastIN ':'').( $is_first_OUT ? ' first_OUT ' : '').($flow_IN?' is_parents ':' is_children ').' is_'.$node[$key]['parents'][0]['grandpa_id'].'" id="link'.$node[$key]['id'].'" data-link-index="'.$key.'" is-direct="'.( $is_direct? 1 : 0 ).'" edit-mode="0" new-parent-id="0" data-link-id="'.$node[$key]['id'].'" node-id="'.$node[$key]['node_id'].'">';
	
	$return_string .= 
	'<h4 class="list-group-item-heading handler node_top_node '.( $key==0 ? ' '.($flow_IN?'is_parents':'is_children').' is_'.$node[$key]['parents'][0]['grandpa_id'].' node_details' : '').'">'.
	
	'<a href="'.$href.'" class="expA"><span class="boldbadge badge '.( !$flow_IN? 'pink-bg' : 'blue-bg').( $node[$key]['parents'][0]['link_count']<=1 ? '-light' : '' ).'" aria-hidden="true" title="'.$node[$key]['parents'][0]['link_count'].' Gems =
1 DIRECT IN +
'.($node[$key]['parents'][0]['link_count']-$node[$key]['parents'][0]['out_count']-1).' INs +
'.($node[$key]['parents'][0]['out_count']-$node[$key]['parents'][0]['direct_out_count']).' OUTs +
'.($node[$key]['parents'][0]['direct_out_count']).' DIRECT OUTs" data-toggle="tooltip">'.
	
	//Link Count
	( $node[$key]['parents'][0]['direct_out_count']>0 ? $node[$key]['parents'][0]['direct_out_count'].'/' : '').$node[$key]['parents'][0]['link_count'].' '.$direct_anchor.'</span></a>'.
		
		'<a href="javascript:toggleValue('.$node[$key]['id'].');" class="'.( $key==0 ? 'parentTopLink' : 'parentLink '.( $ui_setting['auto_open'] ? 'zoom-out' : 'zoom-in' )).'">'.
			
		'<span class="glyphicon gh'.$node[$key]['id'].' glyphicon-triangle-'.( $ui_setting['auto_open'] || $key==0? 'bottom' : 'right' ).( $key==0 ? ' is_zero' : '' ).'" aria-hidden="true"></span>'.
		
				//TOP Title
				'<span class="anchor">'. $node[$key]['parents'][0]['sign'] . '<span id="tl'.$node[$key]['id'].'">'.$anchor.'</span></span>'.
				
				//Description
				( $ui_setting['node_description'] ? ' <span class="glyphicon glyphicon-info-sign grey hastt" aria-hidden="true" title="'.str_replace('"',"'",strip_tags($ui_setting['node_description'])).'" data-toggle="tooltip"></span>' : '').

				//Messaging content?
				( $ui_setting['is_ml_related'] ? ' <span class="glyphicon glyphicon-comment grey hastt '.$attention_color.'" aria-hidden="true" title="api.ai logic pattern, including messaging content that would be shared with users." data-toggle="tooltip"></span>' : '').
				
				//Workflow under dev?
	( $ui_setting['workflow_dev'] ? ' <span class="glyphicon glyphicon-alert grey hastt '.$attention_color.'" aria-hidden="true" title="Pending Development" data-toggle="tooltip"></span>' : '').
				
				//Is live via api.ai?
	( $ui_setting['is_live'] ? ' <span class="glyphicon glyphicon-phone grey hastt '.$attention_color.'" aria-hidden="true" title="Synced with api.ai which makes it accessible to our users on Messenger" data-toggle="tooltip"></span>' : '').
				
				//Is pending verification?
				( $node[$key]['status']<1 ? ' <span class="hastt grey" title="Pending Approval" data-toggle="tooltip"><span class="glyphicon glyphicon-warning-sign" aria-hidden="true" style="color:#FF0000;"></span></span>' : '' ).
				
				
				//Engagement Stats
				//TODO '<span class="grey hastt" style="padding-left:5px;" title="54 User Message Reads and 156 Foundation Clicks (Community Engagement)" data-toggle="tooltip"><span class="glyphicon glyphicon-eye-open" aria-hidden="true"></span> 210</span>'.
				
	(count($node[$key]['parents'])>19 ? ' <span class="'.$attention_color.'">!'.count($node[$key]['parents']).'IN</span>' : '' ).' <span class="sortconf"></span>'.
				
		'</a>'.
	'</h4>';
	
	$return_string .= '<div id="linkval'.$node[$key]['id'].'" class="link-details value '.( $key==0 ? 'is_top' : '').'" style="display:'.( $ui_setting['auto_open'] ?'block':'none').';">';
	$return_string .= '<'.( $key==0 ? 'h1' : 'p').' class="list-group-item-text node_h1 '.( $key==0 ? 'is_top' : '').'">';
	
	
	//We do not show {value} for DIRECT OUT because its a duplicate of its TOP title, which is redundant
	if($flow_IN || !$is_direct){
		//Did we find any template matches? If not, just display:
		if(!$ui_setting['template_matched']){
			$return_string .= echoValue($node[$key]['value']);
		} else {
			$return_string .= $ui_setting['value_template'];
		}
	}
	
	
	
	//This is only used for special nodes for now:
	$return_string .= $ui_setting['followup_content'];
	
	$return_string .= '</'.( $key==0 ? 'h1' : 'p').'>';
	$return_string .= '<div class="list-group-item-text hover node_stats"><div>';
	//TODO $return_string .= '<span title="Revision history to browse previous versions." data-toggle="tooltip" class="hastt"><a href="alert(\'Version Tracking Under Development\')"><span class="glyphicon glyphicon-backward" aria-hidden="true"></span> 5</a></span>';
	
	//Collector:
	$return_string .= '<span title="@'.nodeName($node[$key]['us_node'][0]['value']).' collected this Gem." data-toggle="tooltip"><a href="/'.$node[$key]['us_node'][0]['node_id'].'"><img src="https://www.gravatar.com/avatar/'.md5(strtolower(trim($node[$key]['us_node'][1]['value']))).'?d=identicon" class="mini-image" /></a></span>';
	
	//Pattern ID
	$bot_pid = ( $flow_IN && $key>0 ? $node[$key]['parent_id'] : $node[$key]['node_id'] );
	$ref_key = $bot_pid.'_'.$user_data['node_id'];
	$return_string .= ( ( $node[$key]['grandpa_id']==3 || $node[$key]['parents'][0]['grandpa_id']==3 ) ? ' <span title="Click to Copy URL to share Plugin on Messenger." data-toggle="tooltip" class="hastt clickcopy ref_'.$ref_key.'" data-clipboard-text="'.$active_bots[0]['bot_ref_url'].$ref_key.'"><img src="/img/icons/messenger.png" class="action_icon" /><b>'.$bot_pid.'</b></span>': '' );
	
	//Date
	$return_string .= '<span title="Added '.substr($node[$key]['timestamp'],0,19).' UTC" data-toggle="tooltip" class="hastt"><span class="glyphicon glyphicon-time" aria-hidden="true" style="margin-right:2px;"></span>'.format_timestamp($node[$key]['timestamp']).'</span>';
	
	//Update ID
	$return_string .= '<span title="Unique Update ID assigned per each edit." data-toggle="tooltip" class="hastt">#'.$node[$key]['id'].'</span>';
	
	if(auth_admin(1)){
		$return_string .= '<div class="btn-group"><button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="glyphicon glyphicon-option-horizontal" aria-hidden="true"></span></button>';
		$return_string .= '<ul class="dropdown-menu">';
		$return_string .= '<li><a href="javascript:edit_link('.$key.','.$node[$key]['id'].')" class="edit_link"><span class="glyphicon glyphicon-cog" aria-hidden="true"></span> Edit</a></li>';
		
		//Make sure this is not a grandpa before showing the delete button:
		$grandparents = $CI->config->item('grand_parents');
		if(!($key==0 && array_key_exists($node[$key]['node_id'],$grandparents))){
			$return_string .= '<li><a href="javascript:delete_link('.$key.','.$node[$key]['id'].');"><span class="glyphicon glyphicon-minus-sign" aria-hidden="true"></span> Remove</a></li>';
		}
		
		//Add search shortcuts:
		$return_string .= '<li><a href="https://www.google.com/search?q='.urlencode($node[$key]['value']).'" target="_blank"><span class="glyphicon glyphicon-search" aria-hidden="true"></span> Google</a></li>';
		$return_string .= '<li><a href="https://www.youtube.com/results?search_query='.urlencode($node[$key]['value']).'" target="_blank"><span class="glyphicon glyphicon-search" aria-hidden="true"></span> YouTube</a></li>';
		
		//Display inversing if NOT direct
		if(!$is_direct){
			//TODO $return_string .= '<li><a href="javascript:inverse_link('.$key.','.$node[$key]['id'].')"><span class="glyphicon glyphicon-refresh" aria-hidden="true"></span> Flip Direction</a></li>';
		}
		if($node[$key]['update_id']>0){
			//This gem has previous revisions:
			//TODO $return_string .= '<li><a href="javascript:browse_revisions('.$key.','.$node[$key]['id'].')"><span class="glyphicon glyphicon-refresh" aria-hidden="true"></span> Revisions</a></li>';
		}
		
    $return_string .= '</ul></div>';
    
	} else {
		$return_string .= ''; //<span title="Request admin access to start collecting Gems." data-toggle="tooltip" class="hastt"><span class="glyphicon glyphicon-alert" aria-hidden="true"></span> Limited Access</span>
	}
	$return_string .= '</div></div>';
	$return_string .= '</div>';
	$return_string .= '</div>';
	
	//Return:
	return $return_string;
}

function fetchMax($input_array,$searchKey){
	//Find the biggest $searchKey in $input_array:
	$max_ui_rank = 0;
	foreach($input_array as $child){
		if($child[$searchKey]>$max_ui_rank){
			$max_ui_rank = $child[$searchKey];
		}
	}
	return $max_ui_rank;
}

function echoFetchNode($link_id,$parent_id,$node_id,$regular=1,$load_open=false){
	
	$CI =& get_instance();
	
	//First lets make sure the link is not updated:
	$link = $CI->Us_model->fetch_link($link_id);
	if(intval($link['status'])<0 && intval($link['update_id'])>0){
		//Update the link ID to the latest link:
		$link_id = $link['update_id'];
	}
	
	//Load $node_id with parent $parent_id
	$focus_node = ($regular ? $parent_id : $node_id);
	$node = $CI->Us_model->fetch_full_node($focus_node);
	
	foreach($node as $key=>$value){
		if($value['id']==$link_id){
			return echoNode($node,$key,$load_open);
		}
	}
	
	//There is an issue if we're still here:
	return '<div class="list-group-item">Error finding Gem'.$link_id.' in Node'.$focus_node.'</div>';
}
