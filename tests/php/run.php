<?php
/**
 * Bare-PHP tests for the 3.9.0 custom form. Run: php tests/php/run.php
 */
require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/connect-stubs.php';
$root = dirname( __DIR__, 2 );
require $root . '/includes/class-settings.php';
require $root . '/includes/class-custom-form.php';
require $root . '/includes/class-cache-purge.php';
require $root . '/includes/class-elementor-import.php';
require $root . '/includes/class-webhook.php';
require $root . '/includes/class-submissions.php';
require $root . '/includes/class-form-api.php';
require $root . '/admin/class-settings-saver.php';
require __DIR__ . '/fixtures.php';

use BSPE\Connect\Custom_Form;
use BSPE\Connect\Elementor_Import;
use BSPE\Connect\Webhook;
use BSPE\Connect\Form_Api;
use BSPE\Connect\Settings;
use BSPE\Connect\Admin\Settings_Saver;

$fails = 0; $passes = 0;
function check( $cond, $label ) { global $fails, $passes; if ( $cond ) { $passes++; } else { $fails++; echo "FAIL: $label\n"; } }
function eq( $a, $b, $label ) { check( $a === $b, $label . ' — got ' . var_export( $a, true ) . ', want ' . var_export( $b, true ) ); }

/* ---------- Elementor import (global widget on page 97 → template 8355) */
$conv = Elementor_Import::convert( 97, 'bdb0bf3' );
check( is_array( $conv ), 'convert returns array' );
eq( count( $conv['steps'] ), 3, 'three steps' );
eq( array_column( $conv['steps'][0]['fields'], 'key' ), [ 'fname', 'lname', 'phone', 'email' ], 'step 1 keys (honeypots skipped)' );
eq( array_column( $conv['steps'][1]['fields'], 'key' ), [ 'city', 'state', 'employer_name', 'employer_city' ], 'step 2 keys' );
eq( array_column( $conv['steps'][2]['fields'], 'key' ), [ 'salary', 'still_employed', 'spanish', 'situation' ], 'step 3 keys' );
eq( $conv['steps'][2]['fields'][0]['type'], 'number', 'salary is number (template wins over stale stub copy)' );
eq( $conv['steps'][2]['fields'][0]['label'], 'Annual Salary', 'salary label from template' );
eq( $conv['steps'][2]['fields'][1]['placeholder'], 'Still Employed', 'select placeholder from empty option, not the stale "Anual Salary"' );
eq( $conv['steps'][1]['fields'][1]['placeholder'], 'Your location - State', 'state placeholder' );
eq( $conv['steps'][0]['title'], '', 'step title empty with step_type none' );
eq( $conv['back_label'], 'Back', 'back label from step previous_button BACK' );
eq( $conv['submit_label'], 'Submit', 'SUBMIT → Submit' );
eq( $conv['source']['form_id'], 'bdb0bf3', 'form id kept from stub' );
eq( $conv['source']['template_id'], 8355, 'template id recorded' );
check( in_array( 'subject (honeypot)', $conv['_skipped'], true ), 'honeypot reported as skipped' );

$conv2 = Elementor_Import::convert( 8355 );
eq( count( $conv2['steps'] ), 3, 'template post converts directly' );
check( is_wp_error( Elementor_Import::convert( 97, 'nope' ) ), 'unknown form id → WP_Error' );

/* ---------- normalize */
unset( $conv['_skipped'] );
$n = Custom_Form::normalize( $conv );
eq( $n['errors'], [], 'imported form normalizes without errors' );
$cfg = $n['config'];
$state = $cfg['steps'][1]['fields'][1];
eq( count( $state['options'] ), 50, '50 state options (placeholder line dropped; note AZ is missing on the site too)' );
eq( $state['options'][0], [ 'value' => 'AL', 'label' => 'AL' ], 'first state option' );
$roles = Custom_Form::effective_roles( $cfg );
eq( $roles, [ 'first_name' => 'fname', 'last_name' => 'lname', 'message' => 'situation', 'phone' => 'phone', 'email' => 'email' ], 'roles inferred' );

