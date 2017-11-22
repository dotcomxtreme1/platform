<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cockpit extends CI_Controller {
	
	function __construct() {
		parent::__construct();
		
		//Load our buddies:
		$this->output->enable_profiler(FALSE);
	}
	
	
	function udemy(){
	    //Authenticate level 3 or higher, redirect if not:
	    $udata = auth(3,1);
	    
	    if(isset($_GET['cat'])){
	        
	        //Load instructor list:
	        $this->load->view('console/shared/d_header', array(
	            'title' => urldecode($_GET['cat']).' Udemy Instructors',
	            'breadcrumb' => array(
	                array(
	                    'link' => '/cockpit/udemy',
	                    'anchor' => 'Udemy Instructors',
	                ),
	                array(
	                    'link' => null,
	                    'anchor' => urldecode($_GET['cat']).' <a href="/scraper/udemy_csv?cat='.urlencode($_GET['cat']).'"><i class="fa fa-cloud-download" aria-hidden="true"></i>CSV</a>',
	                ),
	            ),
	        ));
	        $this->load->view('cockpit/udemy_category' , array(
	            'il_category' => $this->Db_model->il_fetch(array(
	                'il_udemy_user_id >' => 0,
	                'il_student_count >' => 0,
	                'il_udemy_category' => urldecode($_GET['cat']),
	            )),
	        ));
	        $this->load->view('console/shared/d_footer');
	        
	    } else {
	        
	        //Load category list:
	        $this->load->view('console/shared/d_header', array(
	            'title' => 'Udemy Instructors',
	            'breadcrumb' => array(
	                array(
	                    'link' => null,
	                    'anchor' => 'Udemy Instructors',
	                ),
	            ),
	        ));
	        $this->load->view('cockpit/udemy_all' , array(
	            'il_overview' => $this->Db_model->il_overview_fetch(),
	        ));
	        $this->load->view('console/shared/d_footer');
	        
	    }
	}
	
	
	
	
	function engagements(){
	    //Authenticate level 3 or higher, redirect if not:
	    $udata = auth(3,1);
	    
	    //This lists all users based on the permissions of the user
	    $this->load->view('console/shared/d_header', array(
	        'title' => 'Platform-Wide Engagements',
	        'breadcrumb' => array(
	            array(
	                'link' => null,
	                'anchor' => 'Platform-Wide Engagements',
	            ),
	        ),
	    ));
	    $this->load->view('cockpit/engagements' , array(
	       'engagements' => $this->Db_model->e_fetch(), //Fetch Last 100 Engagements
	    ));
	    $this->load->view('console/shared/d_footer');
	}
	
}