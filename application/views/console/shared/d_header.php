<?php 
//Attempt to fetch session variables:
$udata = $this->session->userdata('user');
$uadmission = $this->session->userdata('uadmission');
$website = $this->config->item('website');
$mench_bots = $this->config->item('mench_bots');
?><!doctype html>
<html lang="en">
<head>
    <!--

    WELCOME TO MENCH SOURCE CODE!

    INTERESTED IN HELPING US BUILD THE FUTURE OF EDUCATION?

    YOU CAN WORK WITH US AS AN INDEPENDENT CONTRACTOR FROM ANYWHERE IN THE WORLD.

    AND IF YOU ACHIEVE THE MENCH STATUS, YOU CAN ALSO APPLY TO WORK AS A CORE TEAM MEMBER.

    -->
	<meta charset="utf-8" />
	<link rel="icon" type="image/png" href="/img/bp_16.png">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
	<meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' name='viewport' />
    <meta name="viewport" content="width=device-width" />
	<title>Mench<?= ( isset($title) ? ' | '.$title : '' ) ?></title>

    <link href="/css/lib/devices.min.css" rel="stylesheet" />
    <link href="/css/lib/jquery.mCustomScrollbar.min.css" rel="stylesheet" />
	<?php $this->load->view('front/shared/header_resources' ); ?>

    <script src="/js/lib/autocomplete.jquery.min.js"></script>
    <script src="/js/lib/algoliasearch.min.js"></script>
    <script src="/js/lib/sortable.min.js" type="text/javascript"></script>
    <script src="/js/front/global.js?v=v<?= $website['version'] ?>" type="text/javascript"></script>
    <script src="/js/console/console.js?v=v<?= $website['version'] ?>" type="text/javascript"></script>

</head>




<body id="console_body">

<?php
//Start the filtering array for unread notifications:
$unread_notification_filters = array(
    'e_recipient_u_id' => $udata['u_id'], //The instructor received these messages
    'e_type_id' => "7", //Outbound messages towards instructors
);

//Fetch their last read engagement
$last_read = $this->Db_model->e_fetch(array(
    'e_initiator_u_id' => $udata['u_id'], //The reading of the message was initiated by student
    'e_type_id' => "1", //Message read
),1); //We only need the lates one!

//Did we have any? If so, append that to the filter:
if(count($last_read)>0){
    $unread_notification_filters['e_timestamp >'] = $last_read[0]['e_timestamp'];
}

//See how many unread notifications we have:
$unread_notifications = $this->Db_model->e_fetch($unread_notification_filters);

//Facebook chat in console ONLY if activated already:
if(isset($udata['u_fb_id']) && $udata['u_fb_id']>0){
    echo echo_chat('381488558920384',count($unread_notifications));
}