$bad = Custom_Form::normalize( [ 'steps' => [ [ 'fields' => [
	[ 'key' => 'a', 'type' => 'text', 'label' => 'A' ],
	[ 'key' => 'a', 'type' => 'text', 'label' => 'A again' ],
	[ 'key' => 'b', 'type' => 'banana', 'label' => 'B' ],
	[ 'key' => 'c', 'type' => 'text', 'label' => 'C', 'role' => 'phone' ],
	[ 'key' => '9x', 'type' => 'text', 'label' => 'D' ],
	[ 'key' => 'e', 'type' => 'select', 'label' => 'E', 'options' => [] ],
] ] ] ] );
eq( count( $bad['errors'] ), 5, 'five schema errors (dup key, bad type, wrong role type, bad key, no options): ' . implode( ' | ', $bad['errors'] ) );

$derived = Custom_Form::normalize( [ 'steps' => [ [ 'fields' => [ [ 'type' => 'text', 'label' => 'Your City!' ], [ 'type' => 'radio', 'label' => 'Pick', 'options' => "One|1\nTwo|2\n|\nTwo|2" ] ] ] ] ] );
eq( $derived['config']['steps'][0]['fields'][0]['key'], 'your_city', 'key derived from label' );
eq( $derived['config']['steps'][0]['fields'][1]['options'], [ [ 'value' => '1', 'label' => 'One' ], [ 'value' => '2', 'label' => 'Two' ] ], 'options parsed + deduped' );
check( count( $derived['warnings'] ) >= 2, 'warnings for derived key + no phone/email' );

/* ---------- collect */
$good = [
	'fname' => ' John ', 'lname' => 'Smith', 'phone' => '+1 (555) 123-4567', 'email' => 'john@example.com',
	'city' => 'Austin', 'state' => 'TX', 'employer_name' => 'Acme', 'employer_city' => 'Dallas',
	'salary' => '$65,000', 'still_employed' => 'No', 'spanish' => 'Yes', 'situation' => "Fired after\ncomplaining.",
	'evil' => 'ignored',
];
$c = Custom_Form::collect( $good, $cfg );
eq( $c['errors'], [], 'valid submission has no errors' );
eq( $c['values']['phone'], '5551234567', 'phone normalized, leading 1 dropped' );
eq( $c['values']['salary'], '65000', 'salary stripped to digits' );
check( ! isset( $c['values']['evil'] ), 'unknown keys ignored' );
eq( $c['core']['name'], 'John Smith', 'core name from first+last' );
eq( $c['core']['message'], "Fired after\ncomplaining.", 'message role → situation' );

$c2 = Custom_Form::collect( [ 'fname' => 'J', 'phone' => '555', 'email' => 'nope', 'state' => 'ZZ', 'salary' => 'abc' ], $cfg );
eq( $c2['errors']['phone'] ?? '', 'Please enter a 10-digit US phone number.', 'short phone rejected' );
eq( $c2['errors']['email'] ?? '', 'Please enter a valid email address.', 'bad email rejected' );
eq( $c2['errors']['state'] ?? '', 'Please choose one of the options.', 'state outside options rejected' );
eq( $c2['errors']['lname'] ?? '', 'This field is required.', 'missing required text' );
eq( $c2['errors']['still_employed'] ?? '', 'Please make a selection.', 'missing required select' );
check( isset( $c2['errors']['salary'] ), 'non-numeric salary rejected' );

