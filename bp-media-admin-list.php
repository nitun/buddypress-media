<?php
//require_once('admin.php');
function media_add_admin_css() {
    wp_enqueue_style( 'media_add_admin_css',  BP_MEDIA_PLUGIN_URL . '/themes/media/css/media-admin.css' );
    wp_enqueue_style( 'media_add_datepicker_css',  BP_MEDIA_PLUGIN_URL . '/themes/media/css/datepicker/jquery.ui.all.css' );

}
add_action( 'admin_menu', 'media_add_admin_css' );

function media_add_admin_js() {
    wp_deregister_script('jquery');
    wp_enqueue_script( 'jquery', 'http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js');
    wp_enqueue_script( 'rt_jqueryui',  BP_MEDIA_PLUGIN_URL . '/themes/media/js/jquery.ui.core.js','jquery' );
    wp_enqueue_script( 'media_add_admin_js',  BP_MEDIA_PLUGIN_URL . '/themes/media/js/jquery.ui.datepicker.js','rt_jqueryui' );
}
add_action( 'admin_menu', 'media_add_admin_js' );

function rt_media_administration() {
    global $wpdb, $bp;

    if ( !is_site_admin() )
        return false;

    /* Add the administration tab under the "Site Admin" tab for site administrators */
    add_submenu_page('bp-general-settings', //$parent
            __('Media Adminstration','Media Adminstration'),//$page_title
            __('Media Adminstration','Media Adminstration'),//$menu_title
            'manage_options',//$access_level
            'bp-media-admin',//$file
            "rt_media_admin_page" );//$function
}
add_action('admin_menu', 'rt_media_administration');


//code to catch the post fields




