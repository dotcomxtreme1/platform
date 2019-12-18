<?php
$en_all_6225 = $this->config->item('en_all_6225');
$en_all_11035 = $this->config->item('en_all_11035'); //MENCH PLAYER NAVIGATION

$this_attempt = array(
    'ln_type_player_id' => ( $referrer_in_id > 0 ? 7560 /* User Signin Blog Channel Choose */ : 7561 /* User Signin on Website */ ),
    'ln_parent_blog_id' => $referrer_in_id,
);

$current_sign_in_attempt = array(); //Will try to find this...
$current_sign_in_attempts = $this->session->userdata('sign_in_attempts');
if(is_array($current_sign_in_attempts) && count($current_sign_in_attempts) > 0){
    //See if any of the current sign-in attempts match this:
    foreach($current_sign_in_attempts as $sign_in_attempt){
        $all_match = true;
        foreach(array('ln_parent_blog_id') as $sign_in_attempt_field){
            if(intval($this_attempt[$sign_in_attempt_field]) != intval($sign_in_attempt[$sign_in_attempt_field])){
                $all_match = false;
                break;
            }
        }
        if($all_match){
            //We found a match!
            $current_sign_in_attempt = $sign_in_attempt;
            break;
        }
    }
} else {
    $current_sign_in_attempts = array();
}


//See what to do based on current matches:
if(count($current_sign_in_attempt) == 0){

    //Log link:
    $current_sign_in_attempt = $this->READ_model->ln_create($this_attempt);

    //Grow the array:
    array_push($current_sign_in_attempts, $current_sign_in_attempt);

    //Add this sign-in attempt to session:
    $this->session->set_userdata(array('sign_in_attempts' => $current_sign_in_attempts));

}
?>

<script>
    var referrer_in_id = <?= intval($referrer_in_id) ?>;
    var referrer_url = '<?= @$_GET['url'] ?>';
    var channel_choice_messenger = {
        ln_type_player_id: 7558, //User Signin with Messenger Choice
        ln_parent_blog_id: <?= intval($referrer_in_id) ?>,
        ln_parent_read_id: <?= $current_sign_in_attempt['ln_id'] ?>,
    };
    var channel_choice_website = {
        ln_type_player_id: 7559, //User Signin with Website Choice
        ln_parent_blog_id: <?= intval($referrer_in_id) ?>,
        ln_parent_read_id: <?= $current_sign_in_attempt['ln_id'] ?>,
    };
</script>
<script src="/application/views/play/play_signin.js?v=v<?= config_var(11060) ?>"
        type="text/javascript"></script>


