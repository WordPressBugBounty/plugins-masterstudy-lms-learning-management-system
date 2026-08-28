<?php

namespace MasterStudy\Lms\Utility;

final class UserAuthorization {
	public static function can_access_private_data( int $requested_user_id ): bool {
		$current_user_id = get_current_user_id();

		return $requested_user_id > 0
			&& $current_user_id > 0
			&& ( $current_user_id === $requested_user_id || current_user_can( 'manage_options' ) );
	}
}
