<?php

namespace MasterStudy\Lms\Utility;

final class UserAuthorization {
	public static function can_access_private_data( int $requested_user_id ): bool {
		$current_user_id = get_current_user_id();

		return $requested_user_id > 0
			&& $current_user_id > 0
			&& ( $current_user_id === $requested_user_id || current_user_can( 'manage_options' ) );
	}

	public static function can_access_public_student_profile( int $requested_user_id ): bool {
		return self::can_access_public_student_data( $requested_user_id );
	}

	public static function can_access_public_student_courses( int $requested_user_id, string $status ): bool {
		return 'completed' === $status && self::can_access_public_student_profile( $requested_user_id );
	}

	public static function can_access_public_student_stats( int $requested_user_id ): bool {
		return self::can_access_public_student_data( $requested_user_id, true );
	}

	private static function can_access_public_student_data( int $requested_user_id, bool $require_stats = false ): bool {
		if ( $requested_user_id <= 0 || false === get_userdata( $requested_user_id ) ) {
			return false;
		}

		$settings = get_option( 'stm_lms_settings', array() );

		if ( ! (bool) ( $settings['student_public_profile'] ?? true ) ) {
			return false;
		}

		return ! $require_stats || (bool) ( $settings['student_stats_public_profile'] ?? true );
	}
}
