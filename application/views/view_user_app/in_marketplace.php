<?php

//Go through all categories and see which ones have published courses:
foreach($this->config->item('en_all_10709') /* Course Categories */ as $en_id => $m) {

    //Count total published courses here:
    $published_ins = $this->Links_model->ln_fetch(array(
        'ln_status_entity_id IN (' . join(',', $this->config->item('en_ids_7359')) . ')' => null, //Link Statuses Public
        'in_status_entity_id IN (' . join(',', $this->config->item('en_ids_7355')) . ')' => null, //Intent Statuses Public
        'in_level_entity_id IN (' . join(',', $this->config->item('en_ids_7582')) . ')' => null, //Intent Levels Get Started
        'ln_type_entity_id' => 10715, //Intent Note Categories
        'ln_parent_entity_id' => $en_id,
    ), array('in_child'));

    if(!count($published_ins)){
        continue;
    }

    //Create list:
    $category_list = '<div class="list-group actionplan_list grey_list" style="font-size: 0.6em;">';
    foreach($published_ins as $published_in){
        $category_list .= echo_in_recommend($published_in);
    }
    $category_list .= '</div>';

    echo echo_tree_html_body($en_id, $m['m_icon'].' '.$m['m_name'].' ['.count($published_ins).']', $category_list, false);

}

?>