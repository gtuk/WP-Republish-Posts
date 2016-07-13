<?php
/**
 * Plugin Name: Gtuk republish posts
 * Description: A plugin to add a republish date to pages, posts and custom post types.
 * Version: 1.1.0
 * Author: Gtuk
 * Author URI: http://gtuk.me
 * License: GPLv2
 * Text Domain: gtuk-republish-posts
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    die();
}

add_action( 'plugins_loaded', array ( GtukRepublishPosts::get_instance(), 'plugin_setup' ) );

class GtukRepublishPosts {

    /**
     * Plugin instance
     */
    protected static $instance = null;

    /**
     * URL to this plugin's directory
     */
    public $plugin_url = '';

    /**
     * Path to this plugin's directory
     */
    public $plugin_path = '';

    /**
     * Name of the text domain
     */
    public $text_domain = 'gtuk-republish-posts';

    /**
     * Access the plugin’s working instance
     *
     * @return  object of this class
     */
    public static function get_instance() {
        null === self::$instance and self::$instance = new self;
        return self::$instance;
    }

    /**
     * Plugin setup
     *
     * @return  void
     */
    public function plugin_setup() {
        $this->plugin_url    = plugins_url( '/', __FILE__ );
        $this->plugin_path   = plugin_dir_path( __FILE__ );

        $this->load_language( $this->text_domain );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_init', array( $this, 'add_columns' ) );

        if ( is_admin() ) {
            add_action( 'post_submitbox_misc_actions', array( $this, 'edit_republish_box' ) );
            add_action( 'save_post', array( $this, 'modify_post_content' ) );
        }

        add_action( 'republish_post',array( $this, 'republish' ), 10, 1 );
    }

    /**
     * Constructor
     */
    public function __construct() {}

    /**
     * Load the translation file
     *
     * @param   string $domain
     *
     * @return  void
     */
    public function load_language( $domain ) {
        load_plugin_textdomain(
            $domain,
            FALSE,
            $this->plugin_path . '/languages'
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_script( 'gtuk-republish-posts', plugins_url( 'js/republish-posts.js', __FILE__ ), array( 'jquery' ), '', true );
        wp_enqueue_style( 'gtuk-republish-posts', plugins_url( 'css/republish-posts.css', __FILE__ ) );
    }

    /**
     * Register column at the different post types
     */
    public function add_columns() {
        $args = array(
            'public'   => true,
            '_builtin' => false,
            'has_archive' => true,
        );
        $post_types = get_post_types( $args );

        add_action( 'pre_get_posts', array( $this, 'republish_order_by' ), 1 );

        add_action( 'manage_posts_custom_column', array( $this, 'print_republish_column' ), 10, 2 );
        add_filter( 'manage_edit-post_columns', array( $this, 'register_republish_column' ) );
        add_filter( 'manage_edit-post_sortable_columns', array( $this, 'sortable_republish_column' ) );

        add_action( 'manage_pages_custom_column', array( $this, 'print_republish_column' ), 10, 2 );
        add_filter( 'manage_edit-page_columns', array( $this, 'register_republish_column' ) );
        add_filter( 'manage_edit-page_sortable_columns', array( $this, 'sortable_republish_column' ) );

        /**
         * Add custom column to custom post types
         */
        foreach ( $post_types as $post_type ) {
            add_action( 'manage_'.$post_type.'s_custom_column', array( $this, 'print_republish_column' ), 10, 2 );
            add_filter( 'manage_edit-'.$post_type.'_columns', array( $this, 'register_republish_column' ) );
            add_filter( 'manage_edit-'.$post_type.'_sortable_columns', array( $this, 'sortable_republish_column' ) );
        }
    }

    /**
     * Display custom column content
     *
     * @param $column
     * @param $post_id
     */
    public function print_republish_column( $column, $post_id ) {
        switch ( $column ) {
            case 'republish':
                $timestamp = get_post_meta( $post_id, '_republish_datetime', true );
                if ( $timestamp ) {
                    echo date_i18n( get_option( 'date_format' ), strtotime( $timestamp ) ).'<br>';
                    printf( __( 'at %s O\'clock', $this->text_domain ), date_i18n( get_option( 'time_format' ), strtotime( $timestamp ) ) );
                }
                break;
        }
    }
    /**
     * Register custom column
     *
     * @param $columns
     * @return mixed
     */
    public function register_republish_column( $columns ) {
        $column_republish = array( 'republish' => __( 'Republish date', $this->text_domain ) );
        $columns = array_slice( $columns, 0, 9, true ) + $column_republish + array_slice( $columns, 1, null, true );
        return $columns;
    }

    /**
     * Make custom column sortable
     *
     * @param $columns
     *
     * @return mixed
     */
    public function sortable_republish_column( $columns ) {
        $columns['republish'] = 'republish';

        return $columns;
    }

    /**
     * Filter posts by column
     *
     * @param $query
     */
    public function republish_order_by( $query ) {
        if ( ! is_admin() ) {
            return;
        }

        if ( $query->is_main_query() && ( $orderby = $query->get( 'orderby' ) ) ) {
            switch ( $orderby ) {
                case 'republish':
                    $query->set( 'meta_key', '_republish_datetime' );
                    $query->set( 'orderby', 'meta_value' );
                    break;
            }
        }
    }

    /**
     * Show republish box in post edit
     */
    public function edit_republish_box() {
        global $post;

        if ( 'publish' != $post->post_status ) {
            return;
        }

        $timestamp = get_post_meta( $post->ID, '_republish_datetime', true );
        $post_meta = $timestamp;

        if ( empty( $timestamp ) ) {
            $timestamp = current_time( 'mysql' );
        }

        $monthList = array(
            '01' => '01-Jan',
            '02' => '02-Feb',
            '03' => '03-Mrz',
            '04' => '04-Apr',
            '05' => '05-Mai',
            '06' => '06-Jun',
            '07' => '07-Jul',
            '08' => '08-Aug',
            '09' => '09-Sep',
            '10' => '10-Okt',
            '11' => '11-Nov',
            '12' => '12-Dez',
        );

        $day = date( 'd', strtotime( $timestamp ) );
        $month = date( 'm', strtotime( $timestamp ) );
        $year = date( 'Y', strtotime( $timestamp ) );
        $hour = date( 'H', strtotime( $timestamp ) );
        $minute = date( 'i', strtotime( $timestamp ) );
        ?>
        <div class="misc-pub-section curtime">
            <span id="timestamp"><?php _e( 'Republish', $this->text_domain ); ?>:</span>
			<span>
				<b>
                    <?php
                    if ( ! empty( $post_meta ) ) {
                        $datef = __( 'M j, Y @ H:i' );
                        echo date_i18n( $datef, strtotime( $timestamp ) );
                    } else {
                        _e( 'Never', $this->text_domain );
                    }
                    ?>
                </b>
			</span>
            <a href="#edit_republish" class="edit-republish hide-if-no-js"><span aria-hidden="true"><?php _e( 'Edit', $this->text_domain ); ?></span> <span class="screen-reader-text"><?php _e( 'Edit republish date', $this->text_domain ); ?></span></a>
            <div id="gtuk-republish" class="hide-if-js">
                <div>
                    <label for="jj" class="screen-reader-text"><?php _e( 'Day', $this->text_domain ); ?></label>
                    <input type="text" id="jj" name="republish[day]" value="<?php echo $day; ?>" size="2" maxlength="2" autocomplete="off">
                    <label for="mm" class="screen-reader-text"><?php _e( 'Month', $this->text_domain ); ?></label>
                    <select id="mm" name="republish[month]">
                        <?php foreach ( $monthList as $key => $currentMonth ) { ?>
                            <option <?php echo ( $key == $month ? 'selected' : '' ) ?> value="<?php echo $key; ?>"><?php echo $currentMonth; ?></option>
                        <?php } ?>
                    </select>
                    <label for="aa" class="screen-reader-text"><?php _e( 'Year', $this->text_domain ); ?></label>
                    <input type="text" id="aa" name="republish[year]" value="<?php echo $year; ?>" size="4" maxlength="4" autocomplete="off">,
                    <label for="hh" class="screen-reader-text"><?php _e( 'Hour', $this->text_domain ); ?></label>
                    <input type="text" id="hh" name="republish[hour]" value="<?php echo $hour; ?>" size="2" maxlength="2" autocomplete="off"> :
                    <label for="mn" class="screen-reader-text"><?php _e( 'Minute', $this->text_domain ); ?></label>
                    <input type="text" id="mn" name="republish[minute]" value="<?php echo $minute; ?>" size="2" maxlength="2" autocomplete="off">
                </div>
                <div>
                    <a class="gtuk-cancel-republish hide-if-no-js button-cancel" href="#edit_republish"><?php _e( 'Cancel', $this->text_domain ); ?></a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * If post is saved
     *
     * @param $post_id
     */
    public function modify_post_content( $post_id ) {
        if ( null === $post_id ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( isset( $_POST['republish'] )
            && ! empty( $_POST['republish']['day'] )
            && ! empty( $_POST['republish']['month'] )
            && ! empty( $_POST['republish']['year'] )
            && ! empty( $_POST['republish']['hour'] )
            && ! empty( $_POST['republish']['minute'] )
        ) {
            $current_date = current_time( 'mysql' );
            $republish_date = $_POST['republish']['year'].'-'.$_POST['republish']['month'].'-'.$_POST['republish']['day'].' '.$_POST['republish']['hour'].':'.$_POST['republish']['minute'].':00';

            if ( $republish_date > $current_date ) {
                $timestamp = get_gmt_from_date( $republish_date, 'U' );
                update_post_meta( $post_id, '_republish_datetime', $republish_date );
                $this->schedule_republish( $post_id, $timestamp );
            } else {
                delete_post_meta( $post_id, '_republish_datetime' );
                $this->unschedule_republish( $post_id );
            }
        }
    }

    /**
     * Republish post
     *
     * @param $post_id
     */
    public function republish( $post_id ) {
        $post = array(
            'ID'            => $post_id,
            'post_date' => current_time( 'mysql' ),
            'post_date_gmt' => current_time( 'mysql', 1 ),
        );

        wp_update_post( $post );

        delete_post_meta( $post_id, '_republish_datetime' );
    }

    /**
     * Schedule event to republish post
     *
     * @param $post_id
     * @param $timestamp
     */
    private function schedule_republish( $post_id, $timestamp ) {
        $this->unschedule_republish( $post_id );
        wp_schedule_single_event( $timestamp, 'republish_post', array( $post_id ) );
    }

    /**
     * Unschedule event to republish post
     *
     * @param $post_id
     */
    private function unschedule_republish( $post_id ) {
        wp_clear_scheduled_hook( 'republish_post', array( $post_id ) );
    }
}
