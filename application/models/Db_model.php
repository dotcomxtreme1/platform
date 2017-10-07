<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

class Db_model extends CI_Model {
	
	//This model handles all DB calls from our local database.
	
	function __construct() {
		parent::__construct();
	}
	
	
	/* ******************************
	 * Users
	 ****************************** */
	
	//Called upon user login to save their Challenges/Runs into the session:
	function u_privileges($u_id){
		
		if(intval($u_id)<=0){
			return false;
		}
		
		//The framework:
		$user_access = array(
				'c' => array(), //Challenges
		);
		
		//Fetch Runs:
		$this->db->select('ru.ru_r_id');
		$this->db->from('v5_cohort_students ru');
		$this->db->where('ru.ru_u_id',$u_id);
		$this->db->where('ru.ru_status >=',2); //Leader or Admin
		$q = $this->db->get();
		$run_users = $q->result_array();
		foreach($run_users as $ru){
			if(!in_array($ru['ru_r_id'], $user_access['r'], true)){
				array_push($user_access['r'] , $ru['ru_r_id']);
			}
		}
		
		if(count($user_access['r'])>0){
			//Now fetch unique challenges:
			$this->db->select('r.r_c_id');
			$this->db->from('v5_cohorts r');
			$this->db->where_in('r.r_id',$user_access['r']);
			$this->db->or_where('r.r_creator_id',$u_id);
			$q = $this->db->get();
			$runs = $q->result_array();
			foreach($runs as $r){
				if(!in_array($r['r_c_id'], $user_access['c'], true)){
					array_push($user_access['c'] , $r['r_c_id']);
				}
			}
		}
		
		//Any challenges they directly created?
		$this->db->select('c.c_id');
		$this->db->from('v5_intents c');
		$this->db->where('c.c_creator_id',$u_id); //Challenges they created them selves
		$this->db->where('c.c_status >=',-1); //Deleted by user, but not removed by moderator.
		$this->db->where('c.c_is_grandpa',true); //necessary here since we have many none-grandpa challenges
		$q = $this->db->get();
		$bootcamps = $q->result_array();
		foreach($bootcamps as $c){
			if(!in_array($c['c_id'], $user_access['c'], true)){
				array_push($user_access['c'] , $c['c_id']);
			}
		}
		
		return $user_access;
	}
	
	function u_fetch($match_columns){
		//Fetch the target gems:
		$this->db->select('*');
		$this->db->from('v5_users u');
		foreach($match_columns as $key=>$value){
			$this->db->where($key,$value);
		}
		$q = $this->db->get();
		return $q->result_array();
	}
	
	function u_create($insert_columns){
		
		//Make sure required fields are here:
		if(!isset($insert_columns['u_fname'])){
			log_error('u_create() Missing u_fname.',$insert_columns,2);
			return false;
		} elseif(!isset($insert_columns['u_lname'])){
			log_error('u_create() Missing u_lname.',$insert_columns,2);
			return false;
		} elseif(!isset($insert_columns['u_fb_id'])){
			log_error('u_create() Missing u_fb_id.',$insert_columns,2);
			return false;
		}
		
		
		//Missing anything?
		if(!isset($insert_columns['u_timestamp'])){
			$insert_columns['u_timestamp'] = date("Y-m-d H:i:s");
		}
		if(!isset($insert_columns['u_status'])){
			$insert_columns['u_status'] = 1;
		}
		if(!isset($insert_columns['u_url_key'])){
			$insert_columns['u_url_key'] = preg_replace("/[^A-Za-z0-9]/", '', $insert_columns['u_fname'].$insert_columns['u_lname']);
		}
		
		
		//Check u_url_key to be unique, and if not, add a number and increment:
		$original_u_url_key = $insert_columns['u_url_key'];
		$is_duplicate = true;
		$increment = 0;
		while($is_duplicate){
			$matching_users = $this->u_fetch(array(
					'u_url_key' => $insert_columns['u_url_key'],
			));
			
			if(count($matching_users)==0){
				//Yes!
				$is_duplicate = false;
				break;
			} else {
				//This is a duplicate:
				$increment++;
				$insert_columns['u_url_key'] = $original_u_url_key.$increment;
			}
		}
		
		//Lets now add:
		$this->db->insert('v5_users', $insert_columns);
		
		//Fetch inserted id:
		$insert_columns['u_id'] = $this->db->insert_id();
		
		return $insert_columns;
	}
	
