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

		$plum  = '#351E28';
		$ivory = '#FAF7F2';
		$teal  = '#3AAFB9';
		$muted = '#6b5862';

		ob_start();
		?>
<!doctype html>
<html><body style="margin:0;background:<?php echo esc_attr( $ivory ); ?>;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#2a1820;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:<?php echo esc_attr( $ivory ); ?>;padding:24px 12px;">
	<tr><td align="center">
		<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background:#fff;border-radius:14px;overflow:hidden;border:1px solid rgba(53,30,40,0.08);">
			<tr><td style="background:<?php echo esc_attr( $plum ); ?>;padding:20px 28px;color:<?php echo esc_attr( $ivory ); ?>;border-bottom:1px solid #D4AF37;">
				<div style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.7;">
					<?php
					/* translators: %s: site name */
					echo esc_html( sprintf( __( 'New lead via %s', 'bspe-connect' ), $vars['site_name'] ?? '' ) );
					?>
				</div>
				<div style="font-size:20px;font-weight:600;margin-top:4px;">
					<?php
					/* translators: %s: source (text/email) */
					echo esc_html( sprintf( __( 'BSPE Connect — %s form', 'bspe-connect' ), ucfirst( $vars['source'] ?? '' ) ) );
					?>
				</div>
			</td></tr>
			<tr><td style="padding:24px 28px;">
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
				<?php foreach ( $rows as $label => $value ) :
					if ( '' === trim( (string) $value ) ) {
						continue;
					}
					?>
					<tr>
						<td style="padding:8px 0;border-bottom:1px solid rgba(53,30,40,0.08);width:130px;color:<?php echo esc_attr( $muted ); ?>;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;vertical-align:top;">
							<?php echo esc_html( (string) $label ); ?>
						</td>
						<td style="padding:8px 0;border-bottom:1px solid rgba(53,30,40,0.08);font-size:14px;color:#2a1820;line-height:1.5;">
							<?php echo nl2br( esc_html( (string) $value ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</table>
				<?php if ( ! empty( $vars['page_url'] ) ) : ?>
					<p style="margin:18px 0 0;font-size:12px;color:<?php echo esc_attr( $muted ); ?>;">
						<?php esc_html_e( 'Submitted from:', 'bspe-connect' ); ?>
						<a href="<?php echo esc_url( $vars['page_url'] ); ?>" style="color:<?php echo esc_attr( $teal ); ?>;text-decoration:none;">
							<?php echo esc_html( $vars['page_url'] ); ?>
						</a>
					</p>
				<?php endif; ?>
			</td></tr>
		</table>
		<p style="margin:14px 0 0;font-size:11px;color:<?php echo esc_attr( $muted ); ?>;letter-spacing:0.05em;">
			<?php esc_html_e( 'Sent by BSPE Connect', 'bspe-connect' ); ?>
		</p>
	</td></tr>
</table>
</body></html>
		<?php
		return (string) ob_get_clean();
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
