<?php
/**
 * Standalone checks for Translate_Extractor's pure logic.
 * Run: php tests/translate-extractor-test.php
 */
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
function is_serialized( $data ) { return is_string( $data ) && (bool) preg_match( '/^[aOs]:\d+:/', $data ); }
function apply_filters( $tag, $value ) { return $value; }

require __DIR__ . '/../includes/class-translate-extractor.php';

use BSPE\Connect\Translate_Extractor as X;

$fails = 0;
function ok( bool $cond, string $msg ): void {
	global $fails;
	if ( $cond ) { echo "  ok  $msg\n"; } else { $fails++; echo "FAIL  $msg\n"; }
}

// 1. split_html round-trips and respects the limit at block boundaries.
$para = '<p>' . str_repeat( 'Lorem ipsum dolor sit amet. ', 20 ) . '</p>';
$html = str_repeat( $para, 30 );
$runs = X::split_html( $html, 6000 );
ok( count( $runs ) > 1, 'long html is split into ' . count( $runs ) . ' runs' );
ok( implode( '', $runs ) === $html, 'split runs concatenate back to the original' );
foreach ( $runs as $i => $r ) { ok( str_ends_with( $r, '</p>' ), "run $i ends at a block boundary" ); }
ok( X::split_html( '<p>short</p>', 6000 ) === [ '<p>short</p>' ], 'short html is one run' );

// 2. looks_like_text filters.
ok( ! X::looks_like_text( 'https://example.com/x' ), 'url rejected' );
ok( ! X::looks_like_text( '#ff0000' ), 'hex color rejected' );
ok( ! X::looks_like_text( '42' ), 'number rejected' );
ok( ! X::looks_like_text( 'fas fa-check' ), 'icon class rejected' );
ok( ! X::looks_like_text( 'my-anchor_id' ), 'slug token rejected' );
ok( X::looks_like_text( 'Contact' ), 'single word kept' );
ok( X::looks_like_text( 'Free Case Review' ), 'phrase kept' );

// 3. Elementor walk + rebuild.
$tree = [
	[
		'id' => 'sec1', 'elType' => 'section', 'settings' => [ 'background_color' => '#fff' ],
		'elements' => [
			[
				'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'heading',
				'settings' => [ 'title' => 'We Fight For You', 'size' => 'xl', 'link' => [ 'url' => 'https://x.com' ] ],
			],
			[
				'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'icon-list',
				'settings' => [
					'icon_list' => [
						[ '_id' => 'a1', 'text' => 'Car accidents', 'selected_icon' => [ 'value' => 'fas fa-check' ] ],
						[ '_id' => 'a2', 'text' => 'Slip and fall', 'link' => [ 'url' => '' ] ],
					],
				],
			],
			[ 'id' => 'w3', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => [ 'text' => 'Call now', 'button_type' => 'info' ] ],
		],
	],
];
$fields = [ 'post_title' => 'Personal Injury Lawyer', 'post_name' => 'personal-injury-lawyer', 'post_excerpt' => '', 'post_content' => '<p>Intro.</p>' ];
$meta   = [ '_elementor_data' => json_encode( $tree ), '_seopress_titles_title' => 'PI Lawyer | Firm', '_thumbnail_id' => '12' ];
$res    = X::extract_from( $fields, $meta, X::SEOPRESS_META );
$seg    = $res['segments'];
ok( $seg['title'] === 'Personal Injury Lawyer', 'title extracted' );
ok( $seg['slug'] === 'personal injury lawyer', 'slug extracted as words' );
ok( ! isset( $seg['excerpt'] ), 'empty excerpt skipped' );
ok( $seg['content'] === '<p>Intro.</p>', 'content extracted' );
ok( $seg['el:w1:title'] === 'We Fight For You', 'heading title extracted' );
ok( ! isset( $seg['el:w1:size'] ), 'non-text setting skipped' );
ok( $seg['el:w2:icon_list:0:text'] === 'Car accidents', 'repeater item 0 extracted' );
ok( $seg['el:w2:icon_list:1:text'] === 'Slip and fall', 'repeater item 1 extracted' );
ok( $seg['el:w3:text'] === 'Call now', 'button text extracted' );
ok( $seg['meta:_seopress_titles_title'] === 'PI Lawyer | Firm', 'seopress title extracted' );
ok( ! isset( $seg['meta:_thumbnail_id'] ), 'non-translatable meta skipped' );
ok( $res['map']['elementor'] === true, 'map flags elementor' );

$translated = array_map( static fn( $s ) => 'ES(' . $s . ')', $seg );
$rebuilt    = X::rebuild_elementor( $tree, $translated, $res['map'] );
ok( $rebuilt[0]['elements'][0]['settings']['title'] === 'ES(We Fight For You)', 'heading rebuilt' );
ok( $rebuilt[0]['elements'][0]['settings']['size'] === 'xl', 'untouched setting preserved' );
ok( $rebuilt[0]['elements'][0]['settings']['link']['url'] === 'https://x.com', 'link object preserved' );
ok( $rebuilt[0]['elements'][1]['settings']['icon_list'][1]['text'] === 'ES(Slip and fall)', 'repeater rebuilt' );
ok( $rebuilt[0]['elements'][1]['settings']['icon_list'][0]['selected_icon']['value'] === 'fas fa-check', 'repeater icon preserved' );
ok( $rebuilt[0]['settings']['background_color'] === '#fff', 'section color preserved' );

// 4. Long content splits into runs and rebuilds in order.
$fields2 = [ 'post_title' => 'T', 'post_name' => 't', 'post_excerpt' => '', 'post_content' => $html ];
$res2    = X::extract_from( $fields2, [], [] );
$n       = $res2['map']['runs']['content'] ?? 0;
ok( $n > 1, "content split into $n runs" );
ok( isset( $res2['segments']['content:0'] ) && ! isset( $res2['segments']['content'] ), 'runs use :N ids' );
$tr2 = $res2['segments'];
ok( X::rebuild( 'content', $tr2, $res2['map'], '' ) === $html, 'identity rebuild of runs equals original' );

// 5. Long Elementor editor value splits and rebuilds too.
$tree3 = [ [ 'id' => 'e1', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => [ 'editor' => $html ] ] ];
$res3  = X::extract_from( [ 'post_title' => 'x', 'post_name' => 'x', 'post_excerpt' => '', 'post_content' => '' ], [ '_elementor_data' => json_encode( $tree3 ) ], [] );
ok( ( $res3['map']['runs']['el:e1:editor'] ?? 0 ) > 1, 'long elementor editor split into runs' );
$re3 = X::rebuild_elementor( $tree3, $res3['segments'], $res3['map'] );
ok( $re3[0]['settings']['editor'] === $html, 'long elementor editor rebuilt identically' );

echo $fails ? "\n$fails FAILED\n" : "\nAll passed\n";
exit( $fails ? 1 : 0 );
