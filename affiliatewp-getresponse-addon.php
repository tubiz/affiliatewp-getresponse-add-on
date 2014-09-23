<?php
/*
    Plugin Name: AffiliateWP GetResponse Add-on
    Plugin URI: http://bosun.me/affiliatewp-getresponse-addon
    Description: Adds a checkbox for new affiliate to subscribe to your GetResponse Campaign during signup.
    Version: 1.0.0
    Author: Tunbosun Ayinla
    Author URI: http://www.bosun.me
    License:           GPL-2.0+
    License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
    GitHub Plugin URI: https://github.com/tubiz/affiliatewp-getresponse-add-on
 */


if ( ! defined( 'ABSPATH' ) ) exit;

if( ! class_exists( 'AffiliateWP_GetResponse_Add_on' ) ){

    final class AffiliateWP_GetResponse_Add_on {
        private static $instance = false;

        public static function get_instance() {
            if ( ! self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_action( 'admin_init', array( $this, 'activation' ) );
            add_action( 'affwp_settings_integrations', array( $this, 'affwp_getresponse_settings' ), 10 , 1 );
            add_action( 'affwp_register_user', array( $this, 'affwp_getresponse_add_user_to_list'), 10 , 2 );

            if( !is_admin() ) {
                add_action( 'affwp_register_fields_before_tos', array( $this, 'affwp_getresponse_subscribe_checkbox' ) );
                add_action( 'affwp_affiliate_dashboard_before_submit', array( $this, 'affwp_dashboard_getresponse_subscribe_checkbox' ), 10, 2 );
                add_action( 'affwp_update_affiliate_profile_settings', array( $this, 'affwp_dashboard_getresponse_add_user_to_list' ) );
            }

            if( is_admin() ){
                add_action( 'affwp_new_affiliate_bottom', array( $this, 'affwp_getresponse_admin_subscribe_checkbox' ) );
                add_action( 'affwp_insert_affiliate', array( $this, 'affwp_getresponse_admin_add_user_to_list' ) );
                add_action( 'affwp_tools_tab_export_import',  array( $this, 'affwp_getresponse_export' ), 20 );
                add_action( 'affwp_export_getresponse', array( $this, 'affwp_process_getresponse_export' ) );
                add_action( 'admin_notices', array( $this, 'show_notices' ) );
            }
        }

        //check if affiliatewp is installed
        public function activation() {
            global $wpdb;

            $affwp_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/affiliate-wp/affiliate-wp.php', false, false );

            if ( ! class_exists( 'Affiliate_WP' ) ) {

                // is this plugin active?
                if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {

                    // deactivate the plugin
                    deactivate_plugins( plugin_basename( __FILE__ ) );

                    // unset activation notice
                    unset( $_GET[ 'activate' ] );

                    // display notice
                    add_action( 'admin_notices', array( $this, 'admin_notices' ) );
                }

            }
            else {
                add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'settings_link' ), 10, 2 );
            }
        }

        //show admin notice if affiliatewp isn't installed
        public function admin_notices() {

            $affwp_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/affiliate-wp/affiliate-wp.php', false, false );

            if ( ! class_exists( 'Affiliate_WP' ) ) {
                echo '<div class="error"><p>You must install and activate <strong><a href="https://affiliatewp.com/pricing" title="AffiliateWP" target="_blank">AffiliateWP</a></strong> to use <strong>AffiliateWP GetResponse Add-on</strong></p></div>';
            }

            if ( $affwp_plugin_data['Version'] < '1.1' ) {
                echo '<div class="error"><p><strong>AffiliateWP GetResponse Add-on</strong> requires <strong>AffiliateWP 1.1</strong> or greater. Please update <strong>AffiliateWP</strong>.</p></div>';
            }
        }

        //plugin settings link
        public function settings_link( $links ) {
            $plugin_link = array(
                '<a href="' . admin_url( 'admin.php?page=affiliate-wp-settings&tab=integrations' ) . '">Settings</a>',
            );
            return array_merge( $plugin_link, $links );
        }

        //affiliatewp getresponse settings
        public function affwp_getresponse_settings( $settings ) {

            $getresponse_api_key  = affiliate_wp()->settings->get( 'affwp_getresponse_api_key' );

            $getresponse_campaigns    = $this->affwp_getresponse_get_campaigns();

            if ($getresponse_campaigns === false ) {
                $getresponse_campaigns = array ();
            }

            if( ! empty ( $getresponse_api_key ) ){
                $getresponse_campaigns = array_merge( array( '' => 'Select a campaign' ), $getresponse_campaigns );
            }
            else{
                $getresponse_campaigns = array( '' => 'Enter your GetResponse API Key and save to see your campaigns' );
            }


            $affwp_getresponse_settings = array(
                'affwp_getresponse_header' => array(
                    'name' => '<strong>AffiliateWP GetResponse Settings</strong>',
                    'type' => 'header'
                ),
                'affwp_enable_getresponse' => array(
                    'name' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'desc' => 'Enable GetResponse Campaign. This will add a checkbox to the affiliate registration page.'
                ),
                'affwp_getresponse_form_label' => array(
                    'name' =>'Checkbox Label',
                    'desc' => '<br />Enter the form label here, this will be displayed on the registration page.',
                    'type' => 'text',
                    'std' => 'Signup for our newsletter'
                ),
                'affwp_getresponse_api_key' => array(
                    'name' =>'GetResponse API Key',
                    'desc' => '<br />Enter your GetResponse API Key here. Click <a href="https://app.getresponse.com/my_api_key.html" target="_blank">here</a> to login to GetResponse and get your API key.',
                    'type' => 'text',
                    'std' => ''
                ),
                'affwp_getresponse_campaign_id' => array(
                    'name' => 'Campaign',
                    'desc' => '<br />Select the campaign you want the affiliate to be added to when they register.<br >To export registered Affiliates to your GetResponse campaign, click the "Tools" sub-menu.',
                    'type' => 'select',
                    'options' => $getresponse_campaigns
                )
            );

            return array_merge( $settings, $affwp_getresponse_settings );
        }

        //add subscribe checkbox to the signup page
        public function affwp_getresponse_subscribe_checkbox(){
            $getresponse_enabled        = affiliate_wp()->settings->get( 'affwp_enable_getresponse' );
            $getresponse_label          = affiliate_wp()->settings->get( 'affwp_getresponse_form_label' );
            $getresponse_api_key        = affiliate_wp()->settings->get( 'affwp_getresponse_api_key' );
            $getresponse_campaign_id    = affiliate_wp()->settings->get( 'affwp_getresponse_campaign_id' );

            ob_start();
                if ( ! empty ( $getresponse_enabled ) && ! empty ( $getresponse_api_key )  && ! empty ( $getresponse_campaign_id ) ){ ?>
                <p>
                    <input name="affwp_getresponse_subscribe" id="affwp_getresponse_subscribe" type="checkbox" checked="checked"/>
                    <label for="affwp_getresponse_subscribe" style="width: auto;">
                        <?php
                            if ( ! empty ( $getresponse_label ) ){
                                echo $getresponse_label;
                            }
                            else{
                                echo 'Signup for our newsletter';
                            }
                        ?>
                    </label>
                </p>
                <?php
            }
            echo ob_get_clean();
        }

        //add subscribe checkbox to the add new affiliate page in the wordpress backend
        public function affwp_getresponse_admin_subscribe_checkbox(){
            $getresponse_enabled        = affiliate_wp()->settings->get( 'affwp_enable_getresponse' );
            $getresponse_label          = affiliate_wp()->settings->get( 'affwp_getresponse_form_label' );
            $getresponse_api_key        = affiliate_wp()->settings->get( 'affwp_getresponse_api_key' );
            $getresponse_campaign_id    = affiliate_wp()->settings->get( 'affwp_getresponse_campaign_id' );

            ob_start();
                if ( ! empty ( $getresponse_enabled ) && ! empty ( $getresponse_api_key )  && ! empty ( $getresponse_campaign_id ) ){ ?>
                <p>
                    <input name="affwp_getresponse_subscribe" id="affwp_getresponse_subscribe" type="checkbox" checked="checked"/>
                    <label for="affwp_getresponse_subscribe" style="width: auto;">Add Affiliate to GetResponse Campaign</label>
                </p>
                <?php
            }
            echo ob_get_clean();
        }

        //add new affiliate to campaign
        public function affwp_getresponse_add_user_to_list( $affiliate_id, $status ){

            $affiliate  = affiliate_wp()->affiliates->get_by( 'affiliate_id', $affiliate_id );
            $user_id    = $affiliate->user_id;

            $getresponse_api_key  = affiliate_wp()->settings->get( 'affwp_getresponse_api_key' );

            if( ! empty( $_POST['affwp_getresponse_subscribe'] ) && ! empty( $getresponse_api_key ) ) {

                $name   = ucwords( sanitize_text_field( $_POST['affwp_user_name'] ) );

                $email  = sanitize_text_field( $_POST['affwp_user_email'] );

                $this->affwp_add_to_getresponse( $name, $email, $user_id );

            }

            return false;
        }

        //add new affiliate to campaign from the admin add new affiliate page
        public function affwp_getresponse_admin_add_user_to_list( $add ){

            global $wpdb;

            $affiliate  = affiliate_wp()->affiliates->get_by( 'affiliate_id', $add );
            $user_id    = $affiliate->user_id;

            $email      = $wpdb->get_var( $wpdb->prepare( "SELECT user_email FROM $wpdb->users WHERE ID = '%d'", $user_id ) );
            $name       = $wpdb->get_var( $wpdb->prepare( "SELECT display_name FROM $wpdb->users WHERE ID = '%d'", $user_id ) );

            $getresponse_api_key  = affiliate_wp()->settings->get( 'affwp_getresponse_api_key' );

            if( ! empty( $_POST['affwp_getresponse_subscribe'] ) && ! empty( $getresponse_api_key ) ) {

                $this->affwp_add_to_getresponse( $name, $email, $user_id );

            }

            return false;
        }

        //get getresponse campaigns
        public function affwp_getresponse_get_campaigns(){

            if ( ! class_exists( 'jsonRPCClient' ) )
                require_once( 'classes/jsonRPCClient.php' );

            $api_url = 'http://api2.getresponse.com';

            $client = new jsonRPCClient($api_url);

            try {

                $getresponse_api_key      = affiliate_wp()->settings->get( 'affwp_getresponse_api_key' );
                $getresponse_api_key      = trim( $getresponse_api_key );

                $getresponse_campaigns        = array();

                $campaigns = $client->get_campaigns( $getresponse_api_key );

                foreach ($campaigns as $key => $value) {
                    $getresponse_campaigns[ $key ]  = $value[ 'name' ];
                }

                return $getresponse_campaigns;
            }
            catch (Exception $e) {
                return false;
            }
        }

        //Add subscribe checkbox to the Affiliate settings dashboard page
        public function affwp_dashboard_getresponse_subscribe_checkbox(  $affiliate_id, $user_id ){

            $getresponse_enabled        = affiliate_wp()->settings->get( 'affwp_enable_getresponse' );
            $getresponse_label          = affiliate_wp()->settings->get( 'affwp_getresponse_form_label' );
            $getresponse_api_key        = affiliate_wp()->settings->get( 'affwp_getresponse_api_key' );
            $getresponse_campaign_id    = affiliate_wp()->settings->get( 'affwp_getresponse_campaign_id' );

            $subscribe_status           = get_user_meta( $user_id, 'tbz_affwp_subscribed_to_getresponse', true );

            if( ! $subscribe_status && $getresponse_enabled && $getresponse_api_key && $getresponse_campaign_id) {
                ob_start();
            ?>
                    <p>
                        <label for="affwp_getresponse_subscribe" style="width: auto;">
                        <input name="affwp_getresponse_subscribe" id="affwp_getresponse_subscribe" type="checkbox"/>
                            <?php
                                if ( ! empty ( $getresponse_label ) ){
                                    echo $getresponse_label;
                                }
                                else{
                                    echo 'Signup for our newsletter';
                                }
                            ?>
                        </label>
                    </p>
            <?php
                echo ob_get_clean();

            }
        }


        //Add new Affiliate from the Affiliate settings dashboard page
        public function affwp_dashboard_getresponse_add_user_to_list( $data ){

            global $wpdb;

            $affiliate_id   = $data['affiliate_id'];

            $affiliate      = affiliate_wp()->affiliates->get_by( 'affiliate_id', $affiliate_id );
            $user_id        = $affiliate->user_id;

            $email          = $wpdb->get_var( $wpdb->prepare( "SELECT user_email FROM $wpdb->users WHERE ID = '%d'", $user_id ) );
            $name           = affiliate_wp()->affiliates->get_affiliate_name( $affiliate_id );

            $getresponse_api_key        = affiliate_wp()->settings->get( 'affwp_getresponse_api_key' );


            if( ! empty( $_POST['affwp_getresponse_subscribe'] ) && $getresponse_api_key  ) {

                $this->affwp_add_to_getresponse( $name, $email, $user_id );

            }

            return false;
        }

        public function affwp_getresponse_export(){

            $getresponse_api_key        = affiliate_wp()->settings->get( 'affwp_getresponse_api_key' );
            $getresponse_campaign_id    = affiliate_wp()->settings->get( 'affwp_getresponse_campaign_id' );

            if( $getresponse_api_key && $getresponse_campaign_id) {
        ?>
                <div class="metabox-holder">
                    <div class="postbox" style="margin: 0px 8px 20px;">
                        <h3><span><?php _e( 'Export Affiliates To GetResponse', 'affiliate-wp' ); ?></span></h3>
                        <div class="inside">
                            <p><?php _e( 'Export all Affiliates to GetResponse. This will add all the Affiliates to your GetResponse Campaign', 'affiliate-wp' ); ?></p>
                            <form method="post" action="<?php echo admin_url( 'admin.php?page=affiliate-wp-tools&tab=export_import' ); ?>">
                                <p><input type="hidden" name="affwp_action" value="export_getresponse" /></p>
                                <p>
                                    <?php wp_nonce_field( 'affwp_getresponse_export_nonce', 'affwp_getresponse_export_nonce' ); ?>
                                    <?php submit_button( __( 'Export Affiliates to GetResponse Campaign', 'affiliate-wp' ), 'secondary', 'submit', false ); ?>
                                </p>
                            </form>
                        </div><!-- .inside -->
                    </div><!-- .postbox -->
                </div>
        <?php
            }
        }

        public function affwp_process_getresponse_export(){

            if( empty( $_POST['affwp_getresponse_export_nonce'] ) )
                return;

            if( ! wp_verify_nonce( $_POST['affwp_getresponse_export_nonce'], 'affwp_getresponse_export_nonce' ) )
                return;

            if( ! current_user_can( 'manage_options' ) )
                return;

            $affiliates = affiliate_wp()->affiliates->get_affiliates( array(
                'orderby'  => 'date_registered',
                'order'    => 'ASC',
                'number'   => -1,
                'date'     => $date
            ) );

            if( $affiliates ) {
                foreach( $affiliates as $affiliate ) {

                    global $wpdb;

                    $user_id = $affiliate->user_id;

                    $email   = $wpdb->get_var( $wpdb->prepare( "SELECT user_email FROM $wpdb->users WHERE ID = '%d'", $user_id ) );
                    $name    = $wpdb->get_var( $wpdb->prepare( "SELECT display_name FROM $wpdb->users WHERE ID = '%d'", $user_id ) );


                    $subscribe_status       = get_user_meta( $user_id, 'tbz_affwp_subscribed_to_getresponse', true );

                    $getresponse_api_key    = affiliate_wp()->settings->get( 'affwp_getresponse_api_key' );
                    $getresponse_enabled    = affiliate_wp()->settings->get( 'affwp_enable_getresponse' );
                    $getresponse_campaign_id    = affiliate_wp()->settings->get( 'affwp_getresponse_campaign_id' );

                    if( ! $subscribe_status && $getresponse_enabled && $getresponse_api_key && $getresponse_campaign_id) {

                        $this->affwp_add_to_getresponse( $name, $email, $user_id );

                    }
                }

                wp_safe_redirect( admin_url( 'admin.php?page=affiliate-wp-tools&affwp_notice=affiliate_exported' ) );
                exit;
            }
        }

        public function show_notices() {

            $class = 'updated';

            if ( isset( $_GET['affwp_notice'] ) && $_GET['affwp_notice'] ) {

                switch( $_GET['affwp_notice'] ) {

                    case 'affiliate_exported' :

                        $message = __( 'Affiliates Exported to GetResponse', 'affiliate-wp' );

                        break;
                }
            }

            if ( ! empty( $message ) ) {
                echo '<div class="' . esc_attr( $class ) . '"><p><strong>' .  $message  . '</strong></p></div>';
            }

        }

        //Add registered affiliates to GetResponse helper function
        public function affwp_add_to_getresponse( $name, $email, $user_id ){

            if( empty( $name ) || empty( $email ) || empty( $user_id ) )
                return;

            $getresponse_campaign_id    = affiliate_wp()->settings->get( 'affwp_getresponse_campaign_id' );
            $getresponse_api_key        = affiliate_wp()->settings->get( 'affwp_getresponse_api_key' );
            $getresponse_api_key        = trim( $getresponse_api_key );

            if ( ! class_exists( 'jsonRPCClient' ) )
                require_once( 'classes/jsonRPCClient.php' );

            $api_url = 'http://api2.getresponse.com';

            $client = new jsonRPCClient($api_url);

            try{
                $response = $client->add_contact(
                    $getresponse_api_key,
                    array (
                        'campaign'  => $getresponse_campaign_id,
                        'name'      => $name,
                        'email'     => $email
                    )
                );

                update_user_meta( $user_id, 'tbz_affwp_subscribed_to_getresponse', 'yes' );

                return true;
            }

            catch (Exception $e) {
                return false;
            }

        }

    }

}

function tbz_affwp_getresponse_addon() {
    return AffiliateWP_GetResponse_Add_on::get_instance();
}
add_action( 'plugins_loaded', 'tbz_affwp_getresponse_addon' );