	function u_update($user_id,$update_columns){
	    //Update first
	    $this->db->where('u_id', $user_id);
	    $this->db->update('v5_users', $update_columns);
	    //Return new row:
	    $users = $this->u_fetch(array(
	        'u_id' => $user_id
	    ));
	    return $users[0];
	}
	
	
	function c_admins($c_id){
	    //Fetch the admins of the bootcamps
	    $this->db->select('*');
	    $this->db->from('v5_users u');
	    $this->db->join('v5_bootcamp_admins ba', 'ba.ba_u_id = u.u_id');
	    $this->db->where('ba.ba_status >=',0);
	    $this->db->where('ba.ba_c_id',$c_id);
	    $this->db->where('u.u_status >=',0);
	    $this->db->order_by('ba.ba_status','DESC');
	    $this->db->order_by('ba.ba_team_display','DESC');
	    $q = $this->db->get();
	    return $q->result_array();
	}
	
	
	function u_bootcamps($match_columns){
	    $this->db->select('*');
	    $this->db->from('v5_intents c');
	    $this->db->join('v5_bootcamp_admins ba', 'ba.ba_c_id = c.c_id');
	    $this->db->order_by('c.c_status', 'DESC');
	    $this->db->order_by('c.c_objective', 'ASC');
	    foreach($match_columns as $key=>$value){
	        $this->db->where($key,$value);
	    }
	    $q = $this->db->get();
	    return $q->result_array();
	}
	
	
	function u_fb_search($u_fb_id){
		//A function to check or create a new user using FB id:
		
		//Search for current users:
		$matching_users = $this->u_fetch(array(
				'u_fb_id' => $u_fb_id,
		));
		
		$u_id = null;
		
		//What did we find?
		if(count($matching_users) == 1){
			
			//Yes, we found them!
			$u_id = $matching_users[0]['u_id'];
			
		} elseif(count($matching_users) <= 0){
			
			//This is a new user that needs to be registered!
			$u_id = $this->u_fb_create($u_fb_id);
			
		} else {
			//Inconsistent data:
			log_error('Found multiple users for Facebook ID ['.$u_fb_id.']',$matching_users,2);
			return 0;
		}
		
		
		if($u_id){
			//We found it!
			return $u_id;
		} else {
			//Ooops, some error!
			log_error('Failed to create/fetch user using their Facebook ID ['.$u_fb_id.']',null,2);
			return 0;
		}
	}
	
	function u_fb_fetch($full_name){		
		//Fetch the user using their full name
		$this->db->select('u_fb_id, LOWER(CONCAT(u_fname," ",u_lname)) AS full_name');
		$this->db->from('v5_users u');
		$this->db->where('full_name',trim(strtolower($full_name)));
		$this->db->where('u_status >',0);
		$q = $this->db->get();
		$matching_users = $q->result_array();
		
		if(count($matching_users)>0){
			
			if(count($matching_users)>=2){
				//Multiple users found!
				log_error('Found '.count($matching_users).' users with a full name of ['.$full_name.']. First result returned.',$matching_users,2);
			}
			
			//Return first result:
			return $matching_users[0]['u_fb_id'];
		}
		
		//Ooops, nothing found!
		return null;
	}
	
	
	function u_fb_create($u_fb_id){
		
		//Call facebook messenger API and get user details
		//https://developers.facebook.com/docs/messenger-platform/user-profile/
		$fb_profile = $this->Facebook_model->fetch_profile($u_fb_id);
		
		if(!isset($fb_profile['first_name'])){
			//There was an issue accessing this on FB
			return false;
		}
		
		//Do we already have this person?
		$matching_users = $this->u_fetch(array(
				'u_fb_id' => $u_fb_id,
		));
		
		//Is this user in our system already?
		if(count($matching_users)>0){
			if(count($matching_users)>=2){
				log_error('Found multiple users for Facebook ID ['.$u_fb_id.']',$matching_users,2);
			}
			
			//Yes, just assume the user is the first result:
			return $matching_users[0]['u_id'];
		}
		
		//Split locale into language and country
		$locale = explode('_',$fb_profile['locale'],2);
		
		//Create user
		$udata = $this->u_create(array(
				'u_fb_id' 			=> $u_fb_id,
				'u_fname' 			=> $fb_profile['first_name'],
				'u_lname' 			=> $fb_profile['last_name'],
				'u_timezone' 		=> $fb_profile['timezone'],
				'u_image_url' 		=> $fb_profile['profile_pic'],
				'u_gender'		 	=> strtolower(substr($fb_profile['gender'],0,1)),
				'u_language' 		=> $locale[0],
				'u_country_code' 	=> $locale[1],
		));
		
		return $udata['u_id'];
	}
	
	
	
	
	
	
	/* ******************************
	 * i Messages
	 ****************************** */
	
