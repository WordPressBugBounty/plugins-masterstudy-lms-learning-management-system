<?php

namespace MasterStudy\Lms\Http\Serializers;

final class OrderSerializer extends AbstractSerializer {

	/**
	 * @param array $data
	 *
	 * @return array
	 */
	public function toArray( $data ): array {
		return array(
			'id'           => $data['id'],
			'status'       => $data['status'],
			'order_note'   => $data['order_note'] ?? '',
			'cart_items'   => $data['cart_items'],
			'user'         => array(
				'login' => $data['user']['login'],
				'email' => $data['user']['email'],
				'id'    => $data['user']['id'],
			),
			'total'        => $data['total'],
			'date'         => $data['date'],
			'payment_code' => $data['payment_code'],
			'order_key'    => $data['order_key'],
		);
	}
}
