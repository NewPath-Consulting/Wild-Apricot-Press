<?php
class WAWPLoginPage {
	static function wawp_construct_page() {
		?>
		<div class="wrap">
			<h1>Connect Wild Apricot with WordPress!</h1>
			<div class="waSettings">
				<div class="loginChild">
					<p>In order to connect your Wild Apricot with your WordPress website, WA4WP requires the following credentials from your Wild Apricot account:</p>
					<ul>
						<li>API key</li>
						<li>Client ID</li>
						<li>Client secret</li>
					</ul>
					<p>If you currently do not have these credentials, no problem! Please follow the steps below to obtain them.</p>
					<ol>
						<li>First
					</ol>
				</div>
				<div class="loginChild">
					<form action="options.php" method="post">
						<?php
						settings_fields( 'wawp_wal_options' );
						do_settings_sections( 'wawp_wal' );
						submit_button( 'Save', 'primary' );
						?>
					</form>
					<!-- Check if form is valid -->
					<?php
					$user_options = get_option( 'wawp_wal_options' );
					if (!isset($user_options['api_key']) || !isset($user_options['client_ID']) || !isset($user_options['client_secret']) || $user_options['api_key'] == '' || $user_options['client_ID'] == '' || $user_options['client_secret'] == '') { // not valid
						echo '<p style="color:red">Invalid credentials!</p>';
					} else {
						echo '<p style="color:green">Success! Credentials saved!</p>';
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}
}
?>