	function i_fetch($match_columns){
		$this->db->select('*');
		$this->db->from('v5_media');
		foreach($match_columns as $key=>$value){
			$this->db->where($key,$value);
		}
		$q = $this->db->get();
		return $q->result_array();
	}
	
	function i_create($insert_columns){
		//Missing anything?
		if(!isset($insert_columns['i_c_id'])){
			return false;
		} elseif(!isset($insert_columns['i_message'])){
			return false;
		}
		
		//Autocomplete required
		if(!isset($insert_columns['i_timestamp'])){
			$insert_columns['i_timestamp'] = date("Y-m-d H:i:s");
		}
		if(!isset($insert_columns['i_status'])){
			$insert_columns['i_status'] = 0; //Drafting...
		}
		if(!isset($insert_columns['i_rank'])){
			$insert_columns['i_rank'] = 1;
		}
		if(!isset($insert_columns['i_drip_time'])){
			$insert_columns['i_drip_time'] = 0;
		}
		
		//Lets now add:
		$this->db->insert('v5_media', $insert_columns);
		
		//Fetch inserted id:
		$insert_columns['i_id'] = $this->db->insert_id();
		
		return $insert_columns;
	}
	
	function i_update($i_id,$update_columns){
		$this->db->where('i_id', $i_id);
		$this->db->update('v5_media', $update_columns);
		return $this->db->affected_rows();
	}
	
	/* ******************************
	 * Runs
	 ****************************** */
	
	function r_fetch($match_columns){
	    
		//Missing anything?
		$this->db->select('r.*');
		$this->db->from('v5_cohorts r');
		$this->db->join('v5_cohort_students ru', 'ru.ru_r_id = r.r_id', 'left');
		foreach($match_columns as $key=>$value){
			$this->db->where($key,$value);
		}
		$this->db->group_by('r.r_id');
		$this->db->order_by('r.r_start_date','ASC');
		$q = $this->db->get();
		
		$runs = $q->result_array();
		foreach($runs as $key=>$value){
		    /*
		     * //TODO: Determine enrolled students and apply potential registration limitations
		    $runs[$key]['r__enrolled_students'] = $this->Db_model->ru_fetch(array(
		        'ru.ru_r_id'	    => $value['r_id'],
		        'ru.ru_status <'	=> 2, //TODO Review: Regular students
		        'u.u_status <'		=> 2, //TODO Review: Regular students
		    ));
		    */
		}
		
		return $runs;
	}
	
	
	
	
	/* ******************************
	 * Bootcamps
	 ****************************** */
	
