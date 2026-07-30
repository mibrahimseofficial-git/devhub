<?php
if (!defined('ABSPATH')) {exit;}
class TQB_Admin {
    const MENU_SLUG="tqb-settings";
    const NONCE_ACTION_LINE_ITEMS="tqb_save_line_items";
    const NONCE_ACTION_RATE_BANDS="tqb_save_rate_bands";
    const NONCE_ACTION_GENERAL="tqb_save_general_settings";
    const NONCE_ACTION_SCHEDULE_L="tqb_save_schedule_l";
    const NONCE_ACTION_ADMIN="tqb_admin_nonce";
    public function __construct() {
        add_action("admin_menu",array($this,"register_menu"));
        add_action("admin_post_tqb_save_line_items",array($this,"handle_save_line_items"));
        add_action("admin_post_tqb_save_rate_bands",array($this,"handle_save_rate_bands"));
        add_action("admin_post_tqb_save_general_settings",array($this,"handle_save_general_settings"));
        add_action("admin_post_tqb_save_schedule_l",array($this,"handle_save_schedule_l"));
        add_action("admin_post_tqb_delete_submission",array($this,"handle_delete_submission"));
        add_action("admin_post_tqb_delete_submissions",array($this,"handle_bulk_delete_submissions"));
        add_action("wp_ajax_tqb_fetch_hubspot_pipelines",array($this,"handle_fetch_hubspot_pipelines"));
        add_action("wp_ajax_tqb_get_submission",array($this,"ajax_get_submission"));
        add_action("wp_ajax_tqb_get_submission_email",array($this,"ajax_get_submission_email"));
        add_action("wp_ajax_tqb_update_status",array($this,"ajax_update_status"));
        add_action("wp_ajax_tqb_bulk_status",array($this,"ajax_bulk_status"));
        add_action("wp_ajax_tqb_bulk_delete",array($this,"ajax_bulk_delete"));
        add_action("wp_ajax_tqb_send_email",array($this,"ajax_send_email"));
        add_action("admin_notices",array($this,"maybe_show_saved_notice"));
        add_action("admin_enqueue_scripts",array($this,"enqueue_admin_assets"));
    }
    public function enqueue_admin_assets($hook) {
        if ("toplevel_page_".self::MENU_SLUG!==$hook){return;}
        wp_enqueue_script("tqb-admin",TQB_PLUGIN_URL."admin/js/tqb-admin.js",array(),tqb_asset_version("admin/js/tqb-admin.js"),true);
        wp_localize_script("tqb-admin","tqbAdminData",array("ajaxUrl"=>admin_url("admin-ajax.php"),"nonce"=>wp_create_nonce(self::NONCE_ACTION_ADMIN)));
    }
    public function register_menu() {
        add_menu_page("Tavola Quote Builder","Quote Builder","manage_options",self::MENU_SLUG,array($this,"render_page"),"dashicons-calculator",30);
    }
    public function maybe_show_saved_notice() {
        if (isset($_GET["tqb_saved"])&&"1"===$_GET["tqb_saved"]){echo "<div class=\"notice notice-success is-dismissible\"><p>Pricing settings saved.</p></div>";}
    }
    public function render_page() {
        $active_tab=isset($_GET["tab"])?sanitize_key($_GET["tab"]):"individual";
        echo "<div class=\"wrap\"><h1>Tavola Quote Builder</h1><h2 class=\"nav-tab-wrapper\">";
        echo "<a href=\"?page=".self::MENU_SLUG."&tab=individual\" class=\"nav-tab ".( "individual"===$active_tab?"nav-tab-active":"")."\">Individual Pricing</a>";
        echo "<a href=\"?page=".self::MENU_SLUG."&tab=business\" class=\"nav-tab ".( "business"===$active_tab?"nav-tab-active":"")."\">Business Pricing</a>";
        echo "<a href=\"?page=".self::MENU_SLUG."&tab=submissions\" class=\"nav-tab ".( "submissions"===$active_tab?"nav-tab-active":"")."\">Submissions</a>";
        echo "<a href=\"?page=".self::MENU_SLUG."&tab=general\" class=\"nav-tab ".( "general"===$active_tab?"nav-tab-active":"")."\">Settings</a>";
        echo "</h2>";
        if("individual"===$active_tab){$this->render_line_items_tab();}
        elseif("business"===$active_tab){$this->render_business_tab();}
        elseif("submissions"===$active_tab){$this->render_submissions_tab();}
        elseif("general"===$active_tab){$this->render_general_tab();}
        echo "</div>";
    }
    private function render_line_items_tab() {
        $extra_items=TQB_DB::get_line_items("individual",false);
        $heading="Individual Return - Line Items";
        $quote_type="individual";
        include TQB_PLUGIN_DIR."admin/views/line-items-tab.php";
    }
    private function render_business_tab() {
        $extra_items=TQB_DB::get_line_items("business",false);
        $asset_bands_c_s=TQB_DB::get_all_rate_bands("asset_band","c_s_corp");
        $asset_bands_partnership=TQB_DB::get_all_rate_bands("asset_band","partnership");
        $revenue_addons=TQB_DB::get_all_rate_bands("revenue_addon");
        $schedule_l_thresholds=get_option("tqb_schedule_l_thresholds",array("c_corp"=>array("asset_threshold"=>250000,"revenue_threshold"=>250000,"flat_fee"=>999),"s_corp"=>array("asset_threshold"=>250000,"revenue_threshold"=>250000,"flat_fee"=>999),"partnership"=>array("asset_threshold"=>1000000,"revenue_threshold"=>250000,"flat_fee"=>999)));
        include TQB_PLUGIN_DIR."admin/views/business-tab.php";
    }
    private function render_general_tab() {
        include TQB_PLUGIN_DIR."admin/views/general-tab.php";
    }
    private function render_submissions_tab() {
        global $wpdb;$table=$wpdb->prefix."tqb_submissions";
        $per_page=isset($_GET["per_page"])?absint($_GET["per_page"]):25;
        if(!in_array($per_page,array(10,25,50,100),true)){$per_page=25;}
        $current_page=isset($_GET["paged"])?max(1,absint($_GET["paged"])):1;
        $offset=($current_page-1)*$per_page;
        $status_filter=isset($_GET["status"])?sanitize_key($_GET["status"]):"";
        $type_filter=isset($_GET["type"])?sanitize_key($_GET["type"]):"";
        $search=isset($_GET["s"])?sanitize_text_field(wp_unslash($_GET["s"])):"";
        $allowed_columns=array("id","contact_name","contact_email","contact_phone","quote_type","status","calculated_total","created_at");
        $orderby=isset($_GET["orderby"])&&in_array($_GET["orderby"],$allowed_columns,true)?$_GET["orderby"]:"created_at";
        $order=isset($_GET["order"])&&"asc"===strtolower($_GET["order"])?"ASC":"DESC";
        $where=array("1=1");$where_args=array();
        if(!empty($status_filter)){$where[]="status = %s";$where_args[]=$status_filter;}
        if(!empty($type_filter)){$where[]="quote_type = %s";$where_args[]=$type_filter;}
        if(!empty($search)){$where[]="(contact_name LIKE %s OR contact_email LIKE %s OR contact_phone LIKE %s)";$search_like="%".$wpdb->esc_like($search)."%";$where_args[]=$search_like;$where_args[]=$search_like;$where_args[]=$search_like;}
        $where_clause=implode(" AND ",$where);
        $orderby="`".sanitize_key($orderby)."`";
        $total_count=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where_clause}",$where_args));
        $submissions=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",array_merge($where_args,array($per_page,$offset))),ARRAY_A);
        foreach($submissions as&$sub){if(!empty($sub["answers"])){$decoded=json_decode($sub["answers"],true);if(json_last_error()===JSON_ERROR_NONE){$sub["answers"]=$decoded;}}}
        $counts=array("all"=>$wpdb->get_var("SELECT COUNT(*) FROM {$table}"),"completed"=>$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'completed'"),"in_progress"=>$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'in_progress'"),"abandoned"=>$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'abandoned'"));
        include TQB_PLUGIN_DIR."admin/views/submissions-dashboard.php";
    }
    public function handle_save_line_items() {
        if(!current_user_can("manage_options")){wp_die("No permission.");}
        check_admin_referer(self::NONCE_ACTION_LINE_ITEMS,"tqb_nonce");
        $quote_type=isset($_POST["quote_type"])?sanitize_key($_POST["quote_type"]):"";
        $items=isset($_POST["items"])?(array)$_POST["items"]:array();
        $new_items=isset($_POST["new_items"])?(array)$_POST["new_items"]:array();
        $deleted=isset($_POST["deleted_items"])?$_POST["deleted_items"]:"";
        if(!empty($deleted)){foreach(array_filter(array_map("absint",explode(",",$deleted)))as$del_id){TQB_DB::delete_line_item($del_id);}}
        foreach($items as$item_id=>$fields){$item_id=absint($item_id);TQB_DB::update_line_item($item_id,array("label"=>isset($fields["label"])?sanitize_text_field(wp_unslash($fields["label"])):"","fee"=>isset($fields["fee"])?(float)$fields["fee"]:0,"pricing_pattern"=>isset($fields["pattern"])?sanitize_key($fields["pattern"]):"flat","tooltip"=>isset($fields["tooltip"])?sanitize_text_field(wp_unslash($fields["tooltip"])):"","is_custom_quote_trigger"=>isset($fields["is_custom_quote_trigger"])?1:0,"sort_order"=>isset($fields["sort_order"])?absint($fields["sort_order"]):0));}
        foreach($new_items as$temp_id=>$fields){$label=isset($fields["label"])?sanitize_text_field(wp_unslash($fields["label"])):"";if(empty($label)){continue;}TQB_DB::add_line_item($quote_type,$label,isset($fields["fee"])?(float)$fields["fee"]:0,isset($fields["pattern"])?sanitize_key($fields["pattern"]):"flat",isset($fields["tooltip"])?sanitize_text_field(wp_unslash($fields["tooltip"])):"",isset($fields["is_custom_quote_trigger"])?1:0,isset($fields["sort_order"])?absint($fields["sort_order"]):100);}
        wp_safe_redirect(admin_url("admin.php?page=".self::MENU_SLUG."&tab=".$quote_type."&tqb_saved=1"));exit;
    }
    public function handle_save_rate_bands() {
        if(!current_user_can("manage_options")){wp_die("No permission.");}
        check_admin_referer(self::NONCE_ACTION_RATE_BANDS,"tqb_nonce");
        $deleted_asset=isset($_POST["deleted_asset_bands"])?$_POST["deleted_asset_bands"]:"";
        if(!empty($deleted_asset)){foreach(array_filter(array_map("absint",explode(",",$deleted_asset)))as$del_id){TQB_DB::delete_rate_band($del_id);}}
        $deleted_revenue=isset($_POST["deleted_revenue_addons"])?$_POST["deleted_revenue_addons"]:"";
        if(!empty($deleted_revenue)){foreach(array_filter(array_map("absint",explode(",",$deleted_revenue)))as$del_id){TQB_DB::delete_rate_band($del_id);}}
        $asset_bands=isset($_POST["asset_bands"])?(array)$_POST["asset_bands"]:array();
        foreach($asset_bands as$band_id=>$fields){$band_id=absint($band_id);TQB_DB::update_rate_band_full($band_id,array("band_label"=>isset($fields["label"])?sanitize_text_field(wp_unslash($fields["label"])):"","band_min"=>isset($fields["min"])?(int)$fields["min"]:0,"band_max"=>isset($fields["max"])?(""!==$fields["max"]?(int)$fields["max"]:null):null,"price"=>isset($fields["c_s_price"])?(""!==$fields["c_s_price"]?(float)$fields["c_s_price"]:null):null,"sort_order"=>isset($fields["sort_order"])?(int)$fields["sort_order"]:0));$p_price=isset($fields["p_price"])?(""!==$fields["p_price"]?(float)$fields["p_price"]:null):null;TQB_DB::update_rate_band_price_by_type($band_id,$p_price,"partnership");}
        $new_asset_bands=isset($_POST["new_asset_bands"])?(array)$_POST["new_asset_bands"]:array();
        foreach($new_asset_bands as$temp_id=>$fields){$label=isset($fields["label"])?sanitize_text_field(wp_unslash($fields["label"])):"";if(empty($label)){continue;}$c_s_price=isset($fields["c_s_price"])?(""!==$fields["c_s_price"]?(float)$fields["c_s_price"]:null):null;$p_price=isset($fields["p_price"])?(""!==$fields["p_price"]?(float)$fields["p_price"]:null):null;TQB_DB::add_rate_band("asset_band","c_s_corp",$label,isset($fields["min"])?(int)$fields["min"]:0,isset($fields["max"])?(""!==$fields["max"]?(int)$fields["max"]:null):null,$c_s_price,isset($fields["sort_order"])?(int)$fields["sort_order"]:100);TQB_DB::add_rate_band("asset_band","partnership",$label,isset($fields["min"])?(int)$fields["min"]:0,isset($fields["max"])?(""!==$fields["max"]?(int)$fields["max"]:null):null,$p_price,isset($fields["sort_order"])?(int)$fields["sort_order"]:100);}
        $revenue_addons=isset($_POST["revenue_addons"])?(array)$_POST["revenue_addons"]:array();
        foreach($revenue_addons as$addon_id=>$fields){TQB_DB::update_rate_band_full(absint($addon_id),array("band_label"=>isset($fields["label"])?sanitize_text_field(wp_unslash($fields["label"])):"","band_min"=>isset($fields["min"])?(int)$fields["min"]:0,"band_max"=>isset($fields["max"])?(""!==$fields["max"]?(int)$fields["max"]:null):null,"price"=>isset($fields["price"])?(float)$fields["price"]:0,"sort_order"=>isset($fields["sort_order"])?(int)$fields["sort_order"]:0));}
        $new_revenue_addons=isset($_POST["new_revenue_addons"])?(array)$_POST["new_revenue_addons"]:array();
        foreach($new_revenue_addons as$temp_id=>$fields){$label=isset($fields["label"])?sanitize_text_field(wp_unslash($fields["label"])):"";if(empty($label)){continue;}TQB_DB::add_rate_band("revenue_addon",null,$label,isset($fields["min"])?(int)$fields["min"]:0,isset($fields["max"])?(""!==$fields["max"]?(int)$fields["max"]:null):null,isset($fields["price"])?(float)$fields["price"]:0,isset($fields["sort_order"])?(int)$fields["sort_order"]:100);}
        wp_safe_redirect(admin_url("admin.php?page=".self::MENU_SLUG."&tab=business&tqb_saved=1"));exit;
    }
    public function handle_save_general_settings() {
        if(!current_user_can("manage_options")){wp_die("No permission.");}
        check_admin_referer(self::NONCE_ACTION_GENERAL,"tqb_nonce");
        update_option("tqb_disclaimer_text",isset($_POST["disclaimer_text"])?sanitize_textarea_field(wp_unslash($_POST["disclaimer_text"])):"");
        update_option("tqb_scheduling_link",isset($_POST["scheduling_link"])?esc_url_raw(wp_unslash($_POST["scheduling_link"])):"");
        update_option("tqb_team_notification_email",isset($_POST["notification_email"])?sanitize_email(wp_unslash($_POST["notification_email"])):"");
        update_option("tqb_hubspot_service_key",isset($_POST["hubspot_service_key"])?sanitize_text_field(wp_unslash($_POST["hubspot_service_key"])):"");
        update_option("tqb_hubspot_pipeline_id",isset($_POST["hubspot_pipeline_id"])?sanitize_text_field(wp_unslash($_POST["hubspot_pipeline_id"])):"");
        update_option("tqb_hubspot_stage_new",isset($_POST["hubspot_stage_new"])?sanitize_text_field(wp_unslash($_POST["hubspot_stage_new"])):"");
        update_option("tqb_hubspot_stage_custom",isset($_POST["hubspot_stage_custom"])?sanitize_text_field(wp_unslash($_POST["hubspot_stage_custom"])):"");
        update_option("tqb_enable_abandoned_emails",isset($_POST["enable_abandoned_emails"])?"1":"0");
        update_option("tqb_reminder_email_hours",isset($_POST["reminder_email_hours"])?absint($_POST["reminder_email_hours"]):24);
        update_option("tqb_followup_email_hours",isset($_POST["followup_email_hours"])?absint($_POST["followup_email_hours"]):72);
        update_option("tqb_final_email_hours",isset($_POST["final_email_hours"])?absint($_POST["final_email_hours"]):168);
        update_option("tqb_office_address",isset($_POST["office_address"])?sanitize_textarea_field(wp_unslash($_POST["office_address"])):"");
        wp_safe_redirect(admin_url("admin.php?page=".self::MENU_SLUG."&tab=general&tqb_saved=1"));exit;
    }
    public function handle_save_schedule_l() {
        if(!current_user_can("manage_options")){wp_die("No permission.");}
        check_admin_referer(self::NONCE_ACTION_SCHEDULE_L,"tqb_schedule_l_nonce");
        $schedule_l=isset($_POST["schedule_l"])?(array)$_POST["schedule_l"]:array();
        $thresholds=array();
        foreach(array("c_corp","s_corp","partnership")as$entity){if(isset($schedule_l[$entity])){$thresholds[$entity]=array("asset_threshold"=>isset($schedule_l[$entity]["asset_threshold"])?(int)$schedule_l[$entity]["asset_threshold"]:0,"revenue_threshold"=>isset($schedule_l[$entity]["revenue_threshold"])?(int)$schedule_l[$entity]["revenue_threshold"]:0,"flat_fee"=>isset($schedule_l[$entity]["flat_fee"])?(float)$schedule_l[$entity]["flat_fee"]:999);}}
        update_option("tqb_schedule_l_thresholds",$thresholds);
        wp_safe_redirect(admin_url("admin.php?page=".self::MENU_SLUG."&tab=business&tqb_saved=1"));exit;
    }
    public function handle_delete_submission() {
        if(!current_user_can("manage_options")){wp_die("No permission.");}
        check_admin_referer("tqb_delete_submissions","tqb_delete_nonce");
        $id=isset($_GET["id"])?absint($_GET["id"]):0;
        if($id){global $wpdb;$wpdb->delete($wpdb->prefix."tqb_submissions",array("id"=>$id),array("%d"));}
        wp_safe_redirect(admin_url("admin.php?page=".self::MENU_SLUG."&tab=submissions"));exit;
    }
    public function handle_bulk_delete_submissions() {
        if(!current_user_can("manage_options")){wp_die("No permission.");}
        check_admin_referer("tqb_delete_submissions","tqb_delete_nonce");
        $ids=isset($_POST["ids"])?array_filter(array_map("absint",(array)$_POST["ids"])):array();
        if(!empty($ids)){global $wpdb;$table=$wpdb->prefix."tqb_submissions";$placeholders=implode(",",array_fill(0,count($ids),"%d"));$wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ($placeholders)",$ids));}
        wp_safe_redirect(admin_url("admin.php?page=".self::MENU_SLUG."&tab=submissions"));exit;
    }
    public function handle_fetch_hubspot_pipelines() {
        if(!current_user_can("manage_options")){wp_send_json_error(array("message"=>"Permission denied."),403);}
        check_ajax_referer("tqb_fetch_pipelines","nonce");
        $service_key=get_option("tqb_hubspot_service_key","");
        if(empty($service_key)){wp_send_json_error(array("message"=>"Save a HubSpot Service Key first."),400);}
        $pipelines=TQB_Hubspot::get_pipelines($service_key);
        if(is_wp_error($pipelines)){wp_send_json_error(array("message"=>$pipelines->get_error_message()),400);}
        wp_send_json_success(array("pipelines"=>$pipelines));
    }
    public function ajax_get_submission() {
        if(!current_user_can("manage_options")){wp_send_json_error("Permission denied.");}
        check_ajax_referer(self::NONCE_ACTION_ADMIN,"nonce");
        $id=isset($_POST["id"])?absint($_POST["id"]):0;
        if(!$id){wp_send_json_error("Invalid ID.");}
        $submission=TQB_DB::get_submission($id);
        if(!$submission){wp_send_json_error("Submission not found.");}
        $answers=array();
        if(!empty($submission["answers"])){$decoded=json_decode($submission["answers"],true);if(is_array($decoded)){$answers=$decoded;}}
        ob_start();
        echo "<div class=\"tqb-submission-details\">";
        echo "<p><strong>Name:</strong> ".esc_html($submission["contact_name"]?:"-")."</p>";
        echo "<p><strong>Email:</strong> ".esc_html($submission["contact_email"])."</p>";
        echo "<p><strong>Phone:</strong> ".esc_html($submission["contact_phone"]?:"-")."</p>";
        echo "<p><strong>Type:</strong> ".esc_html(ucfirst($submission["quote_type"]))."</p>";
        echo "<p><strong>Total:</strong> ".($submission["calculated_total"]?"$".number_format((float)$submission["calculated_total"],2):"-")."</p>";
        if(!empty($answers)){echo "<h4>Form Answers:</h4><ul>";foreach($answers as$key=>$value){$label=ucwords(str_replace("_"," ",$key));$val=is_array($value)?json_encode($value):$value;echo "<li><strong>".esc_html($label).":</strong> ".esc_html($val)."</li>";}echo "</ul>";}
        echo "</div>";
        $html=ob_get_clean();
        wp_send_json_success(array("html"=>$html));
    }
    public function ajax_get_submission_email() {
        if(!current_user_can("manage_options")){wp_send_json_error("Permission denied.");}
        check_ajax_referer(self::NONCE_ACTION_ADMIN,"nonce");
        $id=isset($_POST["id"])?absint($_POST["id"]):0;
        $submission=TQB_DB::get_submission($id);
        if(!$submission){wp_send_json_error("Not found.");}
        wp_send_json_success(array("email"=>$submission["contact_email"],"name"=>$submission["contact_name"]));
    }
    public function ajax_update_status() {
        if(!current_user_can("manage_options")){wp_send_json_error("Permission denied.");}
        check_ajax_referer(self::NONCE_ACTION_ADMIN,"nonce");
        $id=isset($_POST["id"])?absint($_POST["id"]):0;
        $status=isset($_POST["status"])?sanitize_key($_POST["status"]):"";
        global $wpdb;
        $wpdb->update($wpdb->prefix."tqb_submissions",array("status"=>$status,"updated_at"=>current_time("mysql")),array("id"=>$id),array("%s","%s"),array("%d"));
        wp_send_json_success();
    }
    public function ajax_bulk_status() {
        if(!current_user_can("manage_options")){wp_send_json_error("Permission denied.");}
        check_ajax_referer(self::NONCE_ACTION_ADMIN,"nonce");
        $ids=isset($_POST["ids"])?array_filter(array_map("absint",explode(",",$_POST["ids"]))):array();
        $status=isset($_POST["status"])?sanitize_key($_POST["status"]):"";
        if(empty($ids)||empty($status)){wp_send_json_error("Invalid parameters.");}
        global $wpdb;$table=$wpdb->prefix."tqb_submissions";$placeholders=implode(",",array_fill(0,count($ids),"%d"));
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET status = %s, updated_at = %s WHERE id IN ($placeholders)",array_merge(array($status,current_time("mysql")),$ids)));
        wp_send_json_success();
    }
    public function ajax_bulk_delete() {
        if(!current_user_can("manage_options")){wp_send_json_error("Permission denied.");}
        check_ajax_referer(self::NONCE_ACTION_ADMIN,"nonce");
        $ids=isset($_POST["ids"])?array_filter(array_map("absint",explode(",",$_POST["ids"]))):array();
        if(empty($ids)){wp_send_json_error("No IDs provided.");}
        global $wpdb;$table=$wpdb->prefix."tqb_submissions";$placeholders=implode(",",array_fill(0,count($ids),"%d"));
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ($placeholders)",$ids));
        wp_send_json_success();
    }
    public function ajax_send_email() {
        if(!current_user_can("manage_options")){wp_send_json_error("Permission denied.");}
        $to=isset($_POST["to_email"])?sanitize_email($_POST["to_email"]):"";
        $subject=isset($_POST["subject"])?sanitize_text_field($_POST["subject"]):"";
        $message=isset($_POST["message"])?sanitize_textarea_field($_POST["message"]):"";
        if(empty($to)||empty($subject)||empty($message)){wp_send_json_error("All fields are required.");}
        $headers=array("Content-Type: text/plain; charset=UTF-8");
        $sent=wp_mail($to,$subject,$message,$headers);
        if($sent){wp_send_json_success();}else{wp_send_json_error("Failed to send email.");}
    }
}
