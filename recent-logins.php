<?php
/**
 * Plugin Name: Recent Logins
 */

class Recent_Logins{
	public function __construct(){
		add_action( 'plugins_loaded', array( $this, 'create_table' ) );
		add_action( 'wp_login', array( $this, 'add_log' ), 10, 2 );
		add_action( 'show_user_profile', array( $this, 'show_recent_logins' ) );
		add_action( 'edit_user_profile', array( $this, 'show_recent_logins' ) );
	}

	public function create_table(){
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table_name = $wpdb->prefix . "recent_logins";

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			user_id mediumint(9) NOT NULL,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			ip_address tinytext NOT NULL,
			client text NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public function add_log( $user_login, $user ){
		global $wpdb;

		$table_name = $wpdb->prefix . "recent_logins";

		$wpdb->insert( 
			$table_name,
			array(
				'user_id' => $user->data->ID,
				'time' => date( 'Y-m-d H:i:s' ),
				'ip_address' => $_SERVER['REMOTE_ADDR'],
				'client' => serialize( $this->getBrowser() )
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s'
			) 
		);
	}

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
								<th>Date</th>
								<th>IP</th>
								<th>Operating System</th>
								<th>Browser</th>
								<th>Version</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach( $logs as $log ){
							$client = unserialize( $log->client );
							?>
							<tr>
								<td><?php echo $log->time; ?></td>
								<td><?php echo $log->ip_address; ?></td>
								<td><?php echo $client['platform']; ?></td>
								<td><?php echo $client['name']; ?></td>
								<td><?php echo $client['version']; ?></td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				<?php else: ?>
					<p>No logs found!</p>
				<?php endif; ?>
					<p class="description"><?php _e( 'Bla bla bla bla..' ); ?></p>
				</td>
			</tr>
		</table>
		<style>
			.recent-login-table td,
			.recent-login-table th {
				display: table-cell;
				padding: 3px 0;
			}
		</style>
	<?php }

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

}
new Recent_Logins;