<?php

namespace MasterStudy\Lms\Http\Serializers;

final class OrderListSerializer extends AbstractSerializer {

	/**
	 * @param array $data
	 *
	 * @return array
	 */
	public function toArray( $data ): array {
		return array(
			'id'      => $data['id'],
			'user'    => $data['user']['login'],
			'user_id' => $data['user']['id'],
			'amount'  => $data['total'],
			'method'  => $data['payment_code'],
			'date'    => $data['date'],
			'status'  => $data['status'],
		);
	}
}
