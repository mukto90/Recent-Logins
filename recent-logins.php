<?php
/**
 * Plugin Name: Recent Logins
 * Description: Is there someone else logging in to your account? Keep an eye on your recent login records. This awesome plugin will help you see your latest login records including time of login, IP address, street address, city, country, location map, browser and OS info.
 * Plugin URI: http://medhabi.com
 * Author: Nazmul Ahsan
 * Author URI: http://nazmulahsan.me
 * Version: 1.0.0
 * Requires at least: 3.0
 * Tested up to: 4.5
 */

/**
 * if accessed directly, exit.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class for this plugin
 */
if( ! class_exists( 'Recent_Logins' ) ) :
class Recent_Logins {

	public function __construct(){
		define( 'RECENT_LOGIN_PRO_URL', 'http://medhabi.com/product/recent-logins-pro/' );

		/**
		 * if we have 'pro/pro.php' file, that means we are using pro version of this plugin.
		 */
		define( 'RECENT_LOGIN_PRO', file_exists( dirname( __FILE__ ) . '/pro/pro.php' ) );

		self::hooks();

		if( RECENT_LOGIN_PRO ) require_once dirname( __FILE__ ) . '/pro/pro.php';
	}

	public function hooks(){
		add_action( 'plugins_loaded', array( $this, 'install' ) );
		add_action( 'wp_login', array( $this, 'add_log' ), 10, 2 );
		add_action( 'show_user_profile', array( $this, 'show_recent_logins' ) );
		add_action( 'edit_user_profile', array( $this, 'show_recent_logins' ) );

		add_filter( 'manage_site-users-network_columns', array( $this, 'manage_user_add_column' ) );
		add_filter( 'manage_users_columns', array( $this, 'manage_user_add_column' ) );
		add_filter( 'wpmu_users_columns', array( $this, 'manage_user_add_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'manage_user_show_content' ), 10, 3 );

		if( ! RECENT_LOGIN_PRO ) add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links') );
	}

	public function install(){
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table_name = $wpdb->prefix . "recent_logins";

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			user_id mediumint(9) NOT NULL,
			time int(10) NOT NULL,
			ip_address tinytext NOT NULL,
			client text NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Add a log entry when a user logges in.
	 */
	public function add_log( $user_login, $user ){
		global $wpdb;

		$table_name = $wpdb->prefix . "recent_logins";

		$wpdb->insert( 
			$table_name,
			array(
				'user_id' => $user->data->ID,
				'time' => time(),
				'ip_address' => $_SERVER['REMOTE_ADDR'],
				'client' => serialize( $this->getBrowser() )
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s'
			) 
		);

		update_user_meta( $user->data->ID, 'last-login', time() );
	}

	/**
	 * recent login record table in user profile page
	 */
	public function show_recent_logins( $profileuser ){
		global $wpdb;

		$table_name = $wpdb->prefix . "recent_logins";

		$user_id = $profileuser->data->ID;

		$limit = apply_filters( 'mdc_num_recent_logins', 50 );

		$logs = $wpdb->get_results( "SELECT * FROM $table_name WHERE user_id = $user_id ORDER BY id DESC LIMIT $limit" );
		?>
		<table class="form-table">
			<tr class="recent-logins-wrap hide-if-no-js">
				<th><?php _e( 'Recent Logins' ); ?></th>
				<td aria-live="assertive">
					<?php if( count( $logs ) ) : ?>
					<table class="recent-login-table">
						<thead>
							<tr>
								<th>Time</th>
								<th>IP Address</th>
								<th>Location</th>
								<th>Platform</th>
								<th>Browser</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach( $logs as $log ){
							$client = unserialize( $log->client ); ?>
							<tr>
								<td title="<?php echo $this->ago( $log->time ); ?>"><?php echo date( get_option( 'date_format' ) . " " . get_option( 'time_format' ), $log->time ); ?></td>
								<td><?php echo $log->ip_address; ?></td>
								<?php echo apply_filters( 'ip_details_button', '<td><input type="button" class="button button-secondary" onclick="alert(\'Pro Feature!\')" value="Show" /></td>', $log ); ?>
								<td><?php echo $client['platform']; ?></td>
								<td><?php echo $client['name']; ?> <?php echo $client['version']; ?></td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				<?php else: ?>
					<p>No logs found!</p>
				<?php endif; ?>
				<?php if( current_user_can( 'manage_options' ) && ! RECENT_LOGIN_PRO ) : ?>
					<p class="description"><a href="<?php echo RECENT_LOGIN_PRO_URL; ?>" target="_blank">Upgrade to Pro</a> to unlock more features like address &amp; location map! Don't worry, your users (non-admin) will not see this notice!</p>
				<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php echo apply_filters( 'recent_logins_below_table', '
		<style>
			.recent-login-table td,
			.recent-login-table th {
				display: table-cell;
				padding: 3px 0;
			}
		</style>
		' ); ?>
		
	<?php }

	public function manage_user_add_column( $cols ) {
	    $cols['last-login'] = 'Last Login';
	    return $cols;
	}

	public function manage_user_show_content( $value, $column_name, $user_id ) {
	    $user = get_userdata( $user_id );
		if ( 'last-login' == $column_name ){
			return apply_filters( 'manage_user_show_content', '<a href="'.RECENT_LOGIN_PRO_URL.'" target="_blank"><i style="">Pro Feature</i></a>', $user_id );
		}
	    return $value;
	}

	public function add_action_links ( $links ) {
		
		$mylinks = array(
			'<a href="'.RECENT_LOGIN_PRO_URL.'" target="_blank">Get Pro</a>'
		);
		
		return array_merge( $links, $mylinks );
	}

	/**
	 * Gets client's browser and OS info.
	 * @link http://www.php.net/manual/en/function.get-browser.php#101125
	 * slightly modified from source code.
	 */
	public function getBrowser(){ 
	    $u_agent = $_SERVER['HTTP_USER_AGENT']; 
	    $bname = 'Unknown';
	    $platform = 'Unknown';
	    $version= "";

	    //First get the platform?
	    if (preg_match('/linux/i', $u_agent)) {
	        $platform = 'Linux';
	    }
	    elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
	        $platform = 'Mac';
	    }
	    elseif (preg_match('/windows|win32/i', $u_agent)) {
	        $platform = 'Windows';
	    }
	    // added
	    if (preg_match('/iPhone/i', $u_agent)) {
	        $platform = 'iPhone';
	    }
	    if (preg_match('/Android/i', $u_agent)) {
	        $platform = 'Android';
	    }

	    // Next get the name of the useragent yes seperately and for good reason
	    if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent)) 
	    { 
	        $bname = 'Internet Explorer'; 
	        $ub = "MSIE"; 
	    } 
	    elseif(preg_match('/Firefox/i',$u_agent)) 
	    { 
	        $bname = 'Mozilla Firefox'; 
	        $ub = "Firefox"; 
	    } 
	    elseif(preg_match('/Chrome/i',$u_agent)) 
	    { 
	        $bname = 'Google Chrome'; 
	        $ub = "Chrome"; 
	    } 
	    elseif(preg_match('/Safari/i',$u_agent)) 
	    { 
	        $bname = 'Apple Safari'; 
	        $ub = "Safari"; 
	    } 
	    elseif(preg_match('/Opera/i',$u_agent)) 
	    { 
	        $bname = 'Opera'; 
	        $ub = "Opera"; 
	    } 
	    elseif(preg_match('/Netscape/i',$u_agent)) 
	    { 
	        $bname = 'Netscape'; 
	        $ub = "Netscape"; 
	    }

	    // finally get the correct version number
	    $known = array('Version', $ub, 'other');
	    $pattern = '#(?<browser>' . join('|', $known) .
	    ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
	    if (!preg_match_all($pattern, $u_agent, $matches)) {
	        // we have no matching number just continue
	    }

	    // see how many we have
	    $i = count($matches['browser']);
	    if ($i != 1) {
	        //we will have two since we are not using 'other' argument yet
	        //see if version is before or after the name
	        if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
	            $version= $matches['version'][0];
	        }
	        else {
	            $version= $matches['version'][1];
	        }
	    }
	    else {
	        $version= $matches['version'][0];
	    }

	    // check if we have a number
	    if ($version==null || $version=="") {$version="?";}

	    return array(
	        'userAgent' => $u_agent,
	        'name'      => $bname,
	        'version'   => $version,
	        'platform'  => $platform,
	        'pattern'   => $pattern
	    );
	}

	/**
	 * @link http://stackoverflow.com/a/14339355/3747157
	 */
	public static function ago( $ptime ){
	    $etime = time() - $ptime;

	    if ($etime < 1)
	    {
	        return '0 seconds';
	    }

	    $a = array( 365 * 24 * 60 * 60  =>  'year',
	                 30 * 24 * 60 * 60  =>  'month',
	                      24 * 60 * 60  =>  'day',
	                           60 * 60  =>  'hour',
	                                60  =>  'minute',
	                                 1  =>  'second'
	                );
	    $a_plural = array( 'year'   => 'years',
	                       'month'  => 'months',
	                       'day'    => 'days',
	                       'hour'   => 'hours',
	                       'minute' => 'minutes',
	                       'second' => 'seconds'
	                );

	    foreach ($a as $secs => $str)
	    {
	        $d = $etime / $secs;
	        if ($d >= 1)
	        {
	            $r = round($d);
	            return $r . ' ' . ($r > 1 ? $a_plural[$str] : $str) . ' ago';
	        }
	    }
	}

}
new Recent_Logins;
endif;