	function ru_fetch($match_columns){
		$this->db->select('*');
		$this->db->from('v5_cohort_students ru');
		$this->db->join('v5_users u', 'u.u_id = ru.ru_u_id');
		foreach($match_columns as $key=>$value){
			$this->db->where($key,$value);
		}
		$q = $this->db->get();
		return $q->result_array();
	}
	
	
	function c_full_fetch($match_columns){
	    //Missing anything?
	    $this->db->select('*');
	    $this->db->from('v5_intents c');
	    foreach($match_columns as $key=>$value){
	        $this->db->where($key,$value);
	    }
	    $q = $this->db->get();
	    $bootcamps = $q->result_array();
	    
	    //Now append more data:
	    foreach($bootcamps as $key=>$c){
	        //Start estimating hours calculation:
	        $bootcamps[$key]['c__estimated_hours'] = $bootcamps[$key]['c_time_estimate'];
	        
	        //Fetch Curriculum:
	        $bootcamps[$key]['c__task_count'] = 0;
	        $bootcamps[$key]['c__sprints'] = $this->Db_model->cr_outbound_fetch(array(
	            'cr.cr_inbound_id' => $c['c_id'],
	            'cr.cr_status >=' => 0,
	        ));
	        
	        foreach($bootcamps[$key]['c__sprints'] as $sprint_key=>$sprint_value){
	            //Addup sprint estimated time:
	            $bootcamps[$key]['c__estimated_hours'] += $sprint_value['c_time_estimate'];
	            //Introduce sprint total time:
	            $bootcamps[$key]['c__sprints'][$sprint_key]['c__estimated_hours'] = $sprint_value['c_time_estimate'];
	            
	            //Fetch sprint tasks at level 3:
	            $bootcamps[$key]['c__sprints'][$sprint_key]['c__tasks'] = $this->Db_model->cr_outbound_fetch(array(
	                'cr.cr_inbound_id' => $sprint_value['c_id'],
	                'cr.cr_status >=' => 0,
	            ));
	            
	            //Addup task values:
	            foreach($bootcamps[$key]['c__sprints'][$sprint_key]['c__tasks'] as $task_key=>$task_value){
	                //Addup task estimated time:
	                $bootcamps[$key]['c__estimated_hours'] += $task_value['c_time_estimate'];
	                $bootcamps[$key]['c__sprints'][$sprint_key]['c__estimated_hours'] += $task_value['c_time_estimate'];
	                $bootcamps[$key]['c__task_count']++;
	            }
	        }
	        
	        //Fetch cohorts:
	        $bootcamps[$key]['c__cohorts'] = $this->r_fetch(array(
	            'r.r_c_id' => $c['c_id'],
	            'r.r_status >=' => 0,
	        ));
	        
	        //Fetch admins:
	        $bootcamps[$key]['c__admins'] =  $this->Db_model->c_admins($c['c_id']);
	    }
	    
	    return $bootcamps;
	}
	
	function c_fetch($match_columns){
	    //Missing anything?
	    $this->db->select('c.*, COUNT(DISTINCT r.r_id) AS count_runs, COUNT(DISTINCT ru.ru_u_id) AS count_users');
	    $this->db->from('v5_intents c');
	    $this->db->join('v5_cohorts r', 'r.r_c_id = c.c_id', 'left');
	    $this->db->join('v5_cohort_students ru', 'ru.ru_r_id = r.r_id', 'left');
	    $this->db->group_by('c.c_id');
	    foreach($match_columns as $key=>$value){
	        $this->db->where($key,$value);
	    }
	    $q = $this->db->get();
	    return $q->result_array();
	}
	
	function c_plain_fetch($match_columns){
		//Missing anything?
		$this->db->select('c.*');
		$this->db->from('v5_intents c');
		foreach($match_columns as $key=>$value){
			$this->db->where($key,$value);
		}
		$q = $this->db->get();
		return $q->row_array();
	}
	
	
	function cr_outbound_fetch($match_columns){
		//Missing anything?
		$this->db->select('*');
		$this->db->from('v5_intents c');
		$this->db->join('v5_intent_links cr', 'cr.cr_outbound_id = c.c_id');
		foreach($match_columns as $key=>$value){
			$this->db->where($key,$value);
		}
		$this->db->order_by('cr.cr_outbound_rank','ASC');
		$q = $this->db->get();
		return $q->result_array();
	}
	
	function cr_inbound_fetch($match_columns){
		//Missing anything?
		$this->db->select('*');
		$this->db->from('v5_intents c');
		$this->db->join('v5_intent_links cr', 'cr.cr_inbound_id = c.c_id');
		foreach($match_columns as $key=>$value){
			$this->db->where($key,$value);
		}
		$this->db->order_by('cr.cr_inbound_rank','ASC');
		$q = $this->db->get();
		return $q->result_array();
	}
	
	
	
	function cr_update($cr_id,$update_columns,$column='cr_id'){
		$this->db->where($column, $cr_id);
		$this->db->update('v5_intent_links', $update_columns);
		return $this->db->affected_rows();
	}
	
	
	
	
	