//Show them if >0
if(count($unread_notifications)>0){
    echo '<a id="msgnotif" href="javascript:mark_read()" data-toggle="tooltip" title="Unread notifications. Click to dismiss." data-placement="top"><i class="fa fa-bell" aria-hidden="true"></i> '.count($unread_notifications).' New</a>';
}
?>

	<div class="wrapper" id="console">
	
		<div class="navbar navbar-transparent navbar-absolute">
			<div class="container-fluid">
				<div class="navbar-header">
					<button type="button" class="navbar-toggle" data-toggle="collapse">
						<span class="sr-only">Toggle navigation</span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
					<span class="navbar-brand dashboard-logo">
						<a href="/console">
						<img src="/img/bp_128.png" />
						<span style="text-transform:none;" class="bg-glow">Bootcamps</span>
						</a>
						<!-- <input type="text" placeholder="Search"> -->
					</span>
				</div>
				
				<?php if($udata['u_fb_id']>0){ ?>
				<div class="collapse navbar-collapse">
					<ul class="nav navbar-nav navbar-right">
						<?php /* <li data-toggle="tooltip" data-placement="bottom" title="View FAQs & best-practices to better manage your bootcamps. Link opens in a new window."><a href="https://support.mench.co/hc/en-us" target="_blank"><i class="fa fa-lightbulb-o" aria-hidden="true"></i><span> Instructors Hub</span></a></li> */ ?>
                        <li><a href="/console/account"><?= (strlen($udata['u_image_url'])>4 ? '<img src="'.$udata['u_image_url'].'" class="profile-icon" />' : '<i class="fa fa-user-circle" aria-hidden="true"></i>') ?> My Account</a></li>
                        <?php if(isset($uadmission) && count($uadmission)>0){ ?>
                            <li data-toggle="tooltip" data-placement="bottom" title="You are seeing this because you are a Bootcamp student. Use this to access your Action Plan on a web-based portal, which replicates the MenchBot."><a href="/my/actionplan"><span> Student Hub <i class="fa fa-chevron-circle-right" aria-hidden="true"></i></span></a></li>
                        <?php } ?>
					</ul>
				</div>
				<?php } ?>
				
			</div>
		</div>
		
	    <div class="sidebar" id="mainsidebar" data-color="purple">
	    	<div class="sidebar-wrapper">
	    		<?php 
	    		if(isset($bootcamp)){
	    		    echo '<div class="left-li-title"><i class="fa fa-dot-circle-o" style="margin-right:3px;" aria-hidden="true"></i><a href="/'.$bootcamp['b_url_key'].'" class="landing_page_url" id="top-left-title" data-toggle="tooltip" data-placement="bottom" title="Visit Landing Page">'.$bootcamp['c_objective'].'</a></div>';
	    		}
	    		?>
	    		<ul class="nav" style="margin-top: 0;">
                    
            	<?php
            	if(isset($bootcamp)){
            	    
            	    $sprint_units = $this->config->item('sprint_units'); 

            	    echo '<li class="li-sep '.( in_array($_SERVER['REQUEST_URI'],array('/console/'.$bootcamp['b_id'],'/console/'.$bootcamp['b_id'].'/')) ? 'active' : '' ).'"><a href="/console/'.$bootcamp['b_id'].'"><i class="fa fa-tachometer" aria-hidden="true"></i><p>Dashboard</p></a></li>';
            	    
            	    echo '<li'.( substr_count($_SERVER['REQUEST_URI'],'/console/'.$bootcamp['b_id'].'/actionplan')>0 ? ' class="active"' : '' ).'><a href="/console/'.$bootcamp['b_id'].'/actionplan"><i class="fa fa-list-ol" aria-hidden="true"></i><p>Action Plan</p></a></li>';
            	    
            	    echo '<li'.( substr_count($_SERVER['REQUEST_URI'],'/console/'.$bootcamp['b_id'].'/classes')>0 ? ' class="active"' : '' ).'><a href="/console/'.$bootcamp['b_id'].'/classes"><i class="fa fa-calendar" aria-hidden="true"></i><p>Classes</p></a></li>';
                	    
            	    echo '<li'.( substr_count($_SERVER['REQUEST_URI'],'/console/'.$bootcamp['b_id'].'/students')>0 ? ' class="active"' : '' ).'><a href="/console/'.$bootcamp['b_id'].'/students"><i class="fa fa-users" aria-hidden="true"></i><p>Students</p></a></li>';
                	    
            	    //echo '<li'.( substr_count($_SERVER['REQUEST_URI'],'/console/'.$bootcamp['b_id'].'/stream')>0 ? ' class="active"' : '' ).'><a href="/console/'.$bootcamp['b_id'].'/stream"><i class="material-icons">forum</i><p>Activity Stream</p></a></li>';
            	    
            	    echo '<li'.( substr_count($_SERVER['REQUEST_URI'],'/console/'.$bootcamp['b_id'].'/team')>0 ? ' class="active"' : '' ).'><a href="/console/'.$bootcamp['b_id'].'/team"><i class="fa fa-user-plus" aria-hidden="true"></i><p>Team</p></a></li>';
        		}
            	?>
            	</ul>
	    	</div>
		</div>


	    <div class="main-panel">
	        <div class="content dash" style="<?= ( isset($bootcamp) && substr_count($_SERVER['REQUEST_URI'],'/console/'.$bootcamp['b_id'].'/actionplan')>0 ? 'min-height: inherit !important;' : '' ) ?>">
	        
    	        <?php 
    	        if(isset($breadcrumb)){
    	            echo '<ol class="breadcrumb">';
    	            foreach($breadcrumb as $link){
    	                if($link['link']){
    	                    echo '<li><a href="'.$link['link'].'">'.$link['anchor'].'</a></li>';
    	                } else {
    	                    echo '<li>'.$link['anchor'].'</li>';
    	                }
    	            }
    	            echo '</ol>';
    	        }
    	        ?>
    	        
    	        
	            <div class="container-fluid">
	            <?php 
	            if(isset($message)){
	                echo $message;
	            }
	            $hm = $this->session->flashdata('hm');
	            if($hm){
	            	echo $hm;
	            }
	            ?>