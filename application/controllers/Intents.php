<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Intents extends CI_Controller
{

    function __construct()
    {
        parent::__construct();

        $this->output->enable_profiler(FALSE);
    }


    //For trainers to see and manage an intent:
    function intent_manage( $inbound_c_id=7240  /* default intent to load */ ){

        //Authenticate level 2 or higher, redirect if not:
        $udata = auth(array(1308,1280),1);

        //Fetch intent:
        $cs = $this->Db_model->c_fetch(array(
            'c.c_id' => $inbound_c_id,
            'c.c_status >' => 0,
        ), 2);
        if(!isset($cs[0])){
            die('Intent ID '.$inbound_c_id.' not found');
        }

        if(isset($_GET['raw'])){
            echo_json($cs);
            exit;
        }

        if($inbound_c_id==7240){
            //Also count orphan intents:
            $orphan_c_count = count($this->Db_model->c_fetch(array(
                'c.c__is_orphan' => 1,
                'c.c_status >' => 0,
            )));
        } else {
            $orphan_c_count = 0;
        }

        //Load view
        $data = array(
            'title' => $cs[0]['c_outcome'],
            'c' => $cs[0],
            'orphan_c_count' => $orphan_c_count,
            'breadcrumb' => array(), //Even if empty show it, we might populate it soon below
            'c__inbounds' => $this->Db_model->cr_inbound_fetch(array(
                'cr.cr_outbound_c_id' => $inbound_c_id,
                'cr.cr_status >=' => 1,
            ), array('c__child_intents')),
        );

        $this->load->view('console/console_header', $data);
        $this->load->view('intents/intent_manage' , $data);
        $this->load->view('console/console_footer');
    }

    function orphan(){

        //Authenticate level 2 or higher, redirect if not:
        $udata = auth(array(1308,1280),1);

        //Fetch intent:
        $orphan_cs = $this->Db_model->c_fetch(array(
            'c.c__is_orphan' => 1,
            'c.c_status >' => 0,
        ), 1);

        //Load view
        $data = array(
            'title' => 'Orphan Intents',
            'breadcrumb' => array(
                array(
                    'link' => null,
                    'anchor' => 'Orphan Intents',
                )
            ),
        );

        $this->load->view('console/console_header', $data);
        $this->load->view('intents/intent_manage' , array('orphan_cs' => $orphan_cs));
        $this->load->view('console/console_footer');
    }


    function intent_public($c_id){

        //Fetch data:
        $cs = $this->Db_model->c_fetch(array(
            'c.c_id' => $c_id,
            'c.c_status >' => 0,
        ), 2, array('i') );


        //TODO Make sure this intent belongs to the public home page tree

        //Validate Intent:
        if(!isset($cs[0])){
            //Invalid key, redirect back:
            redirect_message('/','<div class="alert alert-danger" role="alert">Invalid Intent ID</div>');
        }


        //Load home page:
        $this->load->view('front/shared/f_header' , array(
            'title' => $cs[0]['c_outcome'],
        ));
        $this->load->view('intents/landing_page' , array(
            'c' => $cs[0],
        ));
        $this->load->view('front/shared/f_footer');
    }

    /* ******************************
     * c Intent Processing
     ****************************** */

    function c_new(){

        $udata = auth(array(1308,1280));
        if(!$udata){
            return echo_json(array(
                'status' => 0,
                'message' => 'Invalid Session. Refresh the Page to Continue',
            ));
        } elseif(!isset($_POST['c_id']) || intval($_POST['c_id'])<=0){
            return echo_json(array(
                'status' => 0,
                'message' => 'Invalid Intent ID',
            ));
        } elseif(!isset($_POST['c_outcome']) || strlen($_POST['c_outcome'])<=0){
            return echo_json(array(
                'status' => 0,
                'message' => 'Missing Intent Outcome',
            ));
        } elseif(!isset($_POST['link_c_id'])){
            return echo_json(array(
                'status' => 0,
                'message' => 'Missing Link Intent ID',
            ));
        } elseif(!isset($_POST['next_level'])){
            return echo_json(array(
                'status' => 0,
                'message' => 'Missing Level',
            ));
        }

        $_POST['link_c_id'] = intval($_POST['link_c_id']);

        //Validate Original intent:
        $inbound_intents = $this->Db_model->c_fetch(array(
            'c.c_id' => intval($_POST['c_id']),
        ), 1);
        if(count($inbound_intents)<=0){
            return echo_json(array(
                'status' => 0,
                'message' => 'Invalid Intent ID',
            ));
        }

        if(!$_POST['link_c_id']){

            //Set default new hours:
            $default_new_hours = 0.05; //3 min default
            $recursive_query = array(
                'c__tree_outputs' => 1,
                'c__tree_hours' => $default_new_hours,
            );

            //Create intent:
            $new_c = $this->Db_model->c_create(array(
                'c_inbound_u_id' => $udata['u_id'],
                'c_outcome' => trim($_POST['c_outcome']),
                'c_time_estimate' => $default_new_hours,
                'c_is_output' => 1, //Default
                'c__tree_outputs' => 1, //Default
                'c__tree_inputs' => 0,
                'c__tree_hours' => $default_new_hours,
            ));

            //Log Engagement for New Intent:
            $this->Db_model->e_create(array(
                'e_inbound_u_id' => $udata['u_id'],
                'e_text_value' => 'Intent ['.$new_c['c_outcome'].'] created',
                'e_json' => array(
                    'input' => $_POST,
                    'before' => null,
                    'after' => $new_c,
                ),
                'e_inbound_c_id' => 20, //New Intent
                'e_outbound_c_id' => $new_c['c_id'],
            ));

        } else {

            $new_cs = $this->Db_model->c_fetch(array(
                'c_id' => $_POST['link_c_id'],
                'c.c_status >' => 0,
            ), ( 3 - $_POST['next_level'] ));
            if(count($new_cs)<=0){
                return echo_json(array(
                    'status' => 0,
                    'message' => 'Invalid Linked Intent ID',
                ));
            }
            $new_c = $new_cs[0];


            //Make sure none of the parents are the same:
            if($new_c['c_id']==$_POST['c_id']){
                return echo_json(array(
                    'status' => 0,
                    'message' => 'You cannot add "'.$new_c['c_outcome'].'" as its own child.',
                ));
            } else {
                //check for all parents:
                $parent_tree = $this->Db_model->c_recursive_fetch($_POST['c_id']);
                if(in_array($new_c['c_id'],$parent_tree['c_flat'])){
                    return echo_json(array(
                        'status' => 0,
                        'message' => 'You cannot add "'.$new_c['c_outcome'].'" as its own grandchild.',
                    ));
                }
            }

            //Make sure this is not a duplicate level 2 intent:
            if($_POST['next_level']==2){
                foreach($inbound_intents[0]['c__child_intents'] as $current_c){
                    if($current_c['c_id']==$_POST['link_c_id']){
                        //Ooops, this is already added in Level 2, cannot add again:
                        return echo_json(array(
                            'status' => 0,
                            'message' => '"'.$new_c['c_outcome'].'" is already added to this Action Plan and cannot be added again.',
                        ));
                    }
                }
            }

            //Remove orphan status if that was the case before:
            if(intval($new_c['c__is_orphan'])){
                $this->Db_model->c_update( $new_c['c_id'] , array(
                    'c__is_orphan' => 0,
                ));
            }

            //Prepare recursive update:
            $recursive_query = array(
                'c__tree_outputs' => $new_c['c__tree_outputs'],
                'c__tree_inputs' => $new_c['c__tree_inputs'],
                'c__tree_hours' => number_format($new_c['c__tree_hours'],3),
                'c__tree_messages' => $new_c['c__tree_messages'],
            );
        }


        //Create Link:
        $relation = $this->Db_model->cr_create(array(
            'cr_inbound_u_id' => $udata['u_id'],
            'cr_inbound_c_id'  => intval($_POST['c_id']),
            'cr_outbound_c_id' => $new_c['c_id'],
            'cr_outbound_rank' => 1 + $this->Db_model->max_value('v5_intent_links','cr_outbound_rank', array(
                'cr_status >=' => 1,
                'c_status >' => 0,
                'cr_inbound_c_id' => intval($_POST['c_id']),
            )),
        ));

        //Update tree count from parent and above:
        $updated_recursively = $this->Db_model->c_update_tree($_POST['c_id'], $recursive_query);

        //Log Engagement for new link:
        $this->Db_model->e_create(array(
            'e_inbound_u_id' => $udata['u_id'],
            'e_text_value' => 'Linked intent ['.$new_c['c_outcome'].'] as outbound of intent ['.$inbound_intents[0]['c_outcome'].']',
            'e_json' => array(
                'input' => $_POST,
                'before' => null,
                'after' => $relation,
                'recursive_query' => $recursive_query,
                'updated_recursively' => $updated_recursively,
            ),
            'e_inbound_c_id' => 23, //New Intent Link
            'e_cr_id' => $relation['cr_id'],
        ));

        $relations = $this->Db_model->cr_outbound_fetch(array(
            'cr.cr_id' => $relation['cr_id'],
        ));

        //Return result:
        echo_json(array(
            'status' => 1,
            'c_id' => $new_c['c_id'],
            'c__tree_hours' => $new_c['c__tree_hours'],
            'adjusted_c_count' => ( $new_c['c__tree_outputs'] + $new_c['c__tree_inputs'] ),
            'html' => echo_actionplan(array_merge($new_c,$relations[0]),$_POST['next_level'],intval($_POST['c_id'])),
        ));
    }

    function c_move_c(){

        //Auth user and Load object:
        $udata = auth(array(1308,1280));
        if(!$udata){
            return echo_json(array(
                'status' => 0,
                'message' => 'Invalid Session. Login again to Continue.',
            ));
        } elseif(!isset($_POST['cr_id']) || intval($_POST['cr_id'])<=0){
            return echo_json(array(
                'status' => 0,
                'message' => 'Invalid cr_id',
            ));
        } elseif(!isset($_POST['c_id']) || intval($_POST['c_id'])<=0){
            return echo_json(array(
                'status' => 0,
                'message' => 'Invalid c_id',
            ));
        } elseif(!isset($_POST['from_c_id']) || intval($_POST['from_c_id'])<=0) {
            return echo_json(array(
                'status' => 0,
                'message' => 'Missing from_c_id',
            ));
        } elseif(!isset($_POST['to_c_id']) || intval($_POST['to_c_id'])<=0) {
            return echo_json(array(
                'status' => 0,
                'message' => 'Missing to_c_id',
            ));
        }


        //Fetch all three intents to ensure they are all valid and use them for engagement logging:
        $subject = $this->Db_model->c_fetch(array(
            'c.c_id' => intval($_POST['c_id']),
        ));
        $from = $this->Db_model->c_fetch(array(
            'c.c_id' => intval($_POST['from_c_id']),
        ));
        $to = $this->Db_model->c_fetch(array(
            'c.c_id' => intval($_POST['to_c_id']),
        ));

        if(!isset($subject[0]) || !isset($from[0]) || !isset($to[0])){
            return echo_json(array(
                'status' => 0,
                'message' => 'Invalid intent IDs',
            ));
        }


        //Make the move:
        $this->Db_model->cr_update( intval($_POST['cr_id']) , array(
            'cr_inbound_u_id' => $udata['u_id'],
            'cr_timestamp' => date("Y-m-d H:i:s"),
            'cr_inbound_c_id' => intval($_POST['to_c_id']),
            //No need to update sorting here as a separate JS function would call that within half a second after the move...
        ));


        //Adjust tree on both branches:
        $updated_from_recursively = $this->Db_model->c_update_tree( $from[0]['c_id'] , array(
            'c__tree_outputs' => -($subject[0]['c__tree_outputs']),
            'c__tree_inputs' => -($subject[0]['c__tree_inputs']),
            'c__tree_hours' => -(number_format($subject[0]['c__tree_hours'],3)),
            'c__tree_messages' => -($subject[0]['c__tree_messages']),
        ));
        $updated_to_recursively = $this->Db_model->c_update_tree( $to[0]['c_id'] , array(
            'c__tree_outputs' => +($subject[0]['c__tree_outputs']),
            'c__tree_inputs' => +($subject[0]['c__tree_inputs']),
            'c__tree_hours' => +(number_format($subject[0]['c__tree_hours'],3)),
            'c__tree_messages' => +($subject[0]['c__tree_messages']),
        ));


        //Log engagement:
        $this->Db_model->e_create(array(
            'e_inbound_u_id' => $udata['u_id'],
            'e_json' => array(
                'post' => $_POST,
                'updated_from_recursively' => $updated_from_recursively,
                'updated_to_recursively' => $updated_to_recursively,
            ),
            'e_text_value' => '['.$subject[0]['c_outcome'].'] was migrated from ['.$from[0]['c_outcome'].'] to ['.$to[0]['c_outcome'].']', //Message migrated
            'e_inbound_c_id' => 50, //Intent migrated
            'e_outbound_c_id' => intval($_POST['c_id']),
            'e_cr_id' => intval($_POST['cr_id']),
        ));


        //Return success
        echo_json(array(
            'status' => 1,
            'message' => 'Move completed',
        ));
    }

    function c_save_settings(){

        //Auth user and check required variables:
        $udata = auth(array(1308,1280));

        //Validate Original intent:
        $cs = $this->Db_model->c_fetch(array(
            'c.c_id' => intval($_POST['c_id']),
        ), 0 );

        if(!$udata){
            echo_json(array(
                'status' => 0,
                'message' => 'Session Expired',
            ));
            return false;
        } elseif(!isset($_POST['c_id']) || intval($_POST['c_id'])<=0){
            echo_json(array(
                'status' => 0,
                'message' => 'Invalid Intent ID',
            ));
            return false;
        } elseif(!isset($_POST['level']) || intval($_POST['level'])<0){
            echo_json(array(
                'status' => 0,
                'message' => 'Missing level',
            ));
            return false;
        } elseif(!isset($_POST['c_outcome']) || strlen($_POST['c_outcome'])<=0){
            echo_json(array(
                'status' => 0,
                'message' => 'Missing Intent',
            ));
            return false;
        } elseif(!isset($_POST['c_time_estimate'])){
            echo_json(array(
                'status' => 0,
                'message' => 'Missing Time Estimate',
            ));
            return false;
        } elseif(!isset($_POST['c_is_any']) || !isset($_POST['c_is_output']) || !isset($_POST['c_require_url_to_complete']) || !isset($_POST['c_require_notes_to_complete'])){
            echo_json(array(
                'status' => 0,
                'message' => 'Missing Completion Settings',
            ));
            return false;
        } elseif(count($cs)<=0){
            echo_json(array(
                'status' => 0,
                'message' => 'Invalid Invalid c_id',
            ));
            return false;
        }


        //Update array:
        $c_update = array(
            'c_outcome' => trim($_POST['c_outcome']),
            'c_require_url_to_complete' => intval($_POST['c_require_url_to_complete']),
            'c_require_notes_to_complete' => intval($_POST['c_require_notes_to_complete']),

            //These are also in the recursive adjustment array as they affect cache data like c__tree_hours
            'c_time_estimate' => doubleval($_POST['c_time_estimate']),
            'c_is_any' => intval($_POST['c_is_any']),
            'c_is_output' => intval($_POST['c_is_output']),
        );


        //This determines if there are any recursive updates needed on the tree:
        $updated_recursively = 0;
        $recursive_query = array();


        //Check to see which variables actually changed:
        foreach($c_update as $key=>$value){

            //Did this value change?
            if($_POST[$key]==$cs[0][$key]){

                //No it did not! Remove it!
                unset($c_update[$key]);

            } else {

                //Something was updated!

                //Does it required a recursive upward update on the tree?

                if($key=='c_time_estimate'){

                    $recursive_query['c__tree_hours'] = number_format((doubleval($_POST[$key]) - doubleval($cs[0][$key])),3);

                } elseif($key=='c_is_output'){

                    if(intval($_POST['c_is_output'])){
                        //Changed to output:
                        $recursive_query['c__tree_inputs'] = -1;
                        $recursive_query['c__tree_outputs'] = 1;
                    } else {
                        //Changed to input:
                        $recursive_query['c__tree_outputs'] = -1;
                        $recursive_query['c__tree_inputs'] = 1;
                    }

                }
            }
        }



        //Did anything change?
        if(count($c_update)>0){

            //YES, update the DB:
            $this->Db_model->c_update( intval($_POST['c_id']) , $c_update );

            //Any recursive updates needed?
            if(count($recursive_query)>0){
                $updated_recursively = $this->Db_model->c_update_tree(intval($_POST['c_id']), $recursive_query);
            }

            //Update Algolia object:
            $this->Db_model->algolia_sync('c', $_POST['c_id']);

            //Log Engagement for New Intent Link:
            $this->Db_model->e_create(array(
                'e_inbound_u_id' => $udata['u_id'],
                'e_text_value' => readable_updates($cs[0],$c_update,'c_'),
                'e_json' => array(
                    'input' => $_POST,
                    'before' => $cs[0],
                    'after' => $c_update,
                    'updated_recursively' => $updated_recursively,
                    'recursive_query' => $recursive_query,
                ),
                'e_inbound_c_id' => ( $_POST['level']>=2 && isset($c_update['c_status']) && $c_update['c_status']<0 ? 21 : 19 ), //Intent Deleted OR Updated
                'e_outbound_c_id' => intval($_POST['c_id']),
            ));

        }

        //Show success:
        echo_json(array(
            'status' => 1,
            'message' => '<span><i class="fas fa-check"></i> Saved</span>',
            'recursive_updates' => $updated_recursively,
        ));

    }

    function c_unlink(){

        //Auth user and check required variables:
        $udata = auth(array(1308,1280));

        if(!$udata){
            echo_json(array(
                'status' => 0,
                'message' => 'Session Expired',
            ));
            return false;
        } elseif(!isset($_POST['c_id']) || intval($_POST['c_id'])<=0){
            echo_json(array(
                'status' => 0,
                'message' => 'Missing Intent ID',
            ));
            return false;
        } elseif(!isset($_POST['cr_id']) || intval($_POST['cr_id'])<=0){
            echo_json(array(
                'status' => 0,
                'message' => 'Missing Intent Link ID',
            ));
            return false;
        }

        //Fetch intent to see what kind is it:
        $cs = $this->Db_model->c_fetch(array(
            'c.c_id' => intval($_POST['c_id']),
            'c.c_status >' => 0,
        ));
        if(!isset($cs[0])){
            echo_json(array(
                'status' => 0,
                'message' => 'Invalid Intent ID',
            ));
            return false;
        }

        //Fetch parent ID:
        $c__inbounds = $this->Db_model->cr_inbound_fetch(array(
            'cr.cr_id' => $_POST['cr_id'],
            'cr.cr_status >=' => 1,
        ));
        if(!isset($c__inbounds[0])){
            echo_json(array(
                'status' => 0,
                'message' => 'Invalid Intent Link ID',
            ));
            return false;
        }


        //Update parent tree (and upwards) based on the intent type BEFORE removing the link:
        $recursive_query = array(
            'c__tree_outputs' => -($cs[0]['c__tree_outputs']),
            'c__tree_inputs' => -($cs[0]['c__tree_inputs']),
            'c__tree_hours' => -(number_format($cs[0]['c__tree_hours'],3)),
            'c__tree_messages' => -($cs[0]['c__tree_messages']),
        );
        $updated_recursively = $this->Db_model->c_update_tree( $c__inbounds[0]['cr_inbound_c_id'] , $recursive_query );


        //Now we can remove the link:
        $this->Db_model->cr_update( $_POST['cr_id'] , array(
            'cr_inbound_u_id' => $udata['u_id'],
            'cr_timestamp' => date("Y-m-d H:i:s"),
            'cr_status' => -1, //Archived
        ));


        //Did this intent become an orphan? Does it still have any other parents?
        if(0==count($this->Db_model->cr_inbound_fetch(array(
                'cr.cr_outbound_c_id' => $_POST['c_id'],
                'cr.cr_status >=' => 1,
            )))){
            //We made this orphan!
            $this->Db_model->c_update( intval($_POST['c_id']) , array(
                'c__is_orphan' => 1,
            ));
        }

        //Log Engagement for Link removal:
        $this->Db_model->e_create(array(
            'e_inbound_u_id' => $udata['u_id'],
            'e_inbound_c_id' => 89, //Intent Link Archived
            'e_outbound_c_id' => intval($_POST['c_id']),
            'e_cr_id' => intval($_POST['cr_id']),
            'e_json' => array(
                'input' => $_POST,
                'recursive_query' => $recursive_query,
                'updated_recursively' => $updated_recursively,
            ),
        ));

        //Show success:
        echo_json(array(
            'status' => 1,
            'c_inbound' => $c__inbounds[0]['cr_inbound_c_id'],
            'adjusted_c_count' => -($cs[0]['c__tree_outputs'] + $cs[0]['c__tree_inputs']),
        ));
    }

    function c_sync(){
        $c_id=7240;
        $sync = $this->Db_model->c_recursive_fetch($c_id,1,1);

        //Check how many are outside of this:
        $orphans = $this->Db_model->c_fetch(array(
            'c.c_id NOT IN ('.join(',',$sync['c_flat']).')' => null,
            'c.c_status >' => 0,
        ));
        $sync['orphan_count_update'] = 0;
        $sync['orphan_total'] = 0;

        //Update orphan status:
        foreach($orphans as $c){
            //Is it an orphan?
            $c__inbounds = $this->Db_model->cr_inbound_fetch(array(
                'cr.cr_outbound_c_id' => $c['c_id'],
                'cr.cr_status >=' => 1,
            ));

            if((!count($c__inbounds) && !intval($c['c__is_orphan'])) || (count($c__inbounds) && intval($c['c__is_orphan']))){
                //Needs adjustment:
                $this->Db_model->c_update( $c['c_id'] , array(
                    'c__is_orphan' => ( count($c__inbounds) ? 0 : 1 ),
                ));
                $sync['orphan_count_update']++;
            }

            if(!count($c__inbounds)){
                $sync['orphan_total']++;
            }
        }

        echo_json($sync);
    }

    function c_sort(){
        //Auth user and Load object:
        $udata = auth(array(1308,1280));
        if(!$udata){
            echo_json(array(
                'status' => 0,
                'message' => 'Invalid Session. Login again to Continue.',
            ));
        } elseif(!isset($_POST['c_id']) || intval($_POST['c_id'])<=0){
            echo_json(array(
                'status' => 0,
                'message' => 'Invalid c_id',
            ));
        } elseif(!isset($_POST['new_sort']) || !is_array($_POST['new_sort']) || count($_POST['new_sort'])<=0){
            echo_json(array(
                'status' => 0,
                'message' => 'Nothing passed for sorting',
            ));
        } else {

            //Validate Parent intent:
            $inbound_intents = $this->Db_model->c_fetch(array(
                'c.c_id' => intval($_POST['c_id']),
            ));
            if(count($inbound_intents)<=0){
                echo_json(array(
                    'status' => 0,
                    'message' => 'Invalid c_id',
                ));
            } else {

                //Fetch for the record:
                $outbounds_before = $this->Db_model->cr_outbound_fetch(array(
                    'cr.cr_inbound_c_id' => intval($_POST['c_id']),
                    'cr.cr_status >=' => 0,
                ));

                //Update them all:
                foreach($_POST['new_sort'] as $rank=>$cr_id){
                    $this->Db_model->cr_update( intval($cr_id) , array(
                        'cr_inbound_u_id' => $udata['u_id'],
                        'cr_timestamp' => date("Y-m-d H:i:s"),
                        'cr_outbound_rank' => intval($rank), //Might have decimal for DRAFTING Tasks/Steps
                    ));
                }

                //Fetch for the record:
                $outbounds_after = $this->Db_model->cr_outbound_fetch(array(
                    'cr.cr_inbound_c_id' => intval($_POST['c_id']),
                    'cr.cr_status >=' => 0,
                ));

                //Log Engagement:
                $this->Db_model->e_create(array(
                    'e_inbound_u_id' => $udata['u_id'],
                    'e_text_value' => 'Sorted outbound intents for ['.$inbound_intents[0]['c_outcome'].']',
                    'e_json' => array(
                        'input' => $_POST,
                        'before' => $outbounds_before,
                        'after' => $outbounds_after,
                    ),
                    'e_inbound_c_id' => 22, //Links Sorted
                    'e_outbound_c_id' => intval($_POST['c_id']),
                ));

                //Display message:
                echo_json(array(
                    'status' => 1,
                    'message' => '<i class="fas fa-check"></i> Sorted',
                ));
            }
        }
    }

    function c_echo_tip(){
        $udata = auth(array(1308,1280));
        //Used to load all the help messages within the Console:
        if(!$udata || !isset($_POST['intent_id']) || intval($_POST['intent_id'])<1){
            echo_json(array(
                'success' => 0,
            ));
        }

        //Fetch Messages and the User's Got It Engagement History:
        $messages = $this->Db_model->i_fetch(array(
            'i_outbound_c_id' => intval($_POST['intent_id']),
            'i_status >' => 0, //Published in any form
        ));

        //Log an engagement for all messages
        foreach($messages as $i){
            $this->Db_model->e_create(array(
                'e_inbound_u_id' => $udata['u_id'],
                'e_json' => $i,
                'e_inbound_c_id' => 40, //Got It
                'e_outbound_c_id' => intval($_POST['intent_id']),
                'e_i_id' => $i['i_id'],
            ));
        }

        //Build UI friendly Message:
        $help_content = null;
        foreach($messages as $i){
            $help_content .= echo_i(array_merge($i,array('e_outbound_u_id'=>$udata['u_id'])),$udata['u_full_name']);
        }

        //Return results:
        echo_json(array(
            'success' => ( $help_content ?  1 : 0 ), //No Messages perhaps!
            'intent_id' => intval($_POST['intent_id']),
            'help_content' => $help_content,
        ));
    }

    /* ******************************
	 * i Messages
	 ****************************** */

    function i_load_frame(){
        $udata = auth();
        if(!$udata){
            //Display error:
            die('<span style="color:#FF0000;">Error: Invalid Session. Login again to continue.</span>');
        } elseif(!isset($_POST['c_id']) || intval($_POST['c_id'])<=0){
            die('<span style="color:#FF0000;">Error: Invalid Intent id.</span>');
        } else {
            //Load the phone:
            $this->load->view('intents/frame_messages' , $_POST);
        }
    }

    function i_attach(){

        $udata = auth(array(1308,1280));
        $file_limit_mb = $this->config->item('file_limit_mb');
        if(!$udata){
            echo_json(array(
                'status' => 0,
                'message' => 'Invalid Session. Refresh to Continue',
            ));
            exit;
        } elseif(!isset($_POST['c_id']) || !isset($_POST['i_status'])){
            echo_json(array(
                'status' => 0,
                'message' => 'Missing intent data.',
            ));
            exit;
        } elseif(!isset($_POST['upload_type']) || !in_array($_POST['upload_type'],array('file','drop'))){
            echo_json(array(
                'status' => 0,
                'message' => 'Unknown upload type.',
            ));
            exit;
        } elseif(!isset($_FILES[$_POST['upload_type']]['tmp_name']) || strlen($_FILES[$_POST['upload_type']]['tmp_name'])==0 || intval($_FILES[$_POST['upload_type']]['size'])==0){
            echo_json(array(
                'status' => 0,
                'message' => 'Unable to save file. Max file size allowed is '.$file_limit_mb.' MB.',
            ));
            exit;
        } elseif($_FILES[$_POST['upload_type']]['size']>($file_limit_mb*1024*1024)){

            echo_json(array(
                'status' => 0,
                'message' => 'File is larger than '.$file_limit_mb.' MB.',
            ));
            exit;

        }


        //Attempt to save file locally:
        $file_parts = explode('.',$_FILES[$_POST['upload_type']]["name"]);
        $temp_local = "application/cache/temp_files/".md5($file_parts[0]).'.'.$file_parts[(count($file_parts)-1)];
        move_uploaded_file( $_FILES[$_POST['upload_type']]['tmp_name'] , $temp_local );


        //Attempt to store in Cloud:
        if(isset($_FILES[$_POST['upload_type']]['type']) && strlen($_FILES[$_POST['upload_type']]['type'])>0){
            $mime = $_FILES[$_POST['upload_type']]['type'];
        } else {
            $mime = mime_content_type($temp_local);
        }

        //Upload to S3:
        $new_file_url = trim(save_file( $temp_local , $_FILES[$_POST['upload_type']] , true ));

        //What happened?
        if(!$new_file_url){
            //Oops something went wrong:
            echo_json(array(
                'status' => 0,
                'message' => 'Could not save to cloud!',
            ));
            exit;
        }

        //Detect file type:
        $i_media_type = mime_type($mime);

        //Create Message:
        $message = '/attach '.$i_media_type.':'.$new_file_url;

        //Create message:
        $i = $this->Db_model->i_create(array(
            'i_inbound_u_id' => $udata['u_id'],
            'i_outbound_c_id' => intval($_POST['c_id']),
            'i_media_type' => $i_media_type,
            'i_message' => $message,
            'i_url' => $new_file_url,
            'i_status' => $_POST['i_status'],
            'i_rank' => 1 + $this->Db_model->max_value('v5_messages','i_rank', array(
                    'i_status' => $_POST['i_status'],
                    'i_outbound_c_id' => $_POST['c_id'],
                )),
        ));

        //Update intent count:
        $this->db->query("UPDATE v5_intents SET c__this_messages=c__this_messages+1 WHERE c_id=".intval($_POST['c_id']));

        //Update tree:
        $updated_recursively = $this->Db_model->c_update_tree( intval($_POST['c_id']) , array(
            'c__tree_messages' => 1,
        ));

        //Fetch full message:
        $new_messages = $this->Db_model->i_fetch(array(
            'i_id' => $i['i_id'],
        ));

        //Log engagement:
        $this->Db_model->e_create(array(
            'e_inbound_u_id' => $udata['u_id'],
            'e_json' => array(
                'post' => $_POST,
                'file' => $_FILES,
                'after' => $new_messages[0],
            ),
            'e_inbound_c_id' => 34, //Message added e_inbound_c_id=34
            'e_i_id' => intval($new_messages[0]['i_id']),
            'e_outbound_c_id' => intval($new_messages[0]['i_outbound_c_id']),
        ));


        //Does it have an attachment and a connected Facebook Page? If so, save the attachment:
        if(in_array($i_media_type,array('image','audio','video','file'))){
            //Log engagement for this to be done via a Cron Job:
            $this->Db_model->e_create(array(
                'e_inbound_u_id' => $udata['u_id'],
                'e_inbound_c_id' => 83, //Message Facebook Sync e_inbound_c_id=83
                'e_i_id' => intval($new_messages[0]['i_id']),
                'e_outbound_c_id' => intval($new_messages[0]['i_outbound_c_id']),
                'e_status' => 0, //Job pending
            ));
        }


        //Echo message:
        echo_json(array(
            'status' => 1,
            'message' => echo_message( array_merge($new_messages[0], array(
                'e_outbound_u_id'=>$udata['u_id'],
            ))),
        ));
    }

    function i_create(){

        $udata = auth(array(1308,1280));
        if(!$udata){
            echo_json(array(
                'status' => 0,
                'message' => 'Session Expired. Login and Try again.',
            ));
        } elseif(!isset($_POST['c_id']) || intval($_POST['c_id'])<=0 || !is_valid_intent($_POST['c_id'])){
            echo_json(array(
                'status' => 0,
                'message' => 'Invalid Step',
            ));
        } else {

            //Make sure message is all good:
            $validation = message_validation($_POST['i_status'],$_POST['i_message']);

            if(!$validation['status']){

                //There was some sort of an error:
                echo_json($validation);

            } else {

                //Detect file type:
                if(count($validation['urls'])==1 && trim($validation['urls'][0])==trim($_POST['i_message'])){

                    //This message is a URL only, perform raw URL to file conversion
                    //This feature only available for newly created message, NOT in editing mode!
                    $mime = remote_mime($validation['urls'][0]);
                    $i_media_type = mime_type($mime);
                    if($i_media_type=='file'){
                        $i_media_type = 'text';
                    }

                } else {
                    //This channel is all text:
                    $i_media_type = 'text'; //Possible: text,image,video,audio,file
                }

                //Create Message:
                $i = $this->Db_model->i_create(array(
                    'i_inbound_u_id' => $udata['u_id'],
                    'i_outbound_c_id' => intval($_POST['c_id']),
                    'i_media_type' => $i_media_type,
                    'i_message' => trim($_POST['i_message']),
                    'i_url' => ( count($validation['urls'])==1 ? $validation['urls'][0] : null ),
                    'i_status' => $_POST['i_status'],
                    'i_rank' => 1 + $this->Db_model->max_value('v5_messages','i_rank', array(
                            'i_status' => $_POST['i_status'],
                            'i_outbound_c_id' => intval($_POST['c_id']),
                        )),
                ));

                //Fetch full message:
                $new_messages = $this->Db_model->i_fetch(array(
                    'i_id' => $i['i_id'],
                ), 1, array('x'));


                //Update intent count:
                $this->db->query("UPDATE v5_intents SET c__this_messages=c__this_messages+1 WHERE c_id=".intval($_POST['c_id']));

                //Update tree:
                $updated_recursively = $this->Db_model->c_update_tree( intval($_POST['c_id']) , array(
                    'c__tree_messages' => 1,
                ));


                //Log engagement:
                $this->Db_model->e_create(array(
                    'e_inbound_u_id' => $udata['u_id'],
                    'e_json' => array(
                        'cache' => $this->Db_model->c_recursive_fetch(intval($_POST['c_id'])),
                        'input' => $_POST,
                        'after' => $new_messages[0],
                        'updated_recursively' => $updated_recursively,
                    ),
                    'e_inbound_c_id' => 34, //Message added
                    'e_i_id' => intval($new_messages[0]['i_id']),
                    'e_outbound_c_id' => intval($_POST['c_id']),
                ));

                //Print the challenge:
                echo_json(array(
                    'status' => 1,
                    'message' => echo_message(array_merge($new_messages[0], array(
                        'e_outbound_u_id'=>$udata['u_id'],
                    ))),
                ));
            }
        }
    }

    function i_modify(){

        //Auth user and Load object:
        $udata = auth(array(1308,1280));
        if(!$udata){
            echo_json(array(
                'status' => 0,
                'message' => 'Invalid Session. Refresh.',
            ));
        } elseif(!isset($_POST['i_media_type'])){
            echo_json(array(
                'status' => 0,
                'message' => 'Missing Type',
            ));
        } elseif(!isset($_POST['i_id']) || intval($_POST['i_id'])<=0){
            echo_json(array(
                'status' => 0,
                'message' => 'Missing Message ID',
            ));
        } elseif(!isset($_POST['c_id']) || intval($_POST['c_id'])<=0 || !is_valid_intent($_POST['c_id'])){
            echo_json(array(
                'status' => 0,
                'message' => 'Invalid Intent ID',
            ));
        } else {

            //Fetch Message:
            $messages = $this->Db_model->i_fetch(array(
                'i_id' => intval($_POST['i_id']),
                'i_status >=' => 0,
            ));

            //Make sure message is all good:
            $validation = message_validation($_POST['i_status'],( isset($_POST['i_message']) ? $_POST['i_message'] : null ),$_POST['i_media_type']);

            if(!isset($messages[0])){
                echo_json(array(
                    'status' => 0,
                    'message' => 'Message Not Found',
                ));
            } elseif(!$validation['status']){

                //There was some sort of an error:
                echo_json($validation);

            } else {

                //All good, lets move on:
                //Define what needs to be updated:
                $to_update = array(
                    'i_inbound_u_id' => $udata['u_id'],
                    'i_timestamp' => date("Y-m-d H:i:s"),
                );

                //Is this a text message?
                if($_POST['i_media_type']=='text'){

                    //Always trim message:
                    $to_update['i_message'] = trim($_POST['i_message']);


                    if(isset($validation['urls'][0])){
                        //We need to make a new entity (or find existing one) and reference:

                    }


                    //Do we have any entity references?
                    preg_match('/@(\d+)/', $to_update['i_message'], $matches);
                    if(isset($matches[1]) && strlen($matches[1])>0 && strlen($matches[1])==strlen(intval($matches[1]))){

                        $us = $this->Db_model->u_fetch(array(
                            'u_id' => $matches[1],
                        ));
                        if(count($us)==0){
                            //Invalid Entity ID
                            return echo_json(array(
                                'status' => 0,
                                'message' => '[@'.$matches[1].'] is an Invalid Entity reference',
                            ));
                        } elseif(!array_key_exists(1326, $us[0]['u__inbounds'])){
                            //Entity is from a non-allowed category:
                            return echo_json(array(
                                'status' => 0,
                                'message' => '['.$us[0]['u_full_name'].'] is not a valid content entity',
                            ));
                        }

                        //All good:
                        $to_update['i_outbound_u_id'] = $matches[1];
                    }
                }

                if(!($_POST['initial_i_status']==$_POST['i_status'])){
                    //Change the status:
                    $to_update['i_status'] = $_POST['i_status'];
                    //Put it at the end of the new list:
                    $to_update['i_rank'] = 1 + $this->Db_model->max_value('v5_messages','i_rank', array(
                            'i_status' => $_POST['i_status'],
                            'i_outbound_c_id' => intval($_POST['c_id']),
                        ));
                }

                //Now update the DB:
                $this->Db_model->i_update( intval($_POST['i_id']) , $to_update );

                //Re-fetch the message for display purposes:
                $new_messages = $this->Db_model->i_fetch(array(
                    'i_id' => intval($_POST['i_id']),
                ), 0, array('x'));

                //Log engagement:
                $this->Db_model->e_create(array(
                    'e_inbound_u_id' => $udata['u_id'],
                    'e_json' => array(
                        'input' => $_POST,
                        'before' => $messages[0],
                        'after' => $new_messages[0],
                    ),
                    'e_inbound_c_id' => 36, //Message edited
                    'e_i_id' => $messages[0]['i_id'],
                    'e_outbound_c_id' => intval($_POST['c_id']),
                ));

                //Print the challenge:
                echo_json(array(
                    'status' => 1,
                    'message' => echo_i(array_merge($new_messages[0],array('e_outbound_u_id'=>$udata['u_id'])),$udata['u_full_name']),
                    'new_status' => echo_status('i_status',$new_messages[0]['i_status'],1,'right'),
                    'success_icon' => '<span><i class="fas fa-check"></i> Saved</span>',
                    'new_uploader' => echo_cover($new_messages[0],null,true, 'data-toggle="tooltip" title="Last modified by '.$new_messages[0]['u_full_name'].' about '.echo_diff_time($new_messages[0]['i_timestamp']).' ago" data-placement="right"'), //If there is a person change...
                ));
            }
        }
    }

    function i_delete(){
        //Auth user and Load object:
        $udata = auth(array(1308,1280));

        if(!$udata){
            echo_json(array(
                'status' => 0,
                'message' => 'Session Expired. Login and try again',
            ));
        } elseif(!isset($_POST['i_id']) || intval($_POST['i_id'])<=0){
            echo_json(array(
                'status' => 0,
                'message' => 'Missing Message ID',
            ));
        } elseif(!isset($_POST['c_id']) || intval($_POST['c_id'])<=0 || !is_valid_intent($_POST['c_id'])){
            echo_json(array(
                'status' => 0,
                'message' => 'Invalid Intent ID',
            ));
        } else {

            //Fetch Message:
            $messages = $this->Db_model->i_fetch(array(
                'i_id' => intval($_POST['i_id']),
                'i_status >=' => 0, //Not deleted
            ));
            if(!isset($messages[0])){
                echo_json(array(
                    'status' => 0,
                    'message' => 'Message Not Found',
                ));
            } else {

                //Now update the DB:
                $this->Db_model->i_update( intval($_POST['i_id']) , array(
                    'i_inbound_u_id' => $udata['u_id'],
                    'i_timestamp' => date("Y-m-d H:i:s"),
                    'i_status' => -1, //Deleted by coach
                ));

                //Update intent count:
                $this->db->query("UPDATE v5_intents SET c__this_messages=c__this_messages-1 WHERE c_id=".intval($_POST['c_id']));

                //Update tree:
                $updated_recursively = $this->Db_model->c_update_tree( intval($_POST['c_id']) , array(
                    'c__tree_messages' => -1,
                ));

                //Log engagement:
                $this->Db_model->e_create(array(
                    'e_inbound_u_id' => $udata['u_id'],
                    'e_json' => array(
                        'input' => $_POST,
                        'before' => $messages[0],
                    ),
                    'e_inbound_c_id' => 35, //Message deleted
                    'e_i_id' => intval($messages[0]['i_id']),
                    'e_outbound_c_id' => intval($_POST['c_id']),
                ));

                echo_json(array(
                    'status' => 1,
                    'message' => '<span style="color:#3C4858;"><i class="fas fa-trash-alt"></i> Deleted</span>',
                ));
            }
        }
    }

    function i_sort(){

        //Auth user and Load object:
        $udata = auth(array(1308,1280));
        if(!$udata){
            echo_json(array(
                'status' => 0,
                'message' => 'Session Expired. Login and try again',
            ));
        } elseif(!isset($_POST['new_sort']) || !is_array($_POST['new_sort']) || count($_POST['new_sort'])<=0){
            echo_json(array(
                'status' => 1, //Do not treat this as error as it could happen in moving Messages between types
                'message' => 'There was nothing to sort',
            ));
        } elseif(!isset($_POST['c_id']) || intval($_POST['c_id'])<=0 || !is_valid_intent($_POST['c_id'])){
            echo_json(array(
                'status' => 0,
                'message' => 'Invalid Intent ID',
            ));
        } else {

            //Update them all:
            $sort_count = 0;
            foreach($_POST['new_sort'] as $i_rank=>$i_id){
                if(intval($i_id)>0){
                    $sort_count++;
                    $this->Db_model->i_update( $i_id , array(
                        'i_rank' => intval($i_rank),
                    ));
                }
            }

            //Log engagement:
            $this->Db_model->e_create(array(
                'e_inbound_u_id' => $udata['u_id'],
                'e_json' => $_POST,
                'e_inbound_c_id' => 39, //Messages sorted
                'e_outbound_c_id' => intval($_POST['c_id']),
            ));

            echo_json(array(
                'status' => 1,
                'message' => $sort_count.' Sorted', //Does not matter as its currently not displayed in UI
            ));
        }
    }

}