	function max_value($table,$column,$match_columns){
		$this->db->select('MAX('.$column.') as largest');
		$this->db->from($table);
		foreach($match_columns as $key=>$value){
			$this->db->where($key,$value);
		}
		$q = $this->db->get();
		$stats = $q->row_array();
		return intval($stats['largest']);
	}
	
	function cr_create($insert_columns){
		
		//Missing anything?
		if(!isset($insert_columns['cr_inbound_id']) || !isset($insert_columns['cr_inbound_rank'])){
			return false;
		} elseif(!isset($insert_columns['cr_outbound_id']) || !isset($insert_columns['cr_outbound_rank'])){
			return false;
		} elseif(!isset($insert_columns['cr_creator_id'])){
			return false;
		}
		
		//Autocomplete required
		if(!isset($insert_columns['cr_timestamp'])){
			$insert_columns['cr_timestamp'] = date("Y-m-d H:i:s");
		}
		if(!isset($insert_columns['cr_status'])){
			$insert_columns['cr_status'] = 1; //Live link
		}
		
		//Lets now add:
		$this->db->insert('v5_intent_links', $insert_columns);
		
		//Fetch inserted id:
		$insert_columns['cr_id'] = $this->db->insert_id();
		
		return $insert_columns;
	}
	
	function c_update($c_id,$update_columns){
	    $this->db->where('c_id', $c_id);
	    $this->db->update('v5_intents', $update_columns);
	    return $this->db->affected_rows();
	}
	
	function r_update($r_id,$update_columns){
	    $this->db->where('r_id', $r_id);
	    $this->db->update('v5_cohorts', $update_columns);
	    return $this->db->affected_rows();
	}
	
	
	
	function r_create($insert_columns){
	    
	    //Lets now add:
	    $this->db->insert('v5_cohorts', $insert_columns);
	    
	    //Fetch inserted id:
	    $insert_columns['r_id'] = $this->db->insert_id();
	    
	    return $insert_columns;
	}
	
	function c_create($insert_columns){
		
		//Missing anything?
		if(!isset($insert_columns['c_timestamp'])){
			$insert_columns['c_timestamp'] = date("Y-m-d H:i:s");
		}
		if(!isset($insert_columns['c_status'])){
			$insert_columns['c_status'] = 0; //Being prepared
		}
		if(!isset($insert_columns['c_is_grandpa'])){
			$insert_columns['c_is_grandpa'] = 'f';
		}
		if(!isset($insert_columns['c_algolia_id'])){
		    $insert_columns['c_algolia_id'] = 0;
		}
		
		//Lets now add:
		$this->db->insert('v5_intents', $insert_columns);
		
		//Fetch inserted id:
		$insert_columns['c_id'] = $this->db->insert_id();
		
		return $insert_columns;
	}
	
	
	/* ******************************
	 * Other
	 ****************************** */
	
	function ba_create($insert_columns){
	    
	    //Missing anything?
	    if(!isset($insert_columns['ba_timestamp'])){
	        $insert_columns['ba_timestamp'] = date("Y-m-d H:i:s");
	    }
	    if(!isset($insert_columns['ba_status'])){
	        $insert_columns['ba_status'] = 2; //Admin
	    }
	    if(!isset($insert_columns['ba_team_display'])){
	        $insert_columns['ba_team_display'] = 't'; //Show this person
	    }
	    
	    //TODO Validate required fields here... (like ba_c_id, etc...)
	    
	    //Lets now add:
	    $this->db->insert('v5_bootcamp_admins', $insert_columns);
	    
	    //Fetch inserted id:
	    $insert_columns['ba_id'] = $this->db->insert_id();
	    
	    return $insert_columns;
	}
	