$hidden_cfg = Custom_Form::normalize( [ 'steps' => [ [ 'fields' => [ [ 'key' => 'src', 'type' => 'hidden', 'default' => 'bar' ], [ 'key' => 'ok', 'type' => 'checkbox', 'label' => 'I agree to the <a href="/privacy">policy</a><script>x</script>', 'required' => true ] ] ] ] ] )['config'];
$h = Custom_Form::collect( [ 'src' => 'tampered' ], $hidden_cfg );
eq( $h['values']['src'], 'bar', 'hidden field ignores browser value' );
eq( $h['errors']['ok'] ?? '', 'Please check this box to continue.', 'required checkbox' );
check( false === strpos( $hidden_cfg['steps'][0]['fields'][1]['label'], '<script' ) && false !== strpos( $hidden_cfg['steps'][0]['fields'][1]['label'], '<a href' ), 'checkbox label keeps link, drops script' );

/* ---------- webhook payload */
$p = Webhook::build_payload( $c['core'], $c['values'], $c['rows'], [ 'form_mode' => 'custom', 'source' => 'email', 'page_url' => 'https://btattorneys.test/', 'submission_id' => 7 ] );
eq( $p['phone_e164'], '+15551234567', 'phone_e164' );
eq( $p['phone_formatted'], '(555) 123-4567', 'phone_formatted' );
eq( $p['first_name'], 'John', 'first_name' );
eq( $p['state'], 'TX', 'custom key top level' );
check( ! isset( $p['field_phone'] ) && '5551234567' === $p['phone'], 'custom "phone" that fills the core phone is not duplicated' );
$p2 = Webhook::build_payload( [ 'name' => 'A', 'phone' => '5551234567' ], [ 'name' => 'Different' ], [], [] );
eq( $p2['field_name'] ?? null, 'Different', 'colliding key with a different value kept as field_<key>' );
check( ! in_array( 'field_phone', Webhook::payload_keys( $cfg ), true ) && in_array( 'employer_city', Webhook::payload_keys( $cfg ), true ), 'payload_keys lists custom keys without duplicates' );
check( false !== strpos( $p['summary'], 'Annual Salary: 65000' ), 'summary has labelled lines' );
$std = Webhook::build_payload( [ 'name' => 'Mary Ann Lee', 'phone' => '5551112222', 'email' => 'm@x.com', 'message' => 'hi', 'contact_pref' => '' ], [], [], [ 'source' => 'text' ] );
eq( [ $std['first_name'], $std['last_name'], $std['form_mode'] ], [ 'Mary', 'Ann Lee', 'standard' ], 'standard payload splits name' );
foreach ( [ 'event', 'source', 'submitted_at', 'name', 'phone', 'email', 'message', 'contact_pref', 'page_url', 'site_name', 'firm_name', 'submission_id' ] as $k ) {
	check( array_key_exists( $k, $std ), "standard payload keeps legacy key $k" );
}

/* ---------- API: update form, dry run, conflict, revisions, restore */
$GLOBALS['t_options']['bspe_connect_settings'] = Settings::defaults();
$req = new WP_REST_Request( [ 'mode' => 'custom', 'custom' => $conv, 'dry_run' => true ] );
$r = Form_Api::route_update_form( $req );
check( $r instanceof WP_REST_Response && true === $r->data['dry_run'] && false === $r->data['applied'], 'dry run does not apply' );
eq( Settings::get( 'form.mode' ), 'standard', 'dry run left mode alone' );
$rev = $r->data['form']['revision'];

$r = Form_Api::route_update_form( new WP_REST_Request( [ 'mode' => 'custom', 'custom' => $conv, 'actor' => [ 'app' => 'fleet', 'name' => 'Claude' ], 'webhook' => [ 'url' => 'https://hooks.zapier.com/hooks/catch/1/abc/', 'enabled' => true ] ] ) );
check( $r instanceof WP_REST_Response && $r->data['applied'], 'apply works' );
eq( Settings::get( 'form.mode' ), 'custom', 'mode saved' );
check( Custom_Form::is_active(), 'custom form active' );
check( in_array( 'mode: standard → custom', $r->data['changes'], true ), 'change list names the mode switch' );
eq( count( Form_Api::revisions() ), 1, 'one revision recorded' );