<div class="container center-info">

    <div class="sign-logo text-center"><img src="/mench.png" class="mench-spin" /></div>

    <h1 class="text-center"><?= $en_all_11035[4269]['m_name'] ?></h1>

    <?php
    if($referrer_in_id > 0){
        $ins = $this->BLOG_model->in_fetch(array(
            'in_id' => $referrer_in_id,
            'in_status_player_id IN (' . join(',', $this->config->item('en_ids_7355')) . ')' => null, //Blog Statuses Public
        ));
        if(count($ins) > 0){
            echo '<p class="text-center">To read <a href="/'.$referrer_in_id.'">'.echo_in_title($ins[0]['in_title']).'</a> for FREE!</p>';
        }
    } elseif(isset($_GET['url']) && strlen($_GET['url']) > 0){
        echo '<p>To access <u>'.urldecode($_GET['url']).'</u></p>';
    }
    ?>

    <div class="login-content" style="margin-top:50px;">

        <!-- Step 1: Choose Channel -->
        <div id="step1" class="signup-steps hidden">

            <?php
            echo '<p>Choose a reading platform:</p>';
            foreach ($this->config->item('en_all_7555') as $en_id => $m) {
                echo '<div class="row" style="padding:5px 0;">';

                echo '<a class="btn btn-play" href="javascript:void(0);" onclick="select_channel('.$en_id.')"><span class="icon-block">' . $m['m_icon'] . '</span>' . $m['m_name'] . ' <i class="fas fa-angle-right"></i></a>';

                echo '<div class="help_me_choose hidden"><i class="fal fa-info-circle"></i> '.$m['m_desc'].'<br /></div>';

                echo '</div>';
            }
            ?>


            <div class="row center" style="padding-top:20px;">
                <a href="javascript:void(0);" onclick="$('.help_me_choose').toggleClass('hidden')" class="help_me_choose"><span class="icon-block"><i class="fas fa-question-circle"></i></span>Help me Choose</a>
                <a href="javascript:void(0);" onclick="$('.vote-platforms').toggleClass('hidden')" class="vote-platforms"><span class="icon-block"><i class="fas fa-vote-yea"></i></span>Vote for Upcoming Platforms</a>
            </div>


            <?php
            echo '<div class="vote-platforms vote-results hidden">';
            echo '<p style="padding-top: 30px;">Cast your vote for one of these upcoming platforms:</p>';
            foreach ($this->config->item('en_all_12105') as $en_id => $m) {
                echo '<div style="padding:5px 0; width: 100%;"><a href="javascript:void(0);" onclick="vote_channel('.$en_id.')"><span class="icon-block"><i class="fas fa-vote-yea"></i></span><span class="icon-block">' . $m['m_icon'] . '</span>' . $m['m_name'] . '</a></div>';
            }
            echo '</div>';
            ?>

        </div>


        <!-- Step 2: Enter Email -->
        <div id="step2" class="signup-steps hidden">
            <span class="medium-header"><?= $en_all_6225[3288]['m_icon'].' '.$en_all_6225[3288]['m_name'] ?></span>
            <div class="form-group is-empty"><input type="email" id="input_email" <?= isset($_GET['input_email']) ? ' value="'.$_GET['input_email'].'" ' : '' ?> class="form-control border"></div>
            <div id="email_errors" class="isred"></div>
            <span id="step2buttons">
                <a href="javascript:void(0)" onclick="goto_step(1)" class="btn btn-play transparent pass btn-raised btn-round <?= ( $referrer_in_id > 0 ? '' : ' hidden ' ) ?>"><i class="fas fa-arrow-left"></i></a>
                <a href="javascript:void(0)" onclick="search_email()" id="email_check_next" class="btn btn-play pass btn-raised btn-round"><i class="fas fa-arrow-right"></i></a>
            </span>
            <span id="messenger_sign" style="padding-left:5px; font-size:1em !important;" class="<?= ( $referrer_in_id > 0 ? ' hidden ' : '' ) ?>">OR <a href="javascript:void(0)" onclick="confirm_sign_on_messenger()" class="underdot" style="font-size:1em !important;">USE MESSENGER</a> <i class="fab fa-facebook-messenger blue"></i></span>
        </div>



        <!-- Step 4: Create New Account -->
        <div id="step4" class="signup-steps hidden">
            <span class="medium-header" style="padding-top: 20px;"><i class="fas fa-user-plus"></i> New Account <b><span class="focus_email"></span></b></span>

            <!-- Full Name -->
            <span class="medium-header" style="padding-top: 20px; display: block;"><?= $en_all_6225[6197]['m_icon'].' '.$en_all_6225[6197]['m_name'] ?>:</span>
            <div class="form-group is-empty"><input type="text" placeholder="Tim Apple" id="input_name" maxlength="<?= config_var(11072) ?>" class="form-control border"></div>

            <!-- New Password -->
            <span class="medium-header" style="padding-top: 20px;"><?= $en_all_6225[3286]['m_icon'] ?> Create a new password:</span>
            <div class="form-group is-empty"><input type="password" id="new_password" class="form-control border"></div>

            <!-- Signup Buttons -->
            <div id="new_account_errors" class="isred"></div>
            <span id="step2buttons">
                <a href="javascript:void(0)" onclick="goto_step(2)" class="btn btn-play transparent pass btn-raised btn-round"><i class="fas fa-arrow-left"></i></a>
                <a href="javascript:void(0)" onclick="add_account()" id="add_acount_next" class="btn btn-play pass btn-raised btn-round"><i class="fas fa-arrow-right"></i></a>
            </span>

        </div>


        <!-- Step 3: Enter Password -->
        <div id="step3" class="signup-steps hidden">

            <!-- To be updated to >0 IF email was found -->
            <input type="hidden" id="login_en_id" value="0" />

            <span class="medium-header"><?= $en_all_6225[3286]['m_icon'].' '.$en_all_6225[3286]['m_name'] ?></span>
            <div class="form-group is-empty"><input type="password" id="input_password" class="form-control border"></div>
            <div id="password_errors" class="isred"></div>
            <span id="step3buttons">
                <a href="javascript:void(0)" data-toggle="tooltip" data-placement="bottom" title="Go Back" onclick="goto_step(2)" class="btn btn-play transparent pass btn-raised btn-round"><i class="fas fa-arrow-left"></i></a>
                <a href="javascript:void(0)" onclick="singin_check_password()" id="password_check_next" class="btn btn-play pass btn-raised btn-round"><i class="fas fa-arrow-right"></i></a>
            </span>

            <span style="padding-left:5px; font-size:0.9em !important;">OR <a href="javascript:void(0)" onclick="magicemail()" class="underdot" style="font-size:1em !important;">MAGIC LINK</a> <i class="fas fa-envelope-open blue"></i></span>

        </div>


        <!-- Step 5: Check your email -->
        <div id="step5" class="signup-steps hidden">
            <span class="medium-header"><i class="fas fa-envelope-open"></i> <span class="focus_email"></span></span>
            <span class="medium-header magic_result"></span>
        </div>



    </div>
</div>