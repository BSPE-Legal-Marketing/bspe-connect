<?php
/**
 * Email delivery for form submissions.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps wp_mail with template-variable substitution and aggressive header
 * sanitization. From email and From name come from settings only — never
 * from form input — to prevent header injection.
 */
final class Mailer {

	/**
	 * Build subject + body, deliver via wp_mail. Returns true if wp_mail
	 * accepted the message for delivery.
	 *
	 * @param array<string,string> $vars Substitution variables for templates.
	 *
	 * @return bool wp_mail return value.
	 */
	public static function send( array $vars ): bool {
		$mail_to    = self::strip_breaks( (string) Settings::get( 'form.mail_to', '' ) );
		$mail_from  = self::strip_breaks( (string) Settings::get( 'form.mail_from', '' ) );
		$from_name  = self::strip_breaks( (string) Settings::get( 'form.mail_from_name', '{site_name}' ) );
		$subject    = self::strip_breaks( (string) Settings::get( 'form.mail_subject', 'New lead from {site_name}: {source}' ) );

		if ( '' === $mail_to ) {
			return false;
		}

		$recipients = self::parse_recipients( $mail_to );
		if ( empty( $recipients ) ) {
			return false;
		}

		$subject   = self::substitute( $subject, $vars );
		$from_name = self::substitute( $from_name, $vars );

		// Use admin_email as the From address fallback so wp_mail produces a deliverable envelope.
		if ( '' === $mail_from || ! is_email( $mail_from ) ) {
			$mail_from = (string) get_option( 'admin_email' );
		}

		$body = self::render_body( $vars );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $mail_from ),
		];

		// If the user supplied a valid email in the form, set Reply-To to that.
		if ( ! empty( $vars['email'] ) && is_email( $vars['email'] ) ) {
			$reply_name = '' !== ( $vars['name'] ?? '' ) ? $vars['name'] : $vars['email'];
			$headers[]  = sprintf( 'Reply-To: %s <%s>', self::strip_breaks( $reply_name ), $vars['email'] );
		}

		return (bool) wp_mail( $recipients, wp_strip_all_tags( $subject ), $body, $headers );
	}

	/**
	 * Substitute {placeholders} in a string. Values are HTML-escaped because
	 * the body is text/html. The subject runs the result through wp_strip_all_tags.
	 *
	 * @param array<string,string> $vars
	 */
	private static function substitute( string $template, array $vars ): string {
		$pairs = [];
		foreach ( $vars as $key => $value ) {
			$pairs[ '{' . $key . '}' ] = is_string( $value ) ? esc_html( $value ) : '';
		}
		return strtr( $template, $pairs );
	}

	/**
	 * Render an HTML email body summarizing the submission.
	 *
	 * Layout choices —
	 *   - 600 px max-width table-in-table (the bulletproof email pattern)
	 *     so Outlook desktop and Gmail render the same as iOS Mail.
	 *   - <meta name="viewport"> + <meta x-apple-disable-message-reformatting>
	 *     so iOS / Apple Mail don't auto-resize text. Plus a media query at
	 *     480 px that drops paddings + label width on phones.
	 *   - color-scheme + supported-color-schemes metas + a
	 *     prefers-color-scheme:dark CSS block so Apple Mail and modern Gmail
	 *     swap the page / card / text colors instead of auto-inverting the
	 *     light-mode design (which ruins brand color contrast).
	 *   - The header band uses the firm's chosen bar_bg + button_fg colors
	 *     from Design → Colors, so the email is on-brand for each install
	 *     instead of forcing the BSPE palette. The accent (link) color
	 *     comes from design.colors.accent. Body backgrounds + text stay
	 *     neutral so the email is always readable regardless of which
	 *     brand colors the firm picked.
	 *
	 * @param array<string,string> $vars
	 */
	private static function render_body( array $vars ): string {
		$rows = [
			__( 'Name', 'bspe-connect' )     => $vars['name'] ?? '',
			__( 'Phone', 'bspe-connect' )    => self::format_phone_display( $vars['phone'] ?? '' ),
			__( 'Email', 'bspe-connect' )    => $vars['email'] ?? '',
			__( 'Preferred', 'bspe-connect' ) => $vars['contact_pref'] ?? '',
			__( 'Message', 'bspe-connect' )  => $vars['message'] ?? '',
		];

		// Use the firm's chosen colors so the email matches the bar.
		// Hex values are passed through self::sanitize_hex which falls back
		// to the plugin defaults if the saved value isn't a valid hex.
		$header_bg = self::sanitize_hex( (string) Settings::get( 'design.colors.bar_bg', '#351E28' ),    '#351E28' );
		$header_fg = self::sanitize_hex( (string) Settings::get( 'design.colors.button_fg', '#FAF7F2' ), '#FAF7F2' );
		$accent    = self::sanitize_hex( (string) Settings::get( 'design.colors.accent', '#3AAFB9' ),    '#3AAFB9' );

		// Neutral surfaces — kept identical across installs so body
		// readability never depends on brand-color choices.
		$page_bg   = '#f5f4f1';
		$card_bg   = '#ffffff';
		$body_fg   = '#2a1820';
		$muted_fg  = '#6b5862';
		$rule      = 'rgba(53,30,40,0.08)';

		// Dark-mode counterparts. Header colors stay the firm's choice in
		// both modes — they were already designed to contrast with each
		// other on the bar, so they survive on a dark page too.
		$dark_page_bg  = '#14080d';
		$dark_card_bg  = '#20161a';
		$dark_body_fg  = '#ece4e6';
		$dark_muted_fg = '#a89aa0';
		$dark_rule     = 'rgba(255,255,255,0.10)';

		$preheader = sprintf(
			/* translators: 1: source (text/email), 2: site name */
			__( 'New %1$s lead from %2$s — details below.', 'bspe-connect' ),
			(string) ( $vars['source']    ?? '' ),
			(string) ( $vars['site_name'] ?? '' )
		);

		ob_start();
		?>
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="x-apple-disable-message-reformatting">
	<meta name="color-scheme" content="light dark">
	<meta name="supported-color-schemes" content="light dark">
	<title><?php echo esc_html( sprintf( /* translators: %s: site name */ __( 'New lead — %s', 'bspe-connect' ), $vars['site_name'] ?? '' ) ); ?></title>
	<!--[if mso]>
	<style type="text/css">
		table, td, div, h1, p, a { font-family: 'Segoe UI', Arial, sans-serif !important; }
	</style>
	<![endif]-->
	<style>
		/* Hide the preheader text from view but keep it in the inbox preview. */
		.bspe-preheader { display:none !important; visibility:hidden; mso-hide:all; opacity:0; height:0; width:0; max-height:0; max-width:0; overflow:hidden; }

		/* Phone layout — drop paddings, stack label/value. */
		@media screen and (max-width:480px) {
			.bspe-shell      { padding:14px 8px !important; }
			.bspe-card       { border-radius:10px !important; }
			.bspe-header     { padding:18px 18px !important; }
			.bspe-header-eyebrow { font-size:10px !important; }
			.bspe-header-title   { font-size:18px !important; }
			.bspe-body       { padding:18px 18px !important; }
			.bspe-row-label  { width:100% !important; display:block !important; padding:10px 0 2px !important; }
			.bspe-row-value  { width:100% !important; display:block !important; padding:0 0 10px !important; font-size:15px !important; }
			.bspe-meta       { font-size:11px !important; }
		}

		/* Dark-mode swap. Header colors are firm-controlled and intentionally
		   left alone (they're a paired bg/fg already). */
		@media (prefers-color-scheme: dark) {
			body, .bspe-shell, .bspe-shell-bg { background:<?php echo esc_attr( $dark_page_bg ); ?> !important; }
			.bspe-card       { background:<?php echo esc_attr( $dark_card_bg ); ?> !important; border-color:<?php echo esc_attr( $dark_rule ); ?> !important; }
			.bspe-row-value  { color:<?php echo esc_attr( $dark_body_fg ); ?> !important; }
			.bspe-row-label,
			.bspe-meta,
			.bspe-footnote   { color:<?php echo esc_attr( $dark_muted_fg ); ?> !important; }
			.bspe-rule       { border-color:<?php echo esc_attr( $dark_rule ); ?> !important; }
		}
		/* Outlook.com (Windows) — uses [data-ogsc] / [data-ogsb] instead of
		   prefers-color-scheme. Mirror the same swaps. */
		[data-ogsc] body,
		[data-ogsc] .bspe-shell,
		[data-ogsc] .bspe-shell-bg { background:<?php echo esc_attr( $dark_page_bg ); ?> !important; }
		[data-ogsc] .bspe-card       { background:<?php echo esc_attr( $dark_card_bg ); ?> !important; border-color:<?php echo esc_attr( $dark_rule ); ?> !important; }
		[data-ogsc] .bspe-row-value  { color:<?php echo esc_attr( $dark_body_fg ); ?> !important; }
		[data-ogsc] .bspe-row-label,
		[data-ogsc] .bspe-meta,
		[data-ogsc] .bspe-footnote   { color:<?php echo esc_attr( $dark_muted_fg ); ?> !important; }
		[data-ogsc] .bspe-rule       { border-color:<?php echo esc_attr( $dark_rule ); ?> !important; }
	</style>
</head>
<body class="bspe-shell-bg" style="margin:0;padding:0;background:<?php echo esc_attr( $page_bg ); ?>;color:<?php echo esc_attr( $body_fg ); ?>;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;mso-line-height-rule:exactly;">

	<div class="bspe-preheader"><?php echo esc_html( $preheader ); ?></div>

	<table role="presentation" class="bspe-shell" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:<?php echo esc_attr( $page_bg ); ?>;padding:24px 12px;">
		<tr><td align="center">
			<!--[if mso]><table role="presentation" align="center" width="600"><tr><td><![endif]-->
			<table role="presentation" class="bspe-card" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background:<?php echo esc_attr( $card_bg ); ?>;border-radius:14px;overflow:hidden;border:1px solid <?php echo esc_attr( $rule ); ?>;">
				<tr>
					<td class="bspe-header" style="background:<?php echo esc_attr( $header_bg ); ?>;padding:22px 28px;color:<?php echo esc_attr( $header_fg ); ?>;border-bottom:1px solid #D4AF37;">
						<div class="bspe-header-eyebrow" style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.78;color:<?php echo esc_attr( $header_fg ); ?>;">
							<?php
							/* translators: %s: site name */
							echo esc_html( sprintf( __( 'New lead via %s', 'bspe-connect' ), $vars['site_name'] ?? '' ) );
							?>
						</div>
						<div class="bspe-header-title" style="font-size:20px;font-weight:600;margin-top:4px;color:<?php echo esc_attr( $header_fg ); ?>;line-height:1.25;">
							<?php
							/* translators: %s: source (text/email) */
							echo esc_html( sprintf( __( '%s form submission', 'bspe-connect' ), ucfirst( $vars['source'] ?? '' ) ) );
							?>
						</div>
					</td>
				</tr>
				<tr>
					<td class="bspe-body" style="padding:24px 28px;">
						<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
						<?php foreach ( $rows as $label => $value ) :
							if ( '' === trim( (string) $value ) ) {
								continue;
							}
							?>
							<tr>
								<td class="bspe-row-label bspe-rule" style="padding:10px 0;border-bottom:1px solid <?php echo esc_attr( $rule ); ?>;width:130px;color:<?php echo esc_attr( $muted_fg ); ?>;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;vertical-align:top;">
									<?php echo esc_html( (string) $label ); ?>
								</td>
								<td class="bspe-row-value bspe-rule" style="padding:10px 0;border-bottom:1px solid <?php echo esc_attr( $rule ); ?>;font-size:14px;color:<?php echo esc_attr( $body_fg ); ?>;line-height:1.5;word-break:break-word;">
									<?php echo nl2br( esc_html( (string) $value ) ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</table>
						<?php if ( ! empty( $vars['page_url'] ) ) : ?>
							<p class="bspe-meta" style="margin:20px 0 0;font-size:12px;color:<?php echo esc_attr( $muted_fg ); ?>;line-height:1.5;">
								<?php esc_html_e( 'Submitted from:', 'bspe-connect' ); ?>
								<a href="<?php echo esc_url( $vars['page_url'] ); ?>" style="color:<?php echo esc_attr( $accent ); ?>;text-decoration:none;word-break:break-all;">
									<?php echo esc_html( $vars['page_url'] ); ?>
								</a>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<!--[if mso]></td></tr></table><![endif]-->
			<p class="bspe-footnote" style="margin:14px 0 0;font-size:11px;color:<?php echo esc_attr( $muted_fg ); ?>;letter-spacing:0.05em;">
				<?php esc_html_e( 'Sent by BSPE Connect', 'bspe-connect' ); ?>
			</p>
		</td></tr>
	</table>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Validate a hex color from settings; return the fallback if the saved
	 * value isn't a 3- or 6-digit hex. Used by render_body so a corrupt
	 * setting can never leak into the email as an unescaped string.
	 */
	private static function sanitize_hex( string $value, string $fallback ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return $fallback;
		}
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value ) ) {
			return $value;
		}
		return $fallback;
	}

	/**
	 * @return string[]
	 */
	private static function parse_recipients( string $raw ): array {
		$out = [];
		foreach ( preg_split( '/[,\s;]+/', $raw ) as $candidate ) {
			$candidate = trim( (string) $candidate );
			if ( '' !== $candidate && is_email( $candidate ) ) {
				$out[] = $candidate;
			}
		}
		return $out;
	}

	/**
	 * Strip CR/LF to prevent header injection.
	 */
	private static function strip_breaks( string $value ): string {
		return trim( str_replace( [ "\r", "\n", "\0" ], '', $value ) );
	}

	private static function format_phone_display( string $digits ): string {
		$d = preg_replace( '/\D/', '', $digits ) ?? '';
		if ( strlen( $d ) === 10 ) {
			return sprintf( '(%s) %s-%s', substr( $d, 0, 3 ), substr( $d, 3, 3 ), substr( $d, 6, 4 ) );
		}
		return $d;
	}
}
