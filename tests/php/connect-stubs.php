<?php
namespace BSPE\Connect;

final class Logger { public static $lines = []; public static function log( $level, $msg, $ctx = [] ) { self::$lines[] = [ $level, $msg, $ctx ]; } }
final class Licensing { public static function is_functional() { return true; } }
final class Privacy { public const PROFILES = [ 'us', 'us_ca', 'strict' ]; }
