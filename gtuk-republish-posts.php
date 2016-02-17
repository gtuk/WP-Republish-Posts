<?php
/**
 * Plugin Name: Gtuk republish posts
 * Description: A plugin to add a republish date to pages, posts and custom post types.
 * Version: 1.0.0
 * Author: Gtuk
 * Author URI: http://gtuk.me
 * License: GPLv2
 */

if ( ! defined( 'ABSPATH' ) ) {
    die();
}

class GtukRepublishPosts {

    /**
     * GtukRepublishPosts constructor
     */
    function __construct() {
        if ( is_admin() ) {
            add_action( 'post_submitbox_misc_actions', array( $this, 'post_submitbox_misc_actions' ) );
            add_action( 'save_post', array( $this, 'modify_post_content' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

            add_action ( 'manage_posts_custom_column',	array( $this, 'print_republish_column' ), 10, 2 );
            add_filter ( 'manage_edit-post_columns', array( $this, 'register_republish_column' ) );
        }

        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'republish_post', array( $this, 'republish_post' ) );
    }

    /**
     * Load plugin internationalisation
     */
    function load_textdomain() {
        load_plugin_textdomain( 'gtuk-republish-posts', get_template_directory() . '/languages/' );
    }

    /**
     * Enqueue admin scripts and styles
     */
    function enqueue_scripts() {
        wp_enqueue_script( 'gtuk-republish-posts', plugins_url( 'js/republish-posts.js', __FILE__ ), array( 'jquery' ), '', true );
        wp_enqueue_style( 'gtuk-republish-posts', plugins_url( 'css/republish-posts.css', __FILE__ ) );
    }

    /**
     * Display custom column content
     *
     * @param $column
     * @param $post_id
     */
    function print_republish_column( $column, $post_id ) {
        switch ( $column ) {
            case 'republish':
                $timestamp = get_post_meta($post_id, '_republish_datetime', true);

                if ( $timestamp ) {
                    echo date_i18n( get_option( 'date_format' ), strtotime( $timestamp ) );
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
    function register_republish_column( $columns ) {
        $columns['republish'] = __( 'Republish date', 'gtuk-republish-posts');
        return $columns;
    }

    /**
     * Show republish box in post edit
     */
    function post_submitbox_misc_actions() {
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
            <span id="timestamp"><?php _e( 'Republish', 'gtuk-republish-posts' ); ?>:</span>
			<span>
				<b>
                    <?php
                    if ( ! empty( $post_meta ) ) {
                        $datef = __( 'M j, Y @ H:i' );
                        echo date_i18n( $datef, strtotime( $timestamp ) );
                    } else {
                        _e( 'Never', 'gtuk-republish-posts' );
                    }
                    ?>
                </b>
			</span>
            <a href="#edit_republish" class="edit-republish hide-if-no-js"><span aria-hidden="true"><?php _e( 'Edit', 'gtuk-republish-posts' ); ?></span> <span class="screen-reader-text"><?php _e( 'Edit republish date', 'gtuk-republish-posts' ); ?></span></a>
            <div id="gtuk-republish" class="hide-if-js">
                <div>
                    <label for="jj" class="screen-reader-text"><?php _e( 'Day', 'gtuk-republish-posts' ); ?></label>
                    <input type="text" id="jj" name="republish[day]" value="<?php echo $day; ?>" size="2" maxlength="2" autocomplete="off">
                    <label for="mm" class="screen-reader-text"><?php _e( 'Month', 'gtuk-republish-posts' ); ?></label>
                    <select id="mm" name="republish[month]">
                        <?php foreach ( $monthList as $key => $currentMonth ) { ?>
                            <option <?php echo ( $key == $month ? 'selected' : '' ) ?> value="<?php echo $key; ?>"><?php echo $currentMonth; ?></option>
                        <?php } ?>
                    </select>
                    <label for="aa" class="screen-reader-text"><?php _e( 'Year', 'gtuk-republish-posts' ); ?></label>
                    <input type="text" id="aa" name="republish[year]" value="<?php echo $year; ?>" size="4" maxlength="4" autocomplete="off">,
                    <label for="hh" class="screen-reader-text"><?php _e( 'Hour', 'gtuk-republish-posts' ); ?></label>
                    <input type="text" id="hh" name="republish[hour]" value="<?php echo $hour; ?>" size="2" maxlength="2" autocomplete="off"> :
                    <label for="mn" class="screen-reader-text"><?php _e( 'Minute', 'gtuk-republish-posts' ); ?></label>
                    <input type="text" id="mn" name="republish[minute]" value="<?php echo $minute; ?>" size="2" maxlength="2" autocomplete="off">
                </div>
                <div>
                    <a class="gtuk-cancel-republish hide-if-no-js button-cancel" href="#edit_republish"><?php _e( 'Cancel', 'gtuk-republish-posts' ); ?></a>
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
    function republish_post( $post_id, $timestamp ) {
        wp_update_post( array( 'ID' => $post_id, 'post_date' => $timestamp ) );
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
        if ( wp_next_scheduled( 'republish_post', array( $post_id ) ) !== false ) {
            wp_clear_scheduled_hook( 'republish_post', array( $post_id ) );
        }
    }
}

new GtukRepublishPosts();
