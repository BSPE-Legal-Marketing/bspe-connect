<?php
/** Elementor data from btattorneys.com (2026-11-25): page template 97 holds a global-widget
 * stub pointing at template 8355, which holds the live multi-step form. */
$form_fields = [
	[ 'field_type' => 'step', '_id' => '861d293', 'custom_id' => 'field_861d293' ],
	[ 'custom_id' => 'fname', 'field_label' => 'First Name', 'placeholder' => 'First Name', 'required' => 'true' ],
	[ 'custom_id' => 'lname', 'field_label' => 'Last Name', 'placeholder' => 'Last Name', 'required' => 'true' ],
	[ 'field_type' => 'tel', 'field_label' => 'Phone number', 'placeholder' => 'Phone number', 'required' => 'true', 'custom_id' => 'phone' ],
	[ 'field_type' => 'honeypot', 'field_label' => 'Subject', 'placeholder' => 'Phone number', 'required' => 'true', 'custom_id' => 'subject' ],
	[ 'custom_id' => 'email', 'field_type' => 'email', 'required' => 'true', 'field_label' => 'Email', 'placeholder' => 'Email' ],
	[ 'custom_id' => 'address', 'field_type' => 'honeypot', 'required' => 'true', 'field_label' => 'Address', 'placeholder' => 'Email' ],
	[ 'field_type' => 'step', 'field_label' => 'Step', 'custom_id' => 'step', 'previous_button' => 'BACK' ],
	[ 'field_label' => 'Your location - City', 'placeholder' => 'Your location - City', 'required' => 'true', 'custom_id' => 'city' ],
	[ 'field_label' => 'Your location - State', 'placeholder' => 'Your location - City', 'required' => 'true', 'custom_id' => 'state', 'field_type' => 'select', 'field_options' => "Your location - State |\nAL\nAK\nAR\nCA\nCO\nCT\nDE\nDC\nFL\nGA\nHI\nIA\nID\nIL\nIN\nKS\nKY\nLA\nMA\nMD\nME\nMI\nMN\nMO\nMS\nMT\nNC\nND\nNE\nNH\nNJ\nNM\nNV\nNY\nOH\nOK\nOR\nPA\nRI\nSC\nSD\nTN\nTX\nUT\nVA\nVT\nWA\nWI\nWV\nWY" ],
	[ 'custom_id' => 'employer_name', 'field_label' => 'Employer Name', 'placeholder' => 'Employer Name', 'required' => 'true' ],
	[ 'custom_id' => 'employer_city', 'field_label' => 'Employer City', 'placeholder' => 'Employer City', 'required' => 'true' ],
	[ 'field_type' => 'step', 'field_label' => 'Step', 'custom_id' => 'next_step', 'previous_button' => 'BACK' ],
	[ 'custom_id' => 'salary', 'field_label' => 'Annual Salary', 'placeholder' => 'Annual Salary', 'required' => 'true', 'field_type' => 'number' ],
	[ 'custom_id' => 'still_employed', 'field_label' => 'Still Employed', 'placeholder' => 'Anual Salary', 'required' => 'true', 'field_type' => 'select', 'field_options' => "Still Employed |\nYes\nNo" ],
	[ 'custom_id' => 'spanish', 'field_label' => 'Spanish preferred', 'placeholder' => 'Anual Salary', 'required' => 'true', 'field_type' => 'select', 'field_options' => "Spanish preferred |\nYes\nNo" ],
	[ 'custom_id' => 'situation', 'field_label' => 'Describe your situation', 'placeholder' => 'Describe your situation', 'required' => 'true', 'field_type' => 'textarea', 'field_options' => "Yes\nNo" ],
];
$settings = [ 'form_name' => 'Connect with team form', 'form_fields' => $form_fields, 'step_next_label' => 'Next', 'step_previous_label' => 'Previous', 'button_text' => 'SUBMIT', 'step_type' => 'none' ];

$GLOBALS['t_post_meta'][8355]['_elementor_data'] = json_encode( [ [ 'id' => '8355', 'elType' => 'widget', 'settings' => $settings, 'elements' => [], 'widgetType' => 'form' ] ] );
$stale = $settings;
$stale['form_fields'][13]['field_label'] = 'Anual Salary';
unset( $stale['form_fields'][13]['field_type'] );
$GLOBALS['t_post_meta'][97]['_elementor_data'] = json_encode( [ [ 'id' => '032cab1', 'elType' => 'container', 'settings' => [], 'elements' => [
	[ 'id' => 'f00ff83', 'elType' => 'widget', 'settings' => [ 'title' => 'Connect With Our Team Now' ], 'elements' => [], 'widgetType' => 'heading' ],
	[ 'id' => 'bdb0bf3', 'elType' => 'widget', 'settings' => $stale, 'elements' => [], 'widgetType' => 'global', 'templateID' => 8355 ],
] ] ] );