function rt_media_admin_page() {
    global $bp,$kaltura_validation_data,$wpdb,$kaltura_list,$kaltura_data;
    $pag_num = 4;

    if(isset($_POST['delete_media'])) {
        if($_POST['rt-media-action'] == -1)
            return;
        if($_POST['rt-media-action'] == 'delete' ){

               $rt_entry_list = $_POST['linkcheck'];
               $rt_delete_success = rt_entry_to_delete($rt_entry_list);

        }
}


 
    $fpage = isset( $_REQUEST['fpage'] ) ? intval( $_REQUEST['fpage'] ) : 1;
    $owner = isset( $_REQUEST['filter-user']) ? intval($_REQUEST['filter-user']) : -1;
    $media_type_filter = isset( $_REQUEST['filter-type']) ? intval($_REQUEST['filter-type']) : -1;
    $time_filter = isset( $_REQUEST['filter-date']) ? ($_REQUEST['filter-date']) : -1;
    $specific_date = isset( $_REQUEST['pick-date']) ? ($_REQUEST['pick-date']) : '';
//    var_dump($specific_date);
    $d = strtotime($specific_date);

    $from_date = getDate($d);
    $from_date = $from_date[0];
    $to_date = $from_date+24*60*60;
//    var_dump($from_date,$to_date);

    $today = getdate();
    $unixtime = $today[0];
    if($time_filter=='last-week'){
        $timesince = $unixtime - 7*24*60*60;
    }

    if($time_filter=='last-month'){
        $timesince = $unixtime - 30*24*60*60;
    }

    if (isset ($_POST['filter-user'])) {
        $fpage = 1;
    }

    $where = 'WHERE ';
    if ($owner != -1) {
        $where .= "md.user_id={$owner} AND ";
    }
    if ($media_type_filter != -1) {
        $where .= "md.media_type={$media_type_filter} AND ";
    }
    if($time_filter != -1)
        $where  .= "md.date_uploaded > {$timesince} AND ";

    if($specific_date != ''){
        if($time_filter != -1)
            $where.=' ';

        $where .="md.date_uploaded BETWEEN {$from_date} AND {$to_date} AND ";
    }

    $q =  "select * from {$bp->media->table_media_data} md JOIN {$wpdb->users} wu {$where}md.user_id = wu.id";
    $result = $wpdb->get_results($wpdb->prepare($q));
    
//    echo $wpdb->last_query;
    $cnt = count($result);


    $pagination = array(
      'base' => add_query_arg( array('fpage'=> '%#%', 'num' => $pag_num, 'filter-user' => $owner, 'filter-type' => $media_type_filter, 'pick-date' =>$specific_date ) ), // http://example.com/all_posts.php%_% : %_% is replaced by format (below)
      'format' => '', // ?page=%#% : %#% is replaced by the page number
      'total' => ceil( ($cnt) / $pag_num),
      'current' => $fpage,
      'prev_next' => true,
      'prev_text' => __('&laquo;'),
      'next_text' => __('&raquo;'),
      'type' => 'plain'

    );
    $rt_media_offset = ($fpage-1) * $pag_num;
    $q .= " LIMIT {$rt_media_offset}, {$pag_num}";
//    echo $ql;
    $media_user_name = $wpdb->get_results($wpdb->prepare($q));
//    echo $wpdb->last_query;
//    var_dump($media_user_name);

    $kaltura_data = get_data_from_kaltura();//data fetched from kaltura
     $kaltura_list[0] = array();
    $j = 0;
    foreach ($kaltura_data[0] as $key => $value) {
    for($i=0;$i<$cnt;$i++) {
                if($value->id == $result[$i]->entry_id) {
                    $value->db_id = $result[$i]->id;
                    $value->localview = $result[$i]->views;
                    $value->local_tot_rank = $result[$i]->total_rating;
                    $value->display_name = $result[$i]->display_name;
                    $kaltura_list[0][$i] = $value;
                    switch($value->mediaType) {
                        case '1':
                            $media_slug = 'video';
                            break;
                        case '2':
                            $media_slug = 'photo';
                            break;
                        case '5':
                            $media_slug = 'audio';
                            break;
                        default :
                            $media_slug = 'mediaall';
                    }
                    $kaltura_list[0]['media_slug'] = $media_type;
                }
            }
    }
//  var_dump('-----------',$kaltura_list[0],'----------');

//var_dump($fpage,$pag_num);
$kaltura_list[0] = array_slice( (array)$kaltura_list[0], intval( ( $fpage - 1 ) * $pag_num), intval( $pag_num ) );

    $q_users =  "select DISTINCT(display_name), wu.id from {$bp->media->table_media_data} md JOIN {$wpdb->users} wu WHERE md.user_id = wu.id";
    $res = $wpdb->get_results($wpdb->prepare($q_users));
    
    
?>

<div class="wrap">
   
    <h2>Media Adminstration : Advanced</h2>
    <form id="media-filter" action="" method="post">
        <div class="tablenav">
            <div class="alignleft actions">
                <select name="rt-media-action">
                    <option value="-1" name="bulk-action" selected="selected"><?php _e('Bulk Actions'); ?></option>
                    <option name="bulk-delete" value="delete"><?php _e('Delete Forever'); ?></option>
                </select>
                <input type="submit" value="<?php esc_attr_e('Apply'); ?>" name="delete_media" id="doaction" class="button-secondary action" />

                <select name="filter-date">
                    <option value="-1" selected="selected"><?php _e('Time Filter'); ?></option>
                    <option value="last-week"><?php _e('Last Week'); ?></option>
                    <option value="last-month"><?php _e('Last Month'); ?></option>
                </select>
                <span>Specify Date: <input type="text" id="datepicker" name="pick-date" size="10"></span>

                <select name="filter-user">
                    <option value="-1" selected="selected"><?php _e('All Users'); ?></option>
                        <?php
                        for($name =0 ; $name<count($res);$name++) {
                            ?>
                    <option value="<?php _e($res[$name]->id); ?>"><?php _e($res[$name]->display_name); ?></option>
                            <?php }?>
                </select>

                <select name="filter-type">
                    <option value="-1" <?php if($media_type_filter === -1) { echo 'SELECTED';}?>><?php _e('All Media'); ?></option>
                    <option value="2" <?php if($media_type_filter === 2) { echo 'SELECTED';}?>><?php _e('Photo'); ?></option>
                    <option value="1" <?php if($media_type_filter === 1) { echo 'SELECTED';}?>><?php _e('Video'); ?></option>
                    <option value="5" <?php if($media_type_filter === 5) { echo 'SELECTED';}?>><?php _e('Audio'); ?></option>
                </select>

                <input type="submit" id="post-query-submit" name ="rt-submit" value="<?php esc_attr_e('Filter'); ?>" class="button-secondary" />
                <?php echo paginate_links( $pagination ) ?>
            </div>
        </div>


        <table class="widefat post fixed" cellspacing="0">
            <thead>
                <tr>
                    <th scope="row" class="check-column"><input type="checkbox" name="linkcheck[]" value="a" /></th>
                    <th class="manage-column column-title">Thumbnail</th>
                    <th class="manage-column column-title">Media Owner</th>
                    <th class="manage-column column-title">Media Type</th>
                    <th class="manage-column column-title">Date of Creation</th>
                </tr>
            </thead>
            <tbody>

                    <?php
                  
                    $kaltura_cnt =count($kaltura_list[0]);

                    for($k=0;$k<$kaltura_cnt;$k++) {

                        switch($kaltura_list[0][$k]->mediaType) {
                            case '2':
                                $media_type ='Photo';
                                break;
                            case '1':
                                $media_type ='Video';
                                break;
                            case '5':
                                $media_type ='Audio';
                                break;
                        }
                        echo '</tr>';
                        echo '<th scope="row" class="check-column"><input type="checkbox" name="linkcheck[]" value="'.$kaltura_list[0][$k]->id.'" /></th>';
                        echo '<td class="column-title"><img height= "30" width = "45px"  src= "'.$kaltura_list[0][$k]->thumbnailUrl .'jpg"><p>'.$kaltura_list[0][$k]->name.'</p></td>';
                        echo '<td class="column-author">'.$kaltura_list[0][$k]->display_name.'</td>';
                        echo '<td class="column-categories">'.$media_type.'</td>';
                        echo '<td class="column-date">'.date( "F j, Y",$kaltura_list[0][$k]->createdAt).'</td>';
                        echo '</tr>';

                    }


                    ?>

            </tbody>
            <thead>
                <tr>
                    <th scope="row" class="check-column"><input type="checkbox" name="linkcheck[]" value="a" /></th>
                    <th class="manage-column column-title">Thumbnail</th>
                    <th class="manage-column column-title">Media Owner</th>
                    <th class="manage-column column-title">Media Type</th>
                    <th class="manage-column column-title">Date of Creation</th>
                </tr>
            </thead>

        </table>


    </form>

</div>
    <?php
}

