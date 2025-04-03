<?php
/*
Plugin Name: SAI Google Maps Lead Scraper
Description: Scrapes leads from Google Maps using the Places API.
Version: 1.3
Author: Your Name
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add a menu item in the WordPress dashboard
add_action('admin_menu', 'sgmls_add_menu');

function sgmls_add_menu() {
    add_menu_page('SAI Google Maps Lead Scraper', 'Lead Scraper', 'manage_options', 'sgmls_lead_scraper', 'sgmls_scraper_page', 'dashicons-admin-site', 6);
    add_submenu_page('sgmls_lead_scraper', 'Saved Leads', 'Saved Leads', 'manage_options', 'sgmls_saved_leads', 'sgmls_saved_leads_page');
    add_submenu_page('sgmls_lead_scraper', 'API Setting', 'API Setting', 'manage_options', 'sgmls_api_setting', 'sgmls_api_setting_page');
}

// Create table to store leads on plugin activation
register_activation_hook(__FILE__, 'sgmls_create_leads_table');

function sgmls_create_leads_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sgmls_leads';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        address text NOT NULL,
        phone varchar(20),
        website varchar(255),
        place_url varchar(255),
        search_query varchar(255),
        date_added datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Lead Scraper page
function sgmls_scraper_page() {
    $query = isset($_POST['sgmls_query']) ? sanitize_text_field($_POST['sgmls_query']) : (get_user_option('sgmls_query') ?: 'restaurant');
    $latitude = isset($_POST['sgmls_latitude']) ? sanitize_text_field($_POST['sgmls_latitude']) : (get_user_option('sgmls_latitude') ?: '37.7749');
    $longitude = isset($_POST['sgmls_longitude']) ? sanitize_text_field($_POST['sgmls_longitude']) : (get_user_option('sgmls_longitude') ?: '-122.4194');
    $radius = isset($_POST['sgmls_radius']) ? intval($_POST['sgmls_radius']) : (get_user_option('sgmls_radius') ?: '5000');
    $results_per_page = isset($_POST['sgmls_results_per_page']) ? intval($_POST['sgmls_results_per_page']) : (get_user_option('sgmls_results_per_page') ?: '20');

    ?>
    <div class="wrap">
        <h1>SAI Google Maps Lead Scraper</h1>
        <form method="post" action="" id="sgmls_form">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Search Query</th>
                    <td><input type="text" name="sgmls_query" value="<?php echo esc_attr($query); ?>" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Location</th>
                    <td>
                        <div id="map" style="height: 400px;"></div>
                        <input type="hidden" name="sgmls_latitude" id="sgmls_latitude" value="<?php echo esc_attr($latitude); ?>" />
                        <input type="hidden" name="sgmls_longitude" id="sgmls_longitude" value="<?php echo esc_attr($longitude); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Radius (meters)</th>
                    <td><input type="number" name="sgmls_radius" value="<?php echo esc_attr($radius); ?>" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Results per Page</th>
                    <td><input type="number" name="sgmls_results_per_page" value="<?php echo esc_attr($results_per_page); ?>" required /></td>
                </tr>
            </table>
            <?php submit_button('Scrape Leads'); ?>
        </form>
        <div id="sgmls_loader" style="display:none;">
            <p>SAI Plugins: Please wait, we are collecting leads...</p>
        </div>
    </div>
    <script type="text/javascript">
        document.getElementById('sgmls_form').onsubmit = function() {
            document.getElementById('sgmls_loader').style.display = 'block';
        };

        function initMap() {
            var lat = parseFloat(document.getElementById('sgmls_latitude').value);
            var lng = parseFloat(document.getElementById('sgmls_longitude').value);
            var map = new google.maps.Map(document.getElementById('map'), {
                zoom: 13,
                center: {lat: lat, lng: lng}
            });
            var marker = new google.maps.Marker({
                position: {lat: lat, lng: lng},
                map: map,
                draggable: true
            });
            google.maps.event.addListener(marker, 'dragend', function(event) {
                document.getElementById('sgmls_latitude').value = event.latLng.lat();
                document.getElementById('sgmls_longitude').value = event.latLng.lng();
            });
        }
    </script>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr(get_option('sgmls_api_key')); ?>&callback=initMap"></script>
    <?php

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $query = sanitize_text_field($_POST['sgmls_query']);
        $latitude = sanitize_text_field($_POST['sgmls_latitude']);
        $longitude = sanitize_text_field($_POST['sgmls_longitude']);
        $radius = intval($_POST['sgmls_radius']);
        $results_per_page = intval($_POST['sgmls_results_per_page']);

        update_user_option(get_current_user_id(), 'sgmls_query', $query);
        update_user_option(get_current_user_id(), 'sgmls_latitude', $latitude);
        update_user_option(get_current_user_id(), 'sgmls_longitude', $longitude);
        update_user_option(get_current_user_id(), 'sgmls_radius', $radius);
        update_user_option(get_current_user_id(), 'sgmls_results_per_page', $results_per_page);

        if ($results_per_page > 20) {
            echo '<div class="notice notice-error"><p>Maximum leads limit is 20. Please change latitude and longitude for better results.</p></div>';
        } else {
            sgmls_scrape_leads($query, $latitude, $longitude, $radius, $results_per_page);
        }
    }
}

// API Setting page
function sgmls_api_setting_page() {
    ?>
    <div class="wrap">
        <h1>Google Maps API Setting</h1>
        <form method="post" action="options.php">
            <?php settings_fields('sgmls_api_setting'); ?>
            <?php do_settings_sections('sgmls_api_setting'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Google Maps API Key</th>
                    <td><input type="text" name="sgmls_api_key" value="<?php echo esc_attr(get_option('sgmls_api_key')); ?>" required /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'sgmls_api_setting_init');

function sgmls_api_setting_init() {
    register_setting('sgmls_api_setting', 'sgmls_api_key');
}

function sgmls_scrape_leads($query, $latitude, $longitude, $radius, $results_per_page) {
    $location = $latitude . ',' . $longitude;
    $api_key = get_option('sgmls_api_key');
    if (empty($api_key)) {
        echo '<div class="notice notice-error"><p>Please set your Google Maps API Key in the API Setting menu.</p></div>';
        return;
    }

    $url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=$location&radius=$radius&keyword=$query&key=$api_key&pagetoken=";
    $places = sgmls_get_all_places($url, $api_key, $results_per_page);

    if (empty($places)) {
        echo '<div class="notice notice-warning"><p>No results found for the given location, Please change latitude and longitude!</p></div>';
        return;
    }

    echo '<h2>Leads</h2>';
    echo '<table class="widefat fixed sgmls-table">';
    echo '<thead><tr><th>Serial No.</th><th>Name</th><th>Address</th><th>Phone Number</th><th>Website</th><th>Direct Link</th><th>Action</th></tr></thead>';
    echo '<tbody>';

    $serial_number = 1;
    foreach ($places as $place) {
        $details = sgmls_get_place_details($place['place_id'], $api_key);
        $place_url = 'https://www.google.com/maps/place/?q=place_id:' . $place['place_id'];
        echo '<tr>';
        echo '<td>' . esc_html($serial_number++) . '</td>';
        echo '<td>' . esc_html($place['name']) . '</td>';
        echo '<td>' . esc_html($place['vicinity']) . '</td>';
        echo '<td>' . esc_html($details['formatted_phone_number'] ?? 'N/A') . '</td>';
        echo '<td>' . (isset($details['website']) ? '<a href="' . esc_url($details['website']) . '" target="_blank">' . esc_html($details['website']) . '</a>' : 'N/A') . '</td>';
        echo '<td><a href="' . esc_url($place_url) . '" target="_blank">View on Google Maps</a></td>';
        echo '<td><button class="button sgmls-save-lead" data-name="' . esc_attr($place['name']) . '" data-address="' . esc_attr($place['vicinity']) . '" data-phone="' . esc_attr($details['formatted_phone_number'] ?? '') . '" data-website="' . esc_attr($details['website'] ?? '') . '" data-url="' . esc_attr($place_url) . '">Save Lead</button></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

function sgmls_get_all_places($url, $api_key, $results_per_page) {
    $places = [];
    $page_token = '';
    $limit = 20;

    while (count($places) < $results_per_page && count($places) < $limit) {
        $response = wp_remote_get($url . $page_token);
        if (is_wp_error($response)) {
            return [];
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($data['status'] != 'OK') {
            break;
        }
        $places = array_merge($places, $data['results']);
        if (!isset($data['next_page_token'])) {
            break;
        }
        $page_token = '&pagetoken=' . $data['next_page_token'];
        sleep(2);
    }
    return array_slice($places, 0, $results_per_page);
}

function sgmls_get_place_details($place_id, $api_key) {
    $url = "https://maps.googleapis.com/maps/api/place/details/json?place_id=$place_id&key=$api_key";
    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        return [];
    }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if ($data['status'] != 'OK') {
        return [];
    }
    return $data['result'];
}

// Enqueue custom CSS and JS
add_action('admin_enqueue_scripts', 'sgmls_enqueue_scripts');

function sgmls_enqueue_scripts() {
    wp_enqueue_style('sgmls_styles', plugin_dir_url(__FILE__) . 'sai-google-maps-lead-scraper.css');
    wp_enqueue_script('sgmls_script', plugin_dir_url(__FILE__) . 'sai-google-maps-lead-scraper.js', array('jquery'), null, true);
    wp_localize_script('sgmls_script', 'sgmls_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}

add_action('wp_ajax_sgmls_save_lead', 'sgmls_save_lead');
function sgmls_save_lead() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sgmls_leads';

    $name = sanitize_text_field($_POST['name']);
    $address = sanitize_text_field($_POST['address']);
    $phone = sanitize_text_field($_POST['phone']);
    $website = esc_url_raw($_POST['website']);
    $place_url = esc_url_raw($_POST['place_url']);
    $search_query = sanitize_text_field($_POST['search_query']);

    $result = $wpdb->insert($table_name, array(
        'name' => $name,
        'address' => $address,
        'phone' => $phone,
        'website' => $website,
        'place_url' => $place_url,
        'search_query' => $search_query,
        'date_added' => current_time('mysql')
    ));

    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error(array('message' => 'Failed to save lead: ' . $wpdb->last_error));
    }
}

// Saved Leads page
function sgmls_saved_leads_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sgmls_leads';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY date_added DESC");

    echo '<div class="wrap">';
    echo '<h1>Saved Leads</h1>';
    echo '<table class="widefat fixed sgmls-table">';
    echo '<thead><tr><th>Serial No.</th><th>Name</th><th>Address</th><th>Phone Number</th><th>Website</th><th>Direct Link</th><th>Search Query</th><th>Date Added</th></tr></thead>';
    echo '<tbody>';

    $serial_number = 1;
    foreach ($results as $row) {
        echo '<tr>';
        echo '<td>' . esc_html($serial_number++) . '</td>';
        echo '<td>' . esc_html($row->name) . '</td>';
        echo '<td>' . esc_html($row->address) . '</td>';
        echo '<td>' . esc_html($row->phone) . '</td>';
        echo '<td>' . (isset($row->website) ? '<a href="' . esc_url($row->website) . '" target="_blank">' . esc_html($row->website) . '</a>' : 'N/A') . '</td>';
        echo '<td><a href="' . esc_url($row->place_url) . '" target="_blank">View on Google Maps</a></td>';
        echo '<td>' . esc_html($row->search_query) . '</td>'; // Displaying Search Query
        echo '<td>' . esc_html($row->date_added) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}
?>