	function log_engagement($link_data){
		
		//These are required fields:
		if(!isset($link_data['e_medium_id'])){
			log_error('log_engagement Missing e_medium_id.' , $link_data);
			return false;
		} elseif(!isset($link_data['e_medium_action_id'])){
			log_error('log_engagement Missing e_medium_action_id.' , $link_data, $link_data['e_medium_id']);
			return false;
		} elseif(!isset($link_data['e_creator_id'])){
			//Try to fetch user ID from session:
			$user_data = $this->session->userdata('user');
			if(isset($user_data['u_id']) && intval($user_data['u_id'])>0){
				$link_data['e_creator_id'] = $user_data['u_id'];
			} elseif($link_data['e_medium_action_id']==0) {
				//This is for error loggin, no need for a user...
				$link_data['e_creator_id'] = 0;
			} else {
				//This should not happen, return error:
				log_error('log_engagement Missing e_creator_id.' , $link_data, $link_data['e_medium_id']);
				return false;
			}
		}
		
		//These are optional and could have defaults:
		if(!isset($link_data['e_timestamp'])){
			$link_data['e_timestamp'] = date("Y-m-d H:i:s");
		}
		if(!isset($link_data['e_c_id'])){
			$link_data['e_c_id'] = 0;
		}
		if(!isset($link_data['e_i_id'])){
			$link_data['e_i_id'] = 0;
		}
		if(!isset($link_data['e_json'])){
			$link_data['e_json'] = '';
		}
		if(!isset($link_data['e_message'])){
			$link_data['e_message'] = '';
		}
		
		
		//Lets now add:
		$this->db->insert('v5_engagement', $link_data);
		
		//Fetch inserted id:
		$link_data['e_id'] = $this->db->insert_id();
		
		//Boya!
		return $link_data;
	}
	
	
	
	
	
	
	function sync_algolia($c_id=null){
		
		boost_power();
		
		$website = $this->config->item('website');
		
		if(is_dev()){
		    return file_get_contents($website['url']."process/algolia/".$c_id);
		}
		
		//Include PHP library:
		require_once('application/libraries/algoliasearch.php');
		$client = new \AlgoliaSearch\Client("49OCX1ZXLJ", "84a8df1fecf21978299e31c5b535ebeb");
		$index = $client->initIndex('bootcamps');
		
		//Fetch all nodes:
		$limits = array(
				'c.c_status >=' => 0,
		);
		if($c_id){
			$limits['c_id'] = $c_id;
		} else {
			$index->clearIndex();
		}
		$bootcamps = $this->c_fetch($limits);
		
		if(count($bootcamps)<=0){
			//Nothing found here!
			return false;
		}
		
		//Buildup this array to save to search index
		$return = array();
		foreach($bootcamps as $bootcamp){
			//Adjust Algolia ID:
			if(isset($bootcamp['c_algolia_id']) && intval($bootcamp['c_algolia_id'])>0){
				$bootcamp['objectID'] = intval($bootcamp['c_algolia_id']);
			}
			unset($bootcamp['c_algolia_id']);
			
			//Add to main array
			array_push( $return , $bootcamp);
		}
		
		//$obj = json_decode(json_encode($return), FALSE);
		//print_r($return);
		
		if($c_id){
			
			if($bootcamp['c_status']>=0){
				
				if(isset($bootcamp['c_algolia_id']) && intval($bootcamp['c_algolia_id'])>0){
					//Update existing
					$obj_add_message = $index->saveObjects($return);
				} else {
					//Create new ones
					$obj_add_message = $index->addObjects($return);
					
					//Now update database with the objectIDs:
					if(isset($obj_add_message['objectIDs']) && count($obj_add_message['objectIDs'])>0){
						foreach($obj_add_message['objectIDs'] as $key=>$algolia_id){
							$this->Db_model->c_update( $return[$key]['c_id'] , array(
									'c_algolia_id' => $algolia_id,
							));
						}
					}
				}
				
			} elseif(isset($bootcamp['c_algolia_id']) && intval($bootcamp['c_algolia_id'])>0) {
				//Delete object:
				$index->deleteObject($bootcamp['c_algolia_id']);
			}
			
		} else {
			//Create new ones
			$obj_add_message = $index->addObjects($return);
			
			//Now update database with the objectIDs:
			if(isset($obj_add_message['objectIDs']) && count($obj_add_message['objectIDs'])>0){
				foreach($obj_add_message['objectIDs'] as $key=>$algolia_id){
					$this->Db_model->c_update( $return[$key]['c_id'] , array(
							'c_algolia_id' => $algolia_id,
					));
				}
			}
		}			
		
		
		return array(
				'c_id' => $c_id,
				'bootcamps' => $bootcamps,
				'output' => $obj_add_message['objectIDs'],
		);
	}
}