$r = Form_Api::route_update_form( new WP_REST_Request( [ 'mode' => 'standard', 'if_revision' => 'stale000' ] ) );
check( is_wp_error( $r ) && 409 === $r->get_error_data()['status'], 'stale if_revision → 409' );

$r = Form_Api::route_update_form( new WP_REST_Request( [ 'custom' => [ 'steps' => [ [ 'fields' => [ [ 'key' => 'x', 'type' => 'nope' ] ] ] ] ] ] ) );
check( is_wp_error( $r ) && 'bspe_connect_invalid_form' === $r->get_error_code(), 'invalid schema refused' );
eq( count( Custom_Form::all_fields( Custom_Form::config() ) ), 12, 'refused write left the form intact' );

$t = Form_Api::route_test_webhook( new WP_REST_Request( [] ) );
check( $t->data['delivered'] && true === $t->data['payload']['test'], 'test webhook sends a test payload' );
eq( $t->data['payload']['state'], 'AL', 'sample payload uses first option' );
eq( end( $GLOBALS['t_http'] )[0], 'https://hooks.zapier.com/hooks/catch/1/abc/', 'test webhook posted to the configured URL' );

$revs = Form_Api::route_list_revisions( new WP_REST_Request( [] ) )->data['revisions'];
$r = Form_Api::route_restore( new WP_REST_Request( [ 'revision_id' => $revs[0]['id'] ] ) );
check( $r instanceof WP_REST_Response && $r->data['applied'], 'restore applied' );
eq( Settings::get( 'form.mode' ), 'standard', 'restore brought back standard mode' );
eq( count( Form_Api::revisions() ), 2, 'restore itself is revisioned' );

/* ---------- API: settings patch through the tab sanitizers */
$before = Settings::all();
$r = Form_Api::route_patch_settings( new WP_REST_Request( [ 'patch' => [ 'buttons' => [ 'call' => [ 'phone' => '(512) 555-0100' ] ], 'welcome_bubble' => [ 'heading' => 'Hi <b>there</b>' ] ] ] ) );
check( $r instanceof WP_REST_Response && $r->data['applied'], 'settings patch applied: ' . json_encode( $r->data ?? $r ) );
$after = Settings::all();
eq( $after['welcome_bubble']['heading'], 'Hi there', 'patched heading sanitized' );
eq( $after['welcome_bubble']['enabled'], $before['welcome_bubble']['enabled'], 'unpatched toggle kept' );
eq( $after['buttons']['connect']['enabled'], $before['buttons']['connect']['enabled'], 'other button untouched' );
$r = Form_Api::route_patch_settings( new WP_REST_Request( [ 'patch' => [ 'form' => [ 'custom' => [] ] ] ] ) );
check( is_wp_error( $r ), 'custom via settings patch refused' );
$r = Form_Api::route_patch_settings( new WP_REST_Request( [ 'patch' => [ 'nope' => [] ] ] ) );
check( is_wp_error( $r ), 'unknown section refused' );

/* ---------- admin save path keeps custom form when the builder field is absent */
$cur = Settings::get( 'form' );
$cur['custom'] = $cfg;
$saved = ( function ( $in, $c ) { $m = new ReflectionMethod( Settings_Saver::class, 'sanitize_form' ); return $m->invoke( null, $in, $c ); } )( [ 'mode' => 'custom' ], $cur );
eq( count( Custom_Form::all_fields( $saved['custom'] ) ), 12, 'admin save without custom_json keeps stored custom form' );
$saved2 = ( function ( $in, $c ) { $m = new ReflectionMethod( Settings_Saver::class, 'sanitize_form' ); return $m->invoke( null, $in, $c ); } )( [ 'mode' => 'custom', 'custom_json' => json_encode( $cfg ) ], $cur );
eq( $saved2['custom']['steps'][0]['fields'][0]['key'], 'fname', 'admin save with custom_json stores normalized form' );

