<?php
/**
 * Best-effort free-text opening-hours parsing, shared by the single-store
 * "listing detail" template.
 *
 * `_asl_opening_hours` is a plain textarea field (see Admin/MetaBoxes.php),
 * not structured per-day data, so everything here is deliberately
 * best-effort — the same spirit as the client-side isOpenNow() parser in
 * store-locator.js, just ported to PHP so the detail page can render a
 * status ("Open now" / "Closed") and a day-by-day-looking table without
 * needing JavaScript.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OpeningHours
 */
class OpeningHours {

	/**
	 * Day name/abbreviation -> ISO-ish weekday index (0 = Sunday), matching
	 * PHP's `current_time( 'w' )`.
	 *
	 * @var array<string,int>
	 */
	private static $day_map = array(
		'sun'       => 0,
		'sunday'    => 0,
		'mon'       => 1,
		'monday'    => 1,
		'tue'       => 2,
		'tues'      => 2,
		'tuesday'   => 2,
		'wed'       => 3,
		'wednesday' => 3,
		'thu'       => 4,
		'thur'      => 4,
		'thurs'     => 4,
		'thursday'  => 4,
		'fri'       => 5,
		'friday'    => 5,
		'sat'       => 6,
		'saturday'  => 6,
	);

	/**
	 * Best-effort "is the store open right now" check.
	 *
	 * Scans each line of the free-text hours for one that covers today
	 * (by day name, or a "Mon-Fri" style range, or the words "daily"/
	 * "every day"), then looks for a HH:MM-HH:MM (12 or 24-hour, with or
	 * without am/pm) range on that line and compares it to the current
	 * time.
	 *
	 * @param string $opening_hours Raw free-text hours.
	 * @return bool|null True/false if determinable, null if nothing on
	 *                    today's line could be parsed.
	 */
	public static function is_open_now( $opening_hours ) {
		if ( ! $opening_hours ) {
			return null;
		}

		$today_dow = (int) current_time( 'w' );
		$lines     = preg_split( '/[\r\n]+/', (string) $opening_hours );

		foreach ( $lines as $line ) {
			if ( ! self::line_covers_today( strtolower( $line ), $today_dow ) ) {
				continue;
			}

			if ( ! preg_match( '/(\d{1,2}):?(\d{2})?\s*(am|pm)?\s*[-–]\s*(\d{1,2}):?(\d{2})?\s*(am|pm)?/i', $line, $m ) ) {
				continue;
			}

			$open_minutes  = self::time_to_minutes( $m[1], isset( $m[2] ) ? $m[2] : '00', isset( $m[3] ) ? $m[3] : '' );
			$close_minutes = self::time_to_minutes( $m[4], isset( $m[5] ) ? $m[5] : '00', isset( $m[6] ) ? $m[6] : '' );

			if ( null === $open_minutes || null === $close_minutes ) {
				continue;
			}

			$now_minutes = ( (int) current_time( 'H' ) * 60 ) + (int) current_time( 'i' );

			return ( $now_minutes >= $open_minutes && $now_minutes <= $close_minutes );
		}

		return null;
	}

	/**
	 * Split free-text opening hours into display rows, one per line, each
	 * split into a "day" label and an "hours" value on the first colon
	 * (the common "Monday: 10am-8pm" style admins tend to type). Lines
	 * without a colon are returned as a single-column row instead of
	 * guessing at a split point.
	 *
	 * @param string $opening_hours Raw free-text hours.
	 * @return array<int,array{day:string,hours:string}>
	 */
	public static function to_rows( $opening_hours ) {
		if ( ! $opening_hours ) {
			return array();
		}

		$rows  = array();
		$lines = preg_split( '/[\r\n]+/', (string) $opening_hours );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$pos = strpos( $line, ':' );
			if ( false !== $pos && preg_match( '/\d{1,2}:\d{2}/', substr( $line, 0, $pos + 3 ) ) ) {
				// A colon this early is almost certainly part of a time
				// (e.g. "9:00-18:00"), not a day/hours separator — treat
				// the whole line as unsplit rather than cutting it wrong.
				$pos = false;
			}

			if ( false !== $pos ) {
				$rows[] = array(
					'day'   => trim( substr( $line, 0, $pos ) ),
					'hours' => trim( substr( $line, $pos + 1 ) ),
				);
			} else {
				$rows[] = array(
					'day'   => '',
					'hours' => $line,
				);
			}
		}

		return $rows;
	}

	/**
	 * Whether a (lowercased) line of opening-hours text applies to today.
	 *
	 * @param string $line_lc   Lowercased line.
	 * @param int    $today_dow Today's weekday index (0 = Sunday).
	 * @return bool
	 */
	private static function line_covers_today( $line_lc, $today_dow ) {
		// A day range like "mon-fri" / "mon - sat".
		if ( preg_match( '/\b([a-z]{3,9})\s*-\s*([a-z]{3,9})\b/', $line_lc, $m )
			&& isset( self::$day_map[ $m[1] ], self::$day_map[ $m[2] ] ) ) {
			$start = self::$day_map[ $m[1] ];
			$end   = self::$day_map[ $m[2] ];
			if ( $start <= $end ) {
				return ( $today_dow >= $start && $today_dow <= $end );
			}
			return ( $today_dow >= $start || $today_dow <= $end ); // Wraps past Sunday, e.g. "Fri-Mon".
		}

		foreach ( self::$day_map as $name => $dow ) {
			if ( $dow === $today_dow && preg_match( '/\b' . preg_quote( $name, '/' ) . '\b/', $line_lc ) ) {
				return true;
			}
		}

		return ( false !== strpos( $line_lc, 'daily' ) || false !== strpos( $line_lc, 'every day' ) );
	}

	/**
	 * Convert an hour/minute/am-pm trio to minutes-since-midnight.
	 *
	 * @param string $hour     Hour digits.
	 * @param string $minute   Minute digits (may be empty).
	 * @param string $meridiem 'am', 'pm', or empty (24-hour time assumed).
	 * @return int|null Null if the hour is out of range.
	 */
	private static function time_to_minutes( $hour, $minute, $meridiem ) {
		$hour     = (int) $hour;
		$minute   = (int) $minute;
		$meridiem = strtolower( trim( (string) $meridiem ) );

		if ( 'pm' === $meridiem && 12 !== $hour ) {
			$hour += 12;
		} elseif ( 'am' === $meridiem && 12 === $hour ) {
			$hour = 0;
		}

		if ( $hour < 0 || $hour > 23 ) {
			return null;
		}

		return ( $hour * 60 ) + $minute;
	}
}