function get_data_from_kaltura() {
    global $bp,$kaltura_validation_data;
    ob_clean();

    $filter = new KalturaMediaEntryFilter();
    $pager  = new KalturaFilterPager();

    try {
        $data1 =  $kaltura_validation_data['client']->media->listAction($filter,$pager);
    }
    catch (Exception $e) {
        echo 'Oops Error while fetching data .. try Again ';
    }

    return array($data1->objects,$data1->totalCount);
}

//function to delete selected media
function rt_entry_to_delete($rt_entry_list){
    global $wpdb,$kaltura_validation_data,$bp;
        
        var_dump($bp);
    //Since $wpdb is not having access for the tables  here so this is written
        $bp->media->table_media_data = $wpdb->base_prefix . 'bp_media_data';
        $bp->media->photo_tag = $wpdb->base_prefix . 'bp_media_photo_tags'; //added by ashish
        $bp->media->table_report_abuse = $wpdb->base_prefix . 'bp_media_report_abuse'; //added by ashish
        $bp->media->table_user_rating_data = $wpdb->base_prefix . 'bp_media_user_rating_list';//added by ashish

       
    $media_user_name = $wpdb->get_results($wpdb->prepare($q1));
    try{
    for($i=0;$i<count($rt_entry_list);$i++){
        if(!($rt_entry_list[$i]=='a')){
        $q = "select id from {$bp->media->table_media_data} WHERE entry_id='{$rt_entry_list[$i]}' ";
        $media_id = $wpdb->get_var($wpdb->prepare($q));

        $q_media_data   = "DELETE from {$bp->media->table_media_data} WHERE entry_id='{$rt_entry_list[$i]}' ";
        $q_media_photo_tag    = "DELETE from {$bp->media->table_photo_tag} WHERE entry_id= {$rt_entry_list[$i]} ";
        $q_media_rating = "DELETE from {$bp->media->table_user_rating_data} WHERE image_id='{$media_id}' ";
        $q_report_abuse = "DELETE from {$bp->media->table_report_abuse} WHERE entry_id='{$rt_entry_list[$i]}' ";

        echo $q_report_abuse;
        echo $q_media_data;
        echo $q_media_photo_tag;
        echo $q_media_rating;
        
//        var_dump($media_id);
        }
//        $kaltura_validation_data['client']->media->delete($rt_entry_list[$i]);
        
    }
//    var_dump('-----------------------',$rt_entry_list,'---------------------');
    }
    catch(Exception $e){
        echo "Error deleting data";
    }

}

?>