/* ---------- review fixes */
$lt = Custom_Form::normalize( [ 'steps' => [ [ 'fields' => [ [ 'key' => 'salary_band', 'type' => 'select', 'label' => 'Salary <b>band</b>', 'options' => "< $50,000\n$50,000 - $100,000" ], [ 'key' => 'multi', 'type' => 'checkboxes', 'label' => 'M', 'options' => "a\nb" ], [ 'key' => 'email', 'type' => 'email', 'label' => 'Email' ] ] ] ] ] )['config'];
eq( $lt['steps'][0]['fields'][0]['options'][0]['value'], '< $50,000', '"<" kept as typed in option values' );
eq( $lt['steps'][0]['fields'][0]['label'], 'Salary band', 'tags still stripped from labels' );
$ltc = Custom_Form::collect( [ 'salary_band' => '< $50,000', 'multi' => [ [ 'x' ], 'a' ], 'email' => 'a@b.co' ], $lt );
eq( $ltc['values']['salary_band'], '< $50,000', 'option with "<" accepted' );
eq( $ltc['values']['multi'], 'a', 'nested array ignored without warnings' );
eq( Custom_Form::plain_text( "Line one\r\n\r\n\r\n\r\nLine <script>x</script>two < three", true ), "Line one\n\nLine two < three", 'multiline plain text' );

$GLOBALS['t_options']['bspe_connect_settings'] = Settings::defaults();
eq( Custom_Form::signature(), 's', 'standard signature' );
$GLOBALS['t_options']['bspe_connect_settings']['form']['mode'] = 'custom';
$GLOBALS['t_options']['bspe_connect_settings']['form']['custom'] = $cfg;
$sig1 = Custom_Form::signature();
check( 'c' === $sig1[0] && 11 === strlen( $sig1 ), 'custom signature shape' );
$cfg_relabel = $cfg; $cfg_relabel['steps'][0]['fields'][0]['label'] = 'Your first name';
$GLOBALS['t_options']['bspe_connect_settings']['form']['custom'] = $cfg_relabel;
eq( Custom_Form::signature(), $sig1, 'relabel keeps signature (cached pages still valid)' );
$cfg_req = $cfg; $cfg_req['steps'][1]['fields'][0]['required'] = false;
$GLOBALS['t_options']['bspe_connect_settings']['form']['custom'] = $cfg_req;
check( Custom_Form::signature() !== $sig1, 'required change alters signature' );
eq( Custom_Form::answered_count( Custom_Form::collect( [], $cfg )['rows'] ), 0, 'nothing answered' );

// restore defaults to layout only
$GLOBALS['t_options']['bspe_connect_settings'] = Settings::defaults();
$GLOBALS['t_options']['bspe_connect_settings']['form']['mail_to'] = 'old@firm.test';
$GLOBALS['t_options'][ Form_Api::REVISIONS_OPTION ] = [];
Form_Api::route_update_form( new WP_REST_Request( [ 'mode' => 'custom', 'custom' => $conv ] ) );
Form_Api::route_update_form( new WP_REST_Request( [ 'mail' => [ 'to' => 'new@firm.test' ], 'webhook' => [ 'url' => 'https://hooks.zapier.com/hooks/catch/1/rotated/' ] ] ) );
$revs = Form_Api::revisions();
$first = end( $revs ); // state before the custom form existed
$dry = Form_Api::route_restore( new WP_REST_Request( [ 'revision_id' => $first['id'], 'dry_run' => true ] ) );
check( $dry->data['dry_run'] && in_array( 'mode: custom → standard', $dry->data['changes'], true ) && ! in_array( 'mail_to changed', $dry->data['changes'], true ), 'layout restore preview excludes mail_to' );
Form_Api::route_restore( new WP_REST_Request( [ 'revision_id' => $first['id'] ] ) );
eq( Settings::get( 'form.mail_to' ), 'new@firm.test', 'layout restore keeps current mail_to' );
eq( Settings::get( 'form.webhook.url' ), 'https://hooks.zapier.com/hooks/catch/1/rotated/', 'layout restore keeps rotated webhook' );
eq( Settings::get( 'form.mode' ), 'standard', 'layout restore reverted mode' );
Form_Api::route_restore( new WP_REST_Request( [ 'revision_id' => $first['id'], 'scope' => 'all' ] ) );
eq( Settings::get( 'form.mail_to' ), 'old@firm.test', 'scope all restores mail_to too' );
check( count( $GLOBALS['t_purges'] ) >= 4, 'every applied form change purged the page cache (' . count( $GLOBALS['t_purges'] ) . ')' );

// standard-mode save with a broken draft: saves, keeps stored custom, warns
$stored = Settings::get( 'form' ); $stored['custom'] = $cfg;
$m = new ReflectionMethod( Settings_Saver::class, 'sanitize_form' );
$prop = new ReflectionProperty( Settings_Saver::class, 'form_errors' );
$warn = new ReflectionProperty( Settings_Saver::class, 'form_warnings' );
$prop->setValue( null, [] ); $warn->setValue( null, [] );
$out = $m->invoke( null, [ 'mode' => 'standard', 'mail_to' => 'x@firm.test', 'custom_json' => json_encode( [ 'steps' => [ [ 'fields' => [ [ 'type' => 'text' ] ] ] ] ] ) ], $stored );
eq( $prop->getValue(), [], 'standard mode: draft errors do not block' );
check( count( $warn->getValue() ) > 0, 'standard mode: draft errors reported as warnings' );
eq( count( Custom_Form::all_fields( $out['custom'] ) ), 12, 'standard mode: stored custom form kept' );
$prop->setValue( null, [] ); $warn->setValue( null, [] );
$m->invoke( null, [ 'mode' => 'custom', 'custom_json' => json_encode( [ 'steps' => [ [ 'fields' => [ [ 'type' => 'text' ] ] ] ] ] ) ], $stored );
check( count( $prop->getValue() ) > 0, 'custom mode: draft errors still block' );

/* ---------- template renders */
$GLOBALS['t_options']['bspe_connect_settings']['form']['mode'] = 'custom';
$GLOBALS['t_options']['bspe_connect_settings']['form']['custom'] = $cfg;
$turnstile_enabled = false; $turnstile_site_key = '';
ob_start();
require $root . '/public/templates/form-custom.php';
$html = ob_get_clean();
$dom = new DOMDocument();
libxml_use_internal_errors( true );
$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
$xp = new DOMXPath( $dom );
eq( $xp->query( '//fieldset[@data-bspe-step]' )->length, 3, 'three fieldsets rendered' );
eq( $xp->query( '//fieldset[@data-bspe-step][@hidden]' )->length, 2, 'steps 2 and 3 start hidden' );
eq( $xp->query( '//*[@name="bspe_f[state]"]/option' )->length, 51, 'state select: placeholder + 50 options' );
eq( $xp->query( '//input[@name="bspe_f[phone]"][@data-bspe-phone-mask]' )->length, 1, 'phone mask on tel' );
eq( $xp->query( '//input[@name="bspe_f[fname]"]' )->item( 0 )->getAttribute( 'autocomplete' ), 'given-name', 'first name autocomplete' );
eq( $xp->query( '//button[@data-bspe-submit][@hidden]' )->length, 1, 'submit hidden until last step' );
eq( $xp->query( '//*[@data-bspe-progress-label]' )->item( 0 )->textContent, 'Step 1 of 3', 'progress label' );
eq( $xp->query( '//textarea[@name="bspe_f[situation]"]' )->length, 1, 'situation textarea' );
eq( $xp->query( '//*[@data-bspe-error]' )->length, 12, 'one error slot per field' );
file_put_contents( sys_get_temp_dir() . '/bspe-cf-render.html', $html );

echo "\n$passes passed, $fails failed\n";
exit( $fails ? 1 : 0